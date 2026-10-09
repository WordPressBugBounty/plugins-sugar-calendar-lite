<?php
/**
 * Shared input schema and normalizer for the event write abilities.
 *
 * @since 3.14.0
 */

namespace Sugar_Calendar\Integrations\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Sugar_Calendar\Helpers;
use WP_Error;

/**
 * EventInputSchema — builds the JSON Schema shared by create-event and
 * update-event, and normalizes validated input into the shape SCE stores.
 * Runs no query of its own, and every WordPress call it makes is stubbable, so
 * it is unit-testable without a database.
 *
 * @since 3.14.0
 */
class EventInputSchema {

	/**
	 * Longest accepted title, in characters.
	 *
	 * @since 3.14.0
	 *
	 * @var int
	 */
	const TITLE_MAX = 200;

	/**
	 * Longest accepted description, in characters.
	 *
	 * The storage ceiling: the post_content column is a MySQL TEXT.
	 *
	 * @since 3.14.0
	 *
	 * @var int
	 */
	const DESCRIPTION_MAX = 65535;

	/**
	 * Longest accepted location, in characters.
	 *
	 * @since 3.14.0
	 *
	 * @var int
	 */
	const LOCATION_MAX = 500;

	/**
	 * Longest accepted excerpt, in characters.
	 *
	 * The storage ceiling: `wp_posts.post_excerpt` is a MySQL TEXT.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	const EXCERPT_MAX = 65535;

	/**
	 * Most term ids accepted in one calendar or tag list.
	 *
	 * @since 3.14.0
	 *
	 * @var int
	 */
	const TERM_IDS_MAX = 50;

	/**
	 * Input keys normalize() itself consumes into `row`/`meta`/`terms`.
	 *
	 * Anything else the caller sent — a contributor's own schema property —
	 * rides through untouched in normalize()'s `extra` bucket.
	 *
	 * @since 3.14.0
	 *
	 * @var string[]
	 */
	const KNOWN_KEYS = [
		'title',
		'description',
		'excerpt',
		'location',
		'start',
		'end',
		'all_day',
		'timezone',
		'calendar_ids',
		'tag_ids',
	];

	/**
	 * Build the input properties for an event write ability.
	 *
	 * @since 3.14.0
	 *
	 * @param bool $partial Whether this is a patch (update) rather than a create.
	 *                      Each ability sets its own `required` list around these
	 *                      properties; this method never sets one.
	 *
	 * @return array
	 */
	public static function properties( bool $partial = false ): array {

		$properties = [
			'title'        => [
				'type'        => 'string',
				'minLength'   => 1,
				'maxLength'   => self::TITLE_MAX,
				'description' => __( 'The event name shown everywhere, for example Spring Concert.', 'sugar-calendar-lite' ),
			],
			'description'  => [
				'type'        => 'string',
				'maxLength'   => self::DESCRIPTION_MAX,
				'description' => __( 'The full event text shown on the event page. HTML is allowed.', 'sugar-calendar-lite' ),
			],
			'excerpt'      => [
				'type'        => 'string',
				'maxLength'   => self::EXCERPT_MAX,
				'description' => __( 'A short summary of the event, separate from the full description.', 'sugar-calendar-lite' ),
			],
			'start'        => [
				'type'        => 'string',
				'minLength'   => 10,
				'description' => __( 'Local start date and time: 2026-09-14T18:00:00, 2026-09-14 18:00:00, or 2026-09-14 on its own for an all-day event. A UTC offset or a trailing Z is refused; name the zone in the timezone field instead.', 'sugar-calendar-lite' ),
			],
			'end'          => [
				'type'        => 'string',
				'minLength'   => 10,
				'description' => __( 'Local end date and time, in the same formats as start. A UTC offset or a trailing Z is refused; name the zone in the timezone field instead.', 'sugar-calendar-lite' ),
			],
			'all_day'      => [
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'True for an event with no start or end time. start and end are then dates only, for example 2026-09-14. Default false.', 'sugar-calendar-lite' ),
			],
			'timezone'     => [
				'type'        => 'string',
				'description' => __( 'A city-based IANA time zone such as America/Chicago or Europe/Kyiv. Leave it out to follow the site\'s time zone. Not stored for all-day events.', 'sugar-calendar-lite' ),
			],
			'calendar_ids' => [
				'type'        => 'array',
				'maxItems'    => self::TERM_IDS_MAX,
				'uniqueItems' => true,
				'items'       => [
					'type'    => 'integer',
					'minimum' => 1,
				],
				'description' => __( 'The event\'s whole calendar list, for example [3]: an update replaces it, so to add a calendar send the ones the event already has plus the new one. Get them from sc-events/list-calendars; never guess an id. Leave it out to keep the current calendars.', 'sugar-calendar-lite' ),
			],
			'tag_ids'      => [
				'type'        => 'array',
				'maxItems'    => self::TERM_IDS_MAX,
				'uniqueItems' => true,
				'items'       => [
					'type'    => 'integer',
					'minimum' => 1,
				],
				'description' => __( 'The event\'s whole tag list, for example [7, 9]: an update replaces it, so to add a tag send the tag_ids the event already has plus the new one. Get ids from sc-events/list-tags and the current ones from the event; never guess an id.', 'sugar-calendar-lite' ),
			],
			'location'     => [
				'type'        => 'string',
				'maxLength'   => self::LOCATION_MAX,
				'description' => __( 'Free-text place or address shown on the event, for example Central Library, Room 2.', 'sugar-calendar-lite' ),
			],
		];

		// Offered only once a mode exists: with only `none` in the enum the property could
		// carry nothing useful, which tells the model nothing.
		$enum = SignupModes::enum();

		if ( count( $enum ) > 1 ) {
			$properties['signup'] = [
				'type'        => 'string',
				'enum'        => $enum,
				'description' => trim(
					sprintf(
						/* translators: %s: sentence naming each signup mode's detail object. */
						__( 'How people sign up for this event. One method only: an event cannot take both paid tickets and RSVPs. %s', 'sugar-calendar-lite' ),
						self::signup_modes_description()
					)
				),
			];

			// No default on the patch variant: a default would make every update
			// assert a signup method the caller never mentioned.
			if ( ! $partial ) {
				$properties['signup']['default'] = SignupModes::NONE;
			}
		}

		/**
		 * Filters the event write abilities' input properties.
		 *
		 * Add-ons widen the schema with their own slice (venue, speakers,
		 * ticketing, RSVP, registration form). Must be hooked so it runs
		 * during `wp_abilities_api_init`: a property added later is invisible
		 * to callers, because the schema is captured at registration.
		 *
		 * A property added here that `normalize()`'s whitelist does not also
		 * consume rides through untouched as `$normalized['extra'][ $key ]`,
		 * which `EventWriter` hands to the `sugar_calendar_events_event_writer_after_write`
		 * action — that is the seam a contributor reads its own input from.
		 *
		 * Omit a property entirely when the site cannot use it (e.g. a
		 * Pro-only slice on Lite) so the model never proposes it. That is a
		 * UX affordance, not a security boundary — keep the server-side gate.
		 *
		 * @since 3.14.0
		 *
		 * @param array $properties JSON Schema properties so far.
		 * @param bool  $partial    Whether this is the update (patch) variant.
		 */
		$properties = apply_filters( 'sugar_calendar_integrations_abilities_event_input_schema', $properties, $partial );

		return is_array( $properties ) ? $properties : [];
	}

	/**
	 * Build the sentence naming each signup mode's detail object.
	 *
	 * Reads `SignupModes::available()` so the sentence never hard-codes an add-on's
	 * name: a mode with no detail property is skipped, since it has nothing to name.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	private static function signup_modes_description(): string {

		$names = [];

		foreach ( SignupModes::available() as $slug => $property ) {

			if ( $property === '' ) {
				continue;
			}

			$names[] = sprintf(
				/* translators: 1: signup mode slug, 2: the property its settings go in. */
				__( '%1$s settings in %2$s', 'sugar-calendar-lite' ),
				$slug,
				$property
			);
		}

		if ( empty( $names ) ) {
			return '';
		}

		return sprintf(
			/* translators: %s: comma-separated list of "mode settings in property" pairs. */
			__( "Each method's settings go in the object of the same name: %s. Send the method and its object together.", 'sugar-calendar-lite' ),
			implode( ', ', $names )
		);
	}

	/**
	 * Normalize validated ability input into the shape SCE stores.
	 *
	 * @since 3.14.0
	 *
	 * @param array $input   Validated ability input.
	 * @param bool  $partial Whether absent date fields are allowed (update).
	 *
	 * @return array|WP_Error
	 */
	public static function normalize( array $input, bool $partial = false ) {

		$assumptions = [];
		$errors      = [];
		$row         = [];
		$meta        = [];

		if ( isset( $input['title'] ) ) {
			$title = sanitize_text_field( $input['title'] );

			if ( $title === '' ) {
				return new WP_Error(
					'sc_events_invalid_input',
					__( 'The event needs a title.', 'sugar-calendar-lite' ),
					[ 'status' => 400 ]
				);
			}

			$row['title'] = $title;
		}

		if ( isset( $input['description'] ) ) {
			$description    = (string) $input['description'];
			$row['content'] = wp_kses_post( $description );

			if ( self::description_dropped_markup( $description ) ) {
				$errors[] = __( 'Some HTML in the description was removed (script, iframe or similar). Add it in the event editor if you need it.', 'sugar-calendar-lite' );
			}
		}

		// Same kses pass WordPress itself applies to post_excerpt through
		// wp_insert_post()/wp_update_post() ('pre_post_excerpt' -> wp_filter_post_kses()).
		if ( isset( $input['excerpt'] ) ) {
			$row['excerpt'] = wp_kses_post( $input['excerpt'] );
		}

		if ( isset( $input['location'] ) ) {
			$meta['location'] = sanitize_text_field( $input['location'] );
		}

		$dates = self::normalize_dates( $input, $partial, $assumptions );

		if ( is_wp_error( $dates ) ) {
			return $dates;
		}

		$row = array_merge( $row, $dates );

		// A bucket is present only when the caller mentioned that key, even
		// with an empty list — that is what lets EventWriter tell "omitted"
		// (leave assignments alone) apart from "explicitly cleared" (empty it).
		$terms = [];

		if ( isset( $input['calendar_ids'] ) ) {
			$terms['calendars'] = self::ids( $input['calendar_ids'] );
		}

		if ( isset( $input['tag_ids'] ) ) {
			$terms['tags'] = self::ids( $input['tag_ids'] );
		}

		return [
			'row'         => $row,
			'meta'        => $meta,
			'terms'       => $terms,
			'assumptions' => $assumptions,
			'errors'      => $errors,
			'extra'       => array_diff_key( $input, array_flip( self::KNOWN_KEYS ) ),
		];
	}

	/**
	 * Whether a raw description names a tag `wp_kses_post()` will refuse.
	 *
	 * Checks tag names rather than comparing the sanitized string: a stripped
	 * `<script>`/`<iframe>` tag leaves its inner text behind (kses only removes
	 * the tag itself), so a before/after string diff would miss it.
	 *
	 * @since 3.15.0
	 *
	 * @param string $description Raw input, before sanitizing.
	 *
	 * @return bool
	 */
	private static function description_dropped_markup( string $description ): bool {

		if ( ! preg_match_all( '/<([a-zA-Z][a-zA-Z0-9-]*)/', $description, $matches ) ) {
			return false;
		}

		$allowed = array_keys( wp_kses_allowed_html( 'post' ) );

		foreach ( $matches[1] as $tag ) {
			if ( ! in_array( strtolower( $tag ), $allowed, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize start/end/all_day/timezone.
	 *
	 * Mirrors EventDateTimeRequest's order of operations so an ability-created
	 * event is indistinguishable from an editor-created one: `sanitize_all_day()`
	 * can promote a `00:00:00`-to-`23:59:59` pair, and the end-of-day correction
	 * and both time-zone columns have to follow the promoted value rather than
	 * what the caller sent.
	 *
	 * Calls `Helpers::sanitize_end()` rather than EventDateTimeRequest's own
	 * override, whose purpose is a `$_POST`-based `has_end()` check this path
	 * handles upstream. That helper's `$end_compare > $start_int` early return
	 * is not gated on all-day, so an all-day end that falls after its start
	 * never reaches the helper's end-of-day normalization. Hence `end_of_day()`.
	 *
	 * @since 3.14.0
	 *
	 * @param array $input       Validated ability input.
	 * @param bool  $partial     Whether absent dates are allowed.
	 * @param array $assumptions Collects defaults applied, by reference.
	 *
	 * @return array|WP_Error
	 */
	private static function normalize_dates( array $input, bool $partial, array &$assumptions ) {

		$pair = self::parse_pair( $input, $partial, $assumptions );

		if ( is_wp_error( $pair ) || $pair === [] ) {
			return $pair;
		}

		$start   = Helpers::sanitize_start( $pair['start'], $pair['end'], $pair['all_day'] );
		$end     = Helpers::sanitize_end( $pair['end'], $start, $pair['all_day'] );
		$all_day = Helpers::sanitize_all_day( $pair['all_day'], $start, $end );

		if ( $all_day ) {
			$end = self::end_of_day( $end );
		}

		// Resolved against the promoted all-day value, so the assumptions it
		// records match the row that gets written.
		$timezone = self::resolve_timezone( $input, $all_day, $assumptions );

		if ( is_wp_error( $timezone ) ) {
			return $timezone;
		}

		return [
			'start'    => $start,
			'end'      => $end,
			'all_day'  => $all_day,
			'start_tz' => Helpers::sanitize_timezone( $timezone, $timezone, $all_day ),
			'end_tz'   => Helpers::sanitize_timezone( $timezone, $timezone, $all_day ),
		];
	}

	/**
	 * Read the caller's start/end pair, before any of the repair helpers see it.
	 *
	 * @since 3.14.0
	 *
	 * @param array $input       Validated ability input.
	 * @param bool  $partial     Whether absent dates are allowed.
	 * @param array $assumptions Collects defaults applied, by reference.
	 *
	 * @return array|WP_Error Empty array when a partial patch names no date.
	 */
	private static function parse_pair( array $input, bool $partial, array &$assumptions ) {

		$has_start = isset( $input['start'] ) && $input['start'] !== '';
		$has_end   = isset( $input['end'] ) && $input['end'] !== '';

		if ( ! $has_start && ! $has_end ) {
			if ( $partial ) {
				return [];
			}

			return new WP_Error(
				'sc_events_invalid_input',
				__( 'The event needs a start date and time.', 'sugar-calendar-lite' ),
				[ 'status' => 400 ]
			);
		}

		// A partial patch (update) always has its start filled from the stored event by
		// UpdateEvent::fill_date_group() before this runs, so $has_start is reachable
		// false here only when that fill could not run (no stored start to fall back on),
		// which the fill already refused with its own message. Fall through to the
		// unparseable-start message below rather than blaming the end date for it.
		$start = self::wall_clock( $input['start'] ?? '' );

		if ( $start === '' ) {
			return new WP_Error(
				'sc_events_invalid_input',
				self::carries_zone( $input['start'] )
					? __( 'The start date cannot carry a UTC offset or a trailing Z. Give the local time, for example 2026-09-14T18:00:00, and name the zone in the timezone field.', 'sugar-calendar-lite' )
					: __( 'The start date could not be read. Use the format 2026-09-14T18:00:00.', 'sugar-calendar-lite' ),
				[ 'status' => 400 ]
			);
		}

		if ( $has_end ) {
			$end = self::wall_clock( $input['end'] );

			if ( $end === '' ) {
				return new WP_Error(
					'sc_events_invalid_input',
					self::carries_zone( $input['end'] )
						? __( 'The end date cannot carry a UTC offset or a trailing Z. Give the local time, for example 2026-09-14T19:00:00, and name the zone in the timezone field.', 'sugar-calendar-lite' )
						: __( 'The end date could not be read. Use the format 2026-09-14T19:00:00.', 'sugar-calendar-lite' ),
					[ 'status' => 400 ]
				);
			}
		} else {
			$end           = $start;
			$assumptions[] = __( 'No end time was given, so the event ends when it starts.', 'sugar-calendar-lite' );
		}

		// Refuse an inverted range here rather than downstream:
		// `Helpers::sanitize_end()` repairs one silently in three different ways
		// depending on all-day and the configured minimum duration, so by then
		// the caller's mistake is either stored inverted or collapsed onto the
		// start with nothing reported. Both values are zero-padded
		// `Y-m-d H:i:s`, so comparing them as strings is chronological.
		if ( $end < $start ) {
			return new WP_Error(
				'sc_events_invalid_input',
				__( 'The event ends before it starts. Check the start and end dates.', 'sugar-calendar-lite' ),
				[ 'status' => 400 ]
			);
		}

		return [
			'start'   => $start,
			'end'     => $end,
			'all_day' => ! empty( $input['all_day'] ),
		];
	}

	/**
	 * Validate the requested time zone, recording what was defaulted or dropped.
	 *
	 * @since 3.14.0
	 *
	 * @param array $input       Validated ability input.
	 * @param bool  $all_day     Whether the event is all-day.
	 * @param array $assumptions Collects defaults applied, by reference.
	 *
	 * @return string|WP_Error Time zone string, empty when the event floats.
	 */
	private static function resolve_timezone( array $input, bool $all_day, array &$assumptions ) {

		$timezone = isset( $input['timezone'] ) ? trim( (string) $input['timezone'] ) : '';
		$timezone = $timezone !== '' ? self::normalize_manual_offset( $timezone ) : $timezone;

		// PHP's DateTimeZone constructor throws on the manual-offset shape the editor's own
		// dropdown offers and stores (`UTC+5`, `UTC-3.5`), so is_timezone() alone would refuse
		// it; sugar_calendar_validate_timezone() is what actually recognizes that family.
		if ( $timezone !== '' && ! self::is_timezone( $timezone ) && sugar_calendar_validate_timezone( $timezone ) === '' ) {
			return new WP_Error(
				'sc_events_invalid_input',
				__( 'That time zone is not one WordPress recognizes.', 'sugar-calendar-lite' ),
				[ 'status' => 400 ]
			);
		}

		// All-day events carry no time zone at all, so say the requested one
		// was dropped rather than letting sanitize_timezone() blank it in
		// silence.
		if ( $timezone !== '' && $all_day ) {
			$assumptions[] = __( 'All-day events have no time zone, so the one you sent was not stored.', 'sugar-calendar-lite' );

			return '';
		}

		// PHP accepts zones Sugar Calendar's own list does not (Etc/GMT-14 and
		// the rest of the Etc/* family), and the storage layer would blank
		// those without a word.
		if ( $timezone !== '' && sugar_calendar_validate_timezone( $timezone ) === '' ) {
			return new WP_Error(
				'sc_events_invalid_input',
				__( 'Sugar Calendar does not store that time zone. Use a city-based zone such as America/Chicago, or UTC.', 'sugar-calendar-lite' ),
				[ 'status' => 400 ]
			);
		}

		// Stored empty, which SCE treats as floating: the event shows in
		// whatever time zone the site is set to.
		if ( $timezone === '' && ! $all_day ) {
			$assumptions[] = self::floating_zone_assumption();
		}

		return $timezone;
	}

	/**
	 * Convert `UTC±H:MM` into the manual-offset decimal form Sugar Calendar stores
	 * (`UTC-3:30` becomes `UTC-3.5`), so a caller can send either shape.
	 *
	 * Only the minute values the editor's own dropdown offers (00, 30, 45) convert; any other
	 * minute is returned unchanged, which the validation that follows refuses (Minor 3).
	 *
	 * @since 3.15.0
	 *
	 * @param string $timezone Candidate time zone, already trimmed.
	 *
	 * @return string
	 */
	private static function normalize_manual_offset( string $timezone ): string {

		if ( ! preg_match( '/^UTC([+-])(\d{1,2}):(\d{2})$/', $timezone, $matches ) ) {
			return $timezone;
		}

		$fractions = [
			'00' => 0.0,
			'30' => 0.5,
			'45' => 0.75,
		];

		if ( ! isset( $fractions[ $matches[3] ] ) ) {
			return $timezone;
		}

		$value = ( (int) $matches[2] + $fractions[ $matches[3] ] ) * ( $matches[1] === '-' ? -1 : 1 );

		return 'UTC' . ( $value >= 0 ? '+' : '' ) . $value;
	}

	/**
	 * The assumption line for an event left with no time zone.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public static function floating_zone_assumption(): string {

		return __( "No time zone was given, so the event follows the site's time zone.", 'sugar-calendar-lite' );
	}

	/**
	 * Move a stored wall-clock datetime to the final second of its own day.
	 *
	 * SCE's all-day convention is `00:00:00` to `23:59:59`. Applied to the end
	 * only; `Helpers::sanitize_start()` already floors an all-day start.
	 *
	 * @since 3.14.0
	 *
	 * @param string $end MySQL datetime.
	 *
	 * @return string
	 */
	private static function end_of_day( string $end ): string {

		$timestamp = strtotime( $end );

		return $timestamp === false ? $end : gmdate( 'Y-m-d 23:59:59', $timestamp );
	}

	/**
	 * Convert an ISO 8601 local datetime into SCE's stored wall-clock string.
	 *
	 * Accepts `Y-m-d\TH:i:s`, `Y-m-d H:i:s`, `Y-m-d\TH:i`, `Y-m-d H:i` and
	 * `Y-m-d`. Returns
	 * an empty string when nothing matches — deliberately no strtotime()
	 * fallback, because a misparse writes a wrong date silently. Also rejects
	 * a value that parses but doesn't round-trip through the same format:
	 * `createFromFormat()` overflows out-of-range fields instead of failing
	 * (e.g. `2026-02-30` silently becomes `2026-03-02`).
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $value Raw input value.
	 *
	 * @return string MySQL datetime, or '' when unparseable or out of range.
	 */
	private static function wall_clock( $value ): string {

		if ( ! is_string( $value ) ) {
			return '';
		}

		$value   = trim( $value );
		$formats = [ 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d' ];

		foreach ( $formats as $format ) {
			$date = DateTimeImmutable::createFromFormat( '!' . $format, $value, new DateTimeZone( 'UTC' ) );

			if ( $date instanceof DateTimeImmutable && $date->format( $format ) === $value ) {
				return $date->format( 'Y-m-d H:i:s' );
			}
		}

		return '';
	}

	/**
	 * Whether a datetime string carries a UTC offset or a trailing Z.
	 *
	 * `wall_clock()` rejects both, but it cannot say why. The zone-bearing case
	 * is the one with a remedy the caller can act on, so it earns its own
	 * message.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $value Candidate datetime.
	 *
	 * @return bool
	 */
	private static function carries_zone( $value ): bool {

		if ( ! is_string( $value ) ) {
			return false;
		}

		return (bool) preg_match( '/(?:Z|[+-]\d{2}:?\d{2})$/i', trim( $value ) );
	}

	/**
	 * Whether a string is a timezone PHP recognizes.
	 *
	 * @since 3.14.0
	 *
	 * @param string $timezone Candidate timezone.
	 *
	 * @return bool
	 */
	private static function is_timezone( string $timezone ): bool {

		try {
			new DateTimeZone( $timezone );
		} catch ( Exception $e ) {
			return false;
		}

		return true;
	}

	/**
	 * Coerce a term id list to unique positive integers.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $ids Raw list.
	 *
	 * @return int[]
	 */
	private static function ids( $ids ): array {

		if ( ! is_array( $ids ) ) {
			return [];
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}
}
