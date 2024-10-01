<?php

namespace Sugar_Calendar;

/**
 * Class with all the misc helper functions that don't belong elsewhere.
 *
 * @since 3.0.0
 */
class Helpers {

	/**
	 * Import Plugin_Upgrader class from core.
	 *
	 * @since 3.0.0
	 */
	public static function include_plugin_upgrader() {

		/** \WP_Upgrader class */
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		/** \Plugin_Upgrader class */
		require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
	}

	/**
	 * Whether the current request is a WP CLI request.
	 *
	 * @since 3.0.0
	 */
	public static function is_wp_cli() {

		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * Whether the license is valid or not.
	 *
	 * @since 3.1.0
	 *
	 * @return bool
	 */
	public static function is_license_valid() {

		$key     = sugar_calendar()->get_license_key();
		$license = Options::get( 'license' );

		if ( empty( $key ) || empty( $license ) ) {
			return false;
		}

		$is_expired  = isset( $license['is_expired'] ) && $license['is_expired'] === true;
		$is_disabled = isset( $license['is_disabled'] ) && $license['is_disabled'] === true;
		$is_invalid  = isset( $license['is_invalid'] ) && $license['is_invalid'] === true;

		return ! $is_expired && ! $is_disabled && ! $is_invalid;
	}

	/**
	 * Whether the application fee is supported or not.
	 *
	 * @since 3.1.0
	 *
	 * @return bool
	 */
	public static function is_application_fee_supported() {

		if (
			! class_exists( 'Sugar_Calendar\AddOn\Ticketing\Plugin' ) ||
			! is_plugin_active( 'sc-event-ticketing/sc-event-ticketing.php' )
		) {
			return true;
		}

		$event_ticketing_addon = new \Sugar_Calendar\AddOn\Ticketing\Plugin();

		if ( ! property_exists( $event_ticketing_addon, 'version' ) ) {
			return true;
		}

		return version_compare( $event_ticketing_addon->version, '1.2.0', '<' );
	}

	/**
	 * Clean the incoming data.
	 *
	 * @since 3.1.0
	 *
	 * @param array $incoming_data Data needed to be cleaned.
	 *
	 * @return array
	 */
	public static function clean_block_data_from_ajax( $incoming_data ) { // phpcs:ignore Generic.Metrics.NestingLevel.MaxExceeded, Generic.Metrics.CyclomaticComplexity.MaxExceeded

		$expected_data = [
			'attributes'         => [
				'default' => [],
				'type'    => 'attributes',
			],
			'calendars'          => [
				'default' => [],
				'type'    => 'array',
			],
			'calendarsFilter'    => [
				'default' => [],
				'type'    => 'array',
			],
			'id'                 => [
				'default' => '',
				'type'    => 'string',
			],
			'month'              => [
				'default' => 0,
				'type'    => 'int',
			],
			'search'             => [
				'default' => '',
				'type'    => 'string',
			],
			'year'               => [
				'default' => 0,
				'type'    => 'int',
			],
			'accentColor'        => [
				'default' => '',
				'type'    => 'string',
			],
			'display'            => [
				'default' => 'month',
				'type'    => 'string',
			],
			'visitor_tz_convert' => [
				'default' => 0,
				'type'    => 'int',
			],
			'visitor_tz'         => [
				'default' => '',
				'type'    => 'string',
			],
			'updateDisplay'      => [
				'default' => false,
				'type'    => 'bool',
			],
			'day'                => [
				'default' => 0,
				'type'    => 'int',
			],
			'action'             => [
				'default' => '',
				'type'    => 'string',
			],
		];

		$clean_data = [
			'from_ajax' => true,
		];

		foreach ( $incoming_data as $block_data_key => $block_data_val ) {

			if ( ! array_key_exists( $block_data_key, $expected_data ) ) {
				continue;
			}

			$temp_data = null;

			switch ( $expected_data[ $block_data_key ]['type'] ) {
				case 'array':
					$temp_data = array_map( 'absint', $block_data_val );
					break;

				case 'attributes':
					$temp_data = self::sanitize_attributes( $block_data_val );
					break;

				case 'string':
					$temp_data = sanitize_text_field( $block_data_val );
					break;

				case 'int':
					$temp_data = absint( $block_data_val );
					break;

				case 'bool':
					if ( empty( $block_data_val ) ) {
						$temp_data = false;
					} elseif ( $block_data_val === 'false' ) {
						$temp_data = false;
					} elseif ( $block_data_val === 'true' ) {
						$temp_data = true;
					} else {
						$temp_data = boolval( $block_data_val );
					}
					break;
			}

			if ( empty( $temp_data ) ) {
				$temp_data = $expected_data[ $block_data_key ]['default'];
			}

			$clean_data[ $block_data_key ] = $temp_data;
		}

		return $clean_data;
	}

	/**
	 * Get the URL for an svg icon.
	 *
	 * @since 3.1.0
	 *
	 * @param string $icon Icon name.
	 *
	 * @return string
	 */
	public static function get_svg_url( $icon ) {

		return SC_PLUGIN_ASSETS_URL . 'images/icons/' . $icon . '.svg';
	}

	/**
	 * Whether the current request is on the admin editor.
	 *
	 * @since 3.1.0
	 *
	 * @return bool
	 */
	public static function is_on_admin_editor() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		return defined( 'REST_REQUEST' ) && REST_REQUEST && ( $_GET['context'] === 'edit' );
	}

	/**
	 * Sanitize the attributes.
	 *
	 * @since 3.1.0
	 *
	 * @param array $attributes Attributes to sanitize.
	 *
	 * @return array
	 */
	public static function sanitize_attributes( $attributes ) {

		$sanitized_attributes = [];

		$event_list_attr = [
			'allowUserChangeDisplay',
			'showDescriptions',
			'showFeaturedImages',
		];

		foreach ( $event_list_attr as $attr ) {
			$sanitized_attributes[ $attr ] = ! empty( $attributes[ $attr ] ) && $attributes[ $attr ] === 'true';
		}

		return $sanitized_attributes;
	}

	/**
	 * Get the date/time label.
	 *
	 * @since 3.1.0
	 *
	 * @param \Sugar_Calendar\Event $event The event object.
	 *
	 * @return string
	 */
	public static function get_event_datetime_label( $event ) {

		if ( $event->is_multi() ) {
			return __( 'Date/Time:', 'sugar-calendar' );
		}

		return __( 'Date:', 'sugar-calendar' );
	}

	/**
	 * Get the multi-day date/time.
	 *
	 * @since 3.1.0
	 *
	 * @param \Sugar_Calendar\Event $event The event object.
	 *
	 * @return string|false
	 */
	public static function get_multi_day_event_datetime( $event ) {

		if ( ! $event->is_multi() ) {
			return false;
		}

		$date_format = Options::get( 'date_format' );

		$start_date = sugar_calendar_format_date_i18n( $date_format, $event->start );
		$end_date   = sugar_calendar_format_date_i18n( $date_format, $event->end );

		if ( $event->is_all_day() ) {
			return sprintf(
				/* translators: 1: start date, 2: end date. */
				esc_html__( '%1$s - %2$s', 'sugar-calendar' ),
				$start_date,
				$end_date
			);
		}

		$time_format = Options::get( 'time_format' );

		return sprintf(
			/* translators: 1: start date, 2: start time, 3: end date, 4: end time. */
			'%1$s at %2$s - %3$s at %4$s',
			'<span class="sc-frontend-single-event__details__val-date">' . self::get_event_time_output( $event, $date_format ) . '</span>',
			'<span class="sc-frontend-single-event__details__val-time">' . self::get_event_time_output( $event, $time_format ) . '</span>',
			'<span class="sc-frontend-single-event__details__val-date">' . self::get_event_time_output( $event, $date_format, 'end' ) . '</span>',
			'<span class="sc-frontend-single-event__details__val-time">' . self::get_event_time_output( $event, $time_format, 'end' ) . '</span>'
		);
	}

	/**
	 * Get the event date.
	 *
	 * @since 3.1.0
	 * @since 3.1.2 Refactor the method to output the datetime.
	 *
	 * @param Event  $event        The event object.
	 * @param string $date_or_time Accept either 'date' or 'time'.
	 */
	public static function get_event_datetime( $event, $date_or_time = 'date' ) {

		if ( $date_or_time === 'time' && $event->is_all_day() ) {
			return esc_html__( 'All Day', 'sugar-calendar' );
		}

		$format = 'date_format';

		if ( $date_or_time === 'time' ) {
			$format = 'time_format';
		}

		$format = Options::get( $format );
		$output = self::get_event_time_output( $event, $format, 'start' );

		if ( ! empty( $event->end ) && $event->start !== $event->end ) {
			if (
				$date_or_time === 'time' ||
				$event->is_multi()
			) {
				$output .= ' - ' . self::get_event_time_output( $event, $format, 'end' );
			}
		}

		return $output;
	}

	/**
	 * Get the event time output.
	 *
	 * The output is the event time wrapped in `<time>` tag with the datetime attribute.
	 *
	 * @since 3.1.2
	 * @since 3.2.0 Support 'recurrence_end' as the event time type.
	 *
	 * @param Event  $event           The event object.
	 * @param string $format          The format saved in the options.
	 * @param string $event_time_type Accepts 'start' or 'end'.
	 * @param bool   $output_array    Whether to output an array or not.
	 *
	 * @return string|array
	 */
	public static function get_event_time_output( $event, $format, $event_time_type = 'start', $output_array = false ) {

		// Default format.
		$time_attr_format = 'Y-m-d\TH:i:s';
		$time_attr_tz     = 'floating';

		if ( $event_time_type === 'end' ) {
			$event_timezone = $event->end_tz;
			$event_time     = $event->end;
		} elseif ( $event_time_type === 'recurrence_end' ) {
			$event_timezone = $event->recurrence_end_tz;
			$event_time     = $event->recurrence_end;
		} else {
			$event_timezone = $event->start_tz;
			$event_time     = $event->start;
		}

		if ( ! empty( $event_timezone ) ) {

			$offset = sugar_calendar_get_timezone_offset(
				[
					'time'     => $event_time,
					'timezone' => $event_timezone,
				]
			);

			$time_attr_format = "Y-m-d\TH:i:s{$offset}";
			$time_attr_tz     = $event_timezone;
		}

		// The `<time>` datetime attribute.
		if ( $event_time_type === 'end' ) {
			$time_attr_dt = $event->end_date( $time_attr_format );

			// Fallback timezone to start time timezone if it's not empty.
			if ( $time_attr_tz === 'floating' && ! empty( $event->start_tz ) ) {
				$time_attr_tz = $event->start_tz;
			}
		} else {
			$time_attr_dt = $event->start_date( $time_attr_format );
		}

		if ( $output_array ) {
			return [
				'datetime' => $time_attr_dt,
				'value'    => sugar_calendar_format_date_i18n( $format, $event_time ),
			];
		}

		return sprintf(
			'<time datetime="%1$s" title="%2$s" data-timezone="%3$s">%4$s</time>',
			esc_attr( $time_attr_dt ),
			esc_attr( $time_attr_dt ),
			esc_attr( $time_attr_tz ),
			esc_html( sugar_calendar_format_date_i18n( $format, $event_time ) )
		);
	}

	/**
	 * Whether to allow visitor timezone conversion for the calendar shortcode.
	 *
	 * @since 3.1.2
	 *
	 * @return int
	 */
	public static function should_allow_visitor_tz_convert_cal_shortcode() {

		return absint(
			/**
			 * Filter whether to allow visitor timezone conversion for the calendar shortcode.
			 *
			 * @since 3.1.2
			 *
			 * @param int $allow_visitor_tz_convert_cal_shortcode Whether to allow visitor timezone conversion for the calendar shortcode.
			 */
			apply_filters(
				'sugar_calendar_helpers_allow_visitor_tz_convert_cal_shortcode',
				absint( Options::get( 'timezone_convert' ) )
			)
		);
	}

	/**
	 * Get the valid UTC offset given a UTC string.
	 *
	 * Example.
	 * If the passed `$utc_string` is UTC+7.5, the function will return +07:30.
	 *
	 * @since 3.2.1
	 *
	 * @param string $utc_string The UTC string.
	 *
	 * @return false|string
	 */
	public static function get_valid_utc_offset( $utc_string ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$utc_string = trim( strtoupper( $utc_string ) );

		if ( strpos( $utc_string, 'UTC' ) !== 0 ) {
			return false;
		}

		if ( ! preg_match( '/^UTC([+-])(\d{1,2})(\.5)?$/', $utc_string, $matches ) ) {
			return false;
		}

		$sign      = $matches[1];
		$hours     = intval( $matches[2] );
		$half_hour = isset( $matches[3] ) && $matches[3] === '.5';

		// Validate hours.
		if ( $hours > 14 ) {
			return false;
		}

		// Special case for UTC+14 and UTC-12.
		if (
			( $sign === '+' && $hours === 14 && $half_hour ) ||
			( $sign === '-' && $hours === 12 && $half_hour )
		) {
			return false;
		}

		// Calculate total offset in minutes.
		$total_minutes = $hours * 60 + ( $half_hour ? 30 : 0 );

		if ( $sign === '-' ) {
			$total_minutes = -$total_minutes;
		}

		// Format the offset string directly from the calculated minutes.
		$abs_minutes = abs( $total_minutes );
		$hours       = floor( $abs_minutes / 60 );
		$minutes     = $abs_minutes % 60;
		$sign        = ( $total_minutes >= 0 ) ? '+' : '-';

		return sprintf( '%s%02d:%02d', $sign, $hours, $minutes );
	}

	/**
	 * Get the manual UTC offset timezone to display.
	 *
	 * @since 3.2.1
	 *
	 * @param string $timezone The timezone string.
	 *
	 * @return string
	 */
	public static function get_manual_utc_offset_timezone_display( $timezone ) {

		$offset = self::get_valid_utc_offset( $timezone );

		if ( $offset ) {
			return $timezone;
		}

		// Get the manual offset.
		$offset = sugar_calendar_get_manual_timezone_offset( 'now', $timezone );

		// Make the offset string.
		$offset_st = ( $offset > 0 )
			? "-{$offset}"
			: '+' . absint( $offset );

		// Make the Unknown time zone string.
		$retval = "Etc/GMT{$offset_st}";

		// Filter & return.
		return $retval;
	}
}
