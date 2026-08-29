<?php
/**
 * Shipping rules engine: product × country resolution.
 *
 * @package ShipToRules
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STR_Rules
 */
class STR_Rules {

	const MODE_ALLOW = 'allow';
	const MODE_DENY  = 'deny';

	/**
	 * Whether a product can ship to a country code (ISO2).
	 *
	 * Precedence: product rule > category rule > ships everywhere.
	 *
	 * @param int    $product_id   Product ID (parent for variations).
	 * @param string $country_code ISO2 country code.
	 * @param int    $variation_id Variation ID (optional).
	 * @return bool
	 */
	public static function can_ship( $product_id, $country_code, $variation_id = 0 ) {
		$country_code = STR_Countries::sanitize_iso2( $country_code );
		if ( 2 !== strlen( $country_code ) ) {
			return true;
		}

		$product_id = self::resolve_product_id( $product_id, $variation_id );
		if ( ! $product_id ) {
			return true;
		}

		$product_rule = self::get_product_rule( $product_id );
		if ( null !== $product_rule ) {
			return self::evaluate_rule( $product_rule, $country_code );
		}

		$category_rule = self::get_category_rule( $product_id );
		if ( null !== $category_rule ) {
			return self::evaluate_rule( $category_rule, $country_code );
		}

		return true;
	}

	/**
	 * Resolve parent product ID when a variation is provided.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return int
	 */
	public static function resolve_product_id( $product_id, $variation_id = 0 ) {
		$product_id = absint( $product_id );
		$variation_id = absint( $variation_id );

		if ( $variation_id && function_exists( 'wc_get_product' ) ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation && $variation->get_parent_id() ) {
				return (int) $variation->get_parent_id();
			}
		}

		return $product_id;
	}

	/**
	 * Product-level rule or null when unset (ships everywhere at product level).
	 *
	 * @param int $product_id Product ID.
	 * @return array|null { mode: allow|deny, countries: string[] }
	 */
	public static function get_product_rule( $product_id ) {
		$codes = self::get_product_iso_codes( $product_id );
		if ( empty( $codes ) ) {
			return null;
		}

		return array(
			'mode'      => self::get_product_rule_mode( $product_id ),
			'countries' => $codes,
		);
	}

	/**
	 * Category-level rule or null.
	 *
	 * @param int $product_id Product ID.
	 * @return array|null
	 */
	public static function get_category_rule( $product_id ) {
		$rules = get_option( 'str_category_rules', array() );
		if ( ! is_array( $rules ) || empty( $rules ) ) {
			return null;
		}

		$terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		foreach ( $terms as $term_id ) {
			$term_id = (int) $term_id;
			if ( ! isset( $rules[ $term_id ] ) || ! is_array( $rules[ $term_id ] ) ) {
				continue;
			}

			$rule = $rules[ $term_id ];
			$countries = isset( $rule['countries'] ) ? (array) $rule['countries'] : array();
			$countries = array_values(
				array_unique(
					array_filter(
						array_map(
							array( 'STR_Countries', 'sanitize_iso2' ),
							$countries
						)
					)
				)
			);

			if ( empty( $countries ) ) {
				continue;
			}

			$mode = isset( $rule['mode'] ) && self::MODE_DENY === $rule['mode'] ? self::MODE_DENY : self::MODE_ALLOW;

			return array(
				'mode'      => $mode,
				'countries' => $countries,
			);
		}

		return null;
	}

	/**
	 * Evaluate allow/deny rule against a country.
	 *
	 * @param array  $rule         Rule array.
	 * @param string $country_code ISO2.
	 * @return bool
	 */
	public static function evaluate_rule( array $rule, $country_code ) {
		$country_code = STR_Countries::sanitize_iso2( $country_code );
		$countries    = isset( $rule['countries'] ) ? array_map( 'strtoupper', (array) $rule['countries'] ) : array();
		$mode         = isset( $rule['mode'] ) && self::MODE_DENY === $rule['mode'] ? self::MODE_DENY : self::MODE_ALLOW;
		$in_list      = in_array( $country_code, $countries, true );

		return self::MODE_ALLOW === $mode ? $in_list : ! $in_list;
	}

	/**
	 * Rule mode for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return string allow|deny
	 */
	public static function get_product_rule_mode( $product_id ) {
		$mode = get_post_meta( absint( $product_id ), '_str_rule_mode', true );
		return self::MODE_DENY === $mode ? self::MODE_DENY : self::MODE_ALLOW;
	}

	/**
	 * ISO2 codes assigned to a product via destinations taxonomy.
	 *
	 * @param int $product_id Product ID.
	 * @return string[]
	 */
	public static function get_product_iso_codes( $product_id ) {
		$destinations = STR_Countries::for_product( $product_id );
		$codes        = array();

		foreach ( $destinations as $d ) {
			if ( ! empty( $d->iso2 ) ) {
				$codes[] = strtoupper( $d->iso2 );
			}
		}

		return array_values( array_unique( $codes ) );
	}

	/**
	 * Blocked cart items for a destination country.
	 *
	 * @param array  $items        Cart items.
	 * @param string $country_code ISO2.
	 * @return array<string,array> Keyed by cart item key.
	 */
	public static function blocked_items( $items, $country_code ) {
		$blocked = array();

		foreach ( (array) $items as $key => $item ) {
			$product_id   = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			$variation_id = isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;

			if ( ! self::can_ship( $product_id, $country_code, $variation_id ) ) {
				$blocked[ $key ] = array(
					'item'   => $item,
					'reason' => self::get_block_message( $product_id, $country_code ),
				);
			}
		}

		return $blocked;
	}

	/**
	 * Human-readable block message.
	 *
	 * @param int    $product_id   Product ID.
	 * @param string $country_code ISO2.
	 * @return string
	 */
	public static function get_block_message( $product_id, $country_code ) {
		$country_code = STR_Countries::sanitize_iso2( $country_code );
		$country_name = $country_code;

		if ( function_exists( 'WC' ) && WC()->countries ) {
			$countries = WC()->countries->get_countries();
			if ( isset( $countries[ $country_code ] ) ) {
				$country_name = $countries[ $country_code ];
			}
		}

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		$name    = $product ? $product->get_name() : __( 'This product', 'ship-to-rules' );

		return sprintf(
			/* translators: 1: product name, 2: country name */
			__( '%1$s cannot be shipped to %2$s.', 'ship-to-rules' ),
			wp_strip_all_tags( $name ),
			$country_name
		);
	}

	/**
	 * Intersection of allowed shipping countries for all cart items.
	 *
	 * @param array|null $cart_items Cart items; defaults to current cart.
	 * @return string[] ISO2 codes.
	 */
	public static function allowed_countries_for_cart( $cart_items = null ) {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return array();
		}

		$all = array_keys( WC()->countries->get_shipping_countries() );
		if ( empty( $all ) ) {
			return array();
		}

		if ( null === $cart_items && WC()->cart ) {
			$cart_items = WC()->cart->get_cart();
		}

		if ( empty( $cart_items ) ) {
			return $all;
		}

		$allowed = null;

		foreach ( (array) $cart_items as $item ) {
			$product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			$item_codes = self::allowed_countries_for_product( $product_id, $all );

			if ( null === $allowed ) {
				$allowed = $item_codes;
			} else {
				$allowed = array_values( array_intersect( $allowed, $item_codes ) );
			}
		}

		return is_array( $allowed ) ? $allowed : $all;
	}

	/**
	 * Allowed countries for a single product.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $all        All shipping country codes.
	 * @return string[]
	 */
	public static function allowed_countries_for_product( $product_id, $all = null ) {
		if ( null === $all && function_exists( 'WC' ) && WC()->countries ) {
			$all = array_keys( WC()->countries->get_shipping_countries() );
		}
		$all = (array) $all;

		$product_rule = self::get_product_rule( $product_id );
		if ( null !== $product_rule ) {
			return self::countries_from_rule( $product_rule, $all );
		}

		$category_rule = self::get_category_rule( $product_id );
		if ( null !== $category_rule ) {
			return self::countries_from_rule( $category_rule, $all );
		}

		return $all;
	}

	/**
	 * Expand a rule to allowed country codes.
	 *
	 * @param array $rule Rule.
	 * @param array $all  Universe of countries.
	 * @return string[]
	 */
	public static function countries_from_rule( array $rule, array $all ) {
		$list = array_map( 'strtoupper', (array) ( $rule['countries'] ?? array() ) );
		$mode = isset( $rule['mode'] ) && self::MODE_DENY === $rule['mode'] ? self::MODE_DENY : self::MODE_ALLOW;

		if ( self::MODE_ALLOW === $mode ) {
			return array_values( array_intersect( $all, $list ) );
		}

		return array_values( array_diff( $all, $list ) );
	}

	/**
	 * Best-effort shipping country for enforcement (checkout customer, then cookie).
	 *
	 * @return string ISO2 or empty.
	 */
	public static function resolve_shipping_country() {
		if ( function_exists( 'WC' ) && WC()->customer ) {
			$shipping = STR_Countries::sanitize_iso2( WC()->customer->get_shipping_country() );
			if ( 2 === strlen( $shipping ) ) {
				return $shipping;
			}

			$billing = STR_Countries::sanitize_iso2( WC()->customer->get_billing_country() );
			if ( 2 === strlen( $billing ) ) {
				return $billing;
			}
		}

		$dest = STR_Countries::current();
		if ( $dest && ! empty( $dest->iso2 ) ) {
			return STR_Countries::sanitize_iso2( $dest->iso2 );
		}

		return '';
	}

	/**
	 * Whether all chosen shipping methods are local pickup.
	 *
	 * @return bool
	 */
	public static function is_local_pickup_only() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return false;
		}

		$chosen = WC()->session->get( 'chosen_shipping_methods', array() );
		if ( empty( $chosen ) || ! is_array( $chosen ) ) {
			return false;
		}

		foreach ( $chosen as $method ) {
			if ( false === strpos( (string) $method, 'local_pickup' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether enforcement should run (not local pickup only).
	 *
	 * @return bool
	 */
	public static function should_enforce() {
		return ! self::is_local_pickup_only();
	}
}
