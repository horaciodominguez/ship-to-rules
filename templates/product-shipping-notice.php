<?php
/**
 * Product page shipping notice.
 *
 * @package ShipToRules
 *
 * @var int         $product_id   Product ID.
 * @var array       $destinations Assigned destinations.
 * @var object|null $current      Visitor destination.
 * @var bool|null   $ships        Ships to current destination.
 * @var bool        $blocked      Blocked for current destination.
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="str-shipping-notice" aria-label="<?php esc_attr_e( 'Shipping availability', 'ship-to-rules' ); ?>">
	<?php if ( empty( $destinations ) ) : ?>
		<p class="str-shipping-notice__everywhere">
			<?php esc_html_e( 'This product ships worldwide.', 'ship-to-rules' ); ?>
		</p>
	<?php else : ?>
		<p class="str-shipping-notice__summary">
			<?php
			printf(
				/* translators: %d: number of countries */
				esc_html( _n( 'Ships to %d country', 'Ships to %d countries', count( $destinations ), 'ship-to-rules' ) ),
				count( $destinations )
			);
			?>
		</p>
		<ul class="str-shipping-notice__chips" aria-label="<?php esc_attr_e( 'Destination countries', 'ship-to-rules' ); ?>">
			<?php foreach ( array_slice( $destinations, 0, 8 ) as $d ) : ?>
				<li>
					<span class="str-chip">
						<span class="str-chip__flag" aria-hidden="true"><?php echo esc_html( $d->flag ? $d->flag : '•' ); ?></span>
						<span class="str-chip__name"><?php echo esc_html( $d->name ); ?></span>
					</span>
				</li>
			<?php endforeach; ?>
			<?php if ( count( $destinations ) > 8 ) : ?>
				<li><span class="str-chip">+<?php echo esc_html( (string) ( count( $destinations ) - 8 ) ); ?></span></li>
			<?php endif; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $current ) : ?>
		<?php if ( $ships ) : ?>
			<p class="str-shipping-notice__status str-shipping-notice__status--yes" role="status">
				<span aria-hidden="true"><?php echo esc_html( $current->flag ); ?></span>
				<?php
				printf(
					/* translators: %s: country name */
					esc_html__( 'Available for shipping to %s', 'ship-to-rules' ),
					esc_html( $current->name )
				);
				?>
			</p>
		<?php else : ?>
			<p class="str-shipping-notice__status str-shipping-notice__status--no" role="status">
				<span aria-hidden="true"><?php echo esc_html( $current->flag ); ?></span>
				<?php
				printf(
					/* translators: %s: country name */
					esc_html__( 'Cannot be shipped to %s', 'ship-to-rules' ),
					esc_html( $current->name )
				);
				?>
			</p>
		<?php endif; ?>
	<?php else : ?>
		<p class="str-shipping-notice__hint">
			<?php esc_html_e( 'Select your shipping destination above to check availability.', 'ship-to-rules' ); ?>
		</p>
	<?php endif; ?>

	<?php if ( $blocked ) : ?>
		<p class="str-shipping-notice__blocked">
			<?php esc_html_e( 'This product cannot be added to cart for your selected destination.', 'ship-to-rules' ); ?>
		</p>
	<?php endif; ?>
</section>
