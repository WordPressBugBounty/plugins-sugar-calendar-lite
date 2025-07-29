<?php

namespace Sugar_Calendar\Admin;

/**
 * Handles the admin Screen Option.
 *
 * @since 3.8.0
 */
class ScreenOptions {

	/**
	 * Screen options ID.
	 *
	 * @since 3.8.0
	 *
	 * @var string
	 */
	private $screen_options_id;

	/**
	 * Options.
	 *
	 * @since 3.8.0
	 *
	 * @var array
	 */
	private $options = [];

	/**
	 * Hooks.
	 *
	 * @since 3.8.0
	 */
	public function hooks() {

		if ( empty( $this->screen_options_id ) ) {
			return;
		}

		add_filter( 'screen_options_show_screen', '__return_true', 999 );
		add_filter( 'screen_options_show_submit', '__return_true', 999 );
		add_filter( 'screen_settings', [ $this, 'screen_settings' ] );

		add_action( 'admin_head', [ $this, 'add_screen_options' ] );
		add_filter( "set_screen_option_{$this->screen_options_id}", [ $this, 'save_screen_options' ], 10, 3 );
	}

	/**
	 * Set the screen options ID.
	 *
	 * @since 3.8.0
	 *
	 * @param string $screen_options_id Screen options ID.
	 */
	public function set_screen_options_id( $screen_options_id ) {

		$this->screen_options_id = $screen_options_id . '_screen_options';
	}

	/**
	 * Display the Screen Options.
	 *
	 * @since 3.8.0
	 *
	 * @param string $screen_settings Screen settings.
	 *
	 * @return string
	 */
	public function screen_settings( $screen_settings ) {

		ob_start();

		// Get user saved.
		$user_saved = get_user_option( $this->screen_options_id );

		foreach ( $this->options as $group => $fields ) {
			?>
			<fieldset>
				<legend><?php echo esc_html( $fields['heading'] ); ?></legend>
				<?php
				foreach ( $fields['options'] as $option ) {

					$key = $group . '_' . $option['option'];

					if ( isset( $user_saved[ $key ] ) ) {
						$value = $user_saved[ $key ];
					} else {
						$value = $option['default'];
					}

					switch ( $option['input_type'] ) {
						case 'number':
							$min       = $option['min'] ?? 1;
							$max       = $option['max'] ?? 999;
							$step      = $option['step'] ?? 1;
							$maxlength = $option['maxlength'] ?? 3;

							printf(
								'<label for="%1$s">%2$s</label>',
								esc_attr( $option['option'] ),
								esc_html( $option['label'] )
							);
							printf(
								'<input type="number" id="%1$s" name="%1$s" value="%2$s" min="%3$s" max="%4$s" step="%5$s" maxlength="%6$s">',
								esc_attr( $option['option'] ),
								esc_attr( $value ),
								esc_attr( $min ),
								esc_attr( $max ),
								esc_attr( $step ),
								esc_attr( $maxlength )
							);
							break;

						case 'checkbox':
							printf(
								'<label><input type="checkbox" id="%1$s" name="%1$s" value="%2$s" %3$s>%4$s</label>',
								esc_attr( $option['option'] ),
								esc_attr( $option['default'] ),
								checked( $option['default'], $value, false ),
								esc_html( $option['label'] )
							);
							break;
					}
				}
				?>
				<input name="wp_screen_options[option]" type="hidden" value="<?php echo esc_attr( $this->screen_options_id ); ?>">
				<input name="wp_screen_options[value]" type="hidden" value="">
			</fieldset>
			<?php
		}

		return ob_get_clean();
	}

	/**
	 * Configure screen options.
	 *
	 * @since 3.8.0
	 */
	public function add_screen_options() {

		foreach ( $this->options as $group => $options ) {
			foreach ( $options['options'] as $option ) {
				add_screen_option(
					$group . '_' . $option['option'],
					[
						'label'  => $option['label'],
						'option' => $option['option'],
						'value'  => $option['default'],
					]
				);
			}
		}
	}

	/**
	 * Save the screen options.
	 *
	 * @since 3.8.0
	 *
	 * @param mixed  $screen_option The value to save instead of the option value.
	 *                              Default false (to skip saving the current option).
	 * @param string $option        The option name.
	 * @param int    $value         The option value.
	 *
	 * @return mixed
	 */
	public function save_screen_options( $screen_option, $option, $value ) {

		if ( $this->screen_options_id !== $option ) {
			return $screen_option;
		}

		$final_value = [];

		foreach ( $this->options as $group => $options_group ) {
			foreach ( $options_group['options'] as $option ) {

				$key   = sanitize_key( $group . '_' . $option['option'] );
				$value = $_POST[ $option['option'] ] ?? false;

				switch ( $option['value_type'] ) {
					case 'int':
						$value = absint( $value );
						break;

					default:
						$value = sanitize_text_field( $value );
						break;
				}

				$final_value[ $key ] = $value;
			}
		}

		return $final_value;
	}

	/**
	 * Add a screen option.
	 *
	 * @since 3.8.0
	 *
	 * @param string $group Group where to add the option.
	 * @param array  $args  Args.
	 */
	public function add_option( $group, $args ) {

		$group_headings = [
			'pagination' => esc_html__( 'Pagination', 'sugar-calendar-lite' ),
			'view'       => esc_html__( 'View', 'sugar-calendar-lite' ),
		];

		if ( ! isset( $this->options[ $group ] ) ) {
			$this->options[ $group ] = [
				'heading' => $group_headings[ $group ] ?? '',
			];
		}

		$this->options[ $group ]['options'][] = $args;
	}
}
