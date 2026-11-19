<?php

namespace Sugar_Calendar\Admin\Dashboard\Widgets;

use Sugar_Calendar\AddOn\Ticketing\Renderer as TicketingRenderer;
use Sugar_Calendar\Admin\Dashboard\AccessContext;
use Sugar_Calendar\Admin\Dashboard\Templates;
use Sugar_Calendar\Admin\Dashboard\WidgetState;
use Sugar_Calendar\Admin\Pages\EventNew;
use Sugar_Calendar\Event;
use Sugar_Calendar\Features\FeaturedEvents\Common\Featured;
use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\Helpers\TimezoneFormat;

/**
 * The next events, with capacity.
 *
 * @since 3.18.0
 */
class UpcomingEvents extends AbstractWidget {

	/**
	 * Column.
	 *
	 * @since 3.18.0
	 */
	const COLUMN = 'sidebar';

	/**
	 * Sort position.
	 *
	 * @since 3.18.0
	 */
	const ORDER = 20;

	/**
	 * Events shown.
	 *
	 * @since 3.18.0
	 */
	const LIMIT = 5;

	/**
	 * Events read once per page: the state, the body and its padding all ask.
	 *
	 * @since 3.18.0
	 *
	 * @var Event[]|null
	 */
	private $events;

	/**
	 * Widget identifier.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'upcoming_events';
	}

	/**
	 * Widget title.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_title(): string {

		return __( 'Upcoming Events', 'sugar-calendar-lite' );
	}

	/**
	 * `data`, or `empty` when nothing is coming up.
	 *
	 * @since 3.18.0
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 */
	public function get_state( AccessContext $access ): WidgetState {

		return new WidgetState( true, empty( $this->get_events() ) ? 'empty' : 'data' );
	}

	/**
	 * Render the list or the empty state.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Range data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 */
	protected function render_body( string $variant, array $data, AccessContext $access ): string {

		return Templates::get_html(
			'widgets/upcoming-events',
			[
				'events'        => array_map( [ $this, 'prepare' ], $this->get_events() ),
				'new_event_url' => admin_url( EventNew::get_slug() ),
			]
		);
	}

	/**
	 * The list pads itself; the empty state keeps the body padding.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Range data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return bool
	 */
	protected function is_body_flush( string $variant, array $data, AccessContext $access ): bool {

		return ! empty( $this->get_events() );
	}

	/**
	 * Published events starting today or later, soonest first; on Pro, recurring dates too. An
	 * event that started on an earlier day is left out while it runs, or it would head the list
	 * under its old start date.
	 *
	 * @since 3.18.0
	 *
	 * @return Event[]
	 */
	protected function get_events(): array {

		if ( $this->events === null ) {
			// The query returns one event more than asked for.
			$this->events = array_slice(
				BaseHelpers::get_upcoming_events_list_with_recurring(
					[
						'number'              => self::LIMIT,
						'starting_from_today' => true,
					],
					[]
				),
				0,
				self::LIMIT
			);
		}

		return $this->events;
	}

	/**
	 * Edit screen of the event's post.
	 *
	 * @since 3.18.0
	 *
	 * @param Event $event Event.
	 *
	 * @return string Empty for a post the user can't open in the editor.
	 */
	protected function get_event_edit_url( Event $event ): string {

		return (string) get_edit_post_link( (int) $event->object_id, 'raw' );
	}

	/**
	 * The event's Address, on one line.
	 *
	 * @since 3.18.0
	 *
	 * @param Event $event Event.
	 *
	 * @return string
	 */
	protected function get_place( Event $event ): string {

		// The Address is event meta, which Event::__get() reads the same way.
		return trim( preg_replace( '/\s+/', ' ', (string) get_event_meta( $event->id, 'location', true ) ) );
	}

	/**
	 * Whether the row is one date of a recurring series. Lite lists none.
	 *
	 * @since 3.18.0
	 *
	 * @param Event $event Event.
	 *
	 * @return bool
	 */
	protected function is_recurring( Event $event ): bool {

		return false;
	}

	/**
	 * Tickets sold and the capacity (0 without a limit), as the Events list counts them.
	 *
	 * @since 3.18.0
	 *
	 * @param Event $event Event.
	 *
	 * @return array `sold` and `capacity`, or empty when the event sells no tickets.
	 */
	protected function get_count( Event $event ): array {

		$stats = ( new TicketingRenderer( $event ) )->get_extra_data();

		if ( empty( $stats ) ) {
			return [];
		}

		return [
			'sold'     => absint( $stats['tickets_purchased'] ),
			'capacity' => absint( $stats['ticket_total'] ),
		];
	}

	/**
	 * Display row for one event.
	 *
	 * @since 3.18.0
	 *
	 * @param Event $event Event from get_events().
	 *
	 * @return array
	 */
	private function prepare( Event $event ): array {

		$count = $this->get_count( $event );

		if ( ! empty( $count ) ) {
			$count['is_sold_out'] = $count['capacity'] > 0 && $count['sold'] >= $count['capacity'];
		}

		// The stored start, unconverted: event times read as entered.
		return [
			'month'        => sugar_calendar_format_date_i18n( 'M', $event->start ),
			'day'          => sugar_calendar_format_date_i18n( 'd', $event->start ),
			'name'         => (string) $event->title !== '' ? $event->title : __( '(No title)', 'sugar-calendar-lite' ),
			'url'          => $this->get_event_edit_url( $event ),
			'meta'         => $this->get_meta( $event ),
			'is_recurring' => $this->is_recurring( $event ),
			'is_featured'  => Featured::is_featured( $event ),
			'count'        => $count,
		];
	}

	/**
	 * The line under the name: weekday, time and zone, then the place.
	 *
	 * @since 3.18.0
	 *
	 * @param Event $event Event.
	 *
	 * @return string
	 */
	private function get_meta( Event $event ): string {

		if ( $event->is_all_day() ) {
			$time = __( 'All Day', 'sugar-calendar-lite' );
		} else {
			// The stored time has no zone, so a zone character in the format would print "UTC".
			$format = TimezoneFormat::strip( sc_get_time_format() );
			$time   = trim( sugar_calendar_format_date_i18n( $format, $event->start ) );

			if ( ! empty( $event->start_tz ) ) {
				$time = TimezoneFormat::append( $time, $event->start_tz );
			}
		}

		/* translators: 1: Abbreviated weekday, 2: Start time. */
		$meta  = sprintf( _x( '%1$s, %2$s', 'Upcoming event weekday and time', 'sugar-calendar-lite' ), sugar_calendar_format_date_i18n( 'D', $event->start ), $time );
		$place = $this->get_place( $event );

		if ( $place !== '' ) {
			/* translators: 1: Weekday and start time, 2: Venue name or address. */
			$meta = sprintf( _x( '%1$s · %2$s', 'Upcoming event time and venue', 'sugar-calendar-lite' ), $meta, $place );
		}

		return $meta;
	}
}
