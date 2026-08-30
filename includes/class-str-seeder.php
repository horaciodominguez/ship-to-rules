<?php
/**
 * Seed destination terms from WooCommerce countries and shipping zones.
 *
 * @package ShipToRules
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STR_Seeder
 */
class STR_Seeder {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_str_seed_countries', array( __CLASS__, 'handle_seed_request' ) );
		add_action( 'admin_post_str_reset_countries', array( __CLASS__, 'handle_reset_request' ) );
	}

	/**
	 * Handle admin seed action.
	 */
	public static function handle_seed_request() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'ship-to-rules' ) );
		}

		check_admin_referer( 'str_seed_countries' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$source = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : 'zones';

		if ( 'all' === $source ) {
			$result = self::seed_from_wc_countries();
		} else {
			$result = self::seed_from_shipping_zones();
		}

		$redirect = add_query_arg(
			array(
				'page'       => STR_ADMIN_PAGE,
				'str_seeded'  => 1,
				'str_created' => (int) $result['created'],
				'str_skipped' => (int) $result['skipped'],
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle admin reset action — remove all destination terms.
	 */
	public static function handle_reset_request() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'ship-to-rules' ) );
		}

		check_admin_referer( 'str_reset_countries' );

		$result = self::reset_all_destinations();

		$redirect = add_query_arg(
			array(
				'page'         => STR_ADMIN_PAGE,
				'str_reset'    => 1,
				'str_deleted'  => (int) $result['deleted'],
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Delete every ship-to destination term and clear product assignments.
	 *
	 * @return array{deleted:int}
	 */
	public static function reset_all_destinations() {
		$term_ids = get_terms(
			array(
				'taxonomy'   => STR_TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $term_ids ) ) {
			$term_ids = array();
		}

		$deleted = 0;
		foreach ( $term_ids as $term_id ) {
			$result = wp_delete_term( (int) $term_id, STR_TAXONOMY );
			if ( ! is_wp_error( $result ) && false !== $result ) {
				++$deleted;
			}
		}

		delete_metadata( 'post', 0, '_str_ship_to_ids', '', true );

		STR_Countries::flush_cache();

		return array(
			'deleted' => $deleted,
		);
	}

	/**
	 * Seed from countries covered by shipping zones (recommended default).
	 *
	 * @return array{created:int,skipped:int}
	 */
	public static function seed_from_shipping_zones() {
		$codes = self::get_zone_country_codes();
		if ( empty( $codes ) ) {
			return self::seed_from_wc_countries();
		}

		return self::create_terms_for_codes( $codes );
	}

	/**
	 * Seed all WooCommerce countries.
	 *
	 * @return array{created:int,skipped:int}
	 */
	public static function seed_from_wc_countries() {
		$countries = STR_Countries::wc_countries();
		return self::create_terms_for_codes( array_keys( $countries ) );
	}

	/**
	 * Create or update destination terms for ISO codes.
	 *
	 * @param string[] $codes ISO2 codes.
	 * @return array{created:int,skipped:int}
	 */
	public static function create_terms_for_codes( array $codes ) {
		$countries = STR_Countries::wc_countries();
		$created   = 0;
		$skipped   = 0;

		foreach ( array_unique( array_map( array( 'STR_Countries', 'sanitize_iso2' ), $codes ) ) as $code ) {
			if ( 2 !== strlen( $code ) || ! isset( $countries[ $code ] ) ) {
				++$skipped;
				continue;
			}

			$name = $countries[ $code ];
			$term = term_exists( $code, STR_TAXONOMY );
			if ( ! $term ) {
				$term = term_exists( $name, STR_TAXONOMY );
			}

			if ( $term ) {
				$term_id = (int) ( is_array( $term ) ? $term['term_id'] : $term );
				update_term_meta( $term_id, 'str_iso2', $code );
				update_term_meta( $term_id, 'str_active', '1' );
				++$skipped;
				continue;
			}

			$inserted = wp_insert_term(
				$name,
				STR_TAXONOMY,
				array(
					'slug' => sanitize_title( $code ),
				)
			);

			if ( is_wp_error( $inserted ) ) {
				++$skipped;
				continue;
			}

			$term_id = (int) $inserted['term_id'];
			update_term_meta( $term_id, 'str_iso2', $code );
			update_term_meta( $term_id, 'str_active', '1' );
			++$created;
		}

		STR_Countries::flush_cache();

		return array(
			'created' => $created,
			'skipped' => $skipped,
		);
	}

	/**
	 * Collect country codes from shipping zones (enabled methods only).
	 *
	 * @return string[]
	 */
	public static function get_zone_country_codes() {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return array();
		}

		$codes      = array();
		$continents = self::get_continent_map();

		foreach ( WC_Shipping_Zones::get_zones() as $zone_data ) {
			$zone = WC_Shipping_Zones::get_zone( (int) $zone_data['zone_id'] );
			if ( ! $zone ) {
				continue;
			}

			$methods = $zone->get_shipping_methods( true );
			if ( empty( $methods ) ) {
				continue;
			}

			foreach ( $zone->get_zone_locations() as $location ) {
				$type = isset( $location->type ) ? $location->type : '';
				$code = isset( $location->code ) ? $location->code : '';

				if ( 'country' === $type && $code ) {
					$codes[] = strtoupper( $code );
				} elseif ( 'continent' === $type && $code && isset( $continents[ $code ] ) ) {
					$codes = array_merge( $codes, $continents[ $code ] );
				}
			}
		}

		// Rest-of-world zone (zone_id 0).
		$zone0 = new WC_Shipping_Zone( 0 );
		$methods0 = $zone0->get_shipping_methods( true );
		if ( ! empty( $methods0 ) ) {
			foreach ( $zone0->get_zone_locations() as $location ) {
				if ( 'country' === $location->type && $location->code ) {
					$codes[] = strtoupper( $location->code );
				}
			}
		}

		return array_values( array_unique( array_filter( $codes ) ) );
	}

	/**
	 * Continent => country codes map from WooCommerce.
	 *
	 * @return array<string,string[]>
	 */
	public static function get_continent_map() {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return array();
		}

		$map = array();
		foreach ( WC()->countries->get_continents() as $continent_code => $continent ) {
			$map[ $continent_code ] = isset( $continent['countries'] ) ? $continent['countries'] : array();
		}

		return $map;
	}
}
