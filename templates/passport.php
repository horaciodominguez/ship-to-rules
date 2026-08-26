<?php
/**
 * Availability Passport template.
 *
 * Product destinations are primary. Visitor destination is only a status check.
 *
 * @package DestinationShop
 *
 * @var int         $product_id   Product ID.
 * @var array       $destinations Product destinations.
 * @var object|null $current      Current visitor destination.
 * @var bool|null   $ships        Whether product ships to current.
 * @var string      $results_url  Shop URL.
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="ds-passport" data-ds-passport aria-label="<?php esc_attr_e( 'Shipping destinations', 'destination-shop' ); ?>">
	<header class="ds-passport__header">
		<h2 class="ds-passport__title"><?php esc_html_e( 'Availability Passport', 'destination-shop' ); ?></h2>
	</header>

	<?php if ( empty( $destinations ) ) : ?>
		<p class="ds-passport__everywhere">
			<?php esc_html_e( 'This product ships to all destinations.', 'destination-shop' ); ?>
		</p>
	<?php else : ?>
		<p class="ds-passport__count">
			<?php
			printf(
				/* translators: %d: number of countries */
				esc_html( _n( 'This product ships to %d country:', 'This product ships to %d countries:', count( $destinations ), 'destination-shop' ) ),
				count( $destinations )
			);
			?>
		</p>
		<ul class="ds-passport__chips">
			<?php foreach ( $destinations as $d ) : ?>
				<?php
				$is_current = $current && (int) $current->id === (int) $d->id;
				$url        = add_query_arg(
					array(
						DS_QUERY_VAR => $d->slug,
						'post_type'  => 'product',
					),
					$results_url
				);
				?>
				<li>
					<a
						class="ds-chip<?php echo $is_current ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( $url ); ?>"
						title="<?php echo esc_attr( sprintf( __( 'Browse products that ship to %s', 'destination-shop' ), $d->name ) ); ?>"
					>
						<span class="ds-chip__flag" aria-hidden="true"><?php echo esc_html( $d->flag ? $d->flag : '•' ); ?></span>
						<span class="ds-chip__name"><?php echo esc_html( $d->name ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $current ) : ?>
		<?php if ( $ships ) : ?>
			<p class="ds-passport__status ds-passport__status--yes" role="status">
				<span aria-hidden="true"><?php echo esc_html( $current->flag ); ?></span>
				<?php
				printf(
					/* translators: %s: visitor destination country */
					esc_html__( 'Your destination: %s — available', 'destination-shop' ),
					esc_html( $current->name )
				);
				?>
			</p>
		<?php else : ?>
			<p class="ds-passport__status ds-passport__status--no" role="status">
				<span aria-hidden="true"><?php echo esc_html( $current->flag ); ?></span>
				<?php
				printf(
					/* translators: %s: visitor destination country */
					esc_html__( 'Your destination: %s — not available', 'destination-shop' ),
					esc_html( $current->name )
				);
				?>
			</p>
			<p class="ds-passport__alt">
				<a href="<?php echo esc_url( add_query_arg( array( DS_QUERY_VAR => $current->slug, 'post_type' => 'product' ), $results_url ) ); ?>">
					<?php
					printf(
						/* translators: %s: country */
						esc_html__( 'See products that ship to %s', 'destination-shop' ),
						esc_html( $current->name )
					);
					?>
				</a>
			</p>
		<?php endif; ?>
	<?php endif; ?>
</section>
