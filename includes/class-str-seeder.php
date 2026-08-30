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
				'page'        => STR_ADMIN_PAGE,
				'str_seeded'  => empty( $result['error'] ) ? 1 : 0,
				'str_created' => (int) $result['created'],
				'str_skipped' => (int) $result['skipped'],
			),
			admin_url( 'admin.php' )
		);

		if ( ! empty( $result['removed'] ) ) {
			$redirect = add_query_arg( 'str_removed', (int) $result['removed'], $redirect );
		}

		if ( ! empty( $result['error'] ) ) {
			$redirect = add_query_arg( 'str_seed_error', sanitize_key( $result['error'] ), $redirect );
		}

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
	 * Syncs the destination list to zone countries only — extras from a prior
	 * "seed all" are removed. Never falls back to every WooCommerce country.
	 *
	 * @return array{created:int,skipped:int,removed?:int,error?:string}
	 */
	public static function seed_from_shipping_zones() {
		self::bootstrap_wc_shipping();

		$codes = self::get_zone_country_codes( true );
		if ( empty( $codes ) ) {
			$codes = self::get_zone_country_codes( false );
		}

		if ( empty( $codes ) ) {
			return array(
				'created' => 0,
				'skipped' => 0,
				'error'   => 'no_zones',
			);
		}

		$removed = self::remove_terms_not_in_codes( $codes );
		$result  = self::create_terms_for_codes( $codes );
		$result['removed'] = $removed;

		return $result;
	}

	/**
	 * Seed all WooCommerce countries.
	 *
	 * @return array{created:int,skipped:int}
	 */
	public static function seed_from_wc_countries() {
		self::bootstrap_wc_shipping();
		$countries = STR_Countries::wc_countries();
		return self::create_terms_for_codes( array_keys( $countries ) );
	}

	/**
	 * Ensure WooCommerce shipping classes are loaded (admin-post context).
	 */
	private static function bootstrap_wc_shipping() {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return;
		}

		if ( ! did_action( 'woocommerce_init' ) && method_exists( WC(), 'init' ) ) {
			WC()->init();
		}

		if ( ! WC()->shipping && class_exists( 'WC_Shipping' ) ) {
			WC()->shipping = new WC_Shipping();
		}
	}

	/**
	 * Delete destination terms whose ISO2 is not in the target code list.
	 *
	 * @param string[] $codes ISO2 codes to keep.
	 * @return int Number of terms removed.
	 */
	public static function remove_terms_not_in_codes( array $codes ) {
		$keep = array_flip(
			array_unique(
				array_map( array( 'STR_Countries', 'sanitize_iso2' ), $codes )
			)
		);

		$term_ids = get_terms(
			array(
				'taxonomy'   => STR_TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $term_ids ) ) {
			return 0;
		}

		$removed = 0;
		foreach ( $term_ids as $term_id ) {
			$iso = strtoupper( (string) get_term_meta( (int) $term_id, 'str_iso2', true ) );
			if ( $iso && isset( $keep[ $iso ] ) ) {
				continue;
			}

			$result = wp_delete_term( (int) $term_id, STR_TAXONOMY );
			if ( ! is_wp_error( $result ) && false !== $result ) {
				++$removed;
			}
		}

		if ( $removed > 0 ) {
			STR_Countries::flush_cache();
		}

		return $removed;
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
	 * Collect country codes from shipping zones.
	 *
	 * @param bool $require_enabled_methods When true, skip zones with no enabled methods.
	 * @return string[]
	 */
	public static function get_zone_country_codes( $require_enabled_methods = true ) {
		self::bootstrap_wc_shipping();

		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return array();
		}

		$continents  = self::get_continent_map();
		$named_codes = array();

		foreach ( WC_Shipping_Zones::get_zones() as $zone_data ) {
			$zone = WC_Shipping_Zones::get_zone( (int) $zone_data['zone_id'] );
			if ( ! $zone ) {
				continue;
			}

			if ( $require_enabled_methods && empty( $zone->get_shipping_methods( true ) ) ) {
				continue;
			}

			$named_codes = array_merge(
				$named_codes,
				self::parse_zone_locations( $zone->get_zone_locations(), $continents )
			);
		}

		$named_codes = array_values( array_unique( array_filter( $named_codes ) ) );

		$zone0 = new WC_Shipping_Zone( 0 );
		if ( empty( $zone0->get_shipping_methods( true ) ) ) {
			return $named_codes;
		}

		$zone0_locations = $zone0->get_zone_locations();
		$zone0_codes     = self::parse_zone_locations( $zone0_locations, $continents );

		if ( ! empty( $zone0_codes ) ) {
			return array_values( array_unique( array_merge( $named_codes, $zone0_codes ) ) );
		}

		if ( empty( $zone0_locations ) ) {
			return self::expand_rest_of_world_codes( $named_codes );
		}

		return $named_codes;
	}

	/**
	 * Countries served by the catch-all zone (zone 0).
	 *
	 * @param string[] $named_zone_codes Countries already covered by named zones.
	 * @return string[]
	 */
	private static function expand_rest_of_world_codes( array $named_zone_codes ) {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return $named_zone_codes;
		}

		$all = array_keys( WC()->countries->get_shipping_countries() );
		if ( empty( $named_zone_codes ) ) {
			return $all;
		}

		return array_values( array_diff( $all, $named_zone_codes ) );
	}

	/**
	 * Expand zone location rows into ISO2 country codes.
	 *
	 * @param array<int,object|array<string,string>> $locations Zone locations.
	 * @param array<string,string[]>                   $continents Continent map.
	 * @return string[]
	 */
	private static function parse_zone_locations( $locations, array $continents ) {
		$codes = array();

		foreach ( $locations as $location ) {
			$type = is_array( $location ) ? ( $location['type'] ?? '' ) : ( $location->type ?? '' );
			$code = is_array( $location ) ? ( $location['code'] ?? '' ) : ( $location->code ?? '' );
			$code = strtoupper( (string) $code );

			if ( ! $code ) {
				continue;
			}

			if ( 'country' === $type ) {
				$codes[] = $code;
			} elseif ( 'continent' === $type && isset( $continents[ $code ] ) ) {
				$codes = array_merge( $codes, $continents[ $code ] );
			} elseif ( 'state' === $type && false !== strpos( $code, ':' ) ) {
				$codes[] = strtoupper( explode( ':', $code, 2 )[0] );
			}
		}

		return $codes;
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
