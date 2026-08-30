<?php
/**
 * Cart blocked items notice.
 *
 * @package ShipToRules
 *
 * @var array $blocked Blocked items from STR_Rules::blocked_items().
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $blocked ) ) {
	return;
}
?>
<div class="str-cart-blocked" <?php echo STR_Frontend::theme_attr(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> role="alert">
	<div class="str-alert">
		<p><strong><?php esc_html_e( 'Some items cannot ship to your destination:', 'ship-to-rules' ); ?></strong></p>
		<ul>
			<?php foreach ( $blocked as $key => $data ) : ?>
				<?php
				$item     = $data['item'];
				$product  = isset( $item['data'] ) ? $item['data'] : null;
				$name     = $product && is_a( $product, 'WC_Product' ) ? $product->get_name() : __( 'Product', 'ship-to-rules' );
				$remove   = function_exists( 'wc_get_cart_remove_url' ) ? wc_get_cart_remove_url( $key ) : '';
				?>
				<li>
					<?php echo esc_html( $name ); ?> — <?php echo esc_html( $data['reason'] ); ?>
					<?php if ( $remove ) : ?>
						<a href="<?php echo esc_url( $remove ); ?>"><?php esc_html_e( 'Remove', 'ship-to-rules' ); ?></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>
