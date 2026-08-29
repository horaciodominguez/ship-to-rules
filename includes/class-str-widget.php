<?php
/**
 * Widget: compact destination picker for sidebars / header widget areas.
 *
 * @package ShipToRules
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STR_Ship_To_Picker_Widget
 */
class STR_Ship_To_Picker_Widget extends WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'str_ship_to_picker',
			__( 'Ship-To Rules — Picker', 'ship-to-rules' ),
			array(
				'description' => __( 'Let shoppers view or change their shipping destination.', 'ship-to-rules' ),
			)
		);
	}

	/**
	 * Front output.
	 *
	 * @param array $args     Widget args.
	 * @param array $instance Instance.
	 */
	public function widget( $args, $instance ) {
		if ( ! class_exists( 'STR_Frontend' ) ) {
			return;
		}

		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Shipping destination', 'ship-to-rules' );

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo STR_Frontend::render_picker( array( 'show_clear' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Admin form.
	 *
	 * @param array $instance Instance.
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Shipping destination', 'ship-to-rules' );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'ship-to-rules' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p class="description"><?php esc_html_e( 'Add this widget to your header or sidebar so shoppers can change destination anytime.', 'ship-to-rules' ); ?></p>
		<?php
	}

	/**
	 * Save widget.
	 *
	 * @param array $new_instance New.
	 * @param array $old_instance Old.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = sanitize_text_field( $new_instance['title'] ?? '' );
		return $instance;
	}
}
