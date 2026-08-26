<?php
/**
 * Destination bar template.
 *
 * @package DestinationShop
 *
 * @var array       $destinations Destinations list.
 * @var object|null $current      Current destination.
 * @var string      $action       Form action URL.
 * @var string      $search       Search query.
 */

defined( 'ABSPATH' ) || exit;
?>
<form
	class="ds-bar"
	role="search"
	method="get"
	action="<?php echo esc_url( $action ); ?>"
	data-ds-bar
>
	<input type="hidden" name="post_type" value="product" />

	<div class="ds-bar__destination">
		<label class="screen-reader-text" for="ds-destination-select">
			<?php esc_html_e( 'Shipping destination', 'destination-shop' ); ?>
		</label>

		<div class="ds-combobox" data-ds-combobox>
			<button
				type="button"
				class="ds-combobox__toggle"
				aria-haspopup="listbox"
				aria-expanded="false"
				data-ds-combobox-toggle
			>
				<span class="ds-combobox__flag" data-ds-combobox-flag aria-hidden="true">
					<?php echo $current && $current->flag ? esc_html( $current->flag ) : '🌐'; ?>
				</span>
				<span class="ds-combobox__label" data-ds-combobox-label>
					<?php
					echo $current
						? esc_html( $current->name )
						: esc_html__( 'Where are you shipping to?', 'destination-shop' );
					?>
				</span>
				<svg class="ds-combobox__chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
					<path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</button>

			<div class="ds-combobox__panel" data-ds-combobox-panel hidden>
				<label class="screen-reader-text" for="ds-destination-filter">
					<?php esc_html_e( 'Filter destinations', 'destination-shop' ); ?>
				</label>
				<input
					type="search"
					id="ds-destination-filter"
					class="ds-combobox__search"
					placeholder="<?php esc_attr_e( 'Search destinations…', 'destination-shop' ); ?>"
					autocomplete="off"
					data-ds-combobox-search
				/>
				<ul class="ds-combobox__list" role="listbox" data-ds-combobox-list>
					<li role="option" data-value="" data-name="<?php echo esc_attr__( 'All destinations', 'destination-shop' ); ?>" <?php echo ! $current ? 'aria-selected="true"' : ''; ?>>
						<span class="ds-combobox__flag" aria-hidden="true">🌐</span>
						<span><?php esc_html_e( 'All destinations', 'destination-shop' ); ?></span>
					</li>
					<?php if ( empty( $destinations ) ) : ?>
						<li class="ds-combobox__empty" role="presentation">
							<?php esc_html_e( 'No destinations configured yet.', 'destination-shop' ); ?>
						</li>
					<?php else : ?>
						<?php foreach ( $destinations as $d ) : ?>
							<li
								role="option"
								data-value="<?php echo esc_attr( $d->slug ); ?>"
								data-name="<?php echo esc_attr( $d->name ); ?>"
								data-flag="<?php echo esc_attr( $d->flag ); ?>"
								<?php echo ( $current && (int) $current->id === (int) $d->id ) ? 'aria-selected="true"' : ''; ?>
							>
								<span class="ds-combobox__flag" aria-hidden="true"><?php echo esc_html( $d->flag ? $d->flag : '•' ); ?></span>
								<span><?php echo esc_html( $d->name ); ?></span>
								<?php if ( $d->iso2 ) : ?>
									<code><?php echo esc_html( $d->iso2 ); ?></code>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					<?php endif; ?>
				</ul>
				<p class="ds-combobox__no-match" data-ds-combobox-empty hidden>
					<?php esc_html_e( 'No matching destinations', 'destination-shop' ); ?>
				</p>
			</div>

			<input
				type="hidden"
				name="<?php echo esc_attr( DS_QUERY_VAR ); ?>"
				id="ds-destination-select"
				value="<?php echo $current ? esc_attr( $current->slug ) : ''; ?>"
				data-ds-combobox-input
			/>
		</div>
	</div>

	<div class="ds-bar__search">
		<label class="screen-reader-text" for="ds-product-search">
			<?php esc_html_e( 'Search products', 'destination-shop' ); ?>
		</label>
		<input
			type="search"
			id="ds-product-search"
			name="s"
			class="ds-bar__search-input"
			placeholder="<?php esc_attr_e( 'Search products…', 'destination-shop' ); ?>"
			value="<?php echo esc_attr( $search ); ?>"
		/>
	</div>

	<button type="submit" class="ds-bar__submit">
		<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
			<circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>
			<path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
		</svg>
		<span><?php esc_html_e( 'Browse', 'destination-shop' ); ?></span>
	</button>
</form>
