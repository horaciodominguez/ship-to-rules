<?php
/**
 * Ship-to taxonomy registration and term meta UI.
 *
 * @package DestinationShop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DS_Taxonomy
 */
class DS_Taxonomy {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 5 );
		add_action( 'created_' . DS_TAXONOMY, array( __CLASS__, 'save_term_meta' ) );
		add_action( 'edited_' . DS_TAXONOMY, array( __CLASS__, 'save_term_meta' ) );
		add_action( DS_TAXONOMY . '_add_form_fields', array( __CLASS__, 'add_form_fields' ) );
		add_action( DS_TAXONOMY . '_edit_form_fields', array( __CLASS__, 'edit_form_fields' ) );
		add_filter( 'manage_edit-' . DS_TAXONOMY . '_columns', array( __CLASS__, 'columns' ) );
		add_filter( 'manage_' . DS_TAXONOMY . '_custom_column', array( __CLASS__, 'column_content' ), 10, 3 );
		add_action( 'created_' . DS_TAXONOMY, array( 'DS_Destinations', 'flush_cache' ) );
		add_action( 'edited_' . DS_TAXONOMY, array( 'DS_Destinations', 'flush_cache' ) );
		add_action( 'delete_' . DS_TAXONOMY, array( 'DS_Destinations', 'flush_cache' ) );
	}

	/**
	 * Register taxonomy.
	 */
	public static function register() {
		$labels = array(
			'name'          => __( 'Destinations', 'destination-shop' ),
			'singular_name' => __( 'Destination', 'destination-shop' ),
			'search_items'  => __( 'Search destinations', 'destination-shop' ),
			'all_items'     => __( 'All destinations', 'destination-shop' ),
			'edit_item'     => __( 'Edit destination', 'destination-shop' ),
			'update_item'   => __( 'Update destination', 'destination-shop' ),
			'add_new_item'  => __( 'Add destination', 'destination-shop' ),
			'new_item_name' => __( 'New destination name', 'destination-shop' ),
			'menu_name'     => __( 'Destinations', 'destination-shop' ),
			'not_found'     => __( 'No destinations found.', 'destination-shop' ),
		);

		register_taxonomy(
			DS_TAXONOMY,
			array( 'product' ),
			array(
				'labels'            => $labels,
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => 'edit.php?post_type=product',
				'show_admin_column' => false,
				'show_in_nav_menus' => false,
				'show_tagcloud'     => false,
				'hierarchical'      => false,
				'rewrite'           => false,
				'query_var'         => false,
				'show_in_rest'      => true,
				'capabilities'      => array(
					'manage_terms' => 'manage_woocommerce',
					'edit_terms'   => 'manage_woocommerce',
					'delete_terms' => 'manage_woocommerce',
					'assign_terms' => 'edit_products',
				),
			)
		);
	}

	/**
	 * Add form fields.
	 */
	public static function add_form_fields() {
		?>
		<div class="form-field">
			<label for="ds_iso2"><?php esc_html_e( 'Country code (ISO2)', 'destination-shop' ); ?></label>
			<input type="text" name="ds_iso2" id="ds_iso2" maxlength="2" style="width:4em;text-transform:uppercase;" placeholder="DE" />
			<p><?php esc_html_e( 'Two-letter code used for the flag (e.g. US, AR, JP).', 'destination-shop' ); ?></p>
		</div>
		<div class="form-field">
			<label for="ds_active">
				<input type="checkbox" name="ds_active" id="ds_active" value="1" checked />
				<?php esc_html_e( 'Active (visible in selector)', 'destination-shop' ); ?>
			</label>
		</div>
		<?php
		wp_nonce_field( 'ds_save_term_meta', 'ds_term_meta_nonce' );
	}

	/**
	 * Edit form fields.
	 *
	 * @param WP_Term $term Term.
	 */
	public static function edit_form_fields( $term ) {
		$iso    = get_term_meta( $term->term_id, 'ds_iso2', true );
		$active = get_term_meta( $term->term_id, 'ds_active', true );
		if ( '' === $active ) {
			$active = '1';
		}
		$flag = DS_Destinations::flag_emoji( $iso );
		?>
		<tr class="form-field">
			<th scope="row"><label for="ds_iso2"><?php esc_html_e( 'Country code (ISO2)', 'destination-shop' ); ?></label></th>
			<td>
				<input type="text" name="ds_iso2" id="ds_iso2" value="<?php echo esc_attr( $iso ); ?>" maxlength="2" style="width:4em;text-transform:uppercase;" />
				<?php if ( $flag ) : ?>
					<span class="ds-flag-preview" aria-hidden="true" style="font-size:1.5rem;margin-left:.5rem;"><?php echo esc_html( $flag ); ?></span>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Two-letter code used for the flag (e.g. US, AR, JP).', 'destination-shop' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Status', 'destination-shop' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="ds_active" value="1" <?php checked( '1', (string) $active ); ?> />
					<?php esc_html_e( 'Active (visible in selector)', 'destination-shop' ); ?>
				</label>
			</td>
		</tr>
		<?php
		wp_nonce_field( 'ds_save_term_meta', 'ds_term_meta_nonce' );
	}

	/**
	 * Save term meta.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save_term_meta( $term_id ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! isset( $_POST['ds_term_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ds_term_meta_nonce'] ) ), 'ds_save_term_meta' ) ) {
			return;
		}

		$iso = isset( $_POST['ds_iso2'] ) ? DS_Destinations::sanitize_iso2( wp_unslash( $_POST['ds_iso2'] ) ) : '';
		update_term_meta( $term_id, 'ds_iso2', $iso );
		update_term_meta( $term_id, 'ds_active', isset( $_POST['ds_active'] ) ? '1' : '0' );
	}

	/**
	 * Columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'name' === $key ) {
				$new['ds_flag']   = __( 'Flag', 'destination-shop' );
				$new['ds_iso2']   = __( 'ISO', 'destination-shop' );
				$new['ds_active'] = __( 'Active', 'destination-shop' );
			}
		}
		return $new;
	}

	/**
	 * Column content.
	 *
	 * @param string $content Content.
	 * @param string $column  Column.
	 * @param int    $term_id Term ID.
	 * @return string
	 */
	public static function column_content( $content, $column, $term_id ) {
		if ( 'ds_iso2' === $column ) {
			return esc_html( strtoupper( (string) get_term_meta( $term_id, 'ds_iso2', true ) ) );
		}
		if ( 'ds_flag' === $column ) {
			$iso = get_term_meta( $term_id, 'ds_iso2', true );
			$flag = DS_Destinations::flag_emoji( $iso );
			return $flag ? '<span style="font-size:1.25rem">' . esc_html( $flag ) . '</span>' : '&mdash;';
		}
		if ( 'ds_active' === $column ) {
			$active = get_term_meta( $term_id, 'ds_active', true );
			if ( '' === $active ) {
				$active = '1';
			}
			return '1' === (string) $active
				? '<span style="color:#008a20;">' . esc_html__( 'Yes', 'destination-shop' ) . '</span>'
				: '<span style="color:#8c8f94;">' . esc_html__( 'No', 'destination-shop' ) . '</span>';
		}
		return $content;
	}
}
