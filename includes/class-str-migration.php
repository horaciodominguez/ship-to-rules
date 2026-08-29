<?php
/**
 * Database migrations from legacy Destination Shop / wp-country-search installs.
 *
 * @package ShipToRules
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STR_Migration
 */
class STR_Migration {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_run' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	/**
	 * Run pending migrations once.
	 */
	public static function maybe_run() {
		if ( '1' !== get_option( 'str_needs_migration', '' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		self::run();
		update_option( 'str_needs_migration', '0', false );
		update_option( 'str_migration_done', time(), false );
		STR_Countries::flush_cache();
	}

	/**
	 * Perform all migrations.
	 */
	public static function run() {
		self::migrate_legacy_options();
		self::migrate_taxonomy();
		self::migrate_term_meta();
		self::migrate_post_meta();
		self::migrate_widgets();
		self::migrate_v1_cpt();
	}

	/**
	 * Copy legacy plugin options to str_* keys.
	 */
	private static function migrate_legacy_options() {
		global $wpdb;

		$legacy_settings = get_option( 'ds_settings', null );
		if ( is_array( $legacy_settings ) && false === get_option( 'str_settings', false ) ) {
			if ( isset( $legacy_settings['show_destination_context'] ) && ! isset( $legacy_settings['show_ship_to_context'] ) ) {
				$legacy_settings['show_ship_to_context'] = $legacy_settings['show_destination_context'];
				unset( $legacy_settings['show_destination_context'] );
			}
			add_option( 'str_settings', $legacy_settings, '', false );
		}

		$legacy_rules = get_option( 'ds_category_rules', null );
		if ( is_array( $legacy_rules ) && false === get_option( 'str_category_rules', false ) ) {
			add_option( 'str_category_rules', $legacy_rules, '', false );
		}

		$legacy_map = get_option( 'ds_legacy_country_map', null );
		if ( is_array( $legacy_map ) && false === get_option( 'str_legacy_country_map', false ) ) {
			add_option( 'str_legacy_country_map', $legacy_map, '', false );
		}

		// Drop obsolete options after copy.
		delete_option( 'ds_settings' );
		delete_option( 'ds_category_rules' );
		delete_option( 'ds_version' );
		delete_option( 'ds_needs_migration' );
		delete_option( 'ds_migration_done' );
		delete_option( 'ds_migration_notice_dismissed' );
		delete_option( 'ds_legacy_country_map' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ds_%' OR option_name LIKE '_transient_timeout_ds_%'" );
	}

	/**
	 * Rename taxonomy slug ds_ship_to → str_ship_to.
	 */
	private static function migrate_taxonomy() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->term_taxonomy,
			array( 'taxonomy' => STR_TAXONOMY ),
			array( 'taxonomy' => 'ds_ship_to' )
		);

		clean_term_cache( array(), 'ds_ship_to' );
		clean_term_cache( array(), STR_TAXONOMY );
	}

	/**
	 * Rename term meta keys.
	 */
	private static function migrate_term_meta() {
		global $wpdb;

		$map = array(
			'ds_iso2'   => 'str_iso2',
			'ds_active' => 'str_active',
		);

		foreach ( $map as $old => $new ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->termmeta,
				array( 'meta_key' => $new ),
				array( 'meta_key' => $old )
			);
		}
	}

	/**
	 * Rename product meta keys.
	 */
	private static function migrate_post_meta() {
		global $wpdb;

		$map = array(
			'_ds_rule_mode'    => '_str_rule_mode',
			'_ds_ship_to_ids'  => '_str_ship_to_ids',
		);

		foreach ( $map as $old => $new ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->postmeta,
				array( 'meta_key' => $new ),
				array( 'meta_key' => $old )
			);
		}
	}

	/**
	 * Update widget IDs in sidebars.
	 */
	private static function migrate_widgets() {
		$sidebars = get_option( 'sidebars_widgets', array() );
		if ( ! is_array( $sidebars ) ) {
			return;
		}

		$changed = false;
		foreach ( $sidebars as $sidebar => $widgets ) {
			if ( ! is_array( $widgets ) ) {
				continue;
			}
			foreach ( $widgets as $i => $widget_id ) {
				if ( is_string( $widget_id ) && 0 === strpos( $widget_id, 'ds_destination_picker-' ) ) {
					$new_id = 'str_ship_to_picker-' . substr( $widget_id, strlen( 'ds_destination_picker-' ) );
					$sidebars[ $sidebar ][ $i ] = $new_id;

					$old_opts = get_option( 'widget_ds_destination_picker', array() );
					if ( is_array( $old_opts ) && ! empty( $old_opts ) ) {
						update_option( 'widget_str_ship_to_picker', $old_opts, false );
						delete_option( 'widget_ds_destination_picker' );
					}
					$changed = true;
				}
			}
		}

		if ( $changed ) {
			update_option( 'sidebars_widgets', $sidebars, false );
		}
	}

	/**
	 * Migrate v1 product_country CPT + csb_product_countries meta.
	 */
	private static function migrate_v1_cpt() {
		$map = get_option( 'str_legacy_country_map', array() );
		if ( ! is_array( $map ) ) {
			$map = array();
		}

		$wc_countries = STR_Countries::wc_countries();
		$name_to_iso  = array();
		foreach ( $wc_countries as $code => $name ) {
			$name_to_iso[ strtolower( $name ) ] = $code;
		}

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
			if ( isset( $map[ $post->ID ] ) && term_exists( (int) $map[ $post->ID ], STR_TAXONOMY ) ) {
				continue;
			}

			$existing = term_exists( $post->post_title, STR_TAXONOMY );
			if ( $existing ) {
				$term_id = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
			} else {
				$inserted = wp_insert_term(
					$post->post_title,
					STR_TAXONOMY,
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
				update_term_meta( $term_id, 'str_iso2', STR_Countries::sanitize_iso2( $iso ) );
			}
			update_term_meta( $term_id, 'str_active', '1' );
			$map[ $post->ID ] = $term_id;
		}

		update_option( 'str_legacy_country_map', $map, false );

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
				wp_set_object_terms( $product_id, $term_ids, STR_TAXONOMY, false );
			}
		}
	}

	/**
	 * Admin notice after v1 CPT migration.
	 */
	public static function notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$done  = get_option( 'str_migration_done' );
		$shown = get_option( 'str_migration_notice_dismissed' );
		if ( ! $done || $shown ) {
			return;
		}

		$count = count( (array) get_option( 'str_legacy_country_map', array() ) );
		if ( $count < 1 ) {
			update_option( 'str_migration_notice_dismissed', '1', false );
			return;
		}

		update_option( 'str_migration_notice_dismissed', '1', false );

		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html(
			sprintf(
				/* translators: %d: number of migrated countries */
				__( 'Ship-To Rules migrated %d countries from a previous install. Review countries under Products → Ship-To Countries.', 'ship-to-rules' ),
				(int) $count
			)
		);
		echo '</p></div>';
	}
}
