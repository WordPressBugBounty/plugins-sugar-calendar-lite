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
		return defined( 'REST_REQUEST' ) && REST_REQUEST && ! empty( $_GET['context'] ) && ( $_GET['context'] === 'edit' );
	}

	/**
	 * Sanitize the attributes.
	 *
	 * @since 3.1.0
	 * @since 3.3.0 Add 'appearance' attribute.
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
			'appearance',
		];

		foreach ( $event_list_attr as $attr ) {
			$sanitized_attributes[ $attr ] = ! empty( $attributes[ $attr ] ) && $attributes[ $attr ] === 'true'
				? true
				: sanitize_text_field( $attributes[ $attr ] );
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

	/**
	 * Get the upcoming events list with recurring events.
	 *
	 * @since 3.3.0
	 *
	 * @param int    $number   The number of events to get.
	 * @param string $category The categories separated by comma.
	 *
	 * @return Event[]
	 */
	public static function get_upcoming_events_list_with_recurring( $number = 5, $category = '' ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		global $wpdb;

		$left_join              = '';
		$where_categories_query = '';

		// Get the category left join and where queries if necessary.
		if ( ! empty( $category ) ) {
			$category_arr    = explode( ',', $category );
			$categories_data = [];

			foreach ( $category_arr as $cat ) {
				$categories_data[] = get_term_by( 'slug', $cat, sugar_calendar_get_calendar_taxonomy_id() );
			}

			if ( ! empty( $categories_data ) ) {
				$left_join              = 'LEFT JOIN wp_term_relationships ON (sc_e.object_id = wp_term_relationships.object_id)';
				$where_categories_query = $wpdb->prepare(
					'AND ( wp_term_relationships.term_taxonomy_id IN (%1$s) )',
					implode( ',', wp_list_pluck( $categories_data, 'term_taxonomy_id' ) )
				);
			}
		}

		$now          = sugar_calendar_get_request_time( 'mysql' );
		$select_query = 'SELECT sc_e.id FROM wp_sc_events sc_e';

		if ( ! empty( $left_join ) ) {
			$select_query .= ' ' . $left_join;
		}

		$where_query = $wpdb->prepare(
			"WHERE sc_e.object_type = 'post' AND sc_e.status = 'publish' AND (
				(
					sc_e.recurrence IN ('daily','weekly','monthly','yearly') AND
					sc_e.start <= %s AND
					(
						sc_e.recurrence_end <= '0000-01-01 00:00:00' OR
						sc_e.recurrence_end >= %s
					)
				)
				OR
				(
					sc_e.recurrence = '' AND
					sc_e.end >= %s
				)
			)",
			$now,
			$now,
			$now
		);

		if ( ! empty( $where_categories_query ) ) {
			$where_query .= ' ' . $where_categories_query;
		}

		$order_by = $wpdb->prepare(
			'ORDER BY sc_e.start ASC LIMIT %d',
			$number
		);

		// The query below is prepared/sanitized individually.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$event_ids = $wpdb->get_results( $select_query . ' ' . $where_query . ' ' . $order_by );

		if ( empty( $event_ids ) ) {
			return [];
		}

		return sugar_calendar_get_events(
			[
				'id__in' => wp_list_pluck( $event_ids, 'id' ),
			]
		);
	}

	/**
	 * Sanitizes the start MySQL datetime, so that
	 * if all-day, time is set to midnight.
	 *
	 * @since 2.0.5
	 * @since 3.3.0 Moved to Helpers class.
	 *
	 * @param string $start   The start time, in MySQL format.
	 * @param string $end     The end time, in MySQL format.
	 * @param bool   $all_day True|False, whether the event is all-day.
	 *
	 * @return string
	 */
	public static function sanitize_start( $start = '', $end = '', $all_day = false ) {

		// Bail early if start or end are empty or malformed.
		if ( empty( $start ) || empty( $end ) || ! is_string( $start ) || ! is_string( $end ) ) {
			return $start;
		}

		// Check if the user attempted to set an end date and/or time.
		$start_int = strtotime( $start );

		// All day events end at the final second.
		if ( $all_day === true ) {
			$start_int = gmmktime(
				0,
				0,
				0,
				gmdate( 'n', $start_int ),
				gmdate( 'j', $start_int ),
				gmdate( 'Y', $start_int )
			);
		}

		// Format.
		$retval = gmdate( 'Y-m-d H:i:s', $start_int );

		// Return the new start.
		return $retval;
	}

	/**
	 * Original function - \Sugar_Calendar\Admin\Editor\Meta.
	 * overridden due to has_end() function.
	 *
	 * Sanitizes the end MySQL datetime, so that:
	 *
	 * - It does not end before it starts.
	 * - It is at least as long as the minimum event duration (if exists).
	 * - If the date is empty, the time can still be used.
	 * - If both the date and the time are empty, it will equal the start.
	 *
	 * @since 3.0.0
	 *
	 * @param string $end     The end time, in MySQL format.
	 * @param string $start   The start time, in MySQL format.
	 * @param bool   $all_day True|False, whether the event is all-day.
	 *
	 * @return string
	 */
	public static function sanitize_end( $end = '', $start = '', $all_day = false ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		// Bail early if start or end are empty or malformed.
		if ( empty( $start ) || empty( $end ) || ! is_string( $start ) || ! is_string( $end ) ) {
			return $end;
		}

		// See if there a minimum duration to enforce.
		$minimum = sugar_calendar_get_minimum_event_duration();

		// Convert to integers for faster comparisons.
		$start_int = strtotime( $start );
		$end_int   = strtotime( $end );

		// Calculate the end, based on a minimum duration (if set).
		$end_compare = ! empty( $minimum )
			? strtotime( '+' . $minimum, $end_int )
			: $end_int;

		// Check if the user attempted to set an end date and/or time.
		$has_end = true;

		// Bail if event duration exceeds the minimum (great!).
		if ( $end_compare > $start_int ) {
			return $end;
		}

		// ...or the user attempted an end date and this isn't an all-day event.
		if ( $all_day === false ) {
			// If there is a minimum, the new end is the start + the minimum.
			if ( ! empty( $minimum ) ) {
				$end_int = strtotime( '+' . $minimum, $start_int );

				// If there isn't a minimum, then the end needs to be rejected.
			} else {
				$has_end = false;
			}
		}

		// The above logic deterimned that the end needs to equal the start.
		// This is how events are allowed to have a start without a known end.
		if ( $has_end === false ) {
			$end_int = $start_int;
		}

		// All day events end at the final second.
		if ( $all_day === true ) {
			$end_int = mktime(
				23,
				59,
				59,
				gmdate( 'n', $end_int ),
				gmdate( 'j', $end_int ),
				gmdate( 'Y', $end_int )
			);
		}

		// Return the new end.
		return gmdate( 'Y-m-d H:i:s', $end_int );
	}

	/**
	 * Sanitizes the all-day value.
	 *
	 * - If times align, all-day is made true
	 *
	 * @since 2.0.5
	 * @since 3.3.0 Moved to Helpers class.
	 *
	 * @param bool   $all_day True|False, whether the event is all-day.
	 * @param string $start   The start time, in MySQL format.
	 * @param string $end     The end time, in MySQL format.
	 *
	 * @return string
	 */
	public static function sanitize_all_day( $all_day = false, $start = '', $end = '' ) {

		// Bail early if start or end are empty or malformed.
		if ( empty( $start ) || empty( $end ) || ! is_string( $start ) || ! is_string( $end ) ) {
			return $start;
		}

		// Check if the user attempted to set an end date and/or time.
		$start_int = strtotime( $start );
		$end_int   = strtotime( $end );

		// Starts at midnight and ends 1 second before.
		if (
			( '00:00:00' === gmdate( 'H:i:s', $start_int ) )
			&&
			( '23:59:59' === gmdate( 'H:i:s', $end_int ) )
		) {
			$all_day = true;
		}

		// Return the new start.
		return (bool) $all_day;
	}

	/**
	 * Sanitize a timezone value.
	 *
	 * - it can be empty                     (Floating)
	 * - it can be valid PHP/Olson time zone (America/Chicago)
	 * - it can be UTC offset                (UTC-13)
	 *
	 * @since 2.1.0
	 * @since 3.3.0 Moved to Helpers class.
	 *
	 * @param string $timezone1 First timezone.
	 * @param string $timezone2 Second timezone.
	 * @param string $all_day   Whether the event spans a full day.
	 *
	 * @return string
	 */
	public static function sanitize_timezone( $timezone1 = '', $timezone2 = '', $all_day = false ) {

		// Default return value.
		$retval = $timezone1;

		// All-day events have no time zones.
		if ( ! empty( $all_day ) ) {
			$retval = '';

			// Not all-day, so check time zones.
		} else {

			// Maybe fallback to whatever time zone is not empty.
			$retval = ! empty( $timezone1 )
				? $timezone1
				: $timezone2;
		}

		// Sanitize & return.
		return sugar_calendar_sanitize_timezone( $retval );
	}

	/**
	 * Wrapper for set_time_limit to see if it is enabled.
	 *
	 * @since 3.3.0
	 *
	 * @param int $limit Time limit.
	 */
	public static function set_time_limit( $limit = 0 ) {

		if ( function_exists( 'set_time_limit' ) && false === strpos( ini_get( 'disable_functions' ), 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) { // phpcs:ignore PHPCompatibility.IniDirectives.RemovedIniDirectives.safe_modeDeprecatedRemoved
			@set_time_limit( $limit ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Remove UTF-8 BOM signature if it presents.
	 *
	 * @since 3.3.0
	 *
	 * @param string $str String to process.
	 *
	 * @return string
	 */
	public static function remove_utf8_bom( $str ): string {

		if ( strpos( bin2hex( $str ), 'efbbbf' ) === 0 ) {
			$str = substr( $str, 3 );
		}

		return $str;
	}

	/**
	 * Get the English weekday name by number.
	 *
	 * @since 3.3.0
	 *
	 * @param int $num The number of the weekday.
	 *
	 * @return false|string Returns the English weekday name or `false` if not found.
	 */
	public static function get_english_weekday_by_number( $num ) {

		$weekdays = [
			'Sunday',
			'Monday',
			'Tuesday',
			'Wednesday',
			'Thursday',
			'Friday',
			'Saturday',
		];

		if ( ! empty( $weekdays[ $num ] ) ) {
			return $weekdays[ $num ];
		}

		return false;
	}
}
