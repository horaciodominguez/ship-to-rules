<?php
/**
 * Migrate v1 CPT + serialized meta → taxonomy terms.
 *
 * @package DestinationShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DS_Migration
 */
class DS_Migration {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_run' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	/**
	 * Run migration once when needed.
	 */
	public static function maybe_run() {
		if ( '1' !== get_option( 'ds_needs_migration', '' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		self::run();
		update_option( 'ds_needs_migration', '0', false );
		update_option( 'ds_migration_done', time(), false );
		DS_Destinations::flush_cache();
	}

	/**
	 * Perform migration.
	 */
	public static function run() {
		$map = get_option( 'ds_legacy_country_map', array() );
		if ( ! is_array( $map ) ) {
			$map = array();
		}

		$wc_countries = DS_Destinations::wc_countries();
		$name_to_iso  = array();
		foreach ( $wc_countries as $code => $name ) {
			$name_to_iso[ strtolower( $name ) ] = $code;
		}

		// CPT → terms.
		$posts = get_posts(
			array(
				'post_type'      => 'product_country',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		foreach ( $posts as $post ) {
			if ( isset( $map[ $post->ID ] ) && term_exists( (int) $map[ $post->ID ], DS_TAXONOMY ) ) {
				continue;
			}

			$existing = term_exists( $post->post_title, DS_TAXONOMY );
			if ( $existing ) {
				$term_id = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
			} else {
				$inserted = wp_insert_term(
					$post->post_title,
					DS_TAXONOMY,
					array(
						'slug' => $post->post_name ? $post->post_name : sanitize_title( $post->post_title ),
					)
				);
				if ( is_wp_error( $inserted ) ) {
					continue;
				}
				$term_id = (int) $inserted['term_id'];
			}

			$iso = '';
			$key = strtolower( $post->post_title );
			if ( isset( $name_to_iso[ $key ] ) ) {
				$iso = $name_to_iso[ $key ];
			}
			if ( $iso ) {
				update_term_meta( $term_id, 'ds_iso2', DS_Destinations::sanitize_iso2( $iso ) );
			}
			update_term_meta( $term_id, 'ds_active', '1' );
			$map[ $post->ID ] = $term_id;
		}

		update_option( 'ds_legacy_country_map', $map, false );

		// Product meta → term relationships.
		$q = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => 'csb_product_countries', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);

		foreach ( $q->posts as $product_id ) {
			$old = get_post_meta( $product_id, 'csb_product_countries', true );
			if ( ! is_array( $old ) || empty( $old ) ) {
				continue;
			}

			$term_ids = array();
			foreach ( $old as $old_id ) {
				$old_id = (int) $old_id;
				if ( isset( $map[ $old_id ] ) ) {
					$term_ids[] = (int) $map[ $old_id ];
				}
			}
			$term_ids = array_values( array_unique( array_filter( $term_ids ) ) );
			if ( $term_ids ) {
				wp_set_object_terms( $product_id, $term_ids, DS_TAXONOMY, false );
			}
		}

		// Hide legacy CPT from UI if still registered elsewhere — we no longer register it.
	}

	/**
	 * Admin notice after migration.
	 */
	public static function notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$done = get_option( 'ds_migration_done' );
		$shown = get_option( 'ds_migration_notice_dismissed' );
		if ( ! $done || $shown ) {
			return;
		}

		$count = count( (array) get_option( 'ds_legacy_country_map', array() ) );
		if ( $count < 1 ) {
			update_option( 'ds_migration_notice_dismissed', '1', false );
			return;
		}

		update_option( 'ds_migration_notice_dismissed', '1', false );

		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html(
			sprintf(
				/* translators: %d: number of migrated countries */
				__( 'Destination Shop migrated %d countries from the previous version. Review Destinations under Products and assign ISO codes for flags.', 'destination-shop' ),
				(int) $count
			)
		);
		echo '</p></div>';
	}
}
