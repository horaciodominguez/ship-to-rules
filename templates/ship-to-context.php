<?php
/**
 * Single ship-to context strip (country selector).
 *
 * @package ShipToRules
 *
 * @var array       $destinations Destinations list.
 * @var object|null $current      Current destination.
 */

defined( 'ABSPATH' ) || exit;

$input_id  = 'str-context-picker';
$instant   = true;
$clear_url = esc_url( STR_Countries::clear_url() );
?>
<div class="str-context" <?php echo STR_Frontend::theme_attr(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-str-context role="region" aria-label="<?php esc_attr_e( 'Shipping destination', 'ship-to-rules' ); ?>">
	<div class="str-context__inner">
		<p class="str-context__label">
			<?php esc_html_e( 'Shipping destination', 'ship-to-rules' ); ?>
		</p>
		<div class="str-context__controls">
			<?php include STR_PLUGIN_DIR . 'templates/country-combobox.php'; ?>
			<?php if ( $current ) : ?>
				<a class="str-context__clear" href="<?php echo esc_url( $clear_url ); ?>" data-str-clear>
					<?php esc_html_e( 'Clear', 'ship-to-rules' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</div>
