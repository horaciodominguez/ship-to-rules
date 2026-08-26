<?php
/**
 * Plugin settings (minimal).
 *
 * @package DestinationShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DS_Settings
 */
class DS_Settings {

	const OPTION = 'ds_settings';

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'catalog_mode'    => 'filter', // filter | badge
			'results_url'     => '',
			'show_passport'   => '1',
			'show_loop_badge' => '1',
			'empty_message'   => __( 'No products ship to {country} yet.', 'destination-shop' ),
		);
	}

	/**
	 * Seed defaults once.
	 */
	public static function maybe_seed_defaults() {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
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
	}

	/**
	 * Settings menu under WooCommerce.
	 */
	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Destination Shop', 'destination-shop' ),
			__( 'Destination Shop', 'destination-shop' ),
			'manage_woocommerce',
			'destination-shop',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Register setting.
	 */
	public static function register() {
		register_setting(
			'ds_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
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

		$out['catalog_mode'] = ( isset( $input['catalog_mode'] ) && 'badge' === $input['catalog_mode'] ) ? 'badge' : 'filter';
		$out['results_url']  = isset( $input['results_url'] ) ? esc_url_raw( trim( $input['results_url'] ) ) : '';
		$out['show_passport'] = ! empty( $input['show_passport'] ) ? '1' : '0';
		$out['show_loop_badge'] = ! empty( $input['show_loop_badge'] ) ? '1' : '0';
		$out['empty_message'] = isset( $input['empty_message'] )
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
		?>
		<div class="wrap ds-settings">
			<h1><?php esc_html_e( 'Destination Shop', 'destination-shop' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Let shoppers browse by shipping destination. Clear availability — not geo-blocking.', 'destination-shop' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'ds_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Catalog mode', 'destination-shop' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[catalog_mode]" value="filter" <?php checked( $s['catalog_mode'], 'filter' ); ?> />
									<?php esc_html_e( 'Filter — show only products that ship to the selected destination', 'destination-shop' ); ?>
								</label><br />
								<label>
									<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[catalog_mode]" value="badge" <?php checked( $s['catalog_mode'], 'badge' ); ?> />
									<?php esc_html_e( 'Badge — keep full catalog; mark availability on products', 'destination-shop' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ds_results_url"><?php esc_html_e( 'Results URL', 'destination-shop' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="ds_results_url" name="<?php echo esc_attr( self::OPTION ); ?>[results_url]" value="<?php echo esc_attr( $s['results_url'] ); ?>" placeholder="<?php echo esc_attr( wc_get_page_permalink( 'shop' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Where the destination bar submits. Leave empty to use the WooCommerce shop page.', 'destination-shop' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Availability Passport', 'destination-shop' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[show_passport]" value="1" <?php checked( $s['show_passport'], '1' ); ?> />
								<?php esc_html_e( 'Show passport on product pages', 'destination-shop' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Loop badges', 'destination-shop' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[show_loop_badge]" value="1" <?php checked( $s['show_loop_badge'], '1' ); ?> />
								<?php esc_html_e( 'Show availability badge on product cards when a destination is selected', 'destination-shop' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ds_empty_message"><?php esc_html_e( 'Empty results message', 'destination-shop' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="ds_empty_message" name="<?php echo esc_attr( self::OPTION ); ?>[empty_message]" value="<?php echo esc_attr( $s['empty_message'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Use {country} as a placeholder for the destination name.', 'destination-shop' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Shortcodes', 'destination-shop' ); ?></h2>
			<p><code>[destination_shop_bar]</code> — <?php esc_html_e( 'Destination + product search bar', 'destination-shop' ); ?></p>
			<p><code>[destination_passport]</code> — <?php esc_html_e( 'Availability Passport (product pages)', 'destination-shop' ); ?></p>
			<p><code>ds_get_destination_bar()</code> — <?php esc_html_e( 'PHP helper to render the bar in themes', 'destination-shop' ); ?></p>
		</div>
		<?php
	}
}
