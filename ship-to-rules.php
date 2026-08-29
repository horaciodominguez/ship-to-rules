<?php
/**
 * Plugin Name:       Ship-To Rules for WooCommerce
 * Plugin URI:        https://github.com/horaciodominguez/ship-to-rules
 * Description:       Prevent orders that cannot be fulfilled. Assign shipping destinations per product and enforce at cart and checkout.
 * Version:           3.1.0
 * Author:            Horacio Dominguez
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * WC tested up to:   11.0
 * Text Domain:       ship-to-rules
 * Domain Path:       /languages
 * License:           GPL2+
 *
 * @package ShipToRules
 */

defined( 'ABSPATH' ) || exit;

define( 'STR_VERSION', '3.1.0' );
define( 'STR_PLUGIN_FILE', __FILE__ );
define( 'STR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'STR_TAXONOMY', 'str_ship_to' );
define( 'STR_COOKIE', 'str_ship_to' );
define( 'STR_QUERY_VAR', 'str_ship_to' );
define( 'STR_ADMIN_PAGE', 'ship-to-rules' );

/**
 * Check WooCommerce is active before bootstrapping.
 *
 * @return bool
 */
function str_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * Admin notice when WooCommerce is missing.
 */
function str_missing_woocommerce_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'Ship-To Rules requires WooCommerce to be installed and active.', 'ship-to-rules' );
	echo '</p></div>';
}

/**
 * Declare HPOS compatibility (plugin does not touch orders directly).
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', STR_PLUGIN_FILE, true );
		}
	}
);

/**
 * Bootstrap plugin.
 */
function str_init() {
	load_plugin_textdomain( 'ship-to-rules', false, dirname( plugin_basename( STR_PLUGIN_FILE ) ) . '/languages' );

	if ( ! str_woocommerce_active() ) {
		add_action( 'admin_notices', 'str_missing_woocommerce_notice' );
		return;
	}

	require_once STR_PLUGIN_DIR . 'includes/class-str-countries.php';
	require_once STR_PLUGIN_DIR . 'includes/class-str-rules.php';
	require_once STR_PLUGIN_DIR . 'includes/class-str-taxonomy.php';
	require_once STR_PLUGIN_DIR . 'includes/class-str-migration.php';
	require_once STR_PLUGIN_DIR . 'includes/class-str-settings.php';
	require_once STR_PLUGIN_DIR . 'includes/class-str-product-admin.php';
	require_once STR_PLUGIN_DIR . 'includes/class-str-seeder.php';
	require_once STR_PLUGIN_DIR . 'includes/class-str-audit.php';
	require_once STR_PLUGIN_DIR . 'includes/class-str-query.php';
	require_once STR_PLUGIN_DIR . 'includes/class-str-enforcement.php';
	require_once STR_PLUGIN_DIR . 'includes/class-str-frontend.php';
	require_once STR_PLUGIN_DIR . 'includes/class-str-plugin.php';

	STR_Plugin::instance();
}
add_action( 'plugins_loaded', 'str_init', 20 );

/**
 * Activation: defaults + schedule migration.
 */
function str_activate() {
	if ( ! str_woocommerce_active() ) {
		deactivate_plugins( plugin_basename( STR_PLUGIN_FILE ) );
		wp_die(
			esc_html__( 'Ship-To Rules requires WooCommerce.', 'ship-to-rules' ),
			esc_html__( 'Plugin activation error', 'ship-to-rules' ),
			array( 'back_link' => true )
		);
	}

	require_once STR_PLUGIN_DIR . 'includes/class-str-countries.php';
	require_once STR_PLUGIN_DIR . 'includes/class-str-taxonomy.php';
	require_once STR_PLUGIN_DIR . 'includes/class-str-settings.php';
	require_once STR_PLUGIN_DIR . 'includes/class-str-migration.php';

	STR_Taxonomy::register();
	STR_Settings::maybe_seed_defaults();
	update_option( 'str_needs_migration', '1', false );
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'str_activate' );

/**
 * Deactivation cleanup (keep data).
 */
function str_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'str_deactivate' );

/**
 * Render ship-to context strip (country selector).
 *
 * @return string
 */
function str_get_ship_to_context() {
	if ( ! class_exists( 'STR_Frontend' ) ) {
		return '';
	}
	return STR_Frontend::render_context();
}

/**
 * Render compact country picker.
 *
 * @param array $args Optional picker args.
 * @return string
 */
function str_get_ship_to_picker( $args = array() ) {
	if ( ! class_exists( 'STR_Frontend' ) ) {
		return '';
	}
	return STR_Frontend::render_picker( $args );
}

/**
 * Render product shipping notice.
 *
 * @param int|null $product_id Product ID.
 * @return string
 */
function str_get_ship_to_notice( $product_id = null ) {
	if ( ! class_exists( 'STR_Frontend' ) ) {
		return '';
	}
	return STR_Frontend::render_product_notice( $product_id );
}
