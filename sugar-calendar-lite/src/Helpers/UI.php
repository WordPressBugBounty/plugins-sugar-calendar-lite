<?php

namespace Sugar_Calendar\Helpers;

/**
 * Admin interface helpers.
 *
 * @since 3.0.0
 */
class UI {

	/**
	 * Sanitize one or more HTML classes.
	 *
	 * @since 3.0.0
	 *
	 * @param string|array $class HTML classes.
	 *
	 * @return string
	 */
	public static function sanitize_class( $class ) {

		$class = is_array( $class ) ? $class : [ $class ];
		$class = array_map( 'sanitize_html_class', $class );
		$class = array_filter( $class );
		$class = array_unique( $class );
		$class = implode( ' ', $class );

		return $class;
	}

	/**
	 * Render the dashboard header.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function header() {

		/**
		 * Filter the help URL.
		 *
		 * @since 3.8.0
		 *
		 * @param string $help_url The help URL.
		 */
		$help_url = apply_filters(
			'sugar_calendar_helpers_ui_help_url',
			Helpers::get_utm_url(
				'https://sugarcalendar.com/docs/',
				[
					'content' => 'Help',
				]
			)
		);
		?>
		<div id="sugar-calendar-header" class="sugar-calendar-header">
			<img class="sugar-calendar-header-logo"
				src="<?php echo esc_url( SC_PLUGIN_URL . 'assets/images/logo.svg' ); ?>"
				alt="<?php esc_attr_e( 'Sugar Calendar Logo', 'sugar-calendar-lite' ); ?>"/>
				<a href="<?php echo esc_url( $help_url ); ?>" target="_blank" id="sugar-calendar-header-help">
					<?php esc_html_e( 'Help', 'sugar-calendar-lite' ); ?>
				</a>
		</div>
		<?php
	}

	/**
	 * Render a tab navigation menu.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $tabs     List of tabs.
	 * @param string $selected Selected tab id.
	 *
	 * @return void
	 */
	public static function tabs( $tabs, $selected ) {

		if ( empty( $tabs ) ) {
			return;
		}

		if ( empty( $selected ) ) {
			$selected = array_key_first( $tabs );
		}

		/**
		 * Filter the navigation items before they are used to generate HTML.
		 *
		 * @since 2.0.19
		 *
		 * @param array  $tabs     List of tabs.
		 * @param string $selected Selected tab id.
		 */
		$tabs = (array) apply_filters( 'sugar_calendar_admin_nav_items', $tabs, $selected ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		/**
		 * Fires before the admin navigation.
		 *
		 * @since 2.0.19
		 *
		 * @param array  $tabs     List of tabs.
		 * @param string $selected Selected tab id.
		 */
		do_action( 'sugar_calendar_admin_nav_before_wrapper', $tabs, $selected ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		?>

		<ul class="sugar-calendar-admin-tabs">
			<?php

			/**
			 * Fires before the admin navigation inside the wrapper.
			 *
			 * @since 2.0.19
			 *
			 * @param array  $tabs     List of tabs.
			 * @param string $selected Selected tab id.
			 */
			do_action( 'sugar_calendar_admin_nav_before_items', $tabs, $selected ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			?>

			<?php foreach ( $tabs as $nav_id => $nav ) : ?>

				<li>
					<a href="<?php echo esc_url( $nav['url'] ); ?>"
					   class="<?php echo esc_attr( ( $selected === $nav_id ) ? 'active' : '' ); ?>"><?php echo esc_html( $nav['name'] ); ?></a>
				</li>

			<?php endforeach; ?>

		</ul>

		<?php
		/**
		 * Fires after the admin navigation.
		 *
		 * @since 2.0.19
		 *
		 * @param array  $tabs     List of tabs.
		 * @param string $selected Selected tab id.
		 */
		do_action( 'sugar_calendar_admin_nav_after_wrapper', $tabs, $selected ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Output a select control wrapper.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $args    Wrapper arguments. `required_marker` appends the visible
	 *                        asterisk to the label; it is deliberately separate from
	 *                        `required`, which is the input's HTML attribute, so the
	 *                        rows already passing that one keep their current label.
	 * @param string $content Wrapper contents.
	 */
	public static function field_wrapper( $args, $content = '' ) {

		$args = wp_parse_args(
			$args,
			[
				'type'            => '',
				'id'              => '',
				'class'           => [],
				'required_marker' => false,
			]
		);

		// HTML class.
		$classes = [
			'sugar-calendar-setting-row',
			'sugar-calendar-clear',
		];

		if ( ! empty( $args['class'] ) ) {
			$class   = is_array( $args['class'] ) ? $args['class'] : [ $args['class'] ];
			$classes = [ ...$classes, ...$class ];
		}

		$type = sanitize_key( $args['type'] );

		if ( ! empty( $type ) ) {
			$classes[] = "sugar-calendar-setting-row-{$type}";
		}

		$classes = self::sanitize_class( $classes );

		// HTML id.
		$row_id = '';
		$id     = sanitize_key( $args['id'] );

		if ( ! empty( $id ) ) {
			$row_id = "sugar-calendar-setting-row-{$id}";
			$id     = "sugar-calendar-setting-{$id}";
		}
		?>

		<div id="<?php echo esc_attr( $row_id ); ?>" class="<?php echo esc_attr( $classes ); ?>">

			<?php if ( ! empty( $args['label'] ) ) : ?>

				<span class="sugar-calendar-setting-label">
					<?php
					// The marker sits inside the label, one space after the text, the
					// same shape form_table_row_open() emits — indentation around it
					// would render as extra gap, and a block-level label (wp-admin
					// makes several) would drop it onto its own line. span.required is
					// core's own; aria-hidden because assistive tech reads the input's
					// state, not the glyph.
					printf(
						'<label for="%1$s">%2$s%3$s</label>',
						esc_attr( $id ),
						esc_html( $args['label'] ),
						empty( $args['required_marker'] ) ? '' : ' <span class="required" aria-hidden="true">*</span>'
					);
					?>
				</span>

			<?php endif; ?>

			<span class="sugar-calendar-setting-field"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>

		</div>

		<?php
	}

	/**
	 * Output a select control wrapper.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Description arguments.
	 */
	private static function field_description( $args ) {

		?>

		<?php if ( ! empty( $args['description'] ) ) : ?>

			<p class="desc"><?php echo wp_kses_post( $args['description'] ); ?></p>

		<?php endif; ?>

		<?php
	}

	/**
	 * Output a select setting control.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Control arguments.
	 */
	public static function select_input( $args ) {

		$args = wp_parse_args(
			$args,
			[
				'type'        => 'select',
				'id'          => '',
				'class'       => '',
				'name'        => '',
				'options'     => [],
				'value'       => '',
				'description' => '',
				'choicejs'    => false,
				'multiple'    => false,
			]
		);

		$id = sanitize_key( $args['id'] );

		if ( ! empty( $id ) ) {
			$id = "sugar-calendar-setting-{$id}";
		}

		$choicejs = (bool) $args['choicejs'];
		$multiple = (bool) $args['multiple'];

		// Prioritize args class over choicejs.
		if ( ! empty( $args['class'] ) ) {
			$class = $args['class'];
		} elseif ( $choicejs ) {
			$class = 'choicesjs-select';
		} else {
			$class = '';
		}

		$name = sanitize_key( $args['name'] );

		if ( ! empty( $name ) ) {
			$name = "sugar-calendar[$name]";

			if ( $multiple ) {
				$name .= '[]';
			}
		}

		$options = $args['options'];
		$value   = is_array( $args['value'] ) ? $args['value'] : [ $args['value'] ];

		ob_start();
		?>

		<?php if ( $choicejs ) : ?>

			<span class="choicesjs-select-wrap">

		<?php endif; ?>

		<select name="<?php echo esc_attr( $name ); ?>"
				id="<?php echo esc_attr( $id ); ?>"
				class="<?php echo sanitize_html_class( $class ); ?>"
				<?php echo $multiple ? 'multiple' : ''; ?>>

			<?php foreach ( $options as $option_value => $option_label ) : ?>

				<?php
				$option_enabled = true;

				if ( is_array( $option_label ) ) {
					[ $option_label, $option_enabled ] = $option_label;
				}
				?>

				<option value="<?php echo esc_attr( $option_value ); ?>"
					<?php disabled( ! (bool) $option_enabled ); ?>
					<?php echo in_array( $option_value, $value ) ? 'selected' : ''; ?>><?php echo esc_html( $option_label ); ?></option>

			<?php endforeach; ?>

		</select>

		<?php if ( $choicejs ) : ?>

			</span>

		<?php endif; ?>

		<?php
		self::field_description( $args );
		self::field_wrapper( $args, ob_get_clean() );
	}

	/**
	 * Output a number setting control.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Control arguments.
	 */
	public static function number_input( $args ) {

		$args = wp_parse_args(
			$args,
			[
				'type'        => 'number',
				'id'          => '',
				'name'        => '',
				'value'       => '',
				'description' => '',
				'input_mode'  => 'numeric',
				'step'        => 1,
				'min'         => 0,
				'max'         => '',
			]
		);

		$id = sanitize_key( $args['id'] );

		if ( ! empty( $id ) ) {
			$id = "sugar-calendar-setting-{$id}";
		}

		$name = sanitize_key( $args['name'] );

		if ( ! empty( $name ) ) {
			$name = "sugar-calendar[$name]";
		}

		$value      = $args['value'];
		$input_mode = $args['input_mode'];
		$step       = $args['step'];
		$min        = $args['min'];
		$max        = $args['max'];

		ob_start();
		?>

		<input type="number"
			   name="<?php echo esc_attr( $name ); ?>"
			   value="<?php echo esc_attr( $value ); ?>"
			   id="<?php echo esc_attr( $id ); ?>"
			   inputMode="<?php echo esc_attr( $input_mode ); ?>"
			   step="<?php echo esc_attr( $step ); ?>"
			   min="<?php echo esc_attr( $min ); ?>"
			   max="<?php echo esc_attr( $max ); ?>"/>

		<?php
		self::field_description( $args );
		self::field_wrapper( $args, ob_get_clean() );
	}

	/**
	 * Output a date/time format setting control.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Control arguments.
	 */
	public static function date_time_format_control( $args ) {

		$args = wp_parse_args(
			$args,
			[
				'type'        => 'date_time_format',
				'id'          => '',
				'name'        => '',
				'formats'     => [],
				'value'       => '',
				'description' => '',
			]
		);

		$id = sanitize_key( $args['id'] );

		if ( ! empty( $id ) ) {
			$id = "sugar-calendar-setting-{$id}";
		}

		$name = sanitize_key( $args['name'] );

		if ( ! empty( $name ) ) {
			$name = "sugar-calendar[$name]";
		}

		$value   = $args['value'];
		$formats = $args['formats'];
		$i       = 0;

		ob_start();
		?>

		<?php foreach ( $formats as $format ) : ?>

			<?php
			$timezone = sugar_calendar_get_timezone();
			$date     = sugar_calendar_format_date_i18n( $format, null, $timezone );
			?>

			<span class="sugar-calendar-settings-field-radio-wrapper">
				<input type="radio"
					   name="<?php echo esc_attr( $name ); ?>"
					   id="<?php echo esc_attr( $id ); ?>_<?php echo esc_attr( $i ); ?>"
					   value="<?php echo esc_attr( $format ); ?>"
					<?php checked( $format, $value ); ?>>
				<label for="<?php echo esc_attr( $id ); ?>_<?php echo esc_attr( $i ); ?>">
					<span data-format-i18n><?php echo esc_html( $date ); ?></span>
					<code><?php echo esc_html( $format ); ?></code>
				</label>
			</span>

			<?php $i++; ?>

		<?php endforeach; ?>

		<?php
		$custom_checked = ! in_array( $value, $formats, true );
		$looks_like     = sugar_calendar_format_date_i18n( $value, null, $timezone );
		?>

		<span class="sugar-calendar-settings-field-radio-wrapper">
			<input type="radio"
				   name="<?php echo esc_attr( $name ); ?>"
				   id="<?php echo esc_attr( $id ); ?>_custom"
				   value="custom" <?php checked( $custom_checked ); ?>
				   data-custom-option/>
			<label for="<?php echo esc_attr( $id ); ?>_custom"><?php esc_html_e( 'Custom', 'sugar-calendar-lite' ); ?></label>
			<input type="text"
				   name="<?php echo esc_attr( $name ); ?>"
				   id="<?php echo esc_attr( $id ); ?>_custom_format"
				   class="sugar-calendar-custom-date-time-format"
				   value="<?php echo esc_attr( $value ); ?>"
				   data-custom-field/>
		</span>

		<p class="desc">
			<strong><?php esc_html_e( 'Looks Like:', 'sugar-calendar-lite' ); ?></strong>
			<span data-format-example><?php echo esc_html( $looks_like ); ?></span>
			<span class="spinner" data-spinner></span>
		</p>

		<?php
		self::field_wrapper( $args, ob_get_clean() );
	}

	/**
	 * Output a radio-group control.
	 *
	 * Recognized $args keys: `id` (base id; each option gets `{id}_{index}`),
	 * `name` (POST key), `value` (selected option value), `options` (map of option
	 * value => label), `layout` ('horizontal' default, or 'vertical'), `label`
	 * (row label, wrapper mode only) and `description` (help text).
	 *
	 * @since 3.13.0
	 *
	 * @param array $args Control arguments.
	 * @param bool  $bare Whether to output the control without the row wrapper.
	 *
	 * @return void
	 */
	public static function radio_input( $args, $bare = false ) {

		$args = wp_parse_args(
			$args,
			[
				'type'        => 'radio',
				'id'          => '',
				'name'        => '',
				'value'       => '',
				'options'     => [],
				'layout'      => 'horizontal',
				'description' => '',
			]
		);

		$id = sanitize_key( $args['id'] );

		if ( ! empty( $id ) && ! $bare ) {
			$id = "sugar-calendar-setting-{$id}";
		}

		$name = sanitize_key( $args['name'] );

		if ( ! empty( $name ) && ! $bare ) {
			$name = "sugar-calendar[$name]";
		}

		$layout  = $args['layout'] === 'vertical' ? 'vertical' : 'horizontal';
		$value   = $args['value'];
		$options = (array) $args['options'];
		$i       = 0;

		ob_start();
		?>

		<span class="sugar-calendar-radio-group sugar-calendar-radio-group--<?php echo esc_attr( $layout ); ?>">

			<?php foreach ( $options as $option_value => $option_label ) : ?>

				<?php $option_id = $id !== '' ? $id . '_' . $i : ''; ?>

				<span class="sugar-calendar-settings-field-radio-wrapper">
					<input type="radio"
						   name="<?php echo esc_attr( $name ); ?>"
						   id="<?php echo esc_attr( $option_id ); ?>"
						   value="<?php echo esc_attr( $option_value ); ?>"
						<?php checked( (string) $option_value, (string) $value ); ?>>
					<label for="<?php echo esc_attr( $option_id ); ?>"><?php echo esc_html( $option_label ); ?></label>
				</span>

				<?php ++$i; ?>

			<?php endforeach; ?>

		</span>

		<?php

		self::field_description( $args );

		if ( $bare ) {
			echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			return;
		}

		self::field_wrapper( $args, ob_get_clean() );
	}

	/**
	 * Output a date-range control: a flatpickr-powered display field backed by
	 * two hidden inputs for the start / end bounds.
	 *
	 * The JS initializer binds by the `sugar-calendar-date-range` class and reads
	 * its hidden-field targets from the `data-start-field` / `data-end-field`
	 * attributes, so the control is reusable wherever it is rendered.
	 *
	 * Recognized $args keys: `id` (display field id; bounds derive `{id}-start` /
	 * `{id}-end`), `name_start` / `name_end` (POST names for the bounds),
	 * `value_start` / `value_end` (preselected Y-m-d values), `placeholder`,
	 * `aria_label`, `label` (row label, wrapper mode only) and `description`.
	 *
	 * @since 3.13.0
	 *
	 * @param array $args Control arguments.
	 * @param bool  $bare Whether to output the control without the row wrapper.
	 *
	 * @return void
	 */
	public static function date_range_control( $args, $bare = false ) {

		$args = wp_parse_args(
			$args,
			[
				'type'        => 'date_range',
				'id'          => '',
				'name_start'  => '',
				'name_end'    => '',
				'value_start' => '',
				'value_end'   => '',
				'placeholder' => '',
				'aria_label'  => '',
				'description' => '',
			]
		);

		$id = sanitize_key( $args['id'] );

		if ( ! empty( $id ) && ! $bare ) {
			$id = "sugar-calendar-setting-{$id}";
		}

		$start_id = $id !== '' ? $id . '-start' : '';
		$end_id   = $id !== '' ? $id . '-end' : '';

		ob_start();
		?>

		<input type="text"
			   id="<?php echo esc_attr( $id ); ?>"
			   class="sugar-calendar-date-range"
			   placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
			   aria-label="<?php echo esc_attr( $args['aria_label'] ); ?>"
			   data-start-field="<?php echo esc_attr( $start_id ); ?>"
			   data-end-field="<?php echo esc_attr( $end_id ); ?>"
			   readonly />
		<input type="hidden" name="<?php echo esc_attr( $args['name_start'] ); ?>" id="<?php echo esc_attr( $start_id ); ?>" value="<?php echo esc_attr( $args['value_start'] ); ?>" />
		<input type="hidden" name="<?php echo esc_attr( $args['name_end'] ); ?>" id="<?php echo esc_attr( $end_id ); ?>" value="<?php echo esc_attr( $args['value_end'] ); ?>" />

		<?php

		self::field_description( $args );

		if ( $bare ) {
			echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			return;
		}

		self::field_wrapper( $args, ob_get_clean() );
	}

	/**
	 * Output a toggle setting control.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Control arguments.
	 * @param bool  $bare Whether to output the control wrapper.
	 *
	 * @return void
	 */
	public static function toggle_control( $args, $bare = false ) {

		$args = wp_parse_args(
			$args,
			[
				'type'          => 'toggle',
				'id'            => '',
				'name'          => '',
				'value'         => '1',
				'disabled'      => false,
				'description'   => '',
				'toggle_labels' => [
					esc_html__( 'On', 'sugar-calendar-lite' ),
					esc_html__( 'Off', 'sugar-calendar-lite' ),
				],
			]
		);

		$id = sanitize_key( $args['id'] );

		if ( ! empty( $id ) && ! $bare ) {
			$id = "sugar-calendar-setting-{$id}";
		}

		$name = sanitize_key( $args['name'] );

		if ( ! empty( $name ) && ! $bare ) {
			$name = "sugar-calendar[$name]";
		}

		$value    = (bool) $args['value'];
		$disabled = (bool) $args['disabled'];

		[ $toggle_label_on, $toggle_label_off ] = $args['toggle_labels'];

		ob_start();
		?>

		<span class="sugar-calendar-toggle-control">
			<input type="checkbox"
				   id="<?php echo esc_attr( $id ); ?>"
				   name="<?php echo esc_attr( $name ); ?>"
				   value="1"
				<?php disabled( $disabled ); ?>
				<?php checked( $value ); ?>>
			<label class="sugar-calendar-toggle-control-icon" for="<?php echo esc_attr( $id ); ?>"></label>
			<label class="sugar-calendar-toggle-control-status sugar-calendar-toggle-control-status-on" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $toggle_label_on ); ?></label>
			<label class="sugar-calendar-toggle-control-status sugar-calendar-toggle-control-status-off" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $toggle_label_off ); ?></label>
		</span>

		<?php
		self::field_description( $args );

		if ( $bare ) {
			echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			return;
		}

		self::field_wrapper( $args, ob_get_clean() );
	}

	/**
	 * Output a calendar dropdown setting control.
	 *
	 * @since 3.0.0
	 * @since 3.4.0 Added the `preserved` argument.
	 *
	 * @param array $args Control arguments.
	 * @param bool  $bare Whether to output the control wrapper.
	 *
	 * @return void
	 */
	public static function calendar_dropdown_control( $args, $bare = false ) {

		$args = wp_parse_args(
			$args,
			[
				'type'      => 'select',
				'preserved' => false,
			]
		);

		$id = sanitize_key( $args['id'] );

		if ( ! empty( $id ) && ! $bare ) {
			$id = "sugar-calendar-setting-{$id}";
		}

		$name = sanitize_key( $args['name'] );

		if ( ! empty( $name ) && ! $bare ) {
			$args['name'] = "sugar-calendar[$name]";
		}

		if ( $args['preserved'] && self::is_preserved( $name ) ) {
			$args['selected'] = self::get_preserved_value( $name );
		}

		ob_start();
		wp_dropdown_categories( $args );
		self::field_description( $args );

		if ( $bare ) {
			echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			return;
		}

		self::field_wrapper( $args, ob_get_clean() );
	}

	/**
	 * Output a timezone dropdown setting control.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Control arguments.
	 * @param bool  $bare Whether to output the control wrapper.
	 *
	 * @return void
	 */
	public static function timezone_dropdown_control( $args, $bare = false ) {

		$args = wp_parse_args(
			$args,
			[
				'type' => 'select',
				'id'   => '',
				'name' => '',
			]
		);

		$args['id']   = 'sugar-calendar_' . sanitize_key( $args['id'] );
		$args['name'] = sanitize_key( $args['name'] );

		if ( ! $bare ) {
			$args['name'] = 'sugar-calendar[' . $args['name'] . ']';
		}

		ob_start();
		?>

		<span class="choicesjs-select-wrap">

			<?php sugar_calendar_timezone_dropdown( $args ); ?>

		</span>

		<?php
		self::field_description( $args );

		if ( $bare ) {
			echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			return;
		}

		self::field_wrapper( $args, ob_get_clean() );
	}

	/**
	 * Output a heading setting control.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Control arguments.
	 *
	 * @return void
	 */
	public static function heading( $args ) {

		$args = wp_parse_args(
			$args,
			[
				'type'  => 'heading',
				'id'    => '',
				'title' => '',
			]
		);

		$id = sanitize_key( $args['id'] );

		if ( ! empty( $id ) ) {
			$id = "sugar-calendar-setting-{$id}";
		}

		ob_start();
		?>

		<h4 id="<?php echo esc_attr( $id ); ?>"><?php echo wp_kses_post( $args['title'] ); ?></h4>

		<?php
		self::field_description( $args );
		self::field_wrapper( $args, ob_get_clean() );
	}

	/**
	 * Output a text setting control.
	 *
	 * @since 3.0.0
	 * @since 3.4.0 Added the `preserved` argument.
	 *
	 * @param array $args Control arguments.
	 * @param bool  $bare Whether to output the control wrapper.
	 *
	 * @return void
	 */
	public static function text_input( $args, $bare = false ) {

		$args = wp_parse_args(
			$args,
			[
				'type'        => 'text',
				'id'          => '',
				'name'        => '',
				'value'       => '',
				'placeholder' => '',
				'description' => '',
				'required'    => false,
				'preserved'   => false,
			]
		);

		$type = sanitize_key( $args['type'] );
		$id   = sanitize_key( $args['id'] );

		if ( ! empty( $id ) && ! $bare ) {
			$id = "sugar-calendar-setting-{$id}";
		}

		$name = sanitize_key( $args['name'] );

		if ( ! empty( $name ) && ! $bare ) {
			$name = "sugar-calendar[$name]";
		}

		$value       = $args['value'];
		$placeholder = $args['placeholder'];

		// If the value is preserved, get from $_REQUEST.
		if ( $args['preserved'] && self::is_preserved( $name ) ) {
			$value = urldecode( self::get_preserved_value( $name ) );
		}

		ob_start();
		?>

		<input type="<?php echo esc_attr( $type ); ?>"
			   name="<?php echo esc_attr( $name ); ?>"
			   value="<?php echo esc_attr( $value ); ?>"
			   id="<?php echo esc_attr( $id ); ?>"
			   placeholder="<?php echo esc_attr( $placeholder ); ?>"
			<?php echo( $args['required'] ? esc_attr( 'required' ) : '' ); ?>
			<?php // The asterisk is decorative; without the HTML attribute this is what carries "required" to assistive tech. ?>
			<?php echo( ! empty( $args['required_marker'] ) ? ' aria-required="true"' : '' ); ?>
		/>

		<?php

		self::field_description( $args );

		if ( $bare ) {
			echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			return;
		}

		self::field_wrapper( $args, ob_get_clean() );
	}

	/**
	 * Output a textarea setting control.
	 *
	 * @since 3.0.0
	 * @since 3.4.0 Added the `preserved` argument.
	 *
	 * @param array $args Control arguments.
	 * @param bool  $bare Whether to output the control wrapper.
	 *
	 * @return void
	 */
	public static function textarea( $args, $bare = false ) {

		$args = wp_parse_args(
			$args,
			[
				'id'          => '',
				'name'        => '',
				'value'       => '',
				'placeholder' => '',
				'rows'        => '5',
				'cols'        => '40',
				'description' => '',
				'preserved'   => false,
			]
		);

		$id = sanitize_key( $args['id'] );

		if ( ! empty( $id ) && ! $bare ) {
			$id = "sugar-calendar-setting-{$id}";
		}

		$name = sanitize_key( $args['name'] );

		if ( ! empty( $name ) && ! $bare ) {
			$name = "sugar-calendar[$name]";
		}

		$value       = $args['value'];
		$placeholder = $args['placeholder'];
		$rows        = $args['rows'];
		$cols        = $args['cols'];

		// If the value is preserved, get from $_REQUEST.
		if ( $args['preserved'] && self::is_preserved( $name ) ) {
			$value = self::get_preserved_value( $name );
		}

		ob_start();
		?>

		<textarea name="<?php echo esc_attr( $name ); ?>"
				  id="<?php echo esc_attr( $id ); ?>"
				  cols="<?php echo esc_attr( $cols ); ?>"
				  rows="<?php echo esc_attr( $rows ); ?>"
				  placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $value ); ?></textarea>

		<?php

		self::field_description( $args );

		if ( $bare ) {
			echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			return;
		}

		self::field_wrapper( $args, ob_get_clean() );
	}

	/**
	 * Output a password setting control.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Control arguments.
	 *
	 * @return void
	 */
	public static function password_input( $args ) {

		$args['type'] = 'password';

		self::text_input( $args );
	}

	/**
	 * Output a button setting control.
	 *
	 * @since 3.0.0
	 * @since 3.6.0 Added the data-importer attribute support.
	 *
	 * @param array $args Control arguments.
	 *
	 * @return void
	 */
	public static function button( $args ) {

		$args = wp_parse_args(
			$args,
			[
				'id'     => '',
				'name'   => 'sugar-calendar-submit',
				'class'  => '',
				'type'   => 'primary',
				'size'   => 'md',
				'text'   => '',
				'link'   => '',
				'submit' => true,
				'target' => '_self',
				'data'   => [],
			]
		);

		$id      = $args['id'];
		$name    = $args['name'];
		$classes = [ 'sugar-calendar-btn' ];

		if ( ! empty( $args['class'] ) ) {
			$class   = is_array( $args['class'] ) ? $args['class'] : [ $args['class'] ];
			$classes = [ ...$classes, ...$class ];
		}

		// Type.
		$types     = [ 'primary', 'secondary', 'tertiary' ];
		$type      = in_array( $args['type'], $types, true ) ? $args['type'] : 'primary';
		$classes[] = "sugar-calendar-btn-{$type}";

		// Size.
		$sizes     = [ 'sm', 'md', 'lg', 'xl' ];
		$size      = in_array( $args['size'], $sizes, true ) ? $args['size'] : 'md';
		$classes[] = "sugar-calendar-btn-{$size}";
		$class     = self::sanitize_class( $classes );

		// Submit.
		$submit = (bool) $args['submit'] ? 'submit' : 'button';

		$text   = $args['text'];
		$link   = $args['link'];
		$target = $args['target'];
		?>

		<?php if ( empty( $link ) ) : ?>

			<button
					type="<?php echo esc_attr( $submit ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					id="<?php echo esc_attr( $id ); ?>"
					class="<?php echo esc_attr( $class ); ?>"
				<?php if ( ! empty( $args['data'] ) ) : ?>
					<?php foreach ( $args['data'] as $key => $value ) : ?>
						data-<?php echo esc_attr( $key ); ?>="<?php echo esc_attr( $value ); ?>"
					<?php endforeach; ?>
				<?php endif; ?>
			><?php echo esc_html( $text ); ?></button>

		<?php else : ?>

			<a
					href="<?php echo esc_url( $link ); ?>"
					id="<?php echo esc_attr( $id ); ?>"
					target="<?php echo esc_attr( $target ); ?>"
					class="<?php echo esc_attr( $class ); ?>"
			><?php echo esc_html( $text ); ?></a>

		<?php endif; ?>
		<?php
	}

	/**
	 * Check if a value is preserved.
	 *
	 * @since 3.4.0
	 *
	 * @param string $name Key of the value in $_REQUEST['preserved'].
	 *
	 * @return bool
	 */
	public static function is_preserved( $name ) {

		return isset( $_REQUEST['preserved'][ $name ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * Get preserved values from the request.
	 *
	 * @since 3.4.0
	 *
	 * @param string $name Key of the value in $_REQUEST['preserved'].
	 *
	 * @return string
	 */
	public static function get_preserved_value( $name ) {

		return isset( $_REQUEST['preserved'][ $name ] ) ? wp_unslash( $_REQUEST['preserved'][ $name ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * Get addon badge HTML.
	 *
	 * @since 3.7.0
	 *
	 * @param array $addon Addon data.
	 *
	 * @return string
	 */
	public static function get_addon_badge( array $addon ): string {

		// List of possible badges.
		$badges = [
			'recommended' => [
				'text'  => esc_html__( 'Recommended', 'sugar-calendar-lite' ),
				'color' => 'green',
				'icon'  => 'star',
			],
			'new'         => [
				'text'  => esc_html__( 'New', 'sugar-calendar-lite' ),
				'color' => 'blue',
			],
			'featured'    => [
				'text'  => esc_html__( 'Featured', 'sugar-calendar-lite' ),
				'color' => 'orange',
			],
		];

		$badge = [];

		// Get first badge that exists.
		foreach ( $badges as $key => $value ) {
			if ( ! empty( $addon[ $key ] ) ) {
				$badge = $value;

				break;
			}
		}

		if ( empty( $badge ) ) {
			return '';
		}

		return self::get_badge( $badge['text'], 'sm', 'inline', $badge['color'], $badge['icon'] ?? '' );
	}

	/**
	 * Get badge HTML.
	 *
	 * @since 3.7.0
	 *
	 * @param string $text     Badge text.
	 * @param string $size     Badge size.
	 * @param string $position Badge position.
	 * @param string $color    Badge color.
	 * @param string $icon     Badge icon name in Font Awesome "format", e.g. `fa-check`, defaults to empty string.
	 *
	 * @return string
	 */
	public static function get_badge(
		string $text,
		string $size = 'sm',
		string $position = 'inline',
		string $color = 'neutral',
		string $icon = ''
	): string {

		if ( ! empty( $icon ) ) {
			$icon = self::get_inline_icon( $icon );
		}

		return sprintf(
			'<span class="sugar-calendar-badge sugar-calendar-badge-%1$s sugar-calendar-badge-%2$s sugar-calendar-badge-%3$s">%4$s%5$s</span>',
			esc_attr( $size ),
			esc_attr( $position ),
			esc_attr( $color ),
			wp_kses(
				$icon,
				[
					'i' => [
						'class'       => [],
						'aria-hidden' => [],
					],
				]
			),
			esc_html( $text )
		);
	}

	/**
	 * Print badge HTML.
	 *
	 * @since 3.7.0
	 * @since 3.10.0 Removed the fontawesome support.
	 *
	 * @param string $text     Badge text.
	 * @param string $size     Badge size.
	 * @param string $position Badge position.
	 * @param string $color    Badge color.
	 * @param string $icon     Badge icon name.
	 */
	public static function print_badge(
		string $text,
		string $size = 'sm',
		string $position = 'inline',
		string $color = 'neutral',
		string $icon = ''
	) {

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::get_badge( $text, $size, $position, $color, $icon );
	}

	/**
	 * Get inline icon HTML.
	 *
	 * @since 3.7.0
	 *
	 * @param string $name Font Awesome icon name, e.g. `fa-check`.
	 *
	 * @return string HTML markup for the icon element.
	 */
	public static function get_inline_icon( string $name ): string {

		return sprintf( '<i class="sugar-calendar-icon sugar-calendar-icon--%1$s" aria-hidden="true"></i>', esc_attr( $name ) );
	}

	/**
	 * Print inline SVG markup from the bundled icons directory.
	 *
	 * Reads `assets/images/icons/{$name}.svg` and echoes its markup. Unless
	 * `$original_color` is true, fill colors are swapped for `currentColor` so
	 * the icon inherits the surrounding text color.
	 *
	 * @since 3.12.0
	 *
	 * @param string $name           Icon file name, without the `.svg` extension.
	 * @param bool   $original_color Whether to keep the file's own colors. Default false.
	 */
	public static function svg_icon( string $name, bool $original_color = false ) {

		// Restrict to a safe file-name charset so $name can never escape the icons directory.
		$name = preg_replace( '/[^a-z0-9_-]/i', '', $name );

		$path = SC_PLUGIN_DIR . 'assets/images/icons/' . $name . '.svg';

		if ( ! is_readable( $path ) ) {
			return;
		}

		$svg = file_get_contents( $path );

		if ( $svg === false ) {
			return;
		}

		if ( ! $original_color ) {

			// Force fills to inherit the surrounding text color.
			$svg = preg_replace( '/fill="[^"]*"/', 'fill="currentColor"', $svg );

			if ( strpos( $svg, 'fill=' ) === false ) {
				$svg = str_replace( '<svg', '<svg fill="currentColor"', $svg );
			}
		}

		echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Output the table screen options.
	 *
	 * @since 3.8.0
	 * @since 3.12.0 Render the cog icon via the shared SVG helper.
	 *
	 * @param array $args Arguments.
	 * - string table_name: The identifier name of the table.
	 * - array table_columns: The default columns of the table.
	 * - array table_required_columns: The required columns of the table that cannot be hidden.
	 */
	public static function table_screen_options( $args ) {

		if ( empty( $args['table_name'] ) || empty( $args['table_columns'] ) ) {
			return;
		}

		$table_name             = $args['table_name'];
		$table_columns          = $args['table_columns'];
		$table_required_columns = empty( $args['table_required_columns'] ) ? [] : $args['table_required_columns'];

		$active_columns = get_user_meta( get_current_user_id(), $table_name . '_active_columns', true );
		$active_columns = is_array( $active_columns ) ? $active_columns : array_keys( $table_columns );

		?>
		<div class="sugar-calendar-screen-options sugar-calendar-table-screen-options">
			<button id="sugar-calendar-table-screen-options-toggle" class="sugar-calendar-screen-options-toggle button" type="button" title="<?php esc_attr_e( 'Change columns to display', 'sugar-calendar-lite' ); ?>">
				<?php self::svg_icon( 'cog' ); ?>
			</button>

			<div class="sugar-calendar-screen-options-menu sugar-calendar-table-screen-options-menu" style="display: none;">
				<form action="" method="post">

					<?php wp_nonce_field( 'sugar-calendar-table-active-columns', 'sugar-calendar-table-active-columns-nonce' ); ?>

					<input type="hidden" name="sugar-calendar-table-active-columns[table_name]" value="<?php echo esc_attr( $table_name ); ?>">

					<fieldset>
						<legend><?php esc_html_e( 'Columns', 'sugar-calendar-lite' ); ?></legend>
						<?php
						foreach ( $table_columns as $column_key => $column_display_name ) :
							// Skip if column is cb (checkbox).
							if ( $column_key === 'cb' ) {
								continue;
							}

							$is_required = in_array( $column_key, $table_required_columns, true );
							?>

							<label>
								<?php if ( $is_required ) : ?>

									<input type="checkbox" checked disabled>

								<?php else : ?>

									<input
										type="checkbox"
										name="sugar-calendar-table-active-columns[columns][]"
										value="<?php echo esc_attr( $column_key ); ?>"
										<?php checked( in_array( $column_key, $active_columns, true ) ); ?>
									>
								<?php endif; ?>

								<?php echo esc_html( wp_strip_all_tags( $column_display_name ) ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>

					<?php self::render_screen_options_fieldsets(); ?>

					<p class="submit">
						<button type="submit" name="sugar-calendar-table-active-columns-submit" value="submit" class="button"><?php esc_html_e( 'Save Options', 'sugar-calendar-lite' ); ?></button>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the screen-options fieldsets for the current admin screen.
	 *
	 * @since 3.12.0
	 */
	public static function render_screen_options_fieldsets() {

		$screen_options    = sugar_calendar()->get_admin_screen_options();
		$screen_options_id = $screen_options ? $screen_options->get_screen_options_id() : '';

		if ( empty( $screen_options_id ) ) {
			return;
		}

		$groups = $screen_options->get_options();

		if ( empty( $groups ) ) {
			return;
		}

		$user_saved = get_user_option( $screen_options_id );
		$user_saved = is_array( $user_saved ) ? $user_saved : [];

		foreach ( $groups as $group_key => $group ) :
			?>
			<fieldset>
				<legend><?php echo esc_html( $group['heading'] ); ?></legend>
				<?php
				foreach ( $group['options'] as $option ) :
					$saved_key = $group_key . '_' . $option['option'];
					$value     = $user_saved[ $saved_key ] ?? $option['default'];
					$input_id  = 'sc-screen-option-' . $saved_key;
					$input_name = 'sugar-calendar-table-active-columns[screen_options][' . $saved_key . ']';

					if ( $option['input_type'] === 'number' ) :
						$min  = $option['min'] ?? 1;
						$max  = $option['max'] ?? 999;
						$step = $option['step'] ?? 1;

						// The fieldset legend already names this field, so the
						// input renders on its own line (matching the Events cog).
						// The descriptive label, minus its trailing colon, is the
						// input's accessible name.
						?>
						<input
							type="number"
							id="<?php echo esc_attr( $input_id ); ?>"
							name="<?php echo esc_attr( $input_name ); ?>"
							value="<?php echo esc_attr( $value ); ?>"
							min="<?php echo esc_attr( $min ); ?>"
							max="<?php echo esc_attr( $max ); ?>"
							step="<?php echo esc_attr( $step ); ?>"
							aria-label="<?php echo esc_attr( rtrim( $option['label'], ': ' ) ); ?>"
						>
						<?php
					elseif ( $option['input_type'] === 'checkbox' ) :
						?>
						<label>
							<input
								type="checkbox"
								id="<?php echo esc_attr( $input_id ); ?>"
								name="<?php echo esc_attr( $input_name ); ?>"
								value="1"
								<?php checked( ! empty( $value ) ); ?>
							>
							<?php echo esc_html( $option['label'] ); ?>
						</label>
						<?php
					endif;
				endforeach;
				?>
			</fieldset>
			<?php
		endforeach;
	}

	/**
	 * Open a WordPress core metabox panel.
	 *
	 * Core's own .postbox markup: styled by the admin bundle on every screen
	 * with no plugin CSS. Lives here (not in a host add-on) so any surface
	 * needing a panel can share it. Pair every call with postbox_close().
	 *
	 * @since 3.13.0
	 *
	 * @param string       $id      DOM id for the panel; also the key postboxes.js
	 *                              stores the collapsed state under.
	 * @param string       $title   Raw panel title; escaped here.
	 * @param string|array $classes Extra classes for the .postbox element; a
	 *                              space-separated string is split into tokens.
	 */
	public static function postbox_open( $id, $title, $classes = '' ) {

		$classes = is_array( $classes )
			? $classes
			: preg_split( '/\s+/', trim( (string) $classes ), -1, PREG_SPLIT_NO_EMPTY );

		?>
		<div id="<?php echo esc_attr( $id ); ?>" class="postbox <?php echo esc_attr( self::sanitize_class( (array) $classes ) ); ?>">
			<div class="postbox-header">
				<h2 class="hndle"><span><?php echo esc_html( $title ); ?></span></h2>
				<div class="handle-actions hide-if-no-js">
					<button type="button" class="handlediv" aria-expanded="true">
						<span class="screen-reader-text">
							<?php
							printf(
								/* translators: %s - panel title. */
								esc_html__( 'Toggle panel: %s', 'sugar-calendar-lite' ),
								esc_html( $title )
							);
							?>
						</span>
						<span class="toggle-indicator" aria-hidden="true"></span>
					</button>
				</div>
			</div>
			<div class="inside">
		<?php
	}

	/**
	 * Close a metabox panel opened by postbox_open().
	 *
	 * @since 3.13.0
	 */
	public static function postbox_close() {

		echo '</div></div>';
	}

	/**
	 * Open a core .form-table inside a metabox panel.
	 *
	 * role="presentation" because this table is a layout device, not data; core
	 * uses the same attribute so a screen reader doesn't read row/column coordinates.
	 *
	 * @since 3.13.0
	 */
	public static function form_table_open() {

		echo '<table class="form-table" role="presentation"><tbody>';
	}

	/**
	 * Close a form table.
	 *
	 * @since 3.13.0
	 */
	public static function form_table_close() {

		echo '</tbody></table>';
	}

	/**
	 * Open one label + control row of a form table.
	 *
	 * The caller prints the control between this call and form_table_row_close().
	 * Pass $control_id for a real <label for>; omit it for a read-only row.
	 *
	 * @since 3.13.0
	 *
	 * @param string $label      Raw label text; escaped here.
	 * @param string $control_id Id of the control this labels, when it has one.
	 * @param bool   $required   Whether to append the required marker.
	 */
	public static function form_table_row_open( $label, $control_id = '', $required = false ) {

		echo '<tr><th scope="row">';

		if ( $control_id === '' ) {
			echo esc_html( $label );
		} else {
			printf( '<label for="%1$s">%2$s</label>', esc_attr( $control_id ), esc_html( $label ) );
		}

		if ( $required ) {
			// span.required is core's own; aria-hidden since the required attribute
			// (not the asterisk) is what assistive tech relies on.
			echo ' <span class="required" aria-hidden="true">*</span>';
		}

		echo '</th><td>';
	}

	/**
	 * Close a form-table row.
	 *
	 * @since 3.13.0
	 */
	public static function form_table_row_close() {

		echo '</td></tr>';
	}
}
