<?php
/**
 * Main plugin bootstrapper.
 *
 * @package DestinationShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DS_Plugin
 */
class DS_Plugin {

	/**
	 * Singleton.
	 *
	 * @var DS_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Instance.
	 *
	 * @return DS_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->maybe_upgrade();
		DS_Taxonomy::init();
		DS_Migration::init();
		DS_Settings::init();
		DS_Product_Admin::init();
		DS_Query::init();
		DS_Frontend::init();
	}

	/**
	 * Version bump / first load after upgrade from v1.
	 */
	private function maybe_upgrade() {
		$installed = get_option( 'ds_version', '' );
		if ( DS_VERSION === $installed ) {
			return;
		}

		DS_Settings::maybe_seed_defaults();

		// Fresh install or upgrade from legacy plugin without ds_version.
		if ( '' === $installed || version_compare( (string) $installed, '2.0.0', '<' ) ) {
			update_option( 'ds_needs_migration', '1', false );
		}

		update_option( 'ds_version', DS_VERSION, false );
	}
}
