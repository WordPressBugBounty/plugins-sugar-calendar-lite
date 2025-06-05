<?php

namespace Sugar_Calendar;

use Sugar_Calendar\Options;
use Sugar_Calendar\Plugin;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Features\Tags\Common\Helpers as TagsHelpers;
use Sugar_Calendar\Helpers as BaseHelpers;

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
	 * @since 3.5.0 Add 'venues` and 'venuesFilter' to the expected data.
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
			'tags'               => [
				'default' => [],
				'type'    => 'array',
			],
			'venues'             => [
				'default' => [],
				'type'    => 'array',
			],
			'calendarsFilter'    => [
				'default' => [],
				'type'    => 'array',
			],
			'tagsFilter'         => [
				'default' => [],
				'type'    => 'array',
			],
			'venuesFilter'       => [
				'default' => [],
				'type'    => 'array',
			],
			'speakers'           => [
				'default' => [],
				'type'    => 'array',
			],
			'speakersFilter'     => [
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
			'appearance'         => [
				'default' => 'light',
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
			'paged'              => [
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
			[
				'name'    => 'blockId',
				'type'    => 'string',
				'default' => '',
			],
			[
				'name'    => 'venues',
				'type'    => 'array_int',
				'default' => [],
			],
			[
				'name'    => 'tags',
				'type'    => 'array_int',
				'default' => [],
			],
			[
				'name'    => 'speakers',
				'type'    => 'array_int',
				'default' => [],
			],
			[
				'name' => 'groupEventsByWeek',
				'type' => 'boolean',
			],
			[
				'name'    => 'eventsPerPage',
				'type'    => 'int',
				'default' => 10,
			],
			[
				'name'    => 'maximumEventsToShow',
				'type'    => 'int',
				'default' => 10,
			],
			[
				'name' => 'showBlockHeader',
				'type' => 'boolean',
			],
			[
				'name' => 'allowUserChangeDisplay',
				'type' => 'boolean',
			],
			[
				'name' => 'showSearch',
				'type' => 'boolean',
			],
			[
				'name' => 'showFilters',
				'type' => 'boolean',
			],
			[
				'name' => 'showDateCards',
				'type' => 'boolean',
			],
			[
				'name' => 'showFeaturedImages',
				'type' => 'boolean',
			],
			[
				'name' => 'showDescriptions',
				'type' => 'boolean',
			],
			[
				'name'    => 'imagePosition',
				'type'    => 'string',
				'default' => 'right',
			],
			[
				'name'    => 'appearance',
				'type'    => 'string',
				'default' => 'light',
			],
		];

		foreach ( $event_list_attr as $attr ) {

			if ( $attr['type'] === 'boolean' ) {

				$sanitized_attributes[ $attr['name'] ] = ! empty( $attributes[ $attr['name'] ] ) && $attributes[ $attr['name'] ] === 'true';

			} elseif ( $attr['type'] === 'string' ) {

				$sanitized_attributes[ $attr['name'] ] = ! empty( $attributes[ $attr['name'] ] )
					? sanitize_text_field( $attributes[ $attr['name'] ] )
					: $attr['default'];

			} elseif ( $attr['type'] === 'int' ) {

				$sanitized_attributes[ $attr['name'] ] = ! empty( $attributes[ $attr['name'] ] )
					? absint( $attributes[ $attr['name'] ] )
					: $attr['default'];
			} elseif ( $attr['type'] === 'array_int' ) {

				$sanitized_attributes[ $attr['name'] ] = ! empty( $attributes[ $attr['name'] ] )
					? array_map( 'absint', $attributes[ $attr['name'] ] )
					: $attr['default'];
			}
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
			return __( 'Date/Time:', 'sugar-calendar-lite' );
		}

		return __( 'Date:', 'sugar-calendar-lite' );
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
				esc_html__( '%1$s - %2$s', 'sugar-calendar-lite' ),
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
			return esc_html__( 'All Day', 'sugar-calendar-lite' );
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
	 * @param string $event_time_type Accepts 'start', 'end', 'recurrence_start', 'recurrence_end'.
	 * @param bool   $output_array    Whether to output an array or not.
	 *
	 * @return string|array
	 */
	public static function get_event_time_output( $event, $format, $event_time_type = 'start', $output_array = false ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		// Default format.
		$time_attr_format = 'Y-m-d\TH:i:s';
		$time_attr_tz     = 'floating';

		if ( $event_time_type === 'end' ) {
			$event_timezone = $event->end_tz;
			$event_time     = $event->end;
		} elseif ( $event_time_type === 'recurrence_end' ) {
			$event_timezone = $event->recurrence_end_tz;
			$event_time     = $event->recurrence_end;
		} elseif ( $event_time_type === 'recurrence_start' && ! empty( $event->parent_event_start ) ) {
			$event_timezone = $event->start_tz;
			$event_time     = $event->parent_event_start;
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
	 * @since 3.4.0 Added support for the 'search' parameter.
	 * @since 3.4.0 Use `$wpdb->prefix` instead of hardcoding 'wp_'.
	 * @since 3.5.0 Added support for the 'venues' parameter.
	 * @since 3.6.0 Changed the method signature to accept an array of arguments.
	 * @since 3.7.0 Fixed issue with not displaying on-going events.
	 * @since 3.7.0 Added support for the 'tags' parameter.
	 * @since 3.7.2 Fixed issue with non-array calendar args.
	 *
	 * @param array $args       The arguments to get the events.
	 * @param array $attributes The block attributes.
	 *
	 * @return Event[]
	 */
	public static function get_upcoming_events_list_with_recurring( $args, $attributes ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$default_args = [
			'number'       => 5,
			'calendar_ids' => [],
			'search'       => '',
			'offset'       => 0,
		];

		$args = wp_parse_args(
			$args,
			$default_args
		);

		$args['number'] = absint( $args['number'] );

		if ( empty( $args['number'] ) ) {
			$args['number'] = $default_args['number'];
		}

		// Include an additional `1` result to check if there are more events.
		++$args['number'];

		$args['offset'] = absint( $args['offset'] );

		if ( empty( $args['offset'] ) ) {
			$args['offset'] = $default_args['offset'];
		}

		/**
		 * Filters the upcoming events list with recurring events.
		 *
		 * @since 3.6.0
		 *
		 * @param Event[]|false $upcoming_events The upcoming events list with recurring events.
		 * @param array         $args            The arguments to get the events.
		 * @param array         $attributes      The block attributes.
		 */
		$upcoming_events = apply_filters(
			'sugar_calendar_helpers_get_upcoming_events_list_with_recurring',
			false,
			$args,
			$attributes
		);

		if ( $upcoming_events !== false ) {
			return $upcoming_events;
		}

		global $wpdb;

		$calendars_left_join = '';
		$where_calendars     = '';

		// Get the category left join and where queries if necessary.
		if ( ! empty( $args['calendar_ids'] ) ) {

			if ( ! is_array( $args['calendar_ids'] ) ) {
				$args['calendar_ids'] = [ $args['calendar_ids'] ];
			}

			$term_taxonomy_ids = array_filter( array_map( 'absint', $args['calendar_ids'] ) );

			if ( ! empty( $term_taxonomy_ids ) ) {
				$calendars_left_join = 'LEFT JOIN ' . $wpdb->term_relationships . ' AS cal_terms ON ' . $wpdb->prefix . 'sc_events.object_id = cal_terms.object_id';
				$where_calendars     = $wpdb->prepare(
					'AND ( cal_terms.term_taxonomy_id IN (%1$s) )',
					implode( ',', $term_taxonomy_ids )
				);
			}
		}

		$select_query = 'SELECT ' . $wpdb->prefix . 'sc_events.id FROM ' . $wpdb->prefix . 'sc_events';

		if ( ! empty( $calendars_left_join ) ) {
			$select_query .= ' ' . $calendars_left_join;
		}

		$now   = sugar_calendar_get_request_time( 'mysql' );
		$today = gmdate( 'Y-m-d 00:00:00', strtotime( $now ) );

		$where_query = $wpdb->prepare(
			'WHERE ' . $wpdb->prefix . 'sc_events.status = "publish" AND ' . $wpdb->prefix . 'sc_events.object_subtype = "sc_event" AND '
			. $wpdb->prefix . 'sc_events.`start` >= %s AND ' . $wpdb->prefix . 'sc_events.`end` >= %s' ,
			$today,
			$now
		);

		if ( ! empty( $where_calendars ) ) {
			$where_query .= ' ' . $where_calendars;
		}

		if ( ! empty( $args['search'] ) ) {
			$where_query .= $wpdb->prepare(
				' AND ' . $wpdb->prefix . 'sc_events.title LIKE %s',
				'%' . $wpdb->esc_like( $args['search'] ) . '%'
			);
		}

		// Add tags filter.
		if ( ! empty( $attributes['tags'] ) ) {

			$tag_ids = array_filter( array_map( 'absint', $attributes['tags'] ) );

			if ( ! empty( $tag_ids ) ) {
				// Change the SELECT query to include tag term taxonomy ID.
				$select_query = 'SELECT ' . $wpdb->prefix . 'sc_events.id, tag_terms.term_taxonomy_id AS tag_term_taxonomy_id FROM ' . $wpdb->prefix . 'sc_events';

				// Add tag JOIN - using standard WordPress term relationships table.
				$tag_taxonomy_id = TagsHelpers::get_tags_taxonomy_id();

				// Create tag-specific join clause.
				$tags_join  = 'INNER JOIN ' . $wpdb->term_relationships . ' AS tag_terms ON ' . $wpdb->prefix . 'sc_events.object_id = tag_terms.object_id';
				$tags_join .= ' INNER JOIN ' . $wpdb->term_taxonomy . ' AS tag_taxonomy ON tag_terms.term_taxonomy_id = tag_taxonomy.term_taxonomy_id';

				// Combine joins - if calendar join exists, append to it, otherwise use tag join.
				if ( ! empty( $calendars_left_join ) ) {
					$combined_join = $calendars_left_join . ' ' . $tags_join;
				} else {
					$combined_join = $tags_join;
				}

				// Update SELECT query with combined JOIN clause.
				$select_query .= ' ' . $combined_join;

				// Add tag WHERE clause - filter by taxonomy and tag IDs.
				$placeholders = implode( ',', array_fill( 0, count( $tag_ids ), '%d' ) );
				$where_query .= $wpdb->prepare(
					' AND tag_taxonomy.taxonomy = %s AND tag_taxonomy.term_id IN (' . $placeholders . ')',
					array_merge( [ $tag_taxonomy_id ], $tag_ids )
				);
			}
		}

		$order_by = $wpdb->prepare(
			'ORDER BY ' . $wpdb->prefix . 'sc_events.start ASC LIMIT %d OFFSET %d',
			$args['number'],
			$args['offset']
		);

		$final_query = $select_query . ' ' . $where_query . ' ' . $order_by;

		// The query below is prepared/sanitized individually.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$event_ids = $wpdb->get_results( $final_query );

		if ( empty( $event_ids ) ) {
			return [];
		}

		$sugar_calendar_events_args = [
			'id__in'  => wp_list_pluck( $event_ids, 'id' ),
			'orderby' => 'start',
			'order'   => 'ASC',
		];

		return sugar_calendar_get_events( $sugar_calendar_events_args );
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

	/**
	 * Get the no events message for the legacy event list.
	 *
	 * @since 3.4.0
	 *
	 * @param string $display The display type. This can be 'all', 'upcoming', or 'past'. Default 'all'.
	 *
	 * @return string
	 */
	public static function get_no_events_message_for_legacy_event_list( $display = 'all' ) {

		$no_events_message = __( 'There are no events to display.', 'sugar-calendar-lite' );

		switch ( $display ) {
			case 'upcoming':
				$no_events_message = __( 'There are no upcoming events to display.', 'sugar-calendar-lite' );
				break;

			case 'past':
				$no_events_message = __( 'There are no past events to display.', 'sugar-calendar-lite' );
				break;
		}

		return $no_events_message;
	}

	/**
	 * Get coordinates from an address using Google Maps API.
	 *
	 * @since 3.5.0
	 *
	 * @param string $address       Address to geocode.
	 * @param bool   $force_refresh Whether to force a refresh of the coordinates.
	 *
	 * @return array|false
	 */
	public static function get_coordinates_from_address( $address, $force_refresh = false ) {

		$features            = Plugin::instance()->get_common_features();
		$feature_google_maps = $features->get_feature( 'GoogleMaps' );

		return $feature_google_maps->get_coordinates( $address, $force_refresh );
	}

	/**
	 * Verify the Google Maps API key.
	 *
	 * @since 3.5.0
	 *
	 * @param string $api_key The API key to verify.
	 *
	 * @return array
	 */
	public static function verify_google_maps_api_key( $api_key ) {

		$features            = Plugin::instance()->get_common_features();
		$feature_google_maps = $features->get_feature( 'GoogleMaps' );

		return $feature_google_maps->verify_api_key( $api_key );
	}

	/**
	 * Return the Google Maps API Key from options.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public static function get_google_maps_api_key() {

		return Options::get( 'maps_google_api_key', '' );
	}

	/**
	 * Load compatibility hooks.
	 *
	 * @since 3.5.1
	 *
	 * @return void
	 */
	public static function load_compatibility_hooks() {

		// Detect if ACF is active.
		if ( function_exists( 'acf' ) ) {

			// Load compatibility script.
			add_action( 'admin_enqueue_scripts', [ self::class, 'load_compatibility_script_acf' ], 999 );
		}
	}

	/**
	 * Load compatibility script.
	 *
	 * @since 3.5.1
	 *
	 * @return void
	 */
	public static function load_compatibility_script_acf() {

		wp_register_script(
			'sugar-calendar-admin-compatibility-acf',
			SC_PLUGIN_ASSETS_URL . 'js/compatibility/sc-compatibility-acf' . WP::asset_min() . '.js',
			[ 'jquery' ],
			BaseHelpers::get_asset_version(),
			true
		);

		wp_enqueue_script( 'sugar-calendar-admin-compatibility-acf' );
	}

	/**
	 * Get the version used in our assets.
	 *
	 * @since 3.6.0
	 *
	 * @return int|string
	 */
	public static function get_asset_version() {

		if ( defined( 'SC_SCRIPT_DEBUG' ) && SC_SCRIPT_DEBUG ) {
			return time();
		}

		return SC_PLUGIN_VERSION;
	}

	/**
	 * Get the weekday abbreviation.
	 *
	 * @since 3.6.0
	 *
	 * @param \DateTime $day Datetime object.
	 *
	 * return string
	 */
	public static function get_weekday_abbrev( $day ) {

		global $wp_locale;

		$weekday_abbrev = $day->format( 'D' );

		if (
			isset( $wp_locale->weekday[ $day->format( 'w' ) ] ) &&
			isset( $wp_locale->weekday_abbrev[ $wp_locale->weekday[ $day->format( 'w' ) ] ] )
		) {
			$weekday_abbrev = $wp_locale->weekday_abbrev[ $wp_locale->weekday[ $day->format( 'w' ) ] ];
		}

		/**
		 * Filters the weekday abbreviation.
		 *
		 * @since 3.6.0
		 *
		 * @param string    $weekday_abbrev The weekday abbreviation.
		 * @param \DateTime $day            Datetime object.
		 */
		return apply_filters(
			'sugar_calendar_helpers_get_weekday_abbrev',
			$weekday_abbrev,
			$day
		);
	}

	/**
	 * Get the event slug.
	 *
	 * @since 3.6.1
	 *
	 * @return string
	 */
	public static function get_event_slug() {

		if (
			defined( 'SC_EVENTS_SLUG' ) &&
			! empty( SC_EVENTS_SLUG )
		) {
			$slug = SC_EVENTS_SLUG;
		} else {
			$slug = 'events';
		}

		return $slug;
	}

	/**
	 * Check if an event exists.
	 *
	 * @param int $event_id Event ID.
	 *
	 * @return bool
	 */
	public static function event_exists( $event_id ) {

		global $wpdb;

		$cache_key = 'sc_event_exists_' . $event_id;
		$exists    = wp_cache_get( $cache_key, 'sugar_calendar' );

		if ( $exists !== false ) {
			return (bool) $exists;
		}

		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(id) FROM ' . $wpdb->prefix . 'sc_events WHERE id = %d',
				absint( $event_id )
			)
		);

		wp_cache_set( $cache_key, $exists, 'sugar_calendar' );

		return (bool) $exists;
	}

}
