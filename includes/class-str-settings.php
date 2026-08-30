<?php
/**
 * Plugin settings.
 *
 * @package ShipToRules
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STR_Settings
 */
class STR_Settings {

	const OPTION = 'str_settings';

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enable_catalog_filter'   => '0',
			'catalog_mode'            => 'badge',
			'narrow_checkout_countries' => '0',
			'show_product_notice'     => '1',
			'show_loop_badge'         => '1',
			'show_ship_to_context' => '1',
			'empty_message'           => __( 'No products ship to {country} yet.', 'ship-to-rules' ),
		);
	}

	/**
	 * Seed defaults once.
	 */
	public static function maybe_seed_defaults() {
		$saved = get_option( self::OPTION, false );
		if ( false === $saved ) {
			add_option( self::OPTION, self::defaults(), '', false );
			return;
		}

		// Merge new keys on upgrade without wiping user settings.
		if ( is_array( $saved ) ) {
			update_option( self::OPTION, wp_parse_args( $saved, self::defaults() ), false );
		}
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_init', array( __CLASS__, 'save_category_rules' ) );
	}

	/**
	 * Settings menu under WooCommerce.
	 */
	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Ship-To Rules', 'ship-to-rules' ),
			__( 'Ship-To Rules', 'ship-to-rules' ),
			'manage_woocommerce',
			STR_ADMIN_PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Register setting.
	 */
	public static function register() {
		register_setting(
			'STR_Settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Save category rules from separate form section.
	 */
	public static function save_category_rules() {
		if ( empty( $_POST['str_save_category_rules'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['str_category_rules_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['str_category_rules_nonce'] ) ), 'str_save_category_rules' ) ) {
			return;
		}

		$raw   = isset( $_POST['str_category_rules'] ) ? wp_unslash( $_POST['str_category_rules'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$rules = array();

		if ( is_array( $raw ) ) {
			foreach ( $raw as $term_id => $row ) {
				$term_id = absint( $term_id );
				if ( ! $term_id || ! is_array( $row ) ) {
					continue;
				}
				$countries = isset( $row['countries'] ) ? preg_split( '/[\s,]+/', strtoupper( $row['countries'] ) ) : array();
				$countries = array_values(
					array_unique(
						array_filter(
							array_map( array( 'STR_Countries', 'sanitize_iso2' ), $countries )
						)
					)
				);
				if ( empty( $countries ) ) {
					continue;
				}
				$mode = isset( $row['mode'] ) && STR_Rules::MODE_DENY === $row['mode'] ? STR_Rules::MODE_DENY : STR_Rules::MODE_ALLOW;
				$rules[ $term_id ] = array(
					'mode'      => $mode,
					'countries' => $countries,
				);
			}
		}

		update_option( 'str_category_rules', $rules, false );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$out = self::defaults();
		if ( ! is_array( $input ) ) {
			return $out;
		}

		$out['enable_catalog_filter']    = ! empty( $input['enable_catalog_filter'] ) ? '1' : '0';
		$out['catalog_mode']             = ( isset( $input['catalog_mode'] ) && 'filter' === $input['catalog_mode'] ) ? 'filter' : 'badge';
		$out['narrow_checkout_countries'] = ! empty( $input['narrow_checkout_countries'] ) ? '1' : '0';
		$out['show_product_notice']      = ! empty( $input['show_product_notice'] ) ? '1' : '0';
		$out['show_loop_badge']          = ! empty( $input['show_loop_badge'] ) ? '1' : '0';
		$out['show_ship_to_context'] = ! empty( $input['show_ship_to_context'] ) ? '1' : '0';
		$out['empty_message']            = isset( $input['empty_message'] )
			? sanitize_text_field( $input['empty_message'] )
			: $out['empty_message'];

		return $out;
	}

	/**
	 * Render page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$s = self::all();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['str_seeded'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$created = isset( $_GET['str_created'] ) ? absint( $_GET['str_created'] ) : 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$skipped = isset( $_GET['str_skipped'] ) ? absint( $_GET['str_skipped'] ) : 0;
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html(
				sprintf(
					/* translators: 1: created count, 2: skipped count */
					__( 'Destinations seeded: %1$d created, %2$d already existed.', 'ship-to-rules' ),
					$created,
					$skipped
				)
			);
			echo '</p></div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['str_reset'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$deleted = isset( $_GET['str_deleted'] ) ? absint( $_GET['str_deleted'] ) : 0;
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html(
				sprintf(
					/* translators: %d: number of deleted destination terms */
					__( 'All ship-to destinations cleared. %d term(s) removed.', 'ship-to-rules' ),
					$deleted
				)
			);
			echo '</p></div>';
		}
		?>
		<div class="wrap str-settings">
			<h1><?php esc_html_e( 'Ship-To Rules', 'ship-to-rules' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Prevent orders that cannot be fulfilled. Assign shipping destinations per product, then enforce at cart and checkout — not geo-blocking by IP.', 'ship-to-rules' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'STR_Settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enforcement', 'ship-to-rules' ); ?></th>
						<td>
							<p><?php esc_html_e( 'Shipping rules are always enforced at cart and checkout when a destination country is known.', 'ship-to-rules' ); ?></p>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[narrow_checkout_countries]" value="1" <?php checked( $s['narrow_checkout_countries'], '1' ); ?> />
								<?php esc_html_e( 'Narrow checkout country list to cart-compatible destinations (UX only; server validation remains active)', 'ship-to-rules' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Shopper clarity', 'ship-to-rules' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[show_ship_to_context]" value="1" <?php checked( $s['show_ship_to_context'], '1' ); ?> />
								<?php esc_html_e( 'Show ship-to context strip (single country selector)', 'ship-to-rules' ); ?>
							</label><br />
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[show_product_notice]" value="1" <?php checked( $s['show_product_notice'], '1' ); ?> />
								<?php esc_html_e( 'Show shipping notice on product pages', 'ship-to-rules' ); ?>
							</label><br />
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[show_loop_badge]" value="1" <?php checked( $s['show_loop_badge'], '1' ); ?> />
								<?php esc_html_e( 'Show availability badge in product loops when destination is selected', 'ship-to-rules' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Optional catalog filter', 'ship-to-rules' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enable_catalog_filter]" value="1" <?php checked( $s['enable_catalog_filter'], '1' ); ?> />
								<?php esc_html_e( 'Enable catalog filtering by selected destination (may affect full-page cache)', 'ship-to-rules' ); ?>
							</label>
							<fieldset style="margin-top:8px;">
								<label>
									<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[catalog_mode]" value="filter" <?php checked( $s['catalog_mode'], 'filter' ); ?> />
									<?php esc_html_e( 'Filter — hide products that do not ship to the destination', 'ship-to-rules' ); ?>
								</label><br />
								<label>
									<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[catalog_mode]" value="badge" <?php checked( $s['catalog_mode'], 'badge' ); ?> />
									<?php esc_html_e( 'Badge — keep full catalog, mark availability only', 'ship-to-rules' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="str_empty_message"><?php esc_html_e( 'Empty filter message', 'ship-to-rules' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="str_empty_message" name="<?php echo esc_attr( self::OPTION ); ?>[empty_message]" value="<?php echo esc_attr( $s['empty_message'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Use {country} as a placeholder. Only used when catalog filter is enabled.', 'ship-to-rules' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Destinations', 'ship-to-rules' ); ?></h2>
			<p><?php esc_html_e( 'Create destination terms from your WooCommerce configuration, or clear them to start over. Seeding only adds or updates countries — it does not remove extras created by a previous seed.', 'ship-to-rules' ); ?></p>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=str_seed_countries&source=zones' ), 'str_seed_countries' ) ); ?>">
					<?php esc_html_e( 'Seed from shipping zones', 'ship-to-rules' ); ?>
				</a>
				<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=str_seed_countries&source=all' ), 'str_seed_countries' ) ); ?>">
					<?php esc_html_e( 'Seed all WooCommerce countries', 'ship-to-rules' ); ?>
				</a>
				<a class="button button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=str_reset_countries' ), 'str_reset_countries' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Remove all ship-to destination countries and clear product assignments? This cannot be undone.', 'ship-to-rules' ) ); ?>');">
					<?php esc_html_e( 'Clear all destinations', 'ship-to-rules' ); ?>
				</a>
			</p>

			<?php STR_Audit::render_panel(); ?>

			<hr />
			<h2><?php esc_html_e( 'Category rules', 'ship-to-rules' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Apply shipping rules to entire product categories. Product-level rules take precedence.', 'ship-to-rules' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'str_save_category_rules', 'str_category_rules_nonce' ); ?>
				<input type="hidden" name="str_save_category_rules" value="1" />
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Category', 'ship-to-rules' ); ?></th>
							<th><?php esc_html_e( 'Mode', 'ship-to-rules' ); ?></th>
							<th><?php esc_html_e( 'Countries (ISO2, comma-separated)', 'ship-to-rules' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$rules = get_option( 'str_category_rules', array() );
						$cats  = get_terms(
							array(
								'taxonomy'   => 'product_cat',
								'hide_empty' => false,
								'number'     => 20,
							)
						);
						if ( is_wp_error( $cats ) ) {
							$cats = array();
						}
						foreach ( $cats as $cat ) :
							$row = isset( $rules[ $cat->term_id ] ) ? $rules[ $cat->term_id ] : array();
							?>
							<tr>
								<td><?php echo esc_html( $cat->name ); ?></td>
								<td>
									<select name="str_category_rules[<?php echo esc_attr( $cat->term_id ); ?>][mode]">
										<option value="allow" <?php selected( isset( $row['mode'] ) ? $row['mode'] : '', 'allow' ); ?>><?php esc_html_e( 'Allow only', 'ship-to-rules' ); ?></option>
										<option value="deny" <?php selected( isset( $row['mode'] ) ? $row['mode'] : '', 'deny' ); ?>><?php esc_html_e( 'Deny', 'ship-to-rules' ); ?></option>
									</select>
								</td>
								<td>
									<input type="text" class="regular-text" name="str_category_rules[<?php echo esc_attr( $cat->term_id ); ?>][countries]" value="<?php echo esc_attr( isset( $row['countries'] ) ? implode( ', ', (array) $row['countries'] ) : '' ); ?>" placeholder="US, CA, MX" />
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button( __( 'Save category rules', 'ship-to-rules' ) ); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Shortcodes & helpers', 'ship-to-rules' ); ?></h2>
			<p><code>[ship_to_context]</code> — <?php esc_html_e( 'Ship-to context strip with country selector', 'ship-to-rules' ); ?></p>
			<p><code>[ship_to_picker]</code> — <?php esc_html_e( 'Compact country picker', 'ship-to-rules' ); ?></p>
			<p><code>[ship_to_notice]</code> — <?php esc_html_e( 'Product shipping availability notice', 'ship-to-rules' ); ?></p>
			<p class="description"><?php esc_html_e( 'PHP: str_get_ship_to_context(), str_get_ship_to_picker(), str_get_ship_to_notice().', 'ship-to-rules' ); ?></p>
		</div>
		<?php
	}
}
