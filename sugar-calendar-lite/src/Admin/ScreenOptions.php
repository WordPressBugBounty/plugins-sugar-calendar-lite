<?php

namespace Sugar_Calendar\Admin;

/**
 * Per-screen options data layer.
 *
 * @since 3.8.0
 */
class ScreenOptions {

	/**
	 * Screen options ID (used as the user_option storage key).
	 *
	 * @since 3.8.0
	 *
	 * @var string
	 */
	private $screen_options_id;

	/**
	 * Registered option declarations grouped by section.
	 *
	 * @since 3.8.0
	 *
	 * @var array
	 */
	private $options = [];

	/**
	 * @since      3.8.0
	 * @deprecated 3.12.0
	 */
	public function hooks() {}

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
	 * Add a screen option.
	 *
	 * @since 3.8.0
	 *
	 * @param string $group Group where to add the option.
	 * @param array  $args  Args.
	 */
	public function add_option( $group, $args ) {

		$group_headings = [
			'pagination' => esc_html__( 'Items per page', 'sugar-calendar-lite' ),
			'view'       => esc_html__( 'View', 'sugar-calendar-lite' ),
		];

		if ( ! isset( $this->options[ $group ] ) ) {
			$this->options[ $group ] = [
				'heading' => $group_headings[ $group ] ?? '',
			];
		}

		$this->options[ $group ]['options'][] = $args;
	}

	/**
	 * Get the screen options ID.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_screen_options_id() {

		return $this->screen_options_id;
	}

	/**
	 * Get the registered option declarations.
	 *
	 * @since 3.12.0
	 *
	 * @return array
	 */
	public function get_options() {

		return $this->options;
	}

	/**
	 * Persist a submitted screen-options payload for the current user.
	 *
	 * @since 3.12.0
	 *
	 * @param array $screen_options Raw `$_POST[…][screen_options]` payload.
	 */
	public static function persist_from_post( $screen_options ) {

		$instance = sugar_calendar()->get_admin_screen_options();

		if ( ! $instance ) {
			return;
		}

		$screen_options_id      = $instance->get_screen_options_id();
		$declared_option_groups = $instance->get_options();

		if ( empty( $screen_options_id ) || empty( $declared_option_groups ) ) {
			return;
		}

		$screen_options = is_array( $screen_options ) ? $screen_options : [];

		$existing  = get_user_option( $screen_options_id );
		$existing  = is_array( $existing ) ? $existing : [];
		$sanitized = $existing;

		foreach ( $declared_option_groups as $group_key => $group ) {
			foreach ( $group['options'] as $option ) {
				$saved_key = $group_key . '_' . $option['option'];

				// Unchecked checkboxes are absent from POST; record as 0.
				if ( ! array_key_exists( $saved_key, $screen_options ) ) {
					if ( $option['input_type'] === 'checkbox' ) {
						$sanitized[ $saved_key ] = 0;
					}
					continue;
				}

				$value = $screen_options[ $saved_key ];

				if ( ( $option['value_type'] ?? 'string' ) === 'int' ) {
					$value = absint( $value );

					if ( isset( $option['min'] ) ) {
						$value = max( (int) $option['min'], $value );
					}

					if ( isset( $option['max'] ) ) {
						$value = min( (int) $option['max'], $value );
					}
				} else {
					$value = sanitize_text_field( $value );
				}

				$sanitized[ $saved_key ] = $value;
			}
		}

		update_user_option( get_current_user_id(), $screen_options_id, $sanitized );
	}
}
