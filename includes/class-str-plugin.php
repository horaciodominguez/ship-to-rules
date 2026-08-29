<?php
/**
 * Main plugin bootstrapper.
 *
 * @package ShipToRules
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STR_Plugin
 */
class STR_Plugin {

	/**
	 * Singleton.
	 *
	 * @var STR_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Instance.
	 *
	 * @return STR_Plugin
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
		STR_Taxonomy::init();
		STR_Migration::init();
		STR_Settings::init();
		STR_Product_Admin::init();
		STR_Seeder::init();
		STR_Audit::init();
		STR_Query::init();
		STR_Enforcement::init();
		STR_Frontend::init();
	}

	/**
	 * Version bump / first load after upgrade.
	 */
	private function maybe_upgrade() {
		$installed = get_option( 'str_version', '' );
		if ( STR_VERSION === $installed ) {
			return;
		}

		STR_Settings::maybe_seed_defaults();

		if ( '' === $installed || version_compare( (string) $installed, '3.1.0', '<' ) ) {
			update_option( 'str_needs_migration', '1', false );
		}

		update_option( 'str_version', STR_VERSION, false );
	}
}
