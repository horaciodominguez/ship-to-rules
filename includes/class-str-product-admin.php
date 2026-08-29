<?php
/**
 * Product admin: tab, columns, bulk assign.
 *
 * @package ShipToRules
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STR_Product_Admin
 */
class STR_Product_Admin {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'panel' ) );
		// Classic WC save path (most reliable for product-data checkboxes).
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save' ), 20 );
		// Keep object hook as a second pass for newer editors.
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_from_product' ), 20 );
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_filter( 'bulk_actions-edit-product', array( __CLASS__, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-product', array( __CLASS__, 'handle_bulk' ), 10, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'bulk_notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_footer', array( __CLASS__, 'bulk_modal' ) );
	}

	/**
	 * Product data tab.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function tab( $tabs ) {
		$tabs['STR_Countries'] = array(
			'label'    => __( 'Ship-To', 'ship-to-rules' ),
			'target'   => 'STR_Countries_product_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 75,
		);
		return $tabs;
	}

	/**
	 * Panel markup.
	 *
	 * Uses a custom layout (not WooCommerce p.form-field + nested labels).
	 * WC admin CSS floats every label with margin-left:-150px, which would
	 * pull checklist rows outside the visible box.
	 */
	public static function panel() {
		global $post;
		$destinations = STR_Countries::get_all( true, true );
		$selected     = wp_get_object_terms( $post->ID, STR_TAXONOMY, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $selected ) ) {
			$selected = array();
		}
		$selected = array_map( 'intval', (array) $selected );
		if ( empty( $selected ) ) {
			$meta_ids = get_post_meta( $post->ID, '_str_ship_to_ids', true );
			if ( is_array( $meta_ids ) ) {
				$selected = array_map( 'intval', $meta_ids );
			}
		}
		$rule_mode = STR_Rules::get_product_rule_mode( $post->ID );
		$manage_url = admin_url( 'edit-tags.php?taxonomy=' . STR_TAXONOMY . '&post_type=product' );
		?>
		<div id="STR_Countries_product_data" class="panel woocommerce_options_panel">
			<div class="options_group str-product-destinations">
				<div class="str-ships-to">
					<h4 class="str-ships-to__title"><?php esc_html_e( 'Ships to', 'ship-to-rules' ); ?></h4>

					<?php if ( empty( $destinations ) ) : ?>
						<p class="str-ships-to__empty">
							<?php
							echo wp_kses_post(
								sprintf(
									/* translators: %s: link to Products → Destinations */
									__( 'No countries yet. <a href="%s">Create countries</a> under Products → Ship-To Countries, then return here to assign them.', 'ship-to-rules' ),
									esc_url( $manage_url )
								)
							);
							?>
						</p>
					<?php else : ?>
						<p class="str-ships-to__help">
							<?php esc_html_e( 'Select where this product can be shipped. Leave empty to treat it as available everywhere (unless a category rule applies).', 'ship-to-rules' ); ?>
						</p>
						<p class="form-field str-rule-mode">
							<label for="str_rule_mode"><?php esc_html_e( 'Rule mode', 'ship-to-rules' ); ?></label>
							<select name="str_rule_mode" id="str_rule_mode">
								<option value="allow" <?php selected( $rule_mode, STR_Rules::MODE_ALLOW ); ?>>
									<?php esc_html_e( 'Allow only selected countries', 'ship-to-rules' ); ?>
								</option>
								<option value="deny" <?php selected( $rule_mode, STR_Rules::MODE_DENY ); ?>>
									<?php esc_html_e( 'Deny selected countries (ship everywhere else)', 'ship-to-rules' ); ?>
								</option>
							</select>
						</p>
						<?php
						/*
						 * Hidden payload is the source of truth on save.
						 * WooCommerce often disables checkboxes in inactive product-data
						 * tabs, so those never reach $_POST. This field stays enabled.
						 */
						?>
						<input
							type="hidden"
							name="str_product_countries_payload"
							id="str_product_countries_payload"
							value="<?php echo esc_attr( wp_json_encode( array_values( $selected ) ) ); ?>"
							data-str-dest-payload
						/>
						<input type="hidden" name="str_product_countries_posted" value="1" />
						<?php wp_nonce_field( 'str_save_product_countries', 'str_product_countries_nonce' ); ?>
						<input
							type="search"
							class="str-dest-filter"
							placeholder="<?php esc_attr_e( 'Filter destinations…', 'ship-to-rules' ); ?>"
							autocomplete="off"
						/>
						<div class="str-country-checklist" role="group" aria-label="<?php esc_attr_e( 'Destinations', 'ship-to-rules' ); ?>" data-str-country-list>
							<?php foreach ( $destinations as $d ) : ?>
								<?php
								$search   = strtolower( $d->name . ( $d->iso2 ? ' ' . $d->iso2 : '' ) );
								$input_id = 'str_country_' . (int) $d->id;
								?>
								<div class="str-dest-item" data-name="<?php echo esc_attr( $search ); ?>">
									<input
										type="checkbox"
										class="str-dest-checkbox"
										id="<?php echo esc_attr( $input_id ); ?>"
										value="<?php echo esc_attr( (string) $d->id ); ?>"
										data-str-dest-id="<?php echo esc_attr( (string) $d->id ); ?>"
										<?php checked( in_array( (int) $d->id, $selected, true ) ); ?>
									/>
									<label class="str-dest-label" for="<?php echo esc_attr( $input_id ); ?>">
										<span class="str-dest-flag" aria-hidden="true"><?php echo esc_html( $d->flag ? $d->flag : '•' ); ?></span>
										<span class="str-dest-name"><?php echo esc_html( $d->name ); ?></span>
										<?php if ( $d->iso2 ) : ?>
											<code class="str-dest-iso"><?php echo esc_html( $d->iso2 ); ?></code>
										<?php endif; ?>
									</label>
								</div>
							<?php endforeach; ?>
						</div>
						<p class="str-dest-count" data-str-dest-count>
							<?php
							printf(
								/* translators: %d: selected count */
								esc_html__( '%d selected', 'ship-to-rules' ),
								count( $selected )
							);
							?>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save destinations from classic product meta POST.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function save( $product_id ) {
		self::persist_destinations( (int) $product_id );
	}

	/**
	 * Save destinations from WC product object hook.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function save_from_product( $product ) {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}
		self::persist_destinations( (int) $product->get_id() );
	}

	/**
	 * Persist destination term IDs from request onto a product.
	 *
	 * @param int $product_id Product ID.
	 */
	private static function persist_destinations( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id || ! current_user_can( 'edit_product', $product_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['str_product_countries_posted'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['str_product_countries_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['str_product_countries_nonce'] ) ), 'str_save_product_countries' ) ) {
			return;
		}

		$ids = array();

		// Prefer JSON payload (survives WooCommerce disabling tab checkboxes).
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['str_product_countries_payload'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$raw = wp_unslash( $_POST['str_product_countries_payload'] );
			$decoded = json_decode( is_string( $raw ) ? $raw : '', true );
			if ( is_array( $decoded ) ) {
				$ids = array_map( 'absint', $decoded );
			}
		} elseif ( isset( $_POST['str_product_countries'] ) && is_array( $_POST['str_product_countries'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$ids = array_map( 'absint', wp_unslash( $_POST['str_product_countries'] ) );
		}

		$ids = array_values( array_unique( array_filter( $ids ) ) );

		$result = wp_set_object_terms( $product_id, $ids, STR_TAXONOMY, false );
		if ( is_wp_error( $result ) ) {
			return;
		}

		clean_object_term_cache( $product_id, 'product' );
		clean_object_term_cache( $product_id, STR_TAXONOMY );
		wp_cache_delete( $product_id, STR_TAXONOMY . '_relationships' );
		wp_cache_delete( $product_id, 'product_meta' );
		STR_Countries::flush_cache();

		update_post_meta( $product_id, '_str_ship_to_ids', $ids );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['str_rule_mode'] ) ) {
			$mode = sanitize_key( wp_unslash( $_POST['str_rule_mode'] ) );
			update_post_meta(
				$product_id,
				'_str_rule_mode',
				STR_Rules::MODE_DENY === $mode ? STR_Rules::MODE_DENY : STR_Rules::MODE_ALLOW
			);
		}
	}

	/**
	 * Products list column.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'product_tag' === $key || 'sku' === $key ) {
				$new['STR_Countries'] = __( 'Ships to', 'ship-to-rules' );
			}
		}
		if ( ! isset( $new['STR_Countries'] ) ) {
			$new['STR_Countries'] = __( 'Ships to', 'ship-to-rules' );
		}
		return $new;
	}

	/**
	 * Column content.
	 *
	 * @param string $column Column.
	 * @param int    $post_id Post ID.
	 */
	public static function column_content( $column, $post_id ) {
		if ( 'STR_Countries' !== $column ) {
			return;
		}
		$dests = STR_Countries::for_product( $post_id );
		if ( empty( $dests ) ) {
			echo '<span style="color:#8c8f94;">' . esc_html__( 'Everywhere', 'ship-to-rules' ) . '</span>';
			return;
		}
		$flags = array();
		foreach ( array_slice( $dests, 0, 6 ) as $d ) {
			$flags[] = $d->flag ? $d->flag : $d->iso2;
		}
		echo '<span title="' . esc_attr( implode( ', ', wp_list_pluck( $dests, 'name' ) ) ) . '">';
		echo esc_html( implode( ' ', $flags ) );
		if ( count( $dests ) > 6 ) {
			echo ' +' . esc_html( (string) ( count( $dests ) - 6 ) );
		}
		echo '</span>';
	}

	/**
	 * Bulk actions.
	 *
	 * @param array $actions Actions.
	 * @return array
	 */
	public static function bulk_actions( $actions ) {
		$actions['str_assign_countries'] = __( 'Assign destinations', 'ship-to-rules' );
		$actions['str_clear_countries']  = __( 'Clear destinations', 'ship-to-rules' );
		return $actions;
	}

	/**
	 * Handle bulk.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $action   Action.
	 * @param array  $post_ids IDs.
	 * @return string
	 */
	public static function handle_bulk( $redirect, $action, $post_ids ) {
		if ( 'str_clear_countries' === $action ) {
			foreach ( $post_ids as $id ) {
				if ( current_user_can( 'edit_product', $id ) ) {
					wp_set_object_terms( $id, array(), STR_TAXONOMY, false );
				}
			}
			STR_Countries::flush_cache();
			return add_query_arg( 'str_bulk', 'cleared', $redirect );
		}

		if ( 'str_assign_countries' === $action ) {
			if ( ! isset( $_REQUEST['str_bulk_countries'] ) || ! is_array( $_REQUEST['str_bulk_countries'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return add_query_arg( 'str_bulk', 'missing', $redirect );
			}
			$term_ids = array_map( 'absint', wp_unslash( $_REQUEST['str_bulk_countries'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$term_ids = array_values( array_filter( $term_ids ) );
			foreach ( $post_ids as $id ) {
				if ( current_user_can( 'edit_product', $id ) ) {
					wp_set_object_terms( $id, $term_ids, STR_TAXONOMY, true ); // append
				}
			}
			STR_Countries::flush_cache();
			return add_query_arg( 'str_bulk', 'assigned', $redirect );
		}

		return $redirect;
	}

	/**
	 * Bulk notices.
	 */
	public static function bulk_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_REQUEST['str_bulk'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = sanitize_key( wp_unslash( $_REQUEST['str_bulk'] ) );
		$map = array(
			'assigned' => __( 'Destinations assigned to selected products.', 'ship-to-rules' ),
			'cleared'  => __( 'Destinations cleared on selected products.', 'ship-to-rules' ),
			'missing'  => __( 'Select at least one destination in the bulk panel, then apply again.', 'ship-to-rules' ),
		);
		if ( ! isset( $map[ $msg ] ) ) {
			return;
		}
		$class = ( 'missing' === $msg ) ? 'notice-warning' : 'notice-success';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $map[ $msg ] ) . '</p></div>';
	}

	/**
	 * Admin assets on product screens.
	 *
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		$ok = in_array( $screen->id, array( 'product', 'edit-product' ), true )
			|| ( isset( $screen->taxonomy ) && STR_TAXONOMY === $screen->taxonomy );
		if ( ! $ok ) {
			return;
		}

		wp_enqueue_style(
			'str-admin',
			STR_PLUGIN_URL . 'assets/css/admin.css',
			array( 'woocommerce_admin_styles' ),
			STR_VERSION
		);
		wp_enqueue_script(
			'str-admin',
			STR_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			STR_VERSION,
			true
		);
	}

	/**
	 * Bulk assign destination picker on products list.
	 */
	public static function bulk_modal() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-product' !== $screen->id ) {
			return;
		}
		$destinations = STR_Countries::get_all( true, true );
		if ( empty( $destinations ) ) {
			return;
		}
		?>
		<div id="str-bulk-countries" class="str-bulk-panel" hidden>
			<strong><?php esc_html_e( 'Destinations to assign', 'ship-to-rules' ); ?></strong>
			<div class="str-bulk-list">
				<?php foreach ( $destinations as $d ) : ?>
					<label>
						<input type="checkbox" name="str_bulk_countries[]" value="<?php echo esc_attr( $d->id ); ?>" form="posts-filter" />
						<?php echo esc_html( ( $d->flag ? $d->flag . ' ' : '' ) . $d->name ); ?>
					</label>
				<?php endforeach; ?>
			</div>
			<p class="description"><?php esc_html_e( 'Checked destinations are appended when you use “Assign destinations”.', 'ship-to-rules' ); ?></p>
		</div>
		<?php
	}
}
