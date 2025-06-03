<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Pages\Settings;
use Sugar_Calendar\Common\Editor;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Options;
use Sugar_Calendar\Options as PluginSettings;

/**
 * General Settings tab.
 *
 * @since 3.0.0
 */
class SettingsGeneralTab extends Settings {

	/**
	 * Page tab slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_tab_slug() {

		return 'general';
	}

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'General', 'sugar-calendar-lite' );
	}

	/**
	 * Page menu priority.
	 *
	 * @since 3.0.0
	 *
	 * @return int
	 */
	public static function get_priority() {

		return 0;
	}

	/**
	 * Register page hooks.
	 *
	 * @since 3.0.0
	 */
	public function hooks() {

		parent::hooks();

		add_action( 'sugar_calendar_ajax_date_time_format', [ $this, 'ajax_date_time_format' ] );
	}

	/**
	 * Output setting fields.
	 *
	 * @since 3.0.0
	 *
	 * @param string $section Section id.
	 */
	protected function display_tab( $section = '' ) {

		// License heading.
		UI::heading(
			[
				'title' => esc_html__( 'License', 'sugar-calendar-lite' ),
			]
		);

		/**
		 * Filter the output for license key setting controls.
		 *
		 * @since 3.0.0
		 *
		 * @param null|string $output Control output.
		 */
		$license_key_settings = apply_filters( 'sugar_calendar_admin_pages_settings_general_tab_license_key', null );

		UI::field_wrapper(
			[
				'label' => esc_html__( 'License Key', 'sugar-calendar-lite' ),
				'type'  => 'password',
				'id'    => 'license-key',
			],
			$license_key_settings ?? $this->display_license_key_settings()
		);

		// Display heading.
		UI::heading(
			[
				'title' => esc_html__( 'Display', 'sugar-calendar-lite' ),
			]
		);

		// Maximum events.
		$events_max_num = sc_get_number_of_events();

		UI::number_input(
			[
				'id'          => 'number_of_events',
				'name'        => 'number_of_events',
				'value'       => absint( $events_max_num ),
				'label'       => esc_html__( 'Maximum Events', 'sugar-calendar-lite' ),
				'description' => __( 'Number of events to include in any theme-side calendar. Default <strong>30</strong>. Use <strong>0</strong> for no limit.', 'sugar-calendar-lite' ),
			]
		);

		// Start of the week.
		global $wp_locale;

		$start_of_week = sc_get_week_start_day();

		UI::select_input(
			[
				'id'          => 'start_of_week',
				'name'        => 'start_of_week',
				'options'     => array_map( fn( $day ) => $wp_locale->get_weekday( $day ), range( 0, 6 ) ),
				'value'       => $start_of_week,
				'label'       => esc_html__( 'Start of Week', 'sugar-calendar-lite' ),
				'description' => esc_html__( 'Select the first day of the week.', 'sugar-calendar-lite' ),
			]
		);

		// Date format.
		/**
		 * Filters the default date formats.
		 *
		 * @since 3.0.0
		 *
		 * @param string[] $default_date_formats Array of default date formats.
		 */
		$date_formats = sugar_calendar_date_formats();
		$date_formats = array_unique( $date_formats );
		$date_format  = sc_get_date_format();

		UI::date_time_format_control(
			[
				'id'      => 'date_format',
				'name'    => 'date_format',
				'formats' => $date_formats,
				'value'   => $date_format,
				'label'   => esc_html__( 'Date Format', 'sugar-calendar-lite' ),
			]
		);

		// Time format.
		/**
		 * Filters the default time formats.
		 *
		 * @since 3.0.0
		 *
		 * @param string[] $default_time_formats Array of default time formats.
		 */
		$time_formats = sugar_calendar_time_formats();
		$time_formats = array_unique( $time_formats );
		$time_format  = sc_get_time_format();

		UI::date_time_format_control(
			[
				'id'      => 'time_format',
				'name'    => 'time_format',
				'formats' => $time_formats,
				'value'   => $time_format,
				'label'   => esc_html__( 'Time Format', 'sugar-calendar-lite' ),
			]
		);

		// Calendar day colors.
		/**
		 * Filters the default day color styles.
		 *
		 * @since 2.0.0
		 *
		 * @param array $styles Color styles.
		 */
		$color_styles = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sc_day_color_styles',
			[
				'none'  => esc_html__( 'None', 'sugar-calendar-lite' ),
				'each'  => esc_html__( 'Each', 'sugar-calendar-lite' ),
				'first' => esc_html__( 'First', 'sugar-calendar-lite' ),
				'blend' => esc_html__( 'Blend', 'sugar-calendar-lite' ),
			]
		);
		$color_styles = array_unique( $color_styles );
		$color_style  = sc_get_day_color_style();

		UI::select_input(
			[
				'id'          => 'day_color_style',
				'name'        => 'day_color_style',
				'options'     => $color_styles,
				'value'       => $color_style,
				'label'       => esc_html__( 'Calendar Day Colors', 'sugar-calendar-lite' ),
				'description' => __( 'The theme-side Calendar Color styling strategy.<br><strong>None</strong> by default (no colors).<br><strong>Each</strong> uses a single color for each Event link.<br><strong>First</strong> uses the first color found for the background.<br><strong>Blend</strong> will use the average of all colors for the background.', 'sugar-calendar-lite' ),
			]
		);

		// Dark Mode.
		UI::select_input(
			[
				'id'          => 'single_event_appearance_mode',
				'name'        => 'single_event_appearance_mode',
				'options'     => [
					'light' => esc_html__( 'Light', 'sugar-calendar-lite' ),
					'dark'  => esc_html__( 'Dark', 'sugar-calendar-lite' ),
				],
				'value'       => Editor\get_single_event_appearance_mode(),
				'label'       => esc_html__( 'Single Event Page Appearance', 'sugar-calendar-lite' ),
				'description' => __( 'Adjust the frontend display of event single page.', 'sugar-calendar-lite' ),
			]
		);

		// Editing heading.
		UI::heading(
			[
				'title' => esc_html__( 'Editing', 'sugar-calendar-lite' ),
			]
		);

		// Editor Type
		// Get the current editor settings.
		$type   = Editor\current();
		$fields = Editor\custom_fields();

		// Get the registered editors.
		$editors = Editor\registered();

		$editor_types = array_reduce(
			$editors,
			function ( $editor_types, $editor ) {

				$editor_types[ $editor['id'] ] = [ $editor['label'], ! $editor['disabled'] ];

				return $editor_types;
			},
			[]
		);

		UI::select_input(
			[
				'name'        => 'editor_type',
				'id'          => 'editor_type',
				'options'     => $editor_types,
				'value'       => $type,
				'label'       => esc_html__( 'Editor Type', 'sugar-calendar-lite' ),
				'description' => esc_html__( 'The interface to use when adding or editing Events.', 'sugar-calendar-lite' ),
			]
		);

		// Custom Fields.
		UI::toggle_control(
			[
				'id'          => 'custom_fields',
				'name'        => 'custom_fields',
				'value'       => $fields,
				'label'       => esc_html__( 'Enable Custom Fields', 'sugar-calendar-lite' ),
				'description' => __( 'Allow developers to extend post types that support <code>events</code>.', 'sugar-calendar-lite' ),
			]
		);

		// Default Event Calendar.
		// Get the taxonomy & args.
		$tax     = get_taxonomy( sugar_calendar_get_calendar_taxonomy_id() );
		$current = sugar_calendar_get_default_calendar();
		$name    = sugar_calendar_get_default_calendar_option_name();
		$args    = [
			'taxonomy'         => $tax->name,
			'hierarchical'     => $tax->hierarchical,
			'selected'         => $current,
			'id'               => $name,
			'name'             => $name,
			'hide_empty'       => false,
			'orderby'          => 'name',
			'show_option_none' => esc_html__( '&mdash; No Default &mdash;', 'sugar-calendar-lite' ),
			'label'            => esc_html__( 'Default Event Calendar', 'sugar-calendar-lite' ),
			'description'      => esc_html__( 'When adding a new Event, this Calendar will be preselected.', 'sugar-calendar-lite' ),
		];

		UI::calendar_dropdown_control( $args );

		// Time Zone heading.
		UI::heading(
			[
				'title' => esc_html__( 'Time Zone', 'sugar-calendar-lite' ),
			]
		);

		// Time Zones.
		// Get the current settings.
		$timezone  = PluginSettings::get( 'timezone', '' );
		$tztype    = PluginSettings::get( 'timezone_type', 'off' );
		$tzconvert = PluginSettings::get( 'timezone_convert', false );

		// Types.
		$types = [
			'off'    => esc_html__( 'Off', 'sugar-calendar-lite' ),
			'single' => esc_html__( 'Single', 'sugar-calendar-lite' ),
			'multi'  => esc_html__( 'Multi', 'sugar-calendar-lite' ),
		];

		UI::select_input(
			[
				'name'        => 'timezone_type',
				'id'          => 'timezone_type',
				'options'     => $types,
				'value'       => $tztype,
				'label'       => esc_html__( 'Time Zones', 'sugar-calendar-lite' ),
				'description' => __( '<strong>Off</strong> by default (Existing time zone data still appears).<br><strong>Single</strong> allows Events to have one time zone.<br><strong>Multi</strong> allows Events to have different start & end time zones.<br><strong>Single</strong> and <strong>Multi</strong> will enable time zones for Calendars.', 'sugar-calendar-lite' ),
			]
		);

		// Default Time Zone.
		UI::timezone_dropdown_control(
			[
				'name'        => 'timezone',
				'id'          => 'timezone',
				'current'     => $timezone,
				'label'       => esc_html__( 'Default Time Zone', 'sugar-calendar-lite' ),
				'description' => __( 'When time zones are enabled, new Events will default to this. If you are unsure, leave empty or pick the time zone you are in.', 'sugar-calendar-lite' ),
			]
		);

		// Visitor Conversion.
		UI::toggle_control(
			[
				'id'          => 'timezone_convert',
				'name'        => 'timezone_convert',
				'value'       => $tzconvert,
				'label'       => esc_html__( 'Visitor Conversion', 'sugar-calendar-lite' ),
				'description' => __( 'Attempts to update theme-side Event times according to visitor web browser location. Depends on client-side browser support. May not work for all visitors.', 'sugar-calendar-lite' ),
			]
		);
	}

	/**
	 * Display license key settings controls.
	 *
	 * @since 3.0.0
	 *
	 * @return false|string
	 */
	private function display_license_key_settings() {

		ob_start();
		?>

        <p><?php esc_html_e( 'You\'re using Sugar Calendar Lite - no license needed. Enjoy!', 'sugar-calendar-lite' ); ?> 🙂</p>

        <p>
			<?php
			printf(
				wp_kses( /* translators: %s - WPMailSMTP.com upgrade URL. */
					__( 'To unlock more features, consider <strong><a href="%s" target="_blank" rel="noopener noreferrer" class="sugar-calendar-upgrade-modal">upgrading to PRO</a></strong>.', 'sugar-calendar-lite' ),
					[
						'a'      => [
							'href'   => [],
							'class'  => [],
							'target' => [],
							'rel'    => [],
						],
						'strong' => [],
					]
				),
				esc_url(
					Helpers::get_upgrade_link(
						[
							'content' => 'general-license-key',
							'medium'  => 'settings-general',
						]
					)
				)
			);
			?>
        </p>

        <p class="desc sugar-calendar-license-coupon">
			<?php
			printf(
				wp_kses( /* Translators: %s - discount value 50% */
					__( 'As a valued Sugar Calendar Lite user you receive <strong>%s off</strong>, automatically applied at checkout!', 'sugar-calendar-lite' ),
					[
						'strong' => [],
						'br'     => [],
					]
				),
				'50%'
			);
			?>
        </p>

        <hr>

        <div class="sugar-calendar-setting-license-wrapper">
            <div class="sugar-calendar-setting-license-key-wrapper">
                <input type="password"
                       value=""
                       placeholder="<?php esc_attr_e( 'Paste license key here', 'sugar-calendar-lite' ); ?>"
                       id="sugar-calendar-setting-license-key"/>
            </div>
            <button type="button"
                    class="sugar-calendar-btn sugar-calendar-btn-md sugar-calendar-btn-secondary"
                    id="sugar-calendar-setting-license-key-button"><?php esc_attr_e( 'Verify Key', 'sugar-calendar-lite' ); ?></button>
        </div>

        <p class="desc">
			<?php
			echo wp_kses(
				sprintf( /* translators: %1$s - Sugar Calendar account dashboard url; %2$s - pricing page url. */
					__( 'Your license key can be found in your <a href="%1$s" target="_blank" rel="noopener noreferrer">Sugar Calendar Account Dashboard</a>. Don\'t have a license?  <a href="%2$s" target="_blank" rel="noopener noreferrer">Sign up today!</a>', 'sugar-calendar-lite' ),
					// phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
					esc_url( Helpers::get_utm_url( 'https://sugarcalendar.com/account/', [ 'content' => 'License Key Account Dashboard Link', 'medium' => 'settings-general' ] ) ),
					esc_url(
						Helpers::get_upgrade_link(
							[
								'content' => 'License Sign Up Today',
								'medium'  => 'settings-general',
							]
						)
					)
				),
				[
					'a' => [
						'href'   => [],
						'target' => [],
						'rel'    => [],
					],
				]
			);
			?>
        </p>

		<?php

		return ob_get_clean();
	}

	/**
	 * Handle POST requests.
	 *
	 * @since 3.0.0
	 *
	 * @param array $post_data Post data.
	 */
	public function handle_post( $post_data = [] ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		$settings = [
			'number_of_events',
			'start_of_week',
			'date_format',
			'time_format',
			'day_color_style',
			'timezone_convert',
			'timezone_type',
			'timezone',
			'editor_type',
			'custom_fields',
			'default_calendar',
			'hide_announcements',
			'single_event_appearance_mode',
		];

		foreach ( $settings as $key ) {
			$value = $post_data[ $key ] ?? '';

			switch ( $key ) {
				case 'number_of_events':
					$value = max( 0, intval( $value ) );
					break;

				case 'start_of_week':
					$value = in_array( intval( $value ), range( 0, 6 ) ) ? intval( $value ) : 0;
					break;

				case 'date_format':
				case 'time_format':
					$value = sanitize_option( 'date_format', $value );
					break;

				case 'day_color_style':
					$color_styles = [ 'none', 'each', 'first', 'blend' ];
					$value        = in_array( $value, $color_styles ) ? $value : $color_styles[0];
					break;

				case 'editor_type':
					$editor_types = wp_list_pluck( Editor\registered(), 'id' );
					$value        = in_array( $value, $editor_types ) ? $value : $editor_types[0];
					break;

				case 'custom_fields':
				case 'timezone_convert':
					$value = isset( $post_data[ $key ] );
					break;

				case 'default_calendar':
					$calendars = get_terms(
						[
							'taxonomy'   => 'sc_event_category',
							'orderby'    => 'name',
							'hide_empty' => 0,
							'fields'     => 'ids',
						]
					);
					$calendars = [ -1, ...$calendars ];
					$value     = in_array( intval( $value ), $calendars ) ? intval( $value ) : $calendars[0];
					break;

				case 'timezone_type':
					$types = [ 'off', 'single', 'multi' ];
					$value = in_array( $value, $types ) ? $value : $types[0];
					break;

				case 'timezone':
					$value = sanitize_option( 'timezone_string', $value );
					break;

				case 'single_event_appearance_mode':
					$modes = [ 'light', 'dark' ];
					$value = in_array( $value, $modes, true ) ? $value : $modes[0];
					break;
			}

			Options::update( $key, $value );
		}

		WP::add_admin_notice( esc_html__( 'Settings saved.', 'sugar-calendar-lite' ), WP::ADMIN_NOTICE_SUCCESS );
	}

	/**
	 * Handle date and time formatting AJAX requests.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function ajax_date_time_format() {

		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ( ! isset( $_POST['date_time_format'] ) ) {
			wp_send_json_error();
		}

		// Get format.
		$format = sanitize_option( 'date_format', $_POST['date_time_format'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		// Get the time zone.
		$timezone = sugar_calendar_get_timezone();

		// Format and translate.
		$date_time_format = sugar_calendar_format_date_i18n( $format, null, $timezone );

		wp_send_json_success( $date_time_format );
	}
}
