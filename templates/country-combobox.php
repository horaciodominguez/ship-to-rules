<?php
/**
 * Shared country combobox partial (used by context strip and picker).
 *
 * @package ShipToRules
 *
 * @var array       $destinations Destinations list.
 * @var object|null $current      Current destination.
 * @var string      $input_id     Input element ID.
 * @var bool        $instant      Redirect on selection (no form submit).
 */

defined( 'ABSPATH' ) || exit;

$input_id = isset( $input_id ) ? $input_id : 'str-destination-select';
$instant  = ! empty( $instant );
?>
<div class="str-combobox<?php echo $instant ? ' str-combobox--instant' : ''; ?>" data-str-combobox<?php echo $instant ? ' data-str-instant' : ''; ?>>
	<button
		type="button"
		class="str-combobox__toggle"
		aria-haspopup="listbox"
		aria-expanded="false"
		data-str-combobox-toggle
	>
		<span class="str-combobox__flag" data-str-combobox-flag aria-hidden="true">
			<?php echo $current && $current->flag ? esc_html( $current->flag ) : '🌐'; ?>
		</span>
		<span class="str-combobox__label" data-str-combobox-label>
			<?php
			echo $current
				? esc_html( $current->name )
				: esc_html__( 'Where are you shipping to?', 'ship-to-rules' );
			?>
		</span>
		<svg class="str-combobox__chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
			<path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
		</svg>
	</button>

	<div class="str-combobox__panel" data-str-combobox-panel hidden>
		<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>-filter">
			<?php esc_html_e( 'Filter destinations', 'ship-to-rules' ); ?>
		</label>
		<input
			type="search"
			id="<?php echo esc_attr( $input_id ); ?>-filter"
			class="str-combobox__search"
			placeholder="<?php esc_attr_e( 'Search destinations…', 'ship-to-rules' ); ?>"
			autocomplete="off"
			data-str-combobox-search
		/>
		<ul class="str-combobox__list" role="listbox" data-str-combobox-list>
			<li role="option" data-value="" data-name="<?php echo esc_attr__( 'All destinations', 'ship-to-rules' ); ?>" data-flag="🌐" <?php echo ! $current ? 'aria-selected="true"' : ''; ?>>
				<span class="str-combobox__flag" aria-hidden="true">🌐</span>
				<span class="str-combobox__option-name"><?php esc_html_e( 'All destinations', 'ship-to-rules' ); ?></span>
			</li>
			<?php if ( empty( $destinations ) ) : ?>
				<li class="str-combobox__empty" role="presentation">
					<?php esc_html_e( 'No destinations configured yet.', 'ship-to-rules' ); ?>
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
						<span class="str-combobox__flag" aria-hidden="true"><?php echo esc_html( $d->flag ? $d->flag : '•' ); ?></span>
						<span class="str-combobox__option-name"><?php echo esc_html( $d->name ); ?></span>
						<?php if ( $d->iso2 ) : ?>
							<span class="str-combobox__iso"><?php echo esc_html( $d->iso2 ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			<?php endif; ?>
		</ul>
		<p class="str-combobox__no-match" data-str-combobox-empty hidden>
			<?php esc_html_e( 'No matching destinations', 'ship-to-rules' ); ?>
		</p>
	</div>

	<?php if ( ! $instant ) : ?>
		<input
			type="hidden"
			name="<?php echo esc_attr( STR_QUERY_VAR ); ?>"
			id="<?php echo esc_attr( $input_id ); ?>"
			value="<?php echo $current ? esc_attr( $current->slug ) : ''; ?>"
			data-str-combobox-input
		/>
	<?php endif; ?>
</div>
