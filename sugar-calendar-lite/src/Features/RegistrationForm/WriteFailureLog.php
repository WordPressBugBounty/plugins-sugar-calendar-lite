<?php

namespace Sugar_Calendar\Features\RegistrationForm;

use WP_Error;

/**
 * Durable record of response rows that could not be stored.
 *
 * ResponsePersister::persist() runs after the charge, so a failed write can't
 * be retried; this survives the request in an option, read by
 * Admin\WriteFailureNotice. Stores only identifying fields and the bounded
 * error message, never the answers themselves.
 *
 * @since 3.13.0
 */
class WriteFailureLog {

	/**
	 * Option holding the log.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const OPTION_NAME = 'sugar_calendar_registration_form_write_failures';

	/**
	 * How many individual failures to keep.
	 *
	 * The newest are kept; `total` counts every failure ever recorded, so trimming
	 * cannot make the reported number smaller than the truth.
	 *
	 * @since 3.13.0
	 *
	 * @var int
	 */
	const MAX_ENTRIES = 20;

	/**
	 * Record one failed row write.
	 *
	 * @since 3.13.0
	 *
	 * @param array    $row   The row that could not be stored. Only its identifying
	 *                        fields are read, never `answers`.
	 * @param WP_Error $error The failure.
	 *
	 * @return void
	 */
	public static function record( array $row, WP_Error $error ) {

		$log = self::get();

		++$log['total'];

		$log['entries'][] = [
			'context'      => isset( $row['context'] ) ? sanitize_key( (string) $row['context'] ) : '',
			'context_id'   => isset( $row['context_id'] ) ? (int) $row['context_id'] : 0,
			'attendee_key' => isset( $row['attendee_key'] ) ? sanitize_key( (string) $row['attendee_key'] ) : '',
			'code'         => sanitize_key( (string) $error->get_error_code() ),
			'message'      => substr( sanitize_text_field( (string) $error->get_error_message() ), 0, 250 ),
			'at'           => current_time( 'mysql', true ),
		];

		if ( count( $log['entries'] ) > self::MAX_ENTRIES ) {
			$log['entries'] = array_slice( $log['entries'], - self::MAX_ENTRIES );
		}

		// autoload = false: only read on an admin page, keep it out of alloptions.
		update_option( self::OPTION_NAME, $log, false );
	}

	/**
	 * The log, normalized.
	 *
	 * @since 3.13.0
	 *
	 * @return array{total: int, entries: array[]}
	 */
	public static function get() {

		$log = get_option( self::OPTION_NAME, [] );

		if ( ! is_array( $log ) ) {
			$log = [];
		}

		return [
			'total'   => isset( $log['total'] ) ? (int) $log['total'] : 0,
			'entries' => isset( $log['entries'] ) && is_array( $log['entries'] ) ? $log['entries'] : [],
		];
	}

	/**
	 * How many failures have been recorded.
	 *
	 * @since 3.13.0
	 *
	 * @return int
	 */
	public static function count() {

		return self::get()['total'];
	}

	/**
	 * The most recent failure, or null when there is none.
	 *
	 * @since 3.13.0
	 *
	 * @return array|null
	 */
	public static function latest() {

		$entries = self::get()['entries'];

		return $entries === [] ? null : (array) end( $entries );
	}

	/**
	 * Forget every recorded failure.
	 *
	 * @since 3.13.0
	 *
	 * @return void
	 */
	public static function clear() {

		delete_option( self::OPTION_NAME );
	}
}
