<?php

namespace Sugar_Calendar\Integrations\Google;

use WP_Post;

/**
 * Applies one pulled Google item to Sugar Calendar: match it, map it, then create, update or trash the event.
 *
 * When both sides changed, the most recent edit wins. Recurring series are pulled whole.
 *
 * @since 3.16.0
 */
class GooglePullApplier {

	/**
	 * Event matcher.
	 *
	 * @since 3.16.0
	 *
	 * @var GoogleEventMatcher
	 */
	private $matcher;

	/**
	 * Inbound mapper.
	 *
	 * @since 3.16.0
	 *
	 * @var GoogleItemMapper
	 */
	private $mapper;

	/**
	 * Event writer.
	 *
	 * @since 3.16.0
	 *
	 * @var GooglePulledEventWriter
	 */
	private $writer;

	/**
	 * Push manager, for a local copy newer than Google's.
	 *
	 * @since 3.16.0
	 *
	 * @var GooglePusher
	 */
	private $pusher;

	/**
	 * Constructor.
	 *
	 * @since 3.16.0
	 *
	 * @param GoogleEventMatcher      $matcher Event matcher.
	 * @param GoogleItemMapper        $mapper  Inbound mapper.
	 * @param GooglePulledEventWriter $writer  Event writer.
	 * @param GooglePusher            $pusher  Push manager.
	 */
	public function __construct( GoogleEventMatcher $matcher, GoogleItemMapper $mapper, GooglePulledEventWriter $writer, GooglePusher $pusher ) {

		$this->matcher = $matcher;
		$this->mapper  = $mapper;
		$this->writer  = $writer;
		$this->pusher  = $pusher;
	}

	/**
	 * Apply one Google item.
	 *
	 * @since 3.16.0
	 *
	 * @param array  $item        Google event resource.
	 * @param string $calendar_id Google calendar id.
	 * @param bool   $full_sync   Whether the item belongs to the first full sync.
	 *
	 * @return void
	 */
	public function apply( array $item, string $calendar_id, bool $full_sync ) {

		if ( (string) ( $item['id'] ?? '' ) === '' ) {
			return;
		}

		$instance = null;

		if ( ! empty( $item['recurringEventId'] ) ) {
			$instance = $this->matcher->instance_target( $item, $calendar_id );

			if ( $instance === null ) {
				return;
			}

			// Without a rule the builder maps a one-off: recurrence fields empty, not a series.
			unset( $item['recurrence'] );
		}

		$this->match_and_apply( $item, $calendar_id, $full_sync, $instance );
	}

	/**
	 * Match one Google item to an event and apply it.
	 *
	 * @since 3.16.0
	 *
	 * @param array      $item        Google event resource.
	 * @param string     $calendar_id Google calendar id.
	 * @param bool       $full_sync   Whether the item belongs to the first full sync.
	 * @param array|null $instance    The series date the item replaces, null when it is not one date of a series.
	 *
	 * @return void
	 */
	private function match_and_apply( array $item, string $calendar_id, bool $full_sync, ?array $instance ) {

		$cancelled = ( $item['status'] ?? '' ) === 'cancelled';

		// Instances and cancelled dates carry the series' iCalUID (and instances our stamp), so only a link may match.
		$event = $instance || $cancelled ? $this->matcher->match_by_link( $item, $calendar_id ) : $this->matcher->match( $item, $calendar_id );

		if ( $cancelled ) {
			$handled = $this->apply_cancelled( $event, $item, $calendar_id );
		} else {
			$data    = $this->mapper->pulled_event_data( $item, $calendar_id, $full_sync, $instance !== null );
			$handled = $data !== null && $this->apply_item( $event, $data, $item, $calendar_id );
		}

		if ( $handled && $instance ) {
			/**
			 * Fire when Google moved or cancelled one date of a series.
			 *
			 * @since 3.16.0
			 *
			 * @param int    $series_event_id Series event id.
			 * @param string $original_date   The replaced date, Y-m-d in the series' zone.
			 */
			do_action( 'sugar_calendar_google_pulled_instance', $instance['series_id'], $instance['date'] ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		}
	}

	/**
	 * Trash the event holding a cancelled Google item.
	 *
	 * @since 3.16.0
	 *
	 * @param object|null $event       Matched event, or null.
	 * @param array       $item        Google event resource.
	 * @param string      $calendar_id Google calendar id.
	 *
	 * @return bool Always true: a cancelled item is handled whether or not an event held it.
	 */
	private function apply_cancelled( $event, array $item, string $calendar_id ): bool {

		$google_id = (string) $item['id'];

		if ( $event && ! empty( $event->object_id ) ) {
			$this->writer->trash_event( $event, $calendar_id, $google_id );
		}

		return true;
	}

	/**
	 * Create or update the event for a mapped Google item and store its link.
	 *
	 * @since 3.16.0
	 *
	 * @param object|null $event       Matched event, or null.
	 * @param array       $data        Mapped fields.
	 * @param array       $item        Google event resource.
	 * @param string      $calendar_id Google calendar id.
	 *
	 * @return bool False when the item could not be written.
	 */
	private function apply_item( $event, array $data, array $item, string $calendar_id ): bool {

		$rule = $this->pulled_rule( $data );

		if ( $event ) {
			$event_id = $this->apply_existing( $event, $data, $item, $calendar_id );

			// Nothing to write: Google's copy is unchanged, or the newer local copy went back to Google.
			if ( $event_id === null ) {
				return true;
			}
		} else {
			$event_id = $this->writer->create_event( $data, $item, $calendar_id );
		}

		if ( ! $event_id ) {
			return false;
		}

		$event = sugar_calendar_get_event( $event_id );

		$this->writer->apply_meet_link( $event, (string) ( $data['meet_code'] ?? '' ) );
		$this->writer->store_pulled_link( $event, $item, $calendar_id, $rule );

		return true;
	}

	/**
	 * Apply a Google item to an event already in Sugar Calendar.
	 *
	 * @since 3.16.0
	 *
	 * @param object $event       Existing event.
	 * @param array  $data        Mapped fields.
	 * @param array  $item        Google event resource.
	 * @param string $calendar_id Google calendar id.
	 *
	 * @return int|null Event id, 0 when the write failed, null when there was nothing to write.
	 */
	private function apply_existing( $event, array $data, array $item, string $calendar_id ): ?int {

		$link = $this->matcher->changed_link( $event, $item, $calendar_id );

		if ( $link === null ) {
			return null;
		}

		$post = get_post( (int) $event->object_id );

		// Most recent edit wins: an unpushed local change newer than Google's goes back to Google instead.
		if ( $post instanceof WP_Post && $link !== [] && $this->matcher->local_is_newer( $event, $post, $link, $item ) && $this->pusher->push_link( $event, $post, $calendar_id, $link ) ) {
			return null;
		}

		return $this->writer->update_event( $event, $data, $item, $calendar_id );
	}

	/**
	 * The link's rule owner after a pull: 'pushed' when the rule is held locally, 'google' when it is not, '' for a one-off.
	 *
	 * @since 3.16.0
	 *
	 * @param array $data Mapped fields.
	 *
	 * @return string
	 */
	private function pulled_rule( array $data ): string {

		if ( empty( $data['google_series'] ) ) {
			return '';
		}

		// Held locally, so a later local "Never" must clear it in Google too.
		if ( ( $data['recurrence'] ?? '' ) !== '' ) {
			return 'pushed';
		}

		return 'google';
	}
}
