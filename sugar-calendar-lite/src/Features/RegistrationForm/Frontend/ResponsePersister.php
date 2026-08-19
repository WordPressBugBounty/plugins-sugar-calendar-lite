<?php

namespace Sugar_Calendar\Features\RegistrationForm\Frontend;

use Sugar_Calendar\Features\RegistrationForm\ResponseRepository;
use Sugar_Calendar\Features\RegistrationForm\WriteFailureLog;

/**
 * Writes response rows.
 *
 * Every row carries its attendee_key, which disambiguates a NULL attendee_id:
 * 'main' is the purchaser, while 'a{n}' with no attendee_id is a real case,
 * an anonymous attendee whose row never produced an sc_attendees record.
 *
 * @since 3.13.0
 */
class ResponsePersister {

	/**
	 * Insert response rows.
	 *
	 * Runs after the charge, so a failed row can't fail the request or be
	 * retried; it's logged via WriteFailureLog for Admin\WriteFailureNotice
	 * to surface to the site owner instead of silently vanishing.
	 *
	 * @since 3.13.0
	 *
	 * @param array[] $rows Each row: event_id, context, context_id, attendee_key,
	 *                      attendee_id|null, answers, status.
	 *
	 * @return int[] Inserted row ids.
	 */
	public static function persist( array $rows ) {

		$ids = [];

		foreach ( $rows as $row ) {

			$id = ResponseRepository::insert( $row );

			if ( is_wp_error( $id ) ) {
				WriteFailureLog::record( $row, $id );

				continue;
			}

			$ids[] = (int) $id;
		}

		return $ids;
	}
}
