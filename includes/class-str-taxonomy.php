<?php
/**
 * Ship-to taxonomy registration and term meta UI.
 *
 * @package ShipToRules
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STR_Taxonomy
 */
class STR_Taxonomy {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 5 );
		add_action( 'created_' . STR_TAXONOMY, array( __CLASS__, 'save_term_meta' ) );
		add_action( 'edited_' . STR_TAXONOMY, array( __CLASS__, 'save_term_meta' ) );
		add_action( STR_TAXONOMY . '_add_form_fields', array( __CLASS__, 'add_form_fields' ) );
		add_action( STR_TAXONOMY . '_edit_form_fields', array( __CLASS__, 'edit_form_fields' ) );
		add_filter( 'manage_edit-' . STR_TAXONOMY . '_columns', array( __CLASS__, 'columns' ) );
		add_filter( 'manage_' . STR_TAXONOMY . '_custom_column', array( __CLASS__, 'column_content' ), 10, 3 );
		add_action( 'created_' . STR_TAXONOMY, array( 'STR_Countries', 'flush_cache' ) );
		add_action( 'edited_' . STR_TAXONOMY, array( 'STR_Countries', 'flush_cache' ) );
		add_action( 'delete_' . STR_TAXONOMY, array( 'STR_Countries', 'flush_cache' ) );
		add_action( 'admin_notices', array( __CLASS__, 'term_save_notices' ) );
	}

	/**
	 * Register taxonomy.
	 */
	public static function register() {
		$labels = array(
			'name'          => __( 'Ship-To Countries', 'ship-to-rules' ),
			'singular_name' => __( 'Ship-To Country', 'ship-to-rules' ),
			'search_items'  => __( 'Search countries', 'ship-to-rules' ),
			'all_items'     => __( 'All countries', 'ship-to-rules' ),
			'edit_item'     => __( 'Edit country', 'ship-to-rules' ),
			'update_item'   => __( 'Update country', 'ship-to-rules' ),
			'add_new_item'  => __( 'Add country', 'ship-to-rules' ),
			'new_item_name' => __( 'New country name', 'ship-to-rules' ),
			'menu_name'     => __( 'Ship-To Countries', 'ship-to-rules' ),
			'not_found'     => __( 'No countries found.', 'ship-to-rules' ),
		);

		register_taxonomy(
			STR_TAXONOMY,
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
			<label for="str_iso2"><?php esc_html_e( 'Country code (ISO2)', 'ship-to-rules' ); ?></label>
			<input type="text" name="str_iso2" id="str_iso2" maxlength="2" style="width:4em;text-transform:uppercase;" placeholder="DE" />
			<p><?php esc_html_e( 'Two-letter code used for the flag (e.g. US, AR, JP).', 'ship-to-rules' ); ?></p>
		</div>
		<div class="form-field">
			<label for="str_active">
				<input type="checkbox" name="str_active" id="str_active" value="1" checked />
				<?php esc_html_e( 'Active (visible in selector)', 'ship-to-rules' ); ?>
			</label>
		</div>
		<?php
		wp_nonce_field( 'str_save_term_meta', 'str_term_meta_nonce' );
	}

	/**
	 * Edit form fields.
	 *
	 * @param WP_Term $term Term.
	 */
	public static function edit_form_fields( $term ) {
		$iso    = get_term_meta( $term->term_id, 'str_iso2', true );
		$active = get_term_meta( $term->term_id, 'str_active', true );
		if ( '' === $active ) {
			$active = '1';
		}
		$flag = STR_Countries::flag_emoji( $iso );
		?>
		<tr class="form-field">
			<th scope="row"><label for="str_iso2"><?php esc_html_e( 'Country code (ISO2)', 'ship-to-rules' ); ?></label></th>
			<td>
				<input type="text" name="str_iso2" id="str_iso2" value="<?php echo esc_attr( $iso ); ?>" maxlength="2" style="width:4em;text-transform:uppercase;" />
				<?php if ( $flag ) : ?>
					<span class="str-flag-preview" aria-hidden="true" style="font-size:1.5rem;margin-left:.5rem;"><?php echo esc_html( $flag ); ?></span>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Two-letter code used for the flag (e.g. US, AR, JP).', 'ship-to-rules' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Status', 'ship-to-rules' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="str_active" value="1" <?php checked( '1', (string) $active ); ?> />
					<?php esc_html_e( 'Active (visible in selector)', 'ship-to-rules' ); ?>
				</label>
			</td>
		</tr>
		<?php
		wp_nonce_field( 'str_save_term_meta', 'str_term_meta_nonce' );
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

		if ( ! isset( $_POST['str_term_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['str_term_meta_nonce'] ) ), 'str_save_term_meta' ) ) {
			return;
		}

		$iso = isset( $_POST['str_iso2'] ) ? STR_Countries::sanitize_iso2( wp_unslash( $_POST['str_iso2'] ) ) : '';

		if ( 2 !== strlen( $iso ) ) {
			self::set_term_notice(
				__( 'Country code (ISO2) is required for shipping enforcement. Example: US, DE, AR.', 'ship-to-rules' ),
				'error'
			);
			return;
		}

		$existing = self::find_term_by_iso( $iso, (int) $term_id );
		if ( $existing ) {
			self::set_term_notice(
				sprintf(
					/* translators: %s: ISO country code */
					__( 'ISO code %s is already used by another destination.', 'ship-to-rules' ),
					strtoupper( $iso )
				),
				'error'
			);
			return;
		}

		update_term_meta( $term_id, 'str_iso2', $iso );
		update_term_meta( $term_id, 'str_active', isset( $_POST['str_active'] ) ? '1' : '0' );
		STR_Countries::flush_cache();
	}

	/**
	 * Find another term with the same ISO2.
	 *
	 * @param string $iso2     ISO code.
	 * @param int    $exclude  Term ID to exclude.
	 * @return int|null Term ID or null.
	 */
	private static function find_term_by_iso( $iso2, $exclude = 0 ) {
		$terms = get_terms(
			array(
				'taxonomy'   => STR_TAXONOMY,
				'hide_empty' => false,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => 'str_iso2',
						'value' => strtoupper( $iso2 ),
					),
				),
			)
		);

		if ( is_wp_error( $terms ) ) {
			return null;
		}

		foreach ( $terms as $term ) {
			if ( (int) $term->term_id !== (int) $exclude ) {
				return (int) $term->term_id;
			}
		}

		return null;
	}

	/**
	 * Store admin notice for term save errors.
	 *
	 * @param string $message Message.
	 * @param string $type    notice type.
	 */
	private static function set_term_notice( $message, $type = 'error' ) {
		if ( ! function_exists( 'get_current_user_id' ) ) {
			return;
		}
		set_transient(
			'str_term_notice_' . get_current_user_id(),
			array(
				'message' => $message,
				'type'    => $type,
			),
			60
		);
	}

	/**
	 * Show term save notices.
	 */
	public static function term_save_notices() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$data = get_transient( 'str_term_notice_' . get_current_user_id() );
		if ( ! is_array( $data ) || empty( $data['message'] ) ) {
			return;
		}

		delete_transient( 'str_term_notice_' . get_current_user_id() );

		$class = 'notice-' . ( ! empty( $data['type'] ) ? sanitize_key( $data['type'] ) : 'error' );
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $data['message'] ) . '</p></div>';
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
				$new['str_flag']   = __( 'Flag', 'ship-to-rules' );
				$new['str_iso2']   = __( 'ISO', 'ship-to-rules' );
				$new['str_active'] = __( 'Active', 'ship-to-rules' );
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
		if ( 'str_iso2' === $column ) {
			return esc_html( strtoupper( (string) get_term_meta( $term_id, 'str_iso2', true ) ) );
		}
		if ( 'str_flag' === $column ) {
			$iso = get_term_meta( $term_id, 'str_iso2', true );
			$flag = STR_Countries::flag_emoji( $iso );
			return $flag ? '<span style="font-size:1.25rem">' . esc_html( $flag ) . '</span>' : '&mdash;';
		}
		if ( 'str_active' === $column ) {
			$active = get_term_meta( $term_id, 'str_active', true );
			if ( '' === $active ) {
				$active = '1';
			}
			return '1' === (string) $active
				? '<span style="color:#008a20;">' . esc_html__( 'Yes', 'ship-to-rules' ) . '</span>'
				: '<span style="color:#8c8f94;">' . esc_html__( 'No', 'ship-to-rules' ) . '</span>';
		}
		return $content;
	}
}
