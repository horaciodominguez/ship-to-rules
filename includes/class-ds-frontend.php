<?php
/**
 * Frontend: destination bar, passport, badges, assets.
 *
 * @package DestinationShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DS_Frontend
 */
class DS_Frontend {

	/**
	 * Whether assets were requested this request.
	 *
	 * @var bool
	 */
	private static $needs_assets = false;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_shortcode( 'destination_shop_bar', array( __CLASS__, 'shortcode_bar' ) );
		add_shortcode( 'destination_passport', array( __CLASS__, 'shortcode_passport' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'maybe_enqueue_assets' ), 5 );

		if ( '1' === DS_Settings::get( 'show_passport' ) ) {
			add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_passport_hook' ), 25 );
		}
		if ( '1' === DS_Settings::get( 'show_loop_badge' ) ) {
			add_action( 'woocommerce_after_shop_loop_item_title', array( __CLASS__, 'render_loop_badge' ), 12 );
		}
	}

	/**
	 * Register (not always enqueue) assets.
	 */
	public static function register_assets() {
		wp_register_style(
			'ds-frontend',
			DS_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			DS_VERSION
		);
		wp_register_script(
			'ds-frontend',
			DS_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			DS_VERSION,
			true
		);

		$current = DS_Destinations::current();
		wp_localize_script(
			'ds-frontend',
			'DS_VARS',
			array(
				'queryVar'    => DS_QUERY_VAR,
				'cookieName'  => DS_COOKIE,
				'resultsUrl'  => DS_Destinations::results_url(),
				'currentSlug' => $current ? $current->slug : '',
				'i18n'        => array(
					'search'       => __( 'Search destinations…', 'destination-shop' ),
					'all'          => __( 'All destinations', 'destination-shop' ),
					'noResults'    => __( 'No matching destinations', 'destination-shop' ),
					'shipsTo'      => __( 'Ships to', 'destination-shop' ),
					'available'    => __( 'Available for {country}', 'destination-shop' ),
					'unavailable'  => __( 'Not available for {country}', 'destination-shop' ),
					'selectPrompt' => __( 'Where are you shipping to?', 'destination-shop' ),
				),
			)
		);

		// Auto-load on shop / product when destination UX is relevant.
		if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() || is_product() || is_search() ) ) {
			self::$needs_assets = true;
		}
	}

	/**
	 * Enqueue if needed.
	 */
	public static function maybe_enqueue_assets() {
		if ( ! self::$needs_assets ) {
			return;
		}
		wp_enqueue_style( 'ds-frontend' );
		wp_enqueue_script( 'ds-frontend' );
	}

	/**
	 * Request assets.
	 */
	public static function require_assets() {
		self::$needs_assets = true;
		if ( did_action( 'wp_enqueue_scripts' ) ) {
			wp_enqueue_style( 'ds-frontend' );
			wp_enqueue_script( 'ds-frontend' );
		}
	}

	/**
	 * Shortcode: bar.
	 *
	 * @return string
	 */
	public static function shortcode_bar() {
		return self::render_bar();
	}

	/**
	 * Shortcode: passport.
	 *
	 * @return string
	 */
	public static function shortcode_passport() {
		return self::render_passport();
	}

	/**
	 * Hook wrapper for passport.
	 */
	public static function render_passport_hook() {
		echo self::render_passport(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render destination bar HTML.
	 *
	 * @return string
	 */
	public static function render_bar() {
		self::require_assets();
		$destinations = DS_Destinations::get_all();
		$current      = DS_Destinations::current();
		$action       = DS_Destinations::results_url();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : get_search_query();

		ob_start();
		include DS_PLUGIN_DIR . 'templates/destination-bar.php';
		return ob_get_clean();
	}

	/**
	 * Render Availability Passport.
	 *
	 * @param int|null $product_id Product ID.
	 * @return string
	 */
	public static function render_passport( $product_id = null ) {
		if ( ! $product_id ) {
			$product_id = get_the_ID();
		}
		if ( ! $product_id ) {
			return '';
		}

		self::require_assets();
		// Include inactive assigned destinations so Passport never hides product rules.
		$destinations = DS_Destinations::for_product( $product_id, false );
		$current      = DS_Destinations::current();
		$ships        = DS_Destinations::product_ships_to( $product_id, $current );
		$results_url  = DS_Destinations::results_url();

		ob_start();
		include DS_PLUGIN_DIR . 'templates/passport.php';
		return ob_get_clean();
	}

	/**
	 * Loop availability badge.
	 */
	public static function render_loop_badge() {
		$current = DS_Destinations::current();
		if ( ! $current ) {
			return;
		}

		self::require_assets();
		$product_id = get_the_ID();
		$ships      = DS_Destinations::product_ships_to( $product_id, $current );
		$class      = $ships ? 'ds-badge--yes' : 'ds-badge--no';
		$text       = $ships
			? sprintf(
				/* translators: %s: country name */
				__( 'Ships to %s', 'destination-shop' ),
				$current->name
			)
			: sprintf(
				/* translators: %s: country name */
				__( 'Not for %s', 'destination-shop' ),
				$current->name
			);
		printf(
			'<div class="ds-loop-badge %1$s" role="status"><span class="ds-loop-badge__flag" aria-hidden="true">%2$s</span> %3$s</div>',
			esc_attr( $class ),
			esc_html( $current->flag ),
			esc_html( $text )
		);
	}
}
