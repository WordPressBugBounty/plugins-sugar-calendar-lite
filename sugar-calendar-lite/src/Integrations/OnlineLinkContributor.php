<?php
/**
 * The `online_link` slice of the event write abilities.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Integrations\Abilities\AbstractEventFieldContributor;
use Sugar_Calendar\Integrations\Abilities\SignupModes;

/**
 * OnlineLinkContributor — lets an ability set the event's Custom Link.
 *
 * Only the Custom Link provider is exposed. A real provider meeting (Zoom and
 * friends) needs an OAuth connection and spends relay credits, so it stays a
 * deliberate click in the editor rather than something a prompt can trigger.
 *
 * @since 3.15.0
 */
class OnlineLinkContributor extends AbstractEventFieldContributor {

	/**
	 * Input property name.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const PROPERTY = 'online_link';

	/**
	 * Input property name for the "Show to" setting.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const VISIBILITY_PROPERTY = 'online_visibility';

	/**
	 * Widen the input schema.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $properties Properties so far.
	 * @param mixed $partial    Whether this is the update variant.
	 *
	 * @return mixed
	 */
	public static function add_schema( $properties, $partial ) {

		if ( ! is_array( $properties ) ) {
			return $properties;
		}

		$properties[ self::PROPERTY ] = [
			'type'        => 'string',
			'format'      => 'uri',
			'maxLength'   => 2048,
			'description' => __( 'A link attendees join the event through, for example https://meet.example.com/abc. Makes the event online. Send an empty string to remove it. Cannot create a Zoom meeting: that is done in the event editor.', 'sugar-calendar-lite' ),
		];

		$properties[ self::VISIBILITY_PROPERTY ] = [
			'type'        => 'string',
			'enum'        => [ OnlineMeeting::VISIBILITY_EVERYONE, OnlineMeeting::VISIBILITY_ATTENDEES ],
			'description' => __( 'Who sees the event link on the public event page. Send everyone to show it to every visitor, or attendees to show it only to people who signed up. Applies only when online_link is sent, or when the event already has a meeting.', 'sugar-calendar-lite' ),
		];

		return $properties;
	}

	/**
	 * Set or clear the event's Custom Link.
	 *
	 * @since 3.15.0
	 *
	 * @param int   $event_id   Sugar Calendar event id.
	 * @param array $normalized Normalized input.
	 * @param array $result     Result so far, by reference.
	 *
	 * @return void
	 */
	public static function apply( $event_id, $normalized, &$result ) {

		$extra = isset( $normalized['extra'] ) ? (array) $normalized['extra'] : [];

		$has_link       = array_key_exists( self::PROPERTY, $extra );
		$has_visibility = array_key_exists( self::VISIBILITY_PROPERTY, $extra );

		if ( ! $has_link && ! $has_visibility ) {
			return;
		}

		$event_id = (int) $event_id;

		// "Show to" belongs to whatever meeting the event already has, provider or
		// custom, so it is written on its own rather than only alongside a link.
		if ( ! $has_link ) {
			self::apply_visibility_request( $event_id, $extra, $result );

			return;
		}

		$link     = trim( (string) $extra[ self::PROPERTY ] );
		$provider = (string) get_event_meta( $event_id, 'online_provider', true );

		// A provider meeting was arranged through the editor, and its join URL is
		// the provider's. Overwriting it here would orphan the meeting.
		if ( $provider !== '' && $provider !== OnlineMeeting::PROVIDER_CUSTOM ) {
			$result['errors'][]    = __( 'This event already uses an online meeting provider, so the link was not changed. Remove the meeting in the event editor first.', 'sugar-calendar-lite' );
			$result['explained'][] = self::PROPERTY;

			// "Show to" is still legal on a provider meeting; refusing the link
			// must not swallow it.
			self::apply_sent_visibility( $event_id, $extra, $result );

			return;
		}

		$old_url = $provider === OnlineMeeting::PROVIDER_CUSTOM ? (string) get_event_meta( $event_id, 'custom_link_url', true ) : '';

		if ( $link === '' ) {
			self::remove_link( $event_id, $old_url, $result );
			self::apply_sent_visibility( $event_id, $extra, $result );

			return;
		}

		$url = esc_url_raw( $link );

		// The raw text is checked, not only the cleaned URL: esc_url_raw() prefixes a bare word
		// with http://, which the editor's type="url" field would never have let through.
		if ( $url === '' || ! self::is_acceptable_url( $link ) || ! self::is_acceptable_url( $url ) ) {
			$result['errors'][]    = __( 'The event link was skipped because it is not a valid URL.', 'sugar-calendar-lite' );
			$result['explained'][] = self::PROPERTY;

			return;
		}

		// esc_url_raw() lowercases the scheme, so HTTPS:// is stored as https://. That is the
		// link the caller sent, and the diff must not report it as a failed save.
		$scheme = (string) wp_parse_url( trim( $link ), PHP_URL_SCHEME );

		if ( $url !== $link && $url === strtolower( $scheme ) . substr( trim( $link ), strlen( $scheme ) ) ) {
			$result['explained'][] = self::PROPERTY;
		}

		if ( $url === $old_url ) {
			// Resending the stored link is a no-op for the link itself; a sent
			// visibility still applies on its own.
			self::apply_sent_visibility( $event_id, $extra, $result );

			return;
		}

		update_event_meta( $event_id, 'online_provider', OnlineMeeting::PROVIDER_CUSTOM );
		update_event_meta( $event_id, 'custom_link_url', $url );

		self::record_previous( $result, self::PROPERTY, $old_url );

		$is_add     = $old_url === '';
		$visibility = $is_add
			? self::visibility_for_new_link( $event_id, $extra, $has_visibility, $result )
			: self::visibility_for_changed_link( $event_id, $extra, $has_visibility, $result );

		$result['applied'][] = self::link_written_message( $is_add, $visibility );
	}

	/**
	 * Clear the event's Custom Link, when it held one (B6/no-op).
	 *
	 * @since 3.15.0
	 *
	 * @param int    $event_id Sugar Calendar event id.
	 * @param string $old_url  The link before this write.
	 * @param array  $result   Result so far, by reference.
	 *
	 * @return void
	 */
	private static function remove_link( $event_id, $old_url, array &$result ) {

		if ( $old_url === '' ) {
			return;
		}

		update_event_meta( $event_id, 'online_provider', '' );
		update_event_meta( $event_id, 'custom_link_url', '' );

		self::record_previous( $result, self::PROPERTY, $old_url );

		$result['applied'][] = __( 'Removed the event link.', 'sugar-calendar-lite' );
	}

	/**
	 * Whether a URL is safe to store as an event link.
	 *
	 * Accepts any well-formed http(s) URL with a host, as the editor does
	 * (`OnlineMeetingSection::save()` uses only `esc_url_raw()`). Deliberately
	 * NOT `wp_http_validate_url()`: it also resolves the host over DNS and
	 * refuses one that doesn't, or that resolves to a private/loopback range,
	 * which refused the schema's own example host and any intranet meeting
	 * link (B7).
	 *
	 * @since 3.15.0
	 *
	 * @param string $url URL, raw or `esc_url_raw()`-cleaned.
	 *
	 * @return bool
	 */
	private static function is_acceptable_url( $url ): bool {

		$parts = wp_parse_url( trim( (string) $url ) );

		return is_array( $parts )
			&& in_array( strtolower( $parts['scheme'] ?? '' ), [ 'http', 'https' ], true )
			&& ! empty( $parts['host'] )
			&& ! preg_match( '/\s/', $parts['host'] );
	}

	/**
	 * Write "Show to" only when the caller sent it, never defaulting it.
	 *
	 * Used where a link write was refused or was itself a no-op: the event's
	 * meeting (if any) did not change, so there is nothing to default.
	 *
	 * @since 3.15.0
	 *
	 * @param int   $event_id Sugar Calendar event id.
	 * @param array $extra    Unwhitelisted input.
	 * @param array $result   Result so far, by reference.
	 *
	 * @return void
	 */
	private static function apply_sent_visibility( $event_id, array $extra, array &$result ) {

		if ( array_key_exists( self::VISIBILITY_PROPERTY, $extra ) ) {
			self::apply_visibility_request( $event_id, $extra, $result );
		}
	}

	/**
	 * Handle a "Show to" request on its own, with no link in the same patch.
	 *
	 * Stored even when the event has no meeting yet, but says so, rather than
	 * claiming a link is being shown when none exists (B8).
	 *
	 * @since 3.15.0
	 *
	 * @param int   $event_id Sugar Calendar event id.
	 * @param array $extra    Unwhitelisted input.
	 * @param array $result   Result so far, by reference.
	 *
	 * @return void
	 */
	private static function apply_visibility_request( $event_id, array $extra, array &$result ) {

		$wanted = isset( $extra[ self::VISIBILITY_PROPERTY ] ) ? (string) $extra[ self::VISIBILITY_PROPERTY ] : '';

		if ( ! in_array( $wanted, self::allowed_visibility(), true ) ) {
			$result['errors'][]    = __( 'Who the event link is shown to was not changed, because that setting only accepts everyone or people who signed up.', 'sugar-calendar-lite' );
			$result['explained'][] = self::VISIBILITY_PROPERTY;

			return;
		}

		$stored = (string) get_event_meta( $event_id, self::VISIBILITY_PROPERTY, true );

		self::store_visibility( $event_id, $wanted, $stored, $result );

		$result['applied'][] = self::visibility_label( $wanted, self::has_meeting( $event_id ) );
	}

	/**
	 * Resolve and store "Show to" for a link that just went from none to one.
	 *
	 * With no signup mode nobody is an attendee, so default to everyone even
	 * over a stored `attendees` — that value is the editor's own placeholder
	 * default on every save of a meeting-less event, not a deliberate choice
	 * (B9). An event with a signup mode keeps whatever is stored.
	 *
	 * The mode is read from the same request when it sends `signup`, not only
	 * `SignupModes::current()`: on `create-event`, or an `update-event` that
	 * switches modes in the same patch, RSVP's or ticketing's own contributor
	 * runs later on `sugar_calendar_events_event_writer_after_write` and would
	 * otherwise still see the event's pre-write "none" (final review I1).
	 *
	 * @since 3.15.0
	 *
	 * @param int   $event_id       Sugar Calendar event id.
	 * @param array $extra          Unwhitelisted input.
	 * @param bool  $has_visibility Whether the caller sent a "Show to" value.
	 * @param array $result         Result so far, by reference.
	 *
	 * @return string The visibility now in effect.
	 */
	private static function visibility_for_new_link( $event_id, array $extra, $has_visibility, array &$result ) {

		$stored = (string) get_event_meta( $event_id, self::VISIBILITY_PROPERTY, true );

		if ( $has_visibility ) {
			$wanted = (string) $extra[ self::VISIBILITY_PROPERTY ];

			if ( in_array( $wanted, self::allowed_visibility(), true ) ) {
				self::store_visibility( $event_id, $wanted, $stored, $result );

				return $wanted;
			}
		}

		$mode = array_key_exists( 'signup', $extra ) ? (string) $extra['signup'] : SignupModes::current( $event_id );

		if ( $mode === SignupModes::NONE ) {
			self::store_visibility( $event_id, OnlineMeeting::VISIBILITY_EVERYONE, $stored, $result );

			return OnlineMeeting::VISIBILITY_EVERYONE;
		}

		return $stored !== '' ? $stored : OnlineMeeting::VISIBILITY_ATTENDEES;
	}

	/**
	 * Resolve and store "Show to" for a link that already existed and changed.
	 *
	 * No signup-mode default here: the event already had a meeting, so a
	 * stored value (or the attendees default) is a real prior choice, not the
	 * editor's meeting-less placeholder.
	 *
	 * @since 3.15.0
	 *
	 * @param int   $event_id       Sugar Calendar event id.
	 * @param array $extra          Unwhitelisted input.
	 * @param bool  $has_visibility Whether the caller sent a "Show to" value.
	 * @param array $result         Result so far, by reference.
	 *
	 * @return string The visibility now in effect.
	 */
	private static function visibility_for_changed_link( $event_id, array $extra, $has_visibility, array &$result ) {

		$stored = (string) get_event_meta( $event_id, self::VISIBILITY_PROPERTY, true );

		if ( $has_visibility ) {
			$wanted = (string) $extra[ self::VISIBILITY_PROPERTY ];

			if ( in_array( $wanted, self::allowed_visibility(), true ) ) {
				self::store_visibility( $event_id, $wanted, $stored, $result );

				return $wanted;
			}
		}

		return $stored !== '' ? $stored : OnlineMeeting::VISIBILITY_ATTENDEES;
	}

	/**
	 * Write "Show to" when it actually changed, and report `previous`.
	 *
	 * @since 3.15.0
	 *
	 * @param int    $event_id Sugar Calendar event id.
	 * @param string $wanted   Value to store.
	 * @param string $stored   Value before this write.
	 * @param array  $result   Result so far, by reference.
	 *
	 * @return void
	 */
	private static function store_visibility( $event_id, $wanted, $stored, array &$result ) {

		if ( $wanted === $stored ) {
			return;
		}

		update_event_meta( $event_id, self::VISIBILITY_PROPERTY, $wanted );

		// Undo replays this through the enum, which refuses "". Unset reads as attendees anyway.
		self::record_previous( $result, self::VISIBILITY_PROPERTY, $stored !== '' ? $stored : OnlineMeeting::VISIBILITY_ATTENDEES );
	}

	/**
	 * Whether the event currently has any meeting (provider or custom).
	 *
	 * @since 3.15.0
	 *
	 * @param int $event_id Sugar Calendar event id.
	 *
	 * @return bool
	 */
	private static function has_meeting( $event_id ): bool {

		return (string) get_event_meta( $event_id, 'online_provider', true ) !== '';
	}

	/**
	 * The two values `online_visibility` accepts.
	 *
	 * @since 3.15.0
	 *
	 * @return string[]
	 */
	private static function allowed_visibility(): array {

		return [ OnlineMeeting::VISIBILITY_EVERYONE, OnlineMeeting::VISIBILITY_ATTENDEES ];
	}

	/**
	 * Sentence for a "Show to" write on its own (B8: says so when there is no
	 * meeting yet to show).
	 *
	 * @since 3.15.0
	 *
	 * @param string $wanted      Value written.
	 * @param bool   $has_meeting Whether the event already has a meeting.
	 *
	 * @return string
	 */
	private static function visibility_label( $wanted, $has_meeting ): string {

		if ( ! $has_meeting ) {
			return sprintf(
				/* translators: %s: who will see the link once one exists, "everyone" or "people who signed up". */
				__( 'Saved: once the event has an online link, it will be shown to %s.', 'sugar-calendar-lite' ),
				$wanted === OnlineMeeting::VISIBILITY_EVERYONE
					? __( 'everyone', 'sugar-calendar-lite' )
					: __( 'people who signed up', 'sugar-calendar-lite' )
			);
		}

		return $wanted === OnlineMeeting::VISIBILITY_EVERYONE
			? __( 'The link is shown to everyone.', 'sugar-calendar-lite' )
			: __( 'The link is shown to people who signed up.', 'sugar-calendar-lite' );
	}

	/**
	 * Sentence for a link write, naming who sees it (every link write says so).
	 *
	 * @since 3.15.0
	 *
	 * @param bool   $is_add     Whether the event had no link before this write.
	 * @param string $visibility Visibility now in effect.
	 *
	 * @return string
	 */
	private static function link_written_message( $is_add, $visibility ): string {

		$target = $visibility === OnlineMeeting::VISIBILITY_EVERYONE
			? __( 'Everyone can see it.', 'sugar-calendar-lite' )
			: __( 'Only people who signed up can see it.', 'sugar-calendar-lite' );

		if ( $is_add ) {
			/* translators: %s: who can see the link, for example Everyone can see it. */
			return sprintf( __( 'Added the event link. %s', 'sugar-calendar-lite' ), $target );
		}

		/* translators: %s: who can see the link, for example Everyone can see it. */
		return sprintf( __( 'Changed the event link. %s', 'sugar-calendar-lite' ), $target );
	}

	/**
	 * Report a field's previous value in the shape undo replays.
	 *
	 * @since 3.15.0
	 *
	 * @param array  $result    Result so far, by reference.
	 * @param string $key       Input property name.
	 * @param mixed  $old_value Value before this write.
	 *
	 * @return void
	 */
	private static function record_previous( array &$result, $key, $old_value ) {

		if ( ! array_key_exists( 'previous', $result ) || array_key_exists( $key, $result['previous'] ) ) {
			return;
		}

		$result['previous'][ $key ] = $old_value;
	}

	/**
	 * Add the stored Custom Link and "Show to" setting to the post-write snapshot.
	 *
	 * A provider meeting's join URL is not the custom link, so it is never exposed here.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $snapshot Snapshot so far.
	 * @param mixed $event_id Sugar Calendar event id.
	 *
	 * @return mixed
	 */
	public static function snapshot( $snapshot, $event_id ) {

		if ( ! is_array( $snapshot ) ) {
			return $snapshot;
		}

		$event_id = (int) $event_id;
		$provider = (string) get_event_meta( $event_id, 'online_provider', true );

		$snapshot[ self::PROPERTY ]            = $provider === OnlineMeeting::PROVIDER_CUSTOM
			? (string) get_event_meta( $event_id, 'custom_link_url', true )
			: '';
		$snapshot[ self::VISIBILITY_PROPERTY ] = (string) get_event_meta( $event_id, 'online_visibility', true );

		return $snapshot;
	}
}
