<?php
/**
 * Plugin Name:       Destination Shop for WooCommerce
 * Plugin URI:        https://github.com/horacio/destination-shop
 * Description:       Let customers browse your catalog by shipping destination — clear availability, not geo-blocking.
 * Version:           2.0.3
 * Author:            Horacio Dominguez
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * WC tested up to:   9.3
 * Text Domain:       destination-shop
 * Domain Path:       /languages
 * License:           GPL2+
 *
 * @package DestinationShop
 */

defined( 'ABSPATH' ) || exit;

define( 'DS_VERSION', '2.0.3' );
define( 'DS_PLUGIN_FILE', __FILE__ );
define( 'DS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DS_TAXONOMY', 'ds_ship_to' );
define( 'DS_COOKIE', 'ds_destination' );
define( 'DS_QUERY_VAR', 'ds_destination' );

/**
 * Check WooCommerce is active before bootstrapping.
 */
function ds_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * Admin notice when WooCommerce is missing.
 */
function ds_missing_woocommerce_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'Destination Shop requires WooCommerce to be installed and active.', 'destination-shop' );
	echo '</p></div>';
}

/**
 * Declare HPOS compatibility (plugin does not touch orders).
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DS_PLUGIN_FILE, true );
		}
	}
);

/**
 * Bootstrap plugin.
 */
function ds_init() {
	load_plugin_textdomain( 'destination-shop', false, dirname( plugin_basename( DS_PLUGIN_FILE ) ) . '/languages' );

	if ( ! ds_woocommerce_active() ) {
		add_action( 'admin_notices', 'ds_missing_woocommerce_notice' );
		return;
	}

	require_once DS_PLUGIN_DIR . 'includes/class-ds-destinations.php';
	require_once DS_PLUGIN_DIR . 'includes/class-ds-taxonomy.php';
	require_once DS_PLUGIN_DIR . 'includes/class-ds-migration.php';
	require_once DS_PLUGIN_DIR . 'includes/class-ds-settings.php';
	require_once DS_PLUGIN_DIR . 'includes/class-ds-product-admin.php';
	require_once DS_PLUGIN_DIR . 'includes/class-ds-query.php';
	require_once DS_PLUGIN_DIR . 'includes/class-ds-frontend.php';
	require_once DS_PLUGIN_DIR . 'includes/class-ds-plugin.php';

	DS_Plugin::instance();
}
add_action( 'plugins_loaded', 'ds_init', 20 );

/**
 * Activation: defaults + schedule migration flag check.
 */
function ds_activate() {
	if ( ! ds_woocommerce_active() ) {
		deactivate_plugins( plugin_basename( DS_PLUGIN_FILE ) );
		wp_die(
			esc_html__( 'Destination Shop requires WooCommerce.', 'destination-shop' ),
			esc_html__( 'Plugin activation error', 'destination-shop' ),
			array( 'back_link' => true )
		);
	}

	require_once DS_PLUGIN_DIR . 'includes/class-ds-destinations.php';
	require_once DS_PLUGIN_DIR . 'includes/class-ds-taxonomy.php';
	require_once DS_PLUGIN_DIR . 'includes/class-ds-settings.php';
	require_once DS_PLUGIN_DIR . 'includes/class-ds-migration.php';

	DS_Taxonomy::register();
	DS_Settings::maybe_seed_defaults();
	update_option( 'ds_needs_migration', '1', false );
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ds_activate' );

/**
 * Deactivation cleanup (keep data).
 */
function ds_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ds_deactivate' );

/**
 * Public helper: render destination bar.
 *
 * @return string
 */
function ds_get_destination_bar() {
	if ( ! class_exists( 'DS_Frontend' ) ) {
		return '';
	}
	return DS_Frontend::render_bar();
}
