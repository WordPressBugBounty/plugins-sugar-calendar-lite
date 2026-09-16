<?php

namespace Sugar_Calendar\AddOn\Ticketing\Frontend;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions;
use Sugar_Calendar\AddOn\Ticketing\Database\Attendee;
use Sugar_Calendar\AddOn\Ticketing\Database\Order;
use Sugar_Calendar\AddOn\Ticketing\Database\Ticket;
use Sugar_Calendar\Event;
use Sugar_Calendar\Features\RegistrationForm\RespondentNaming;
use Sugar_Calendar\Features\RegistrationForm\ResponseRepository;
use Sugar_Calendar\Features\RegistrationForm\SchemaRepository;

/**
 * The data the ticket details card renders, and nothing else.
 *
 * Every lookup, filter and formatted string happens in from_ticket(), so
 * `DetailsCard` only turns this object into markup and a preview can fill one
 * in by hand.
 *
 * @since 3.14.0
 */
final class DetailsCardData {

	/**
	 * Outer wrapper classes.
	 *
	 * @since 3.14.0
	 *
	 * @var string[]
	 */
	public array $classes = [ 'sc-ticket-card', 'sc-ticket-card--details' ];

	/**
	 * Whether this is the print view. True omits the button row.
	 *
	 * @since 3.14.0
	 *
	 * @var bool
	 */
	public bool $printing = false;

	/**
	 * Image URL, or '' for none.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $image = '';

	/**
	 * Event title.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $event_title = '';

	/**
	 * Event start month, short form, for the date badge.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $event_month = '';

	/**
	 * Event start day of month, for the date badge.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $event_day = '';

	/**
	 * Formatted event start time.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $event_time = '';

	/**
	 * Event permalink.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $event_url = '';

	/**
	 * Venue name, or '' when the event has none.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $location_name = '';

	/**
	 * Venue link, or '' to show the name as plain text.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $location_url = '';

	/**
	 * Street address, or '' when none.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $location_address = '';

	/**
	 * The event itself, or null when it could not be resolved. The card only
	 * passes it to the actions hook; it reads no field from it.
	 *
	 * @since 3.14.0
	 *
	 * @var Event|null
	 */
	public ?Event $event = null;

	/**
	 * Attendee name, or '' for none.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $attendee_name = '';

	/**
	 * Ticket type name, or '' for none.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $ticket_type = '';

	/**
	 * Ticket code.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $code = '';

	/**
	 * Formatted ticket cost, or '' when unresolvable.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public string $cost = '';

	/**
	 * Answered registration questions, each in the shape of answer_defaults().
	 *
	 * @since 3.14.0
	 *
	 * @var array[]
	 */
	public array $registration = [];

	/**
	 * Build the details card data for a live ticket.
	 *
	 * @since 3.14.0
	 *
	 * @param Order      $order   The order.
	 * @param Ticket     $ticket  The ticket.
	 * @param Event|null $event   The event. Resolved from the ticket when null. A trashed or
	 *                            unpublished event is treated as missing.
	 * @param string     $context Where the card is drawn: `page` or `print`.
	 *
	 * @return self
	 */
	public static function from_ticket( Order $order, Ticket $ticket, ?Event $event = null, string $context = 'page' ): self {

		if ( $event === null ) {
			/** This filter is documented in includes/common/shortcodes.php */
			$event = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- public since 3.6.0; renaming would break site snippets.
				'sc_et_ticket_shortcode_event',
				sugar_calendar_get_event( $ticket->event_id ),
				$ticket
			);
		}

		/**
		 * Filters the attendee whose name heads the icon rows.
		 *
		 * @since 3.14.0
		 *
		 * @param Attendee|false $attendee The ticket's attendee, or false when it has none.
		 * @param Ticket         $ticket   The ticket.
		 */
		$attendee = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_ticketing_details_card_attendee',
			empty( $ticket->attendee_id ) ? false : Functions\get_attendee( $ticket->attendee_id ),
			$ticket
		);

		$attendee_name = empty( $attendee )
			? ''
			: trim( $attendee->first_name . ' ' . $attendee->last_name );

		if ( $attendee_name === '' && ! empty( $attendee->email ) ) {
			$attendee_name = $attendee->email;
		}

		$type = self::ticket_type( $ticket );

		$data = new self();

		$data->printing      = ( $context === 'print' );
		$data->attendee_name = $attendee_name;
		$data->ticket_type   = (string) $type['name'];
		$data->code          = (string) $ticket->code;
		$data->cost          = $type['price'] === null ? '' : wp_strip_all_tags( Functions\display_price( $type['price'] ) );

		// A deleted or unpublished event leaves the image, event block and answers out; the ticket still shows.
		if ( ! $event instanceof Event || ! is_post_publicly_viewable( $event->object_id ) ) {
			return $data;
		}

		/**
		 * Filters the classes on the details card's outer wrapper.
		 *
		 * @since 3.14.0
		 *
		 * @param string[] $classes Class names.
		 * @param object   $ticket  The ticket being shown.
		 * @param object   $event   The ticket's event.
		 */
		$classes = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_ticketing_details_card_class',
			[ 'sc-ticket-card', 'sc-ticket-card--details' ],
			$ticket,
			$event
		);

		/**
		 * Filters the image shown at the top of the card.
		 *
		 * @since 3.14.0
		 *
		 * @param string $url   Featured image URL, or '' when the event has none.
		 * @param Event  $event The event.
		 */
		$image = (string) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_ticketing_details_card_image',
			(string) get_the_post_thumbnail_url( $event->object_id, 'large' ),
			$event
		);

		/** This filter is documented in includes/common/shortcodes.php */
		$event_url = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- public since 3.6.0; renaming would break site snippets.
			'sc_et_ticket_shortcode_event_url',
			get_permalink( $event->object_id ),
			$event,
			$ticket
		);

		$location = self::location( $event, $ticket );

		$data->classes          = (array) $classes;
		$data->image            = $image;
		$data->event            = $event;
		$data->event_title      = (string) $event->title;
		$data->event_month      = $event->format_date( 'M', $event->start );
		$data->event_day        = $event->format_date( 'j', $event->start );
		$data->event_time       = Functions\get_event_start_time( $event );
		$data->event_url        = (string) $event_url;
		$data->location_name    = (string) $location['name'];
		$data->location_url     = (string) $location['url'];
		$data->location_address = (string) $location['address'];
		$data->registration     = self::registration( $order, $ticket, $event );

		return $data;
	}

	/**
	 * Whether the event behind this ticket still exists and is publicly viewable.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public function has_valid_event(): bool {

		return $this->event instanceof Event;
	}

	/**
	 * The empty shape of one entry in $registration.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function answer_defaults(): array {

		return [
			'label'  => '',
			'answer' => '',
		];
	}

	/**
	 * The location shown under the event title, in three parts.
	 *
	 * Mirrors the single-event page: Lite renders the event's own location string,
	 * and Pro Venues replaces it with the venue when the event has one. Split in
	 * three because the design styles a venue name as an accent link and the
	 * address as plain grey text after a separator.
	 *
	 * @since 3.14.0
	 *
	 * @param Event  $event  The event.
	 * @param Ticket $ticket The ticket.
	 *
	 * @return array{name:string,url:string,address:string}
	 */
	private static function location( $event, $ticket ) {

		$location = [
			'name'    => '',
			'url'     => '',
			'address' => '',
		];

		if ( ! empty( $event->location ) ) {
			$location['address'] = (string) $event->location;
		}

		/**
		 * Filters the location name, link and address shown under the event title.
		 *
		 * Pro Venues subscribes to swap in the venue when the event has one.
		 *
		 * @since 3.14.0
		 *
		 * @param array  $location Keys: name, url, address. Omitted keys keep their default.
		 * @param object $event    The event.
		 * @param object $ticket   The ticket.
		 */
		$filtered = apply_filters( 'sugar_calendar_ticketing_details_location', $location, $event, $ticket ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		return wp_parse_args( (array) $filtered, $location );
	}

	/**
	 * The ticket's type name and price, after the public filter.
	 *
	 * @since 3.14.0
	 *
	 * @param Ticket $ticket The ticket.
	 *
	 * @return array Keys: name (string), price (float|null).
	 */
	private static function ticket_type( $ticket ) {

		$defaults = RespondentNaming::ticket_type(
			empty( $ticket->event_id ) ? 0 : (int) $ticket->event_id,
			empty( $ticket->ticket_type_id ) ? 0 : (int) $ticket->ticket_type_id
		);

		/**
		 * Filters the ticket type name and price shown on the card.
		 *
		 * @since 3.14.0
		 *
		 * @param array  $type   Keys: name (string), price (float|null). Omitted keys keep their default.
		 * @param Ticket $ticket The ticket.
		 */
		$filtered = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_ticketing_details_card_ticket_type',
			$defaults,
			$ticket
		);

		return wp_parse_args( (array) $filtered, $defaults );
	}

	/**
	 * This ticket's attendee's answers, picked out of the order's responses.
	 *
	 * Matches on `attendee_id`, the attendee row id checkout stored, NOT the
	 * ticket id. No match means no answers: falling back to another row would
	 * print one person's answers on another's ticket.
	 *
	 * @since 3.14.0
	 *
	 * @param Order  $order  The order.
	 * @param Ticket $ticket The ticket.
	 *
	 * @return array Field id => answer, empty when nothing matches.
	 */
	private static function answers_for_ticket( $order, $ticket ) {

		if ( empty( $ticket->attendee_id ) ) {
			return [];
		}

		foreach ( (array) ResponseRepository::get_for_order( $order->id ) as $row ) {
			$row = (array) $row;

			if ( (int) ( $row['attendee_id'] ?? 0 ) === (int) $ticket->attendee_id ) {
				return (array) ( $row['answers'] ?? [] );
			}
		}

		return [];
	}

	/**
	 * The Registration Details rows: each answered question with its answer.
	 *
	 * @since 3.14.0
	 *
	 * @param Order  $order  The order.
	 * @param Ticket $ticket The ticket.
	 * @param Event  $event  The event.
	 *
	 * @return array[] Each with label and answer; empty when there is nothing to show.
	 */
	private static function registration( $order, $ticket, $event ) {

		$schema        = SchemaRepository::get( $event->id );
		$schema_fields = empty( $schema['fields'] ) ? [] : (array) $schema['fields'];

		/**
		 * Filters the registration questions shown on the card.
		 *
		 * @since 3.14.0
		 *
		 * @param array  $fields Field definitions, each carrying `id` and `label`.
		 * @param Ticket $ticket The ticket.
		 * @param Event  $event  The event.
		 */
		$fields = (array) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_ticketing_details_card_registration_fields',
			$schema_fields,
			$ticket,
			$event
		);

		if ( empty( $fields ) ) {
			return [];
		}

		/**
		 * Filters this attendee's answers, keyed by field id.
		 *
		 * Read after the fields check so an event with no form still costs no query.
		 *
		 * @since 3.14.0
		 *
		 * @param array  $answers Answers keyed by field id.
		 * @param Ticket $ticket  The ticket.
		 * @param Event  $event   The event.
		 */
		$answers = (array) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_ticketing_details_card_registration_answers',
			self::answers_for_ticket( $order, $ticket ),
			$ticket,
			$event
		);

		return self::pair_answers( $fields, $answers );
	}

	/**
	 * Pair each field with its answer, dropping unanswered ones.
	 *
	 * @since 3.14.0
	 *
	 * @param array $fields  Field definitions, each carrying `id` and `label`.
	 * @param array $answers Answers keyed by field id.
	 *
	 * @return array[] Each with label and answer.
	 */
	private static function pair_answers( array $fields, array $answers ) {

		$rows = [];

		foreach ( $fields as $field ) {
			$id = empty( $field['id'] ) ? '' : (string) $field['id'];

			if ( $id === '' || ! isset( $answers[ $id ] ) ) {
				continue;
			}

			// A checkbox field stores an array; everything else a scalar.
			$answer = is_array( $answers[ $id ] )
				? implode( ', ', array_map( 'strval', $answers[ $id ] ) )
				: (string) $answers[ $id ];

			if ( trim( $answer ) === '' ) {
				continue;
			}

			$rows[] = [
				'label'  => (string) ( $field['label'] ?? '' ),
				'answer' => $answer,
			];
		}

		return $rows;
	}
}
