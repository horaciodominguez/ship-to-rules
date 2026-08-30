<?php
/**
 * Compact ship-to country picker.
 *
 * @package ShipToRules
 *
 * @var array       $destinations Destinations list.
 * @var object|null $current      Current destination.
 * @var string      $input_id     Unique input ID prefix.
 * @var bool        $show_clear   Show clear link.
 */

defined( 'ABSPATH' ) || exit;

$input_id   = isset( $input_id ) ? $input_id : 'str-picker';
$show_clear = isset( $show_clear ) ? (bool) $show_clear : true;
$clear_url  = esc_url( STR_Countries::clear_url() );
?>
<div class="str-picker" <?php echo STR_Frontend::theme_attr(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-str-picker>
	<label class="str-picker__label" for="<?php echo esc_attr( $input_id ); ?>-toggle">
		<?php esc_html_e( 'Shipping destination', 'ship-to-rules' ); ?>
	</label>
	<div class="str-picker__controls">
		<?php
		$instant = true;
		include STR_PLUGIN_DIR . 'templates/country-combobox.php';
		?>
		<?php if ( $show_clear && $current ) : ?>
			<a class="str-picker__clear" href="<?php echo esc_url( $clear_url ); ?>" data-str-clear>
				<?php esc_html_e( 'Clear', 'ship-to-rules' ); ?>
			</a>
		<?php endif; ?>
	</div>
</div>
