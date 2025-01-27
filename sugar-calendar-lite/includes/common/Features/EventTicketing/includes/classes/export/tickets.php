<?php
/**
 * Tickets Export Class
 *
 * This class handles exporting ticket data.
 *
 * @since 1.0.0
 */
namespace Sugar_Calendar\AddOn\Ticketing\Export;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Database as Database;
use Sugar_Calendar\AddOn\Ticketing\Common\Functions as Functions;

/**
 * Tickets_Export Class
 *
 * @since 1.0
 */
class Tickets_Export extends CSV_Export {

	/**
	 * Our export type. Used for export-type specific filters/actions
	 *
	 * @var string
	 * @since 1.0
	 */
	public $export_type = 'tickets';

	/**
	 * Set the CSV columns
	 *
	 * @since 1.0
	 * @return array All the columns
	 */
	public function csv_cols() {

		// Setup column names
		$retval = array(

			// Ticket
			'id'                  => esc_html__( 'Ticket ID',           'sugar-calendar-lite' ),
			'code'                => esc_html__( 'Ticket Code',         'sugar-calendar-lite' ),

			// Order
			'order_id'            => esc_html__( 'Order ID',            'sugar-calendar-lite' ),

			// Event
			'event_id'            => esc_html__( 'Event ID',            'sugar-calendar-lite' ),
			'event_name'          => esc_html__( 'Event Name',          'sugar-calendar-lite' ),
			'event_start_date'    => esc_html__( 'Event Start Date',    'sugar-calendar-lite' ),
			'event_start_time'    => esc_html__( 'Event Start Time',    'sugar-calendar-lite' ),
			'event_end_date'      => esc_html__( 'Event End Date',      'sugar-calendar-lite' ),
			'event_end_time'      => esc_html__( 'Event End Time',      'sugar-calendar-lite' ),

			// Attendee
			'attendee_id'         => esc_html__( 'Attendee ID',         'sugar-calendar-lite' ),
			'attendee_first_name' => esc_html__( 'Attendee First Name', 'sugar-calendar-lite' ),
			'attendee_last_name'  => esc_html__( 'Attendee Last Name',  'sugar-calendar-lite' ),
			'attendee_email'      => esc_html__( 'Attendee Email',      'sugar-calendar-lite' )
		);

		// Return
		return $retval;
	}

	/**
	 * Retrieves the data being exported.
	 *
	 * @since  1.0
	 *
	 * @param array $args Array of query arguments
	 * @return array Data for Export
	 */
	public function get_data( $args = array() ) {

		// Query for Tickets
		$this->query = new Database\Ticket_Query( $args );

		// Bail if no Tickets
		if ( empty( $this->query->items ) ) {
			return array();
		}

		// Default return value
		$retval = array();

		// Get formats early (outside of loop)
		$date_format = sc_get_date_format();
		$time_format = sc_get_time_format();

		// Loop through Tickets
		foreach ( $this->query->items as $key => $ticket ) {

			// Reset Attendee data
			$attendee_id = $first_name = $last_name = $email = '';
			$attendee = false;

			// Reset Event data
			$event_name = $event_id = '';
			$event_start_date = $event_start_time = '';
			$event_end_date = $event_end_time = '';

			// Attendee for Ticket
			if ( ! empty( $ticket->attendee_id ) ) {

				// Query for Attendee
				$attendee = Functions\get_attendee( $ticket->attendee_id );

				// Format Attendee data
				if ( ! empty( $attendee ) ) {
					$attendee_id = $attendee->id;
					$first_name  = $attendee->first_name;
					$last_name   = $attendee->last_name;
					$email       = $attendee->email;
				}
			}

			// Event for Ticket
			if ( ! empty( $ticket->event_id ) ) {

				// Query for Event
				$event = sugar_calendar_get_event( $ticket->event_id );

				// Format Event data
				if ( ! empty( $event ) ) {
					$event_id         = $event->id;
					$event_name       = $event->title;
					$event_start_date = date_i18n( $date_format, strtotime( $event->start ) );
					$event_start_time = date_i18n( $time_format, strtotime( $event->start ) );
					$event_end_date   = date_i18n( $date_format, strtotime( $event->end ) );
					$event_end_time   = date_i18n( $time_format, strtotime( $event->end ) );
				}
			}

			// Create the row to export
			$retval[ $key ] = array(

				// Ticket
				'id'                  => $ticket->id,
				'code'                => $ticket->code,

				// Order
				'order_id'            => $ticket->order_id,

				// Event
				'event_id'            => $event_id,
				'event_name'          => $event_name,
				'event_start_date'    => $event_start_date,
				'event_start_time'    => $event_start_time,
				'event_end_date'      => $event_end_date,
				'event_end_time'      => $event_end_time,

				// Attendee
				'attendee_id'         => $attendee_id,
				'attendee_first_name' => $first_name,
				'attendee_last_name'  => $last_name,
				'attendee_email'      => $email
			);
		}

		// Return
		return $retval;
	}
}
