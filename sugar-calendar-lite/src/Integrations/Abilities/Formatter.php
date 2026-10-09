<?php
/**
 * Output shaping for the WordPress Abilities API integration.
 *
 * @since 3.12.0
 */

namespace Sugar_Calendar\Integrations\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Sugar_Calendar\Event;
use Sugar_Calendar\Features\FeaturedEvents\Common\Featured;
use WP_Term;

/**
 * Formatter — transforms from Sugar Calendar objects into the canonical array
 * shapes the abilities return. Callers supply any resolved calendar map; this
 * class still reads the post (permalink, password, excerpt) to shape one event.
 *
 * @since 3.12.0
 */
class Formatter {

	/**
	 * Decode stored HTML entities into plain text.
	 *
	 * Term and venue/calendar names are stored (or, for a post title, rendered) entity-encoded,
	 * so `Rock & Roll` reads back as `Rock &amp; Roll`. These payloads are consumed by language
	 * models and other API clients rather than rendered as HTML, and a model handed `&amp;`
	 * repeats it back in prose. Public and static so any caller shaping a name for the same
	 * abilities (get-event, list-events, a contributor's own snapshot or applied message) uses
	 * the same decoding rather than a second copy (Minor 6).
	 *
	 * Not for event titles: those come out of `wp_sc_events` unencoded, so decoding one would be
	 * a no-op nothing could prove.
	 *
	 * @since 3.14.0
	 * @since 3.15.0 Public and static, so callers outside this class can reuse it.
	 *
	 * @param string $value Stored value.
	 *
	 * @return string
	 */
	public static function text( string $value ): string {

		return html_entity_decode( $value, ENT_QUOTES, get_bloginfo( 'charset' ) );
	}

	/**
	 * Render a calendar (sc_event_category term) into the canonical calendar shape.
	 *
	 * @since 3.12.0
	 *
	 * @param WP_Term $term Calendar term.
	 *
	 * @return array { id: int, slug: string, name: string, count: int }
	 */
	public function calendar( WP_Term $term ): array {

		return [
			'id'    => $term->term_id,
			'slug'  => $term->slug,
			'name'  => self::text( $term->name ),
			'count' => $term->count,
		];
	}

	/**
	 * Shape a tag term. Deliberately identical to calendar() so the data picker
	 * can treat every term dataset the same way.
	 *
	 * @since 3.14.0
	 *
	 * @param WP_Term $term Tag term.
	 *
	 * @return array { id: int, slug: string, name: string, count: int }
	 */
	public function tag( WP_Term $term ): array {

		return [
			'id'    => $term->term_id,
			'slug'  => $term->slug,
			'name'  => self::text( $term->name ),
			'count' => $term->count,
		];
	}

	/**
	 * Normalize a stored datetime + timezone into an ISO 8601 UTC string.
	 * Empty / zero-date / unparseable inputs return ''.
	 *
	 * @since 3.12.0
	 *
	 * @param string $datetime Stored datetime, e.g. '2026-05-15 14:00:00'.
	 * @param string $tz_name  IANA timezone name. Empty falls back to UTC.
	 *
	 * @return string ISO 8601 UTC ('YYYY-MM-DDTHH:MM:SSZ') or ''.
	 */
	public function iso8601( string $datetime, string $tz_name ): string {

		if ( empty( $datetime ) || strpos( $datetime, '0000-00-00' ) === 0 ) {
			return '';
		}

		try {
			$tz = new DateTimeZone( '' !== $tz_name ? $this->resolve_offset( $tz_name ) : 'UTC' );
			$dt = new DateTimeImmutable( $datetime, $tz );

			return $dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Convert Sugar Calendar's manual UTC-offset zone strings (`UTC+5`, `UTC-3.5`) into the
	 * `+HH:MM` shape `DateTimeZone` accepts.
	 *
	 * `sugar_calendar_get_valid_timezones()` stores and offers this bare `UTC±N` form, which
	 * PHP's constructor otherwise rejects outright, leaving an event stored with such a zone
	 * unreadable through this method.
	 *
	 * @since 3.15.0
	 *
	 * @param string $tz_name Stored time zone name.
	 *
	 * @return string
	 */
	private function resolve_offset( string $tz_name ): string {

		if ( ! preg_match( '/^UTC([+-])(\d+(?:\.\d+)?)$/', $tz_name, $matches ) ) {
			return $tz_name;
		}

		$hours   = (float) $matches[2];
		$minutes = round( ( $hours - floor( $hours ) ) * 60 );

		return sprintf( '%s%02d:%02d', $matches[1], floor( $hours ), $minutes );
	}

	/**
	 * Render a Sugar_Calendar\Event into the canonical event shape.
	 *
	 * The caller supplies the resolved calendar map (post_id => WP_Term[]).
	 * Also checks `post_password_required()` for the underlying post — the
	 * `read_event`/`edit_posts` gates these abilities use only confirm the
	 * event itself is visible (matching front-end visibility), the same way
	 * `Admin\Events\Tables\Base::user_can_view()` does for the admin list
	 * table; neither one is a stand-in for "no password is set." Mirrors
	 * WP_REST_Posts_Controller's handling of this same post type (which is
	 * already REST-exposed — `register_post_type()`'s `show_in_rest`): blank
	 * `content` for anyone who can't edit the post, and flag it via
	 * `content_protected` so a caller can tell "no description" apart from
	 * "hidden because password-protected." When the map has no entry for the
	 * event's object_id, `calendars` is an empty array.
	 *
	 * @since 3.12.0
	 *
	 * @since 3.15.0 Takes a tag map and adds `tags`, so a caller can add a tag without dropping the others.
	 *
	 * @param Event $event        The event to format.
	 * @param array $calendar_map Calendar lookup keyed by post_id.
	 * @param array $tag_map      Tag lookup keyed by post_id.
	 *
	 * @return array
	 */
	public function event( Event $event, array $calendar_map = [], array $tag_map = [] ): array {

		$object_id = (int) $event->object_id;
		$terms     = $calendar_map[ $object_id ] ?? [];

		$calendars = [];

		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$calendars[] = $this->calendar( $term );
			}
		}

		$tags = [];

		foreach ( $tag_map[ $object_id ] ?? [] as $term ) {
			if ( $term instanceof WP_Term ) {
				$tags[] = $this->tag( $term );
			}
		}

		$recurrence_end    = ! empty( $event->recurrence_end ) && strpos( $event->recurrence_end, '0000-00-00' ) !== 0
			? $this->iso8601( $event->recurrence_end, $event->recurrence_end_tz )
			: '';
		$recurrence_end_tz = '' !== $recurrence_end ? $event->recurrence_end_tz : null;

		$content_protected = $object_id > 0 && post_password_required( $object_id );
		$hide_protected    = $content_protected && ! current_user_can( 'edit_post', $object_id );
		$content           = $hide_protected ? '' : $event->content;
		$venue_id          = (int) ( $event->venue_id ?? 0 );

		$out = [
			'id'                  => (int) $event->id,
			'object_id'           => $object_id,
			'title'               => $event->title,
			'content'             => $content,
			'content_protected'   => $content_protected,
			'status'              => $event->status,
			'url'                 => $object_id > 0 ? (string) get_permalink( $object_id ) : '',
			'start'               => $this->iso8601( $event->start, $event->start_tz ),
			'end'                 => $this->iso8601( $event->end, $event->end_tz ),
			'start_tz'            => $event->start_tz,
			'end_tz'              => $event->end_tz,
			'all_day'             => (bool) $event->all_day,
			'recurrence'          => '' !== $event->recurrence ? $event->recurrence : null,
			'recurrence_interval' => (int) $event->recurrence_interval > 0 ? (int) $event->recurrence_interval : null,
			'recurrence_count'    => (int) $event->recurrence_count > 0 ? (int) $event->recurrence_count : null,
			'recurrence_end'      => '' !== $recurrence_end ? $recurrence_end : null,
			'recurrence_end_tz'   => $recurrence_end_tz,
			'date_created'        => $this->iso8601( $event->date_created, '' ),
			'date_modified'       => $this->iso8601( $event->date_modified, '' ),
			'calendars'           => $calendars,
			'tags'                => $tags,
			// Excerpt has no counterpart column in wp_sc_events (see EventWriter), so it is
			// always read from the post. Featured is a whole-series flag, resolved from the
			// post for the same reason `Featured::is_featured()` always is. Blanked under the
			// same password gate as content (final review I4): otherwise a password-protected
			// excerpt leaked to anyone who could merely read the event.
			'excerpt'             => ( ! $hide_protected && $object_id > 0 ) ? (string) get_post_field( 'post_excerpt', $object_id ) : '',
			'featured'            => Featured::is_featured( $event ),
			'signup'              => SignupModes::current( (int) $event->id ),
			'location'            => (string) get_event_meta( (int) $event->id, 'location', true ),
			'venue_id'            => $venue_id,
			'venue'               => self::venue_name( $venue_id ),
		];

		/**
		 * Filters one formatted event, for a feature that adds read-only fields of its own.
		 *
		 * Declare an added key through `sugar_calendar_integrations_abilities_formatter_event_schema`.
		 *
		 * @since 3.15.0
		 *
		 * @param array $out   Formatted event.
		 * @param Event $event The event it was formatted from.
		 */
		$filtered = apply_filters( 'sugar_calendar_integrations_abilities_formatter_event', $out, $event );

		return is_array( $filtered ) ? $filtered : $out;
	}

	/**
	 * A venue's title, decoded, or '' for no venue (id 0).
	 *
	 * @since 3.15.0
	 *
	 * @param int $venue_id Venue post id, or 0.
	 *
	 * @return string
	 */
	public static function venue_name( int $venue_id ): string {

		return $venue_id > 0 ? self::text( (string) get_the_title( $venue_id ) ) : '';
	}

	/**
	 * Format a list of events against a pre-resolved calendar map.
	 *
	 * @since 3.12.0
	 * @since 3.15.0 Takes a tag map; primes the venue posts' title cache once for the
	 *                  whole list.
	 *
	 * @param Event[] $events       Event objects.
	 * @param array   $calendar_map Calendar lookup keyed by post_id.
	 * @param array   $tag_map      Tag lookup keyed by post_id.
	 *
	 * @return array
	 */
	public function events( array $events, array $calendar_map, array $tag_map = [] ): array {

		$venue_ids = [];

		foreach ( $events as $event ) {

			$venue_id = (int) ( $event->venue_id ?? 0 );

			if ( $venue_id > 0 ) {
				$venue_ids[] = $venue_id;
			}
		}

		if ( ! empty( $venue_ids ) ) {
			_prime_post_caches( array_values( array_unique( $venue_ids ) ), false, false );
		}

		$out = [];

		foreach ( $events as $event ) {
			$out[] = $this->event( $event, $calendar_map, $tag_map );
		}

		return $out;
	}

	/**
	 * JSON schema for the term shape calendar() and tag() return.
	 *
	 * Static so an ability can describe its output without a Formatter instance —
	 * `AbstractAbility::$formatter` is nullable.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function term_schema(): array {

		return [
			'type'       => 'object',
			'required'   => [ 'id', 'slug', 'name', 'count' ],
			'properties' => [
				'id'    => [ 'type' => 'integer' ],
				'slug'  => [ 'type' => 'string' ],
				'name'  => [ 'type' => 'string' ],
				'count' => [ 'type' => 'integer' ],
			],
		];
	}

	/**
	 * JSON schema for the shape event() returns.
	 *
	 * The five recurrence members and `recurrence_end_tz` are nullable on purpose:
	 * event() distinguishes "not a recurring event" from a zero interval, so the
	 * null has to be declared or a non-recurring event fails output validation.
	 *
	 * `additionalProperties` is deliberately left open. WP_Ability validates the
	 * result *after* `wp_ability_execute_result` has filtered it, so closing the
	 * shape would turn any third-party addition into `ability_invalid_output`.
	 *
	 * The properties below `featured` are not in `required`: `event()` itself sets `signup`,
	 * `location`, `venue_id` and `venue` for every ability; only get-event also merges
	 * `EventSnapshot::for_event()`'s edit-only detail keys (`ticketing`, plus whatever a
	 * signup mode or add-on contributes through `sugar_calendar_integrations_abilities_get_event_detail`),
	 * and none of that detail applies when the event doesn't use that mode.
	 *
	 * @since 3.14.0
	 * @since 3.15.0 Adds `excerpt`, `featured`, `signup`, `location`, `venue_id`, `venue`, and the
	 *                  read-only detail properties get-event layers on top of the shared shape.
	 *
	 * @return array
	 */
	public static function event_schema(): array {

		$schema = [
			'type'       => 'object',
			'required'   => [
				'id',
				'object_id',
				'title',
				'content',
				'content_protected',
				'status',
				'url',
				'start',
				'end',
				'start_tz',
				'end_tz',
				'all_day',
				'recurrence',
				'recurrence_interval',
				'recurrence_count',
				'recurrence_end',
				'recurrence_end_tz',
				'date_created',
				'date_modified',
				'calendars',
				'excerpt',
				'featured',
			],
			'properties' => [
				'id'                  => [ 'type' => 'integer' ],
				'object_id'           => [ 'type' => 'integer' ],
				'title'               => [ 'type' => 'string' ],
				'content'             => [ 'type' => 'string' ],
				'content_protected'   => [ 'type' => 'boolean' ],
				'status'              => [ 'type' => 'string' ],
				'url'                 => [ 'type' => 'string' ],
				'start'               => [ 'type' => 'string' ],
				'end'                 => [ 'type' => 'string' ],
				'start_tz'            => [ 'type' => 'string' ],
				'end_tz'              => [ 'type' => 'string' ],
				'all_day'             => [ 'type' => 'boolean' ],
				'recurrence'          => [ 'type' => [ 'string', 'null' ] ],
				'recurrence_interval' => [ 'type' => [ 'integer', 'null' ] ],
				'recurrence_count'    => [ 'type' => [ 'integer', 'null' ] ],
				'recurrence_end'      => [ 'type' => [ 'string', 'null' ] ],
				'recurrence_end_tz'   => [ 'type' => [ 'string', 'null' ] ],
				'date_created'        => [ 'type' => 'string' ],
				'date_modified'       => [ 'type' => 'string' ],
				'calendars'           => [
					'type'  => 'array',
					'items' => self::term_schema(),
				],
				'tags'                => [
					'type'  => 'array',
					'items' => self::term_schema(),
				],
				'excerpt'             => [ 'type' => 'string' ],
				'featured'            => [ 'type' => 'boolean' ],
				'signup'              => [ 'type' => 'string' ],
				'location'            => [ 'type' => 'string' ],
				'venue_id'            => [ 'type' => 'integer' ],
				'venue'               => [ 'type' => 'string' ],
				'online_link'         => [ 'type' => 'string' ],
				'online_visibility'   => [ 'type' => 'string' ],
				'online_provider'     => [ 'type' => 'string' ],
				'online_join_url'     => [ 'type' => 'string' ],
				'ticketing'           => [ 'type' => 'object' ],
			],
		];

		/**
		 * Filters the formatted event's schema, so a key added through
		 * `sugar_calendar_integrations_abilities_formatter_event` is declared too.
		 *
		 * @since 3.15.0
		 *
		 * @param array $schema Event object schema.
		 */
		$filtered = apply_filters( 'sugar_calendar_integrations_abilities_formatter_event_schema', $schema );

		return is_array( $filtered ) ? $filtered : $schema;
	}

	/**
	 * JSON schema for a paged collection: one named array plus its total.
	 *
	 * @since 3.14.0
	 *
	 * @param string $key  Property name holding the rows.
	 * @param array  $item JSON schema for one row.
	 *
	 * @return array
	 */
	public static function collection_schema( string $key, array $item ): array {

		return [
			'type'       => 'object',
			'required'   => [ $key, 'total' ],
			'properties' => [
				$key    => [
					'type'  => 'array',
					'items' => $item,
				],
				'total' => [ 'type' => 'integer' ],
			],
		];
	}
}
