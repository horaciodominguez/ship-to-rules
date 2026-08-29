<?php
/**
 * Catalog query filter by destination.
 *
 * @package ShipToRules
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STR_Query
 */
class STR_Query {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_product_query', array( __CLASS__, 'filter' ), 20 );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_search' ), 20 );
		add_filter( 'woocommerce_shortcode_products_query', array( __CLASS__, 'shortcode_query' ), 20, 1 );
		add_action( 'template_redirect', array( __CLASS__, 'persist_from_request' ), 5 );
		add_filter( 'woocommerce_no_products_found', array( __CLASS__, 'empty_message' ) );
	}

	/**
	 * Persist destination from request into cookie.
	 */
	public static function persist_from_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ STR_QUERY_VAR ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = sanitize_title( wp_unslash( $_GET[ STR_QUERY_VAR ] ) );
		if ( '' === $raw ) {
			STR_Countries::clear_cookie();
			return;
		}
		$dest = STR_Countries::get( $raw );
		if ( $dest ) {
			STR_Countries::set_cookie( $dest->slug );
			self::sync_customer_country( $dest );
		}
	}

	/**
	 * Sync WooCommerce customer country from selected destination.
	 *
	 * @param object $dest Destination.
	 */
	private static function sync_customer_country( $dest ) {
		if ( empty( $dest->iso2 ) || ! function_exists( 'WC' ) || ! WC()->customer ) {
			return;
		}

		$iso = STR_Countries::sanitize_iso2( $dest->iso2 );
		if ( 2 !== strlen( $iso ) ) {
			return;
		}

		WC()->customer->set_shipping_country( $iso );
		if ( ! WC()->customer->get_billing_country() ) {
			WC()->customer->set_billing_country( $iso );
		}
	}

	/**
	 * Main shop / product category queries.
	 *
	 * @param WC_Query $wc_query Query.
	 */
	public static function filter( $wc_query ) {
		if ( is_admin() || '1' !== STR_Settings::get( 'enable_catalog_filter' ) || 'filter' !== STR_Settings::get( 'catalog_mode' ) ) {
			return;
		}

		$dest = STR_Countries::current();
		if ( ! $dest ) {
			return;
		}

		$wc_query->set( 'tax_query', self::merge_tax_query( $wc_query->get( 'tax_query' ), $dest ) );
	}

	/**
	 * Product search (post_type=product&s=).
	 *
	 * @param WP_Query $query Query.
	 */
	public static function filter_search( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( 'filter' !== STR_Settings::get( 'catalog_mode' ) || '1' !== STR_Settings::get( 'enable_catalog_filter' ) ) {
			return;
		}
		if ( ! $query->is_search() ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		$is_product = ( 'product' === $post_type )
			|| ( is_array( $post_type ) && in_array( 'product', $post_type, true ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $is_product && empty( $_GET['post_type'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $is_product && isset( $_GET['post_type'] ) && 'product' !== $_GET['post_type'] ) {
			return;
		}

		$dest = STR_Countries::current();
		if ( ! $dest ) {
			return;
		}

		$query->set( 'tax_query', self::merge_tax_query( $query->get( 'tax_query' ), $dest ) );
		$query->set( 'post_type', 'product' );
	}

	/**
	 * Products shortcode query args.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function shortcode_query( $args ) {
		if ( 'filter' !== STR_Settings::get( 'catalog_mode' ) || '1' !== STR_Settings::get( 'enable_catalog_filter' ) ) {
			return $args;
		}
		$dest = STR_Countries::current();
		if ( ! $dest ) {
			return $args;
		}
		$args['tax_query'] = self::merge_tax_query( isset( $args['tax_query'] ) ? $args['tax_query'] : array(), $dest );
		return $args;
	}

	/**
	 * Merge destination availability into an existing tax_query.
	 *
	 * Products that ship to $dest OR have no destinations assigned (available everywhere).
	 *
	 * @param array|mixed $tax_query Existing tax query.
	 * @param object      $dest      Destination.
	 * @return array
	 */
	public static function merge_tax_query( $tax_query, $dest ) {
		if ( ! is_array( $tax_query ) ) {
			$tax_query = array();
		}

		$availability = array(
			'relation' => 'OR',
			array(
				'taxonomy' => STR_TAXONOMY,
				'field'    => 'term_id',
				'terms'    => array( (int) $dest->id ),
				'operator' => 'IN',
			),
			array(
				'taxonomy' => STR_TAXONOMY,
				'operator' => 'NOT EXISTS',
			),
		);

		if ( empty( $tax_query ) ) {
			return $availability;
		}

		return array(
			'relation' => 'AND',
			$tax_query,
			$availability,
		);
	}

	/**
	 * Replace empty shop message when destination is active.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function empty_message( $html ) {
		$dest = STR_Countries::current();
		if ( ! $dest ) {
			return $html;
		}
		$tpl = STR_Settings::get( 'empty_message' );
		$msg = str_replace( '{country}', $dest->name, $tpl );
		$shop = esc_url( remove_query_arg( STR_QUERY_VAR, STR_Countries::results_url() ) );

		ob_start();
		?>
		<div class="woocommerce-info str-empty-state">
			<p><?php echo esc_html( $msg ); ?></p>
			<p>
				<a class="button" href="<?php echo esc_url( $shop ); ?>">
					<?php esc_html_e( 'Clear destination', 'ship-to-rules' ); ?>
				</a>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}
}
