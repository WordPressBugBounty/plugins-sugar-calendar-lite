<?php

namespace Sugar_Calendar\AddOn\Ticketing\Helpers;

/**
 * Admin interface helpers.
 *
 * @since 1.2.0
 */
class UI {

	/**
	 * Sanitize one or more HTML classes.
	 *
	 * @since 1.2.0
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
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public static function header() {

		?>
        <div id="sugar-calendar-header"
             class="sugar-calendar-header">
            <img class="sugar-calendar-header-logo"
                 src="<?php echo esc_url( SC_PLUGIN_URL . 'assets/images/logo.svg' ); ?>"
                 alt="Sugar Calendar Logo"/>
            <a href="https://sugarcalendar.com/docs/" target="_blank" id="sugar-calendar-header-help"><?php esc_html_e( 'Help', 'sugar-calendar-lite' ); ?></a>
        </div>
		<?php
	}

	/**
	 * Render a tab navigation menu.
	 *
	 * @since 1.2.0
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
	 * @since 1.2.0
	 *
	 * @param array  $args    Wrapper arguments.
	 * @param string $content Wrapper contents.
	 */
	public static function field_wrapper( $args, $content = '' ) {

		$args = wp_parse_args(
			$args,
			[
				'type'  => '',
				'id'    => '',
				'class' => [],
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
                    <label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $args['label'] ); ?></label>
                </span>

			<?php endif; ?>

            <span class="sugar-calendar-setting-field"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>

        </div>

		<?php
	}

	/**
	 * Output a select control wrapper.
	 *
	 * @since 1.2.0
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
	 * @since 1.2.0
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

        <select
			name="<?php echo esc_attr( $name ); ?>"
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
	 * @since 1.2.0
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
	 * @since 1.2.0
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
	 * Output a toggle setting control.
	 *
	 * @since 1.2.0
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
	 * @since 1.2.0
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
				'type' => 'select',
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
	 * @since 1.2.0
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
	 * @since 1.2.0
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
	 * @since 1.2.0
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

		ob_start();
		?>

        <input type="<?php echo esc_attr( $type ); ?>"
               name="<?php echo esc_attr( $name ); ?>"
               value="<?php echo esc_attr( $value ); ?>"
               id="<?php echo esc_attr( $id ); ?>"
               placeholder="<?php echo esc_attr( $placeholder ); ?>"/>

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
	 * @since 1.2.0
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
	 * @since 1.2.0
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
	 * @since 1.2.0
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
		$type      = in_array( $args['type'], $types ) ? $args['type'] : 'primary';
		$classes[] = "sugar-calendar-btn-{$type}";

		// Size.
		$sizes     = [ 'sm', 'md', 'lg', 'xl' ];
		$size      = in_array( $args['size'], $sizes ) ? $args['size'] : 'md';
		$classes[] = "sugar-calendar-btn-{$size}";
		$class     = self::sanitize_class( $classes );

		// Submit.
		$submit = (bool) $args['submit'] ? 'submit' : 'button';

		$text   = $args['text'];
		$link   = $args['link'];
		$target = $args['target'];
		?>

		<?php if ( empty( $link ) ) : ?>

            <button type="<?php echo esc_attr( $submit ); ?>"
                    name="<?php echo esc_attr( $name ); ?>"
                    id="<?php echo esc_attr( $id ); ?>"
                    class="<?php echo esc_attr( $class ); ?>"
            ><?php echo esc_html( $text ); ?></button>

		<?php else : ?>

            <a href="<?php echo esc_url( $link ); ?>"
               id="<?php echo esc_attr( $id ); ?>"
               target="<?php echo esc_attr( $target ); ?>"
               class="<?php echo esc_attr( $class ); ?>"
            ><?php echo esc_html( $text ); ?></a>

		<?php endif; ?>
		<?php
	}
}
