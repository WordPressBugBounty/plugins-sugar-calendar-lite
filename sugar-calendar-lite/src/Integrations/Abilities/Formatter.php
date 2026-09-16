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
use WP_Term;

/**
 * Formatter — pure transforms from Sugar Calendar objects into the canonical
 * array shapes the abilities return. No database access; callers supply any
 * resolved calendar map.
 *
 * @since 3.12.0
 */
class Formatter {

	/**
	 * Decode stored HTML entities into plain text.
	 *
	 * Term names are stored entity-encoded, so a calendar named `Rock & Roll` reads
	 * back as `Rock &amp; Roll`. These payloads are consumed by language models and
	 * other API clients rather than rendered as HTML, and a model handed `&amp;`
	 * repeats it back in prose.
	 *
	 * Terms only: event titles come out of `wp_sc_events` unencoded, so decoding one
	 * would be a no-op nothing could prove.
	 *
	 * @since 3.14.0
	 *
	 * @param string $value Stored value.
	 *
	 * @return string
	 */
	private function text( string $value ): string {

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
			'name'  => $this->text( $term->name ),
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
			'name'  => $this->text( $term->name ),
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
			$tz = new DateTimeZone( '' !== $tz_name ? $tz_name : 'UTC' );
			$dt = new DateTimeImmutable( $datetime, $tz );

			return $dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
		} catch ( Exception $e ) {
			return '';
		}
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
	 * @param Event $event        The event to format.
	 * @param array $calendar_map Calendar lookup keyed by post_id.
	 *
	 * @return array
	 */
	public function event( Event $event, array $calendar_map = [] ): array {

		$object_id = (int) $event->object_id;
		$terms     = $calendar_map[ $object_id ] ?? [];

		$calendars = [];

		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$calendars[] = $this->calendar( $term );
			}
		}

		$recurrence_end    = ! empty( $event->recurrence_end ) && strpos( $event->recurrence_end, '0000-00-00' ) !== 0
			? $this->iso8601( $event->recurrence_end, $event->recurrence_end_tz )
			: '';
		$recurrence_end_tz = '' !== $recurrence_end ? $event->recurrence_end_tz : null;

		$content_protected = $object_id > 0 && post_password_required( $object_id );
		$content            = ( $content_protected && ! current_user_can( 'edit_post', $object_id ) )
			? ''
			: $event->content;

		return [
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
		];
	}

	/**
	 * Format a list of events against a pre-resolved calendar map.
	 *
	 * @since 3.12.0
	 *
	 * @param Event[] $events       Event objects.
	 * @param array   $calendar_map Calendar lookup keyed by post_id.
	 *
	 * @return array
	 */
	public function events( array $events, array $calendar_map ): array {

		$out = [];

		foreach ( $events as $event ) {
			$out[] = $this->event( $event, $calendar_map );
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
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function event_schema(): array {

		return [
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
			],
		];
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
