<?php
/**
 * Cart and checkout enforcement for ship-to rules.
 *
 * @package ShipToRules
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STR_Enforcement
 */
class STR_Enforcement {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_to_cart' ), 20, 6 );
		add_action( 'woocommerce_store_api_validate_add_to_cart', array( __CLASS__, 'validate_store_api_add_to_cart' ), 10, 2 );
		add_action( 'woocommerce_store_api_cart_errors', array( __CLASS__, 'validate_store_api_cart' ), 10, 2 );
		add_action( 'woocommerce_checkout_validate_order_before_payment', array( __CLASS__, 'validate_store_api_order' ), 10, 2 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'validate_classic_cart' ) );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_classic_checkout' ), 10, 2 );
		add_filter( 'woocommerce_countries_shipping_countries', array( __CLASS__, 'narrow_shipping_countries' ), 20, 1 );
	}

	/**
	 * Layer 1: block add to cart when destination is known.
	 *
	 * @param bool  $passed       Validation result.
	 * @param int   $product_id   Product ID.
	 * @param int   $quantity     Quantity.
	 * @param int   $variation_id Variation ID.
	 * @param array $variations   Variation attributes.
	 * @param array $cart_item_data Cart item data.
	 * @return bool
	 */
	public static function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array() ) {
		unset( $quantity, $variations, $cart_item_data );

		if ( ! $passed || ! STR_Rules::should_enforce() ) {
			return $passed;
		}

		$country = STR_Rules::resolve_shipping_country();
		if ( ! $country ) {
			return $passed;
		}

		if ( ! STR_Rules::can_ship( $product_id, $country, $variation_id ) ) {
			wc_add_notice( STR_Rules::get_block_message( $product_id, $country ), 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * Store API add to cart validation.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $request Request data.
	 */
	public static function validate_store_api_add_to_cart( $product, $request ) {
		unset( $request );

		if ( ! STR_Rules::should_enforce() ) {
			return;
		}

		$country = STR_Rules::resolve_shipping_country();
		if ( ! $country || ! $product ) {
			return;
		}

		$product_id   = $product->get_id();
		$variation_id = $product->is_type( 'variation' ) ? $product_id : 0;
		$parent_id    = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product_id;

		if ( ! STR_Rules::can_ship( $parent_id, $country, $variation_id ) ) {
			throw new Exception( STR_Rules::get_block_message( $parent_id, $country ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * Layer 3: Store API cart errors (block checkout).
	 *
	 * @param WP_Error $errors Cart errors.
	 * @param WC_Cart  $cart   Cart.
	 */
	public static function validate_store_api_cart( $errors, $cart ) {
		if ( ! STR_Rules::should_enforce() || ! $cart ) {
			return;
		}

		$country = STR_Rules::resolve_shipping_country();
		if ( ! $country ) {
			return;
		}

		foreach ( STR_Rules::blocked_items( $cart->get_cart(), $country ) as $key => $data ) {
			$errors->add(
				'str_ship_to_' . sanitize_key( $key ),
				$data['reason']
			);
		}
	}

	/**
	 * Layer 3: Store API order validation before payment.
	 *
	 * @param WC_Order $order  Order.
	 * @param WP_Error $errors Errors.
	 */
	public static function validate_store_api_order( $order, $errors ) {
		if ( ! STR_Rules::should_enforce() || ! $order ) {
			return;
		}

		$country = STR_Countries::sanitize_iso2( $order->get_shipping_country() );
		if ( 2 !== strlen( $country ) ) {
			$country = STR_Countries::sanitize_iso2( $order->get_billing_country() );
		}
		if ( 2 !== strlen( $country ) ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
				continue;
			}
			$product_id   = (int) $item->get_product_id();
			$variation_id = (int) $item->get_variation_id();
			if ( ! STR_Rules::can_ship( $product_id, $country, $variation_id ) ) {
				$errors->add(
					'str_ship_to_order',
					STR_Rules::get_block_message( $product_id, $country )
				);
				break;
			}
		}
	}

	/**
	 * Classic checkout cart validation.
	 */
	public static function validate_classic_cart() {
		if ( ! STR_Rules::should_enforce() || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$country = STR_Rules::resolve_shipping_country();
		if ( ! $country ) {
			return;
		}

		foreach ( STR_Rules::blocked_items( WC()->cart->get_cart(), $country ) as $data ) {
			wc_add_notice( $data['reason'], 'error' );
		}
	}

	/**
	 * Classic checkout validation.
	 *
	 * @param array    $data   Posted data.
	 * @param WP_Error $errors Errors.
	 */
	public static function validate_classic_checkout( $data, $errors ) {
		unset( $data );

		if ( ! STR_Rules::should_enforce() || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$country = STR_Rules::resolve_shipping_country();
		if ( ! $country ) {
			return;
		}

		foreach ( STR_Rules::blocked_items( WC()->cart->get_cart(), $country ) as $data ) {
			$errors->add( 'str_ship_to', $data['reason'] );
		}
	}

	/**
	 * Layer 2: optionally narrow shipping countries in checkout context.
	 *
	 * @param array $countries Countries.
	 * @return array
	 */
	public static function narrow_shipping_countries( $countries ) {
		if ( '1' !== STR_Settings::get( 'narrow_checkout_countries' ) ) {
			return $countries;
		}

		if ( ! self::is_checkout_context() || ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return $countries;
		}

		$allowed = STR_Rules::allowed_countries_for_cart();
		if ( empty( $allowed ) ) {
			return $countries;
		}

		return array_intersect_key( $countries, array_flip( $allowed ) );
	}

	/**
	 * Whether current request is cart/checkout frontend.
	 *
	 * @return bool
	 */
	private static function is_checkout_context() {
		if ( ! function_exists( 'is_checkout' ) && ! function_exists( 'is_cart' ) ) {
			return false;
		}

		return ( function_exists( 'is_checkout' ) && is_checkout() )
			|| ( function_exists( 'is_cart' ) && is_cart() );
	}
}
