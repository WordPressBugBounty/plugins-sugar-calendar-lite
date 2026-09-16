<?php

namespace Sugar_Calendar\Ics;

use DateTimeZone;
use Exception;

/**
 * Format-only iCalendar serializer.
 *
 * @since 3.14.0
 */
class Renderer {

	/**
	 * Array of data brought in during construction.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	private $data = null;

	/**
	 * The timezone of the calendar.
	 *
	 * This is likely derived from a taxonomy term name or site setting.
	 *
	 * @since 3.0.0
	 *
	 * @var string
	 */
	protected $timezone = false;

	/**
	 * The array of events to add to this calendar.
	 *
	 * These are likely derived from a taxonomy term or archive query.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	protected $events = [];

	/**
	 * The array of lines to render for this calendar.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	protected $lines = [];

	/**
	 * The rendered contents of this calendar.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	protected $render = '';

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Calendar data.
	 */
	public function __construct( $args = [] ) {

		if ( ! empty( $args ) ) {
			$this->init( $args );
		}
	}

	/**
	 * Initialize.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Calendar data.
	 */
	public function init( $args = [] ) {

		$r = wp_parse_args(
			$args,
			[
				'name'   => '',
				'desc'   => '',
				'events' => [],
			]
		);

		$this->data = new \stdClass(); // phpcs:ignore WPForms.PHP.BackSlash.RemoveBackslash

		// Set the timezone early, so Events can use them.
		$this->set_timezone();

		foreach ( $r as $key => $value ) {
			$this->data->{$key} = $value;

			if ( is_callable( [ $this, 'set_' . $key ] ) ) {
				call_user_func( [ $this, 'set_' . $key ], $value );
			}
		}
	}

	/** Events ****************************************************************/

	/**
	 * Add an event to this calendar.
	 *
	 * @since 3.0.0
	 * @since 3.11.1 Take into account advanced_recurring.
	 *
	 * @param array $args Event details.
	 *
	 * @return array An array of event details, including any generated UID.
	 */
	public function add_event( $args = [] ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		$r = wp_parse_args(
			$args,
			[
				'id'                  => 0,
				'status'              => '',
				'start'               => false,
				'start_tz'            => '',
				'end'                 => false,
				'end_tz'              => '',
				'all_day'             => false,
				'summary'             => '',
				'description'         => '',
				'url'                 => '',
				'uid'                 => md5( uniqid( mt_rand(), true ) ) . '@SugarCalendar', // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- Preserve exact Pro serializer UID behavior.
				'location'            => '',
				'created'             => false,
				'modified'            => false,
				'recurrence'          => '',
				'recurrence_end'      => false,
				'recurrence_interval' => 0,
				'recurrence_count'    => 0,
			]
		);

		// Format status.
		$status = ( $r['status'] === 'publish' )
			? 'CONFIRMED'
			: 'TENTATIVE';

		// All-day.
		$all_day = ! empty( $r['all_day'] );

		// Start & End - include times if not all-day.
		if ( $all_day === false ) {
			$start = gmdate( 'Ymd', $r['start'] ) . 'T' . gmdate( 'His', $r['start'] );
			$end   = gmdate( 'Ymd', $r['end'] ) . 'T' . gmdate( 'His', $r['end'] );
		} else {
			// Only include dates if all-day.
			$start = gmdate( 'Ymd', $r['start'] );
			$end   = gmdate( 'Ymd', $r['end'] );
		}

		// Format created & modified.
		$created  = gmdate( 'Ymd', $r['created'] ) . 'T' . gmdate( 'His', $r['created'] );
		$modified = gmdate( 'Ymd', $r['modified'] ) . 'T' . gmdate( 'His', $r['modified'] );

		// Get the timezone.
		$timezone = $this->get_timezone();

		// Start Timezone.
		$start_tz = ! empty( $r['start_tz'] )
			? $r['start_tz']
			: $timezone->getName();

		// End Timezone.
		$end_tz = ! empty( $r['end_tz'] )
			? $r['end_tz']
			: $timezone->getName();

		// Description (do not wrap in quotes).
		$description = ! empty( $r['description'] )
			? $this->esc_text( $r['description'] )
			: '';

		// Sumary (do not wrap in quotes).
		$summary = ! empty( $r['summary'] )
			? $this->esc_text( $r['summary'] )
			: '';

		// Url.
		$url = ! empty( $r['url'] )
			? esc_url_raw( $r['url'] )
			: '';

		// UID.
		$uid = ! empty( $r['uid'] )
			? $this->esc_text( $r['uid'] )
			: '';

		// Location.
		$location = ! empty( $r['location'] )
			? $this->esc_text( $r['location'] )
			: '';

		// Recurrence.
		$recurrence = ! empty( $r['recurrence'] )
			? strtoupper( $this->esc_text( $r['recurrence'] ) )
			: '';

		// Recurrence End.
		$recurrence_end = ! empty( $r['recurrence_end'] )
			? gmdate( 'Ymd', $r['recurrence_end'] ) . 'T' . gmdate( 'His', $r['recurrence_end'] ) . 'Z'
			: '';

		// Recurrence Interval.
		$recurrence_interval = ! empty( $r['recurrence_interval'] )
			? absint( $r['recurrence_interval'] )
			: 0; // Empty assumes 1 if $recurrence is non-empty.

		// Recurrence Count.
		$recurrence_count = ! empty( $r['recurrence_count'] )
			? absint( $r['recurrence_count'] )
			: 0; // Forever.

		// Advanced recurring meta.
		$advanced_recurring = ! empty( $r['advanced_recurring'] )
			? (array) $r['advanced_recurring']
			: [];

		// Setup event.
		$event = [
			'id'                  => $r['id'],
			'status'              => $status,
			'start'               => $start,
			'start_tz'            => $start_tz,
			'end'                 => $end,
			'end_tz'              => $end_tz,
			'all_day'             => $all_day,
			'summary'             => $summary,
			'description'         => $description,
			'url'                 => $url,
			'uid'                 => $uid,
			'location'            => $location,
			'created'             => $created,
			'modified'            => $modified,
			'recurrence'          => $recurrence,
			'recurrence_end'      => $recurrence_end,
			'recurrence_interval' => $recurrence_interval,
			'recurrence_count'    => $recurrence_count,
			'advanced_recurring'  => $advanced_recurring,
		];

		// Add event to events array.
		$this->events[] = $event;

		// Return this event.
		return $event;
	}

	/**
	 * Get the array of events.
	 *
	 * @since 3.0.0
	 *
	 * @return array The array of events.
	 */
	public function get_events() {

		return $this->events;
	}

	/**
	 * Reset events back to empty.
	 *
	 * @since 3.0.0
	 */
	public function reset_events() {

		$this->events = [];
	}

	/**
	 * Setup events to render.
	 *
	 * @since 3.0.0
	 *
	 * @param array $events Events to render.
	 */
	public function set_events( $events = [] ) {

		if ( empty( $events ) ) {
			return;
		}

		foreach ( $events as $event ) {
			$args = is_array( $event )
				? $event
				: EventData::from_event( $event );

			if ( ! empty( $args ) ) {
				$this->add_event( $args );
			}
		}
	}

	/**
	 * Get the earliest date from the array of events.
	 *
	 * Used by render_timezones() to get the earliest possible datetime of the
	 * Events in this calendar.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_earliest_timestamp() {

		$events = $this->get_events();

		if ( empty( $events ) ) {
			return false;
		}

		$starts = wp_list_pluck( $events, 'start' );
		$ends   = wp_list_pluck( $events, 'end' );
		$dates  = array_merge( $starts, $ends );

		// Get the smallest date.
		return min( array_map( 'strtotime', $dates ) );
	}

	/**
	 * Get the latest date from the array of events.
	 *
	 * Used by render_timezones() to get the latest possible datetime of the
	 * Events in this calendar.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_latest_timestamp() {

		$events = $this->get_events();

		if ( empty( $events ) ) {
			return false;
		}

		$starts = wp_list_pluck( $events, 'start' );
		$ends   = wp_list_pluck( $events, 'end' );
		$dates  = array_merge( $starts, $ends );

		// Return the smallest date.
		return max( array_map( 'strtotime', $dates ) );
	}

	/**
	 * Get all time zones from array of events.
	 *
	 * Used by render_timezones() to get the DateTimeZone objects of the Events
	 * in this calendar.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function get_all_timezones() {

		$events = $this->get_events();

		if ( empty( $events ) ) {
			return false;
		}

		$starts = wp_list_pluck( $events, 'start_tz' );
		$ends   = wp_list_pluck( $events, 'end_tz' );
		$zones  = array_filter( array_unique( array_merge( $starts, $ends ) ) );

		// Manual offsets such as "UTC+5.5" are not PHP time zones; skipping them
		// keeps render_timezones() from calling getName() on `false`.
		$retval = [];

		foreach ( $zones as $zone ) {
			$timezone = $this->resolve_zone( $zone );

			if ( $timezone !== null ) {
				$retval[] = $timezone;
			}
		}

		// Add the primary time zone to the beginning.
		array_unshift( $retval, $this->get_timezone() );

		// Return the time zones.
		return $retval;
	}

	/** Name ******************************************************************/

	/**
	 * Get the name of the calendar.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_name() {

		return $this->data->name;
	}

	/**
	 * Set the name of the calendar.
	 *
	 * @since 3.0.0
	 *
	 * @param string $name Calendar name.
	 */
	public function set_name( $name = '' ) {

		$this->data->name = wp_strip_all_tags( $name );
	}

	/** Description ***********************************************************/

	/**
	 * Get the description of the calendar.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_desc() {

		return $this->data->desc;
	}

	/**
	 * Set the description of the calendar.
	 *
	 * @since 3.0.0
	 *
	 * @param string $desc Calendar description.
	 */
	public function set_desc( $desc = '' ) {

		$this->data->desc = wp_strip_all_tags( $desc );
	}

	/** Timezone **************************************************************/

	/**
	 * Get the timezone.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	private function get_timezone() {

		return $this->timezone;
	}

	/**
	 * Set the timezone.
	 *
	 * @since 3.0.0
	 *
	 * @param string $timezone The timezone to use.
	 */
	private function set_timezone( $timezone = '' ) {

		// Get site settings (will use term meta eventually!
		$tzstring = get_option( 'timezone_string', $timezone );
		$offset   = get_option( 'gmt_offset' );

		/**
		 * Discourage manual offset
		 *
		 * IANA timezone database that provides PHP's timezone support uses
		 * (i.e. reversed) POSIX style signs
		 *
		 * @see http://us.php.net/manual/en/timezones.others.php
		 * @see https://bugs.php.net/bug.php?id=45543
		 * @see https://bugs.php.net/bug.php?id=45528
		 */
		if ( empty( $tzstring ) && ( $offset !== 0 ) && ( floor( $offset ) === $offset ) ) {

			// Make the offset string.
			$offset_st = ( $offset > 0 )
				? "-{$offset}"
				: '+' . absint( $offset );

			// Make the Unknown timezone string.
			$tzstring = "Etc/GMT{$offset_st}";
		}

		// Issue with the timezone selected, set to 'UTC'.
		if ( empty( $tzstring ) ) {
			$tzstring = 'UTC';
		}

		// Set the timezone.
		$this->timezone = new \DateTimeZone( $tzstring ); // phpcs:ignore WPForms.PHP.BackSlash.RemoveBackslash
	}

	/**
	 * Open a time zone, or null when PHP does not know it.
	 *
	 * Manual offsets such as "UTC+5.5" arrive from event meta and can have no
	 * VTIMEZONE, so callers emit floating time for them instead.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $zone Time zone identifier.
	 *
	 * @return DateTimeZone|null
	 */
	private function resolve_zone( $zone ) {

		try {
			return new DateTimeZone( (string) $zone );
		} catch ( Exception $error ) {
			return null;
		}
	}

	/** Lines *****************************************************************/

	/**
	 * Add a line to the lines array.
	 *
	 * @since 3.0.0
	 *
	 * @param string $contents Line contents.
	 */
	private function add_line( $contents = '' ) {

		if ( empty( $contents ) || ! is_string( $contents ) ) {
			return;
		}

		array_push( $this->lines, $contents );
	}

	/**
	 * Return the lines.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function get_lines() {

		return $this->lines;
	}

	/**
	 * Reset the lines back to empty.
	 *
	 * @since 3.0.0
	 */
	public function reset_lines() {

		$this->lines  = [];
		$this->render = '';
	}

	/**
	 * Split lines that are longer than 75 characters.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	private function fold_lines() {

		$retval = '';
		$lines  = $this->get_lines();

		if ( empty( $lines ) ) {
			return $retval;
		}

		$folded_lines = [];

		foreach ( $lines as $line ) {

			// Maximum length is 75, minus leading space and CLRF, equalling 72.
			$split = str_split( $line, 72 );

			// Skip if not longer than 72.
			if ( count( $split ) === 1 ) {
				array_push( $folded_lines, $line );
				continue;
			}

			// Loop through split lines.
			foreach ( $split as $key => $new_line ) {

				// Pad with a single space.
				if ( ! empty( $key ) ) {

					// What kind of space to use.
					$space    = ' ';
					$length   = strlen( $new_line ) + strlen( $space );
					$new_line = str_pad( $new_line, $length, $space, STR_PAD_LEFT );
				}

				// Push line to the end of the return value array.
				array_push( $folded_lines, $new_line );
			}
		}

		return implode( "\r\n", $folded_lines );
	}

	/** Render ****************************************************************/

	/**
	 * Render and optionally output a VCAL string.
	 *
	 * @since 3.0.0
	 *
	 * @param bool $output Whether to output the calendar data.
	 *
	 * @return string The complete rendered VLAL
	 */
	public function render( $output = true ) {

		$this->reset_lines();
		$this->render_begin();
		$this->render_timezones();
		$this->render_events();
		$this->render_end();

		// Fold lines that are longer than 75 characters.
		$this->render = $this->fold_lines();

		// Maybe output.
		if ( $output === true ) {
			$this->output( $this->render );
		}

		return $this->render;
	}

	/**
	 * Render the VBEGIN.
	 *
	 * @since 3.0.0
	 */
	private function render_begin() {

		// Get the ICS name.
		$name = $this->esc_text( $this->get_name() );
		$desc = $this->esc_text( $this->get_desc() );

		// Define company & software.
		$company  = $this->esc_text( 'WPForms' );
		$software = $this->esc_text( 'Sugar Calendar Feeds v' . SC_PLUGIN_VERSION );

		// Add header.
		$this->add_line( 'BEGIN:VCALENDAR' );
		$this->add_line( 'METHOD:PUBLISH' );
		$this->add_line( 'VERSION:2.0' );
		$this->add_line( 'CALSCALE:GREGORIAN' );
		$this->add_line( sprintf( 'PRODID:-//NONSGML %s//NONSGML %s//EN', $company, $software ) );
		$this->add_line( sprintf( 'X-WR-CALNAME:%s', $name ) );
		$this->add_line( sprintf( 'X-WR-CALDESC:%s', $desc ) );

		// Get the timezone.
		$timezone = $this->get_timezone();

		// Maybe append the timezon.
		if ( ! empty( $timezone ) ) {
			$this->add_line( sprintf( 'X-WR-TIMEZONE:%s', $timezone->getName() ) );
		}
	}

	/**
	 * Render the VEND.
	 *
	 * @since 3.0.0
	 */
	private function render_end() {

		$this->add_line( 'END:VCALENDAR' );
	}

	/**
	 * Render the VEVENTs.
	 *
	 * @since 3.0.0
	 * @since 3.11.1 Added advanced recurrence ICS rule.
	 */
	private function render_events() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.NestingLevel.MaxExceeded -- Preserve exact Pro serializer output.

		$events = $this->get_events();

		if ( ! empty( $events ) ) {

			$now = gmdate( 'Ymd' ) . 'T' . gmdate( 'His' ) . 'Z';

			// Add events to return val.
			foreach ( $events as $event ) {

				$this->add_line( 'BEGIN:VEVENT' );
				$this->add_line( sprintf( 'SUMMARY:%s', $event['summary'] ) );
				$this->add_line( sprintf( 'DESCRIPTION:%s', $event['description'] ) );
				$this->add_line( sprintf( 'URL;VALUE=URI:%s', $event['url'] ) );

				// UID.
				$this->add_line( sprintf( 'UID:%s', $event['uid'] ) );

				// STATUS.
				$this->add_line( sprintf( 'STATUS:%s', $event['status'] ) );

				// ORGANIZER (required, but validates even when empty).
				$this->add_line( sprintf( 'ORGANIZER:%s', '' ) );

				// DTSTAMP.
				$this->add_line( sprintf( 'DTSTAMP:%s', $now ) );

				// Not all-day.
				if ( empty( $event['all_day'] ) ) {

					// START. A zone without a VTIMEZONE is written as floating time.
					$this->add_line(
						$this->resolve_zone( $event['start_tz'] ) === null
							? sprintf( 'DTSTART:%s', $event['start'] )
							: sprintf( 'DTSTART;TZID=%s:%s', $event['start_tz'], $event['start'] )
					);

					// END.
					if ( ! empty( $event['end'] ) ) {
						$this->add_line(
							$this->resolve_zone( $event['end_tz'] ) === null
								? sprintf( 'DTEND:%s', $event['end'] )
								: sprintf( 'DTEND;TZID=%s:%s', $event['end_tz'], $event['end'] )
						);
					}
				} else {
					// All-day.

					// START.
					$this->add_line( sprintf( 'DTSTART;VALUE=DATE:%s', $event['start'] ) );

					// END if not the same as START.
					if ( $event['start'] !== $event['end'] ) {
						$this->add_line( sprintf( 'DTEND;VALUE=DATE:%s', $event['end'] ) );
					}
				}

				// Recurrence.
				if ( ! empty( $event['recurrence'] ) ) {
					$rrule = sprintf( 'RRULE:FREQ=%s', $event['recurrence'] );

					// Recurrence Interval.
					if ( ! empty( $event['recurrence_interval'] ) ) {
						$rrule .= sprintf( ';INTERVAL=%s', $event['recurrence_interval'] );
					} else {
						$rrule .= ';INTERVAL=1';
					}

					// Recurrence Count.
					if ( ! empty( $event['recurrence_count'] ) ) {
						$rrule .= sprintf( ';COUNT=%s', $event['recurrence_count'] );

					// Recurrence End.
					} elseif ( ! empty( $event['recurrence_end'] ) ) {
						$rrule .= sprintf( ';UNTIL=%s', $event['recurrence_end'] );
					}

					// Advanced recurrence components.
					$adv = $event['advanced_recurring'];

					// BYDAY (e.g., MO,WE,FR or FR).
					if ( ! empty( $adv['recurrence_byday'] ) ) {
						$rrule .= sprintf( ';BYDAY=%s', sanitize_text_field( $adv['recurrence_byday'] ) );
					}

					// BYSETPOS (e.g., 4 for "fourth", -1 for "last").
					if ( ! empty( $adv['recurrence_bypos'] ) ) {
						$rrule .= sprintf( ';BYSETPOS=%s', intval( $adv['recurrence_bypos'] ) );
					}

					// BYMONTHDAY (e.g., 1,15).
					if ( ! empty( $adv['recurrence_bymonthday'] ) ) {
						$rrule .= sprintf( ';BYMONTHDAY=%s', sanitize_text_field( $adv['recurrence_bymonthday'] ) );
					}

					// BYMONTH (e.g., 1,6).
					if ( ! empty( $adv['recurrence_bymonth'] ) ) {
						$rrule .= sprintf( ';BYMONTH=%s', sanitize_text_field( $adv['recurrence_bymonth'] ) );
					}

					$this->add_line( $rrule );
				}

				// LOCATION.
				if ( ! empty( $event['location'] ) ) {
					$this->add_line( sprintf( 'LOCATION:%s', $event['location'] ) );
				}

				// END.
				$this->add_line( 'END:VEVENT' );
			}
		}
	}

	/**
	 * Render VTIMEZONEs.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	private function render_timezones() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh, Generic.Metrics.NestingLevel.MaxExceeded -- Preserve exact Pro serializer output.

		// $timezone->getTransitions() doesn't accept any arguments in php 5.2,
		// and would be ineffecient
		if ( version_compare( PHP_VERSION, '5.3.0' ) < 0 ) {
			return '';
		}

		// Get time zones.
		$timezones = $this->get_all_timezones();

		if ( empty( $timezones ) ) {
			return '';
		}

		$from = $this->get_earliest_timestamp();
		$to   = $this->get_latest_timestamp();

		foreach ( $timezones as $tz ) {

			// BEGIN.
			$this->add_line( 'BEGIN:VTIMEZONE' );

			// TZID.
			$this->add_line( sprintf( 'TZID:%s', $tz->getName() ) );

			// Get all transitions, and (as an estimate) an early one which we skip.
			$transitions = $tz->getTransitions( intval( $from - YEAR_IN_SECONDS / 2 ), (int) $to );

			if ( empty( $transitions ) ) {
				continue;
			}

			// Loop through transitions.
			foreach ( $transitions as $i => $trans ) {

				// Determine offset.
				$pm = ( $trans['offset'] >= 0 )
					? '+'
					: '-';

				$hours   = floor( absint( $trans['offset'] ) / HOUR_IN_SECONDS ) % 24;
				$minutes = ( absint( $trans['offset'] ) - $hours * HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS;
				$tzto    = $pm . str_pad( $hours, 2, '0', STR_PAD_LEFT ) . str_pad( $minutes, 2, '0', STR_PAD_LEFT );

				// Skip the first entry, we just want it for the TZOFFSETFROM value
				// of the next one.
				if ( $i === 0 ) {
					$tzfrom = $tzto;

					if ( count( $transitions ) > 1 ) {
						continue;
					}
				}

				$type = ! empty( $trans['isdst'] )
					? 'DAYLIGHT'
					: 'STANDARD';

				// Get the DateTime with the Timezone.
				$dt = new \DateTime( $trans['time'], $tz ); // phpcs:ignore WPForms.PHP.BackSlash.RemoveBackslash

				// BEGIN.
				$this->add_line( sprintf( 'BEGIN:%s', $type ) );
				$this->add_line( sprintf( 'TZOFFSETFROM:%s', $tzfrom ) );
				$this->add_line( sprintf( 'TZOFFSETTO:%s', $tzto ) );
				$this->add_line( sprintf( 'DTSTART:%s', $dt->format( 'Ymd\THis' ) ) );
				$this->add_line( sprintf( 'TZNAME:%s', $trans['abbr'] ) );
				$this->add_line( sprintf( 'END:%s', $type ) );
				$tzfrom = $tzto;
			}

			$this->add_line( 'END:VTIMEZONE' );
		}
	}

	/**
	 * Escape text for use in ICS.
	 *
	 * Escape backslashes, commas, semicolons, and newlines as RFC 5545 TEXT.
	 *
	 * @since 3.0.0
	 *
	 * @param string $string Text to escape.
	 *
	 * @return string
	 */
	private function esc_text( $string = '' ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.stringFound -- Preserve legacy private signature.

		$strip = wp_strip_all_tags( $string );

		// Escape literal backslashes before introducing any TEXT escape sequences.
		$slash    = str_replace( '\\', '\\\\', $strip );
		$comma    = str_replace( ',',  '\,', $slash );
		$colon    = str_replace( ';',  '\;', $comma );
		$newline  = str_replace( "\n", "\\n", $colon );
		$linefeed = str_replace( "\r", '', $newline );

		// Normalize the return value.
		$retval = $linefeed;

		return $retval;
	}

	/**
	 * Output the contents of the file, sending the correct headers.
	 *
	 * @since 3.0.0
	 *
	 * @param string $contents Serialized ICS contents.
	 */
	private function output( $contents = '' ) {

		$file = sanitize_file_name( $this->get_name() . '.ics' );

		// Download flag will force a download.
		$download = ! empty( $_GET['download'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only output mode.

		// Type.
		$type = ( $download === true )
			? 'text/calendar'
			: 'text/plain';

		// Disposition.
		$disposition = ( $download === true )
			? 'attachment'
			: 'inline';

		// Always sent content type header.
		header( "Content-type: {$type}; charset=utf-8" );

		// Always send disposition header.
		header( "Content-Disposition: {$disposition}; filename=\"{$file}\"" );

		echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already serialized ICS.
	}
}
