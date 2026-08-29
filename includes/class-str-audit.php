<?php
/**
 * Admin shipping configuration audit.
 *
 * @package ShipToRules
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STR_Audit
 */
class STR_Audit {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'missing_iso_notice' ) );
	}

	/**
	 * Run full audit and return structured results.
	 *
	 * @return array<string,mixed>
	 */
	public static function run() {
		return array(
			'declared_not_zoned' => self::get_declared_not_zoned(),
			'missing_iso'        => self::get_terms_missing_iso(),
			'no_valid_countries' => self::get_products_without_valid_countries(),
		);
	}

	/**
	 * Countries declared for shipping but not covered by an enabled zone method.
	 *
	 * @return array<string,string> code => name
	 */
	public static function get_declared_not_zoned() {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return array();
		}

		$declared = WC()->countries->get_shipping_countries();
		$zoned    = self::get_zoned_country_codes();
		$missing  = array();

		foreach ( $declared as $code => $name ) {
			if ( ! in_array( $code, $zoned, true ) ) {
				$missing[ $code ] = $name;
			}
		}

		return $missing;
	}

	/**
	 * Country codes with at least one enabled shipping method in a matching zone.
	 *
	 * @return string[]
	 */
	public static function get_zoned_country_codes() {
		return STR_Seeder::get_zone_country_codes();
	}

	/**
	 * Destination terms without ISO2.
	 *
	 * @return array<int,object>
	 */
	public static function get_terms_missing_iso() {
		$terms   = STR_Countries::get_all( true, true );
		$missing = array();

		foreach ( $terms as $term ) {
			if ( empty( $term->iso2 ) ) {
				$missing[] = $term;
			}
		}

		return $missing;
	}

	/**
	 * Products whose rules leave zero valid shipping countries.
	 *
	 * @return array<int,string> product_id => name
	 */
	public static function get_products_without_valid_countries() {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return array();
		}

		$all      = array_keys( WC()->countries->get_shipping_countries() );
		$products = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
			)
		);

		$invalid = array();

		foreach ( $products as $product_id ) {
			$allowed = STR_Rules::allowed_countries_for_product( (int) $product_id, $all );
			if ( empty( $allowed ) && ( STR_Rules::get_product_rule( $product_id ) || STR_Rules::get_category_rule( $product_id ) ) ) {
				$product = wc_get_product( $product_id );
				$invalid[ (int) $product_id ] = $product ? $product->get_name() : '#' . $product_id;
			}
		}

		return $invalid;
	}

	/**
	 * Admin notice for destinations missing ISO2.
	 */
	public static function missing_iso_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'edit-product', 'product', 'woocommerce_page_' . STR_ADMIN_PAGE ), true ) ) {
			return;
		}

		$missing = self::get_terms_missing_iso();
		if ( empty( $missing ) ) {
			return;
		}

		$names = wp_list_pluck( $missing, 'name' );
		$url   = admin_url( 'edit-tags.php?taxonomy=' . STR_TAXONOMY . '&post_type=product' );

		echo '<div class="notice notice-warning"><p>';
		echo esc_html(
			sprintf(
				/* translators: %s: comma-separated destination names */
				__( 'Ship-To Rules: these destinations have no ISO country code and cannot be enforced: %s.', 'ship-to-rules' ),
				implode( ', ', $names )
			)
		);
		echo ' <a href="' . esc_url( $url ) . '">';
		esc_html_e( 'Fix destinations', 'ship-to-rules' );
		echo '</a></p></div>';
	}

	/**
	 * Render audit panel HTML for settings page.
	 */
	public static function render_panel() {
		$audit = self::run();
		?>
		<h2><?php esc_html_e( 'Shipping audit', 'ship-to-rules' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Compare what you declare vs what your shipping zones can actually fulfill.', 'ship-to-rules' ); ?></p>

		<?php if ( ! empty( $audit['declared_not_zoned'] ) ) : ?>
			<div class="notice notice-warning inline"><p>
				<strong><?php esc_html_e( 'Declared but not zoned', 'ship-to-rules' ); ?></strong>:
				<?php echo esc_html( implode( ', ', $audit['declared_not_zoned'] ) ); ?>
			</p></div>
		<?php else : ?>
			<p><?php esc_html_e( 'All declared shipping countries are covered by at least one enabled zone method.', 'ship-to-rules' ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $audit['missing_iso'] ) ) : ?>
			<div class="notice notice-warning inline"><p>
				<strong><?php esc_html_e( 'Destinations missing ISO2', 'ship-to-rules' ); ?></strong>:
				<?php echo esc_html( implode( ', ', wp_list_pluck( $audit['missing_iso'], 'name' ) ) ); ?>
			</p></div>
		<?php endif; ?>

		<?php if ( ! empty( $audit['no_valid_countries'] ) ) : ?>
			<div class="notice notice-error inline"><p>
				<strong><?php esc_html_e( 'Products with zero valid destinations', 'ship-to-rules' ); ?></strong>:
				<?php echo esc_html( implode( ', ', $audit['no_valid_countries'] ) ); ?>
			</p></div>
		<?php endif; ?>
		<?php
	}
}
