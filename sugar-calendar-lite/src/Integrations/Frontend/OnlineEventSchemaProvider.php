<?php

namespace Sugar_Calendar\Integrations\Frontend;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions as TicketingFunctions;
use Sugar_Calendar\AddOn\Ticketing\Helpers\Helpers as TicketingHelpers;
use Sugar_Calendar\Event;
use Sugar_Calendar\Frontend\StructuredData\SchemaProviderInterface;
use Sugar_Calendar\Integrations\OnlineMeeting;

/**
 * Schema.org Event node for events that have an online meeting.
 *
 * Pure: returns one Event node (assoc array) or null. Returns null unless
 * the event has an online meeting (OnlineMeeting::for_event() non-null) and
 * carries the required schema fields (name + start date).
 *
 * @since 3.12.0
 */
class OnlineEventSchemaProvider implements SchemaProviderInterface {

	/**
	 * Build the online Event node, or null.
	 *
	 * @since 3.12.0
	 *
	 * @param Event $event The event being rendered.
	 *
	 * @return array|null
	 */
	public function get_schema( $event ) {

		$meeting = OnlineMeeting::for_event( $event );

		if ( $meeting === null ) {
			return null;
		}

		$name = trim( (string) $event->title );

		// Required fields — emit nothing rather than a partial Event.
		if ( $name === '' || empty( $event->start_dto ) ) {
			return null;
		}

		$permalink = get_permalink( $event->object_id );

		// get_permalink() is string|false; a falsey permalink yields an invalid
		// @id/url, so treat it as a missing required field.
		if ( empty( $permalink ) ) {
			return null;
		}

		$node = [
			'@type'               => 'Event',
			'@id'                 => $permalink . '#event',
			'name'                => $name,
			'eventStatus'         => 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode',
			'startDate'           => $this->format_date( $event, $event->start_dto ),
			'location'            => $this->online_location( $event, $meeting ),
			'organizer'           => [
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url(),
			],
			'url'                 => $permalink,
		];

		return array_merge( $node, $this->optional_fields( $event, $permalink ) );
	}

	/**
	 * Assemble the optional Event fields that are omitted when their source
	 * is empty (endDate, description, image, offers).
	 *
	 * @since 3.12.0
	 *
	 * @param Event  $event     The event.
	 * @param string $permalink The event permalink.
	 *
	 * @return array<string, mixed>
	 */
	private function optional_fields( $event, $permalink ) {

		$fields = [];

		if ( ! empty( $event->end_dto ) ) {
			$fields['endDate'] = $this->format_date( $event, $event->end_dto );
		}

		$description = wp_strip_all_tags( (string) $event->content );

		if ( $description !== '' ) {
			$fields['description'] = $description;
		}

		$image = get_the_post_thumbnail_url( $event->object_id, 'full' );

		if ( ! empty( $image ) ) {
			$fields['image'] = $image;
		}

		$offers = $this->offers( $event, $permalink );

		if ( $offers !== null ) {
			$fields['offers'] = $offers;
		}

		return $fields;
	}

	/**
	 * Build a single Offer node when the ticketing add-on is active and the
	 * event sells tickets. Returns null otherwise.
	 *
	 * Lite ticketing is one general ticket per event (no ticket types), so
	 * this returns a single Offer, never an array.
	 *
	 * @since 3.12.0
	 *
	 * @param Event  $event     The event.
	 * @param string $permalink The event permalink.
	 *
	 * @return array|null
	 */
	private function offers( $event, $permalink ) {

		// Guard the actual symbols used: class_exists() on the aliased FQN does
		// not autoload (safe on Lite, where the ticketing add-on is absent), and
		// the namespaced free functions only exist once the add-on's files load —
		// class presence alone does not prove they were defined.
		if (
			! class_exists( TicketingHelpers::class )
			|| ! function_exists( 'Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_available_tickets' )
			|| ! function_exists( 'Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_currency' )
		) {
			return null;
		}

		if ( ! TicketingHelpers::is_event_ticketing_enabled( $event ) ) {
			return null;
		}

		$price = (string) get_event_meta( $event->id, 'ticket_price', true );

		// get_available_tickets() returns -1 for unlimited; the filter it passes
		// through can yield a numeric string/float, so normalize to int: any
		// non-zero (negative=unlimited, positive=remaining) is in stock.
		$available = (int) TicketingFunctions\get_available_tickets( $event->id );

		return [
			'@type'         => 'Offer',
			'price'         => $price === '' ? '0' : $price,
			'priceCurrency' => TicketingFunctions\get_currency(),
			'availability'  => $available !== 0
				? 'https://schema.org/InStock'
				: 'https://schema.org/SoldOut',
			'url'           => $permalink,
		];
	}

	/**
	 * Format an event DateTime as ISO 8601, date-only for all-day events.
	 *
	 * @since 3.12.0
	 *
	 * @param Event    $event The event.
	 * @param DateTime $dto   The date object to format.
	 *
	 * @return string
	 */
	private function format_date( $event, $dto ) {

		return $event->is_all_day() ? $dto->format( 'Y-m-d' ) : $dto->format( 'c' );
	}

	/**
	 * Build the VirtualLocation node, honoring the join-link privacy rule.
	 *
	 * The real join_url is exposed ONLY when the meeting is public
	 * (visibility = everyone). For attendees-only meetings the event
	 * permalink is used instead — the gated link is never crawlable.
	 *
	 * @since 3.12.0
	 *
	 * @param Event $event   The event.
	 * @param array $meeting OnlineMeeting::for_event() details.
	 *
	 * @return array
	 */
	private function online_location( $event, $meeting ) {

		// $meeting is already resolved by the caller, so check visibility
		// directly rather than is_public() (which would re-run for_event()).
		$url = OnlineMeeting::visibility( $event ) === OnlineMeeting::VISIBILITY_EVERYONE
			? $meeting['join_url']
			: get_permalink( $event->object_id );

		return [
			'@type' => 'VirtualLocation',
			'url'   => $url,
		];
	}
}
