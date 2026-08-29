<?php
/**
 * Frontend: ship-to context, shipping notices, badges, cart UI.
 *
 * @package ShipToRules
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STR_Frontend
 */
class STR_Frontend {

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
		add_shortcode( 'ship_to_context', array( __CLASS__, 'shortcode_context' ) );
		add_shortcode( 'ship_to_picker', array( __CLASS__, 'shortcode_picker' ) );
		add_shortcode( 'ship_to_notice', array( __CLASS__, 'shortcode_notice' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'maybe_enqueue_assets' ), 5 );

		add_action( 'widgets_init', array( __CLASS__, 'register_widget' ) );

		self::register_display_hooks();
	}

	/**
	 * Register conditional display hooks from settings.
	 */
	private static function register_display_hooks() {
		if ( '1' === STR_Settings::get( 'show_ship_to_context' ) ) {
			add_action( 'woocommerce_before_main_content', array( __CLASS__, 'render_context_hook' ), 4 );
		}
		if ( '1' === STR_Settings::get( 'show_product_notice' ) ) {
			add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_product_notice_hook' ), 25 );
			add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'filter_purchasable' ), 20, 2 );
		}
		if ( '1' === STR_Settings::get( 'show_loop_badge' ) ) {
			add_action( 'woocommerce_after_shop_loop_item_title', array( __CLASS__, 'render_loop_badge' ), 12 );
		}

		add_action( 'woocommerce_before_cart', array( __CLASS__, 'render_cart_blocked' ), 5 );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'render_cart_blocked' ), 5 );
	}

	/**
	 * Register widget.
	 */
	public static function register_widget() {
		require_once STR_PLUGIN_DIR . 'includes/class-str-widget.php';
		register_widget( 'STR_Ship_To_Picker_Widget' );
	}

	/**
	 * Register (not always enqueue) assets.
	 */
	public static function register_assets() {
		wp_register_style(
			'str-frontend',
			STR_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			STR_VERSION
		);
		wp_register_script(
			'str-frontend',
			STR_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			STR_VERSION,
			true
		);

		$current = STR_Countries::current();
		wp_localize_script(
			'str-frontend',
			'STR_VARS',
			array(
				'queryVar'    => STR_QUERY_VAR,
				'cookieName'  => STR_COOKIE,
				'currentSlug' => $current ? $current->slug : '',
				'i18n'        => array(
					'search'       => __( 'Search destinations…', 'ship-to-rules' ),
					'all'          => __( 'All destinations', 'ship-to-rules' ),
					'noResults'    => __( 'No matching destinations', 'ship-to-rules' ),
					'shipsTo'      => __( 'Ships to', 'ship-to-rules' ),
					'available'    => __( 'Available for {country}', 'ship-to-rules' ),
					'unavailable'  => __( 'Not available for {country}', 'ship-to-rules' ),
					'selectPrompt' => __( 'Where are you shipping to?', 'ship-to-rules' ),
					'change'       => __( 'Change destination', 'ship-to-rules' ),
				),
			)
		);

		if ( self::is_woocommerce_context() ) {
			self::$needs_assets = true;
		}
	}

	/**
	 * Whether current request is a WooCommerce catalog/product context.
	 *
	 * @return bool
	 */
	private static function is_woocommerce_context() {
		if ( ! function_exists( 'is_woocommerce' ) ) {
			return false;
		}
		return is_woocommerce() || is_search() || is_cart() || is_checkout();
	}

	/**
	 * Enqueue if needed.
	 */
	public static function maybe_enqueue_assets() {
		if ( ! self::$needs_assets ) {
			return;
		}
		wp_enqueue_style( 'str-frontend' );
		wp_enqueue_script( 'str-frontend' );
	}

	/**
	 * Request assets.
	 */
	public static function require_assets() {
		self::$needs_assets = true;
		if ( did_action( 'wp_enqueue_scripts' ) ) {
			wp_enqueue_style( 'str-frontend' );
			wp_enqueue_script( 'str-frontend' );
		}
	}

	/**
	 * Shortcode: product shipping notice.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode_notice( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'product_id' => 0,
			),
			$atts,
			'ship_to_notice'
		);

		return self::render_product_notice( absint( $atts['product_id'] ) ?: null );
	}

	/**
	 * Shortcode: ship-to context strip.
	 *
	 * @return string
	 */
	public static function shortcode_context() {
		return self::render_context();
	}

	/**
	 * Shortcode: compact picker.
	 *
	 * @return string
	 */
	public static function shortcode_picker() {
		return self::render_picker();
	}

	/**
	 * Auto context on WooCommerce pages.
	 */
	public static function render_context_hook() {
		if ( ! self::is_woocommerce_context() ) {
			return;
		}
		echo self::render_context(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Product notice hook.
	 */
	public static function render_product_notice_hook() {
		echo self::render_product_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render unified destination context.
	 *
	 * @return string
	 */
	public static function render_context() {
		self::require_assets();
		$destinations = STR_Countries::get_all();
		$current      = STR_Countries::current();

		ob_start();
		include STR_PLUGIN_DIR . 'templates/ship-to-context.php';
		return ob_get_clean();
	}

	/**
	 * Render compact destination picker.
	 *
	 * @param array $args Args.
	 * @return string
	 */
	public static function render_picker( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'show_clear' => true,
				'input_id'   => 'str-picker-' . wp_unique_id(),
			)
		);

		self::require_assets();
		$destinations = STR_Countries::get_all();
		$current      = STR_Countries::current();
		$input_id     = $args['input_id'];
		$show_clear   = $args['show_clear'];
		$instant      = true;

		ob_start();
		include STR_PLUGIN_DIR . 'templates/ship-to-picker.php';
		return ob_get_clean();
	}

	/**
	 * Render product shipping notice.
	 *
	 * @param int|null $product_id Product ID.
	 * @return string
	 */
	public static function render_product_notice( $product_id = null ) {
		if ( ! $product_id ) {
			$product_id = get_the_ID();
		}
		if ( ! $product_id ) {
			return '';
		}

		self::require_assets();
		$destinations = STR_Countries::for_product( $product_id, false );
		$current      = STR_Countries::current();
		$ships        = STR_Countries::product_ships_to( $product_id, $current );
		$blocked      = false;

		if ( $current && ! empty( $current->iso2 ) ) {
			$blocked = ! STR_Rules::can_ship( $product_id, $current->iso2 );
		}

		ob_start();
		include STR_PLUGIN_DIR . 'templates/product-shipping-notice.php';
		return ob_get_clean();
	}

	/**
	 * Disable add to cart when destination is known and product is blocked.
	 *
	 * @param bool       $purchasable Purchasable.
	 * @param WC_Product $product     Product.
	 * @return bool
	 */
	public static function filter_purchasable( $purchasable, $product ) {
		if ( ! $purchasable || ! $product ) {
			return $purchasable;
		}

		$country = STR_Rules::resolve_shipping_country();
		if ( ! $country ) {
			return $purchasable;
		}

		$product_id   = $product->get_id();
		$variation_id = $product->is_type( 'variation' ) ? $product_id : 0;
		$parent_id    = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product_id;

		return STR_Rules::can_ship( $parent_id, $country, $variation_id );
	}

	/**
	 * Loop availability badge.
	 */
	public static function render_loop_badge() {
		$current = STR_Countries::current();
		if ( ! $current ) {
			return;
		}

		self::require_assets();
		$product_id = get_the_ID();
		$ships      = STR_Countries::product_ships_to( $product_id, $current );
		$class      = $ships ? 'str-badge--yes' : 'str-badge--no';
		$text       = $ships
			? sprintf(
				/* translators: %s: country name */
				__( 'Ships to %s', 'ship-to-rules' ),
				$current->name
			)
			: sprintf(
				/* translators: %s: country name */
				__( 'Not for %s', 'ship-to-rules' ),
				$current->name
			);
		printf(
			'<div class="str-loop-badge %1$s" role="status"><span class="str-loop-badge__flag" aria-hidden="true">%2$s</span> %3$s</div>',
			esc_attr( $class ),
			esc_html( $current->flag ),
			esc_html( $text )
		);
	}

	/**
	 * Show blocked cart items before cart/checkout.
	 */
	public static function render_cart_blocked() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		$country = STR_Rules::resolve_shipping_country();
		if ( ! $country ) {
			return;
		}

		$blocked = STR_Rules::blocked_items( WC()->cart->get_cart(), $country );
		if ( empty( $blocked ) ) {
			return;
		}

		self::require_assets();
		include STR_PLUGIN_DIR . 'templates/cart-blocked-items.php';
	}
}
