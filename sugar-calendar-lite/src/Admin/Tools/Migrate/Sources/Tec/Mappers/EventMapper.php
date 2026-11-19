<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Mappers;

use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data\TecEvent;
use WP_Post;

/**
 * Turns a TEC event into the Sugar Calendar event it becomes, without its repeat.
 *
 * @since 3.18.0
 */
class EventMapper {

	/**
	 * An all-day event's start and end without TEC's end-of-day cutoff.
	 *
	 * With the cutoff at 06:00, TEC saves a one-day event as 06:00 to 05:59:59 the next
	 * day; Sugar Calendar would show that as two days. TEC saves the start at the cutoff,
	 * so the start time gives the shift, also for events saved before the setting changed.
	 *
	 * @since 3.18.0
	 *
	 * @param string $start   TEC `_EventStartDate`.
	 * @param string $end     TEC `_EventEndDate`.
	 * @param bool   $all_day Whether the event is all day.
	 *
	 * @return string[] Start and end.
	 */
	public static function get_all_day_dates( $start, $end, $all_day ) {

		$start_time = strtotime( "{$start} UTC" );
		$end_time   = strtotime( "{$end} UTC" );

		if ( ! $all_day || empty( $start_time ) || empty( $end_time ) ) {
			return [ $start, $end ];
		}

		$shift = $start_time % DAY_IN_SECONDS;

		return [
			gmdate( 'Y-m-d 00:00:00', $start_time - $shift ),
			gmdate( 'Y-m-d 23:59:59', $end_time - $shift ),
		];
	}

	/**
	 * The Sugar Calendar event a TEC event becomes, without its repeat.
	 *
	 * @since 3.18.0
	 *
	 * @param TecEvent     $tec_event   The TEC event.
	 * @param string|false $location    The venue's address, false when the event has no venue.
	 * @param int          $sc_venue_id The migrated venue's ID, 0 for none.
	 *
	 * @return array
	 */
	public static function map( TecEvent $tec_event, $location, $sc_venue_id ) {

		$post = $tec_event->get_post();
		$data = self::get_post_fields( $post ) + [
			'post_id'           => $post->ID,
			'title'             => $post->post_title,
			'content'           => $post->post_content,
			'excerpt'           => $post->post_excerpt,
			// Sugar Calendar has no "ignored" status, so it'd hide the event.
			'status'            => $tec_event->is_ignored() ? 'draft' : $post->post_status,
			'featured'          => ! empty( $tec_event->get_meta( '_tribe_featured' ) ),
			'post_thumbnail_id' => get_post_thumbnail_id( $post->ID ),
			'all_day'           => $tec_event->is_all_day(),
			'start_date'        => $tec_event->get_start_date(),
			'end_date'          => $tec_event->get_end_date(),
			'start_tz'          => $tec_event->get_timezone(),
			'end_tz'            => $tec_event->get_timezone(),
		];

		return self::add_links( $data, $tec_event, $location, $sc_venue_id );
	}

	/**
	 * Add the location, website link, calendar and venue to the event data.
	 *
	 * @since 3.18.0
	 *
	 * @param array        $data        The event data.
	 * @param TecEvent     $tec_event   The TEC event.
	 * @param string|false $location    The venue's address, false when the event has no venue.
	 * @param int          $sc_venue_id The migrated venue's ID, 0 for none.
	 *
	 * @return array
	 */
	private static function add_links( $data, TecEvent $tec_event, $location, $sc_venue_id ) {

		if ( ! empty( $location ) ) {
			$data['location'] = $location;
		}

		if ( ! empty( $tec_event->get_url() ) ) {
			$data['url']        = $tec_event->get_url();
			$data['url_target'] = 1;
		}

		$data['calendars'] = [ absint( sugar_calendar_get_default_calendar() ) ];
		$data['venue_id']  = $sc_venue_id;

		return $data;
	}

	/**
	 * The TEC post fields the migrated event keeps: dates, password and author.
	 *
	 * Without the dates, WordPress publishes a scheduled event at once.
	 *
	 * @since 3.18.0
	 *
	 * @param WP_Post $tec_post TEC event post.
	 *
	 * @return array
	 */
	private static function get_post_fields( $tec_post ) {

		return [
			'post_date'     => $tec_post->post_date,
			'post_date_gmt' => $tec_post->post_date_gmt,
			'post_password' => $tec_post->post_password,
			'post_author'   => (int) $tec_post->post_author,
		];
	}
}
