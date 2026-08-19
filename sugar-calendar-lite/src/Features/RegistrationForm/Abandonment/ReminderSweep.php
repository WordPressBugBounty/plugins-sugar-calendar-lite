<?php

namespace Sugar_Calendar\Features\RegistrationForm\Abandonment;

use Sugar_Calendar\Features\RegistrationForm\Frontend\HostState;
use Sugar_Calendar\Features\RegistrationForm\Frontend\Renderer;
use Sugar_Calendar\Features\RegistrationForm\ResponseRepository;
use Throwable;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_order;

/**
 * Finds abandoned after-checkout registrations and reminds their owner once.
 *
 * Split from ReminderTask so this is callable without Action Scheduler. Mirrors
 * the host predicates TokenResume applies before printing, plus its own
 * ended-event check; suppression is silent and never logged.
 *
 * @since 3.13.0
 */
class ReminderSweep {

	/**
	 * A reminder went out.
	 *
	 * @since 3.13.0
	 */
	const OUTCOME_SENT = 'sent';

	/**
	 * The context can never be reminded, so it is retired without a send.
	 *
	 * @since 3.13.0
	 */
	const OUTCOME_SUPPRESSED = 'suppressed';

	/**
	 * The send could have succeeded and may on a later run.
	 *
	 * @since 3.13.0
	 */
	const OUTCOME_RETRY = 'retry';

	/**
	 * Run one batch.
	 *
	 * @since 3.13.0
	 *
	 * @param int $limit Maximum contexts to consider.
	 *
	 * @return int How many reminders were sent.
	 */
	public static function run( $limit ) {

		/**
		 * Filters whether abandonment reminders are sent at all.
		 *
		 * The documented off switch — there is deliberately no setting (spec §2.4).
		 *
		 * @since 3.13.0
		 *
		 * @param bool $enabled Whether the sweep runs. Default true.
		 */
		$enabled = (bool) apply_filters( 'sugar_calendar_registration_reminders_enabled', true ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		if ( ! $enabled ) {
			return 0;
		}

		$before = gmdate( 'Y-m-d H:i:s', time() - self::get_threshold() );
		$sent   = 0;

		foreach ( ResponseRepository::find_stale_pending_contexts( $before, (int) $limit ) as $context ) {

			// Catch per-context: an uncaught throw would fail the whole Action
			// Scheduler batch and retry it. Not logged; left unstamped so the
			// next run retries, same as a failed send.
			try {
				$outcome = self::process_context( $context );
			} catch ( Throwable $e ) {
				continue;
			}

			if ( $outcome === self::OUTCOME_RETRY ) {
				continue;
			}

			// Suppressed contexts are stamped too. find_stale_pending_contexts()
			// takes the oldest un-reminded contexts, and nothing ever purges a
			// pending row, so an unstamped context keeps its place in that window
			// forever: once as many permanently ineligible ones accumulate as the
			// batch cap allows — ended events are the ordinary case — no newer
			// registration is ever reached and reminders stop site-wide.
			ResponseRepository::mark_reminded( (string) $context['context'], (int) $context['context_id'] );

			$sent += $outcome === self::OUTCOME_SENT ? 1 : 0;
		}

		return $sent;
	}

	/**
	 * Seconds a pending row must age before it is considered abandoned.
	 *
	 * Lives here, not on ReminderTask: the interval and the batch cap are
	 * scheduling concerns; staleness is an eligibility concern.
	 *
	 * @since 3.13.0
	 *
	 * @return int
	 */
	public static function get_threshold() {

		/**
		 * Filters how long a pending registration waits before a reminder.
		 *
		 * @since 3.13.0
		 *
		 * @param int $threshold Seconds. Default 1 hour.
		 */
		return (int) apply_filters( 'sugar_calendar_registration_reminder_threshold', HOUR_IN_SECONDS ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Apply every rule to one context and send when they all pass.
	 *
	 * The caller stamps on anything but OUTCOME_RETRY, so the split here is what
	 * separates "this context will never be reminded" from "this send might work
	 * next time". State the site can no longer change back for this registration
	 * — the event is gone, over or withdrawn, the order refunded, the schema no
	 * longer collecting after checkout — is suppression, and spends the one
	 * reminder rather than blocking the queue behind it.
	 *
	 * @since 3.13.0
	 *
	 * @param array $context One find_stale_pending_contexts() entry.
	 *
	 * @return string One of the OUTCOME_* constants.
	 */
	private static function process_context( array $context ) {

		$event = sugar_calendar_get_event( (int) $context['event_id'] );

		if ( empty( $event ) || ! self::event_permits_reminding( $event ) ) {
			return self::OUTCOME_SUPPRESSED;
		}

		if ( ! self::host_permits_reminding( $context ) ) {
			return self::OUTCOME_SUPPRESSED;
		}

		$recipient = self::recipient( $context );

		if ( $recipient === [] ) {
			return self::OUTCOME_SUPPRESSED;
		}

		// Re-read: the batch is not transactional, and the visitor may have
		// completed the form between the grouped query and this iteration.
		if ( ! self::still_pending( $context ) ) {
			return self::OUTCOME_SUPPRESSED;
		}

		$ok = ReminderEmail::send(
			[
				'to'         => $recipient['email'],
				'name'       => $recipient['name'],
				'event_id'   => (int) $context['event_id'],
				'context'    => (string) $context['context'],
				'context_id' => (int) $context['context_id'],
				'token'      => (string) $context['token'],
			]
		);

		if ( ! $ok ) {
			// At-least-once: leaving the context unstamped means the next run
			// retries it, so an SMTP outage delays reminders instead of destroying
			// them (spec §2.6). A malformed token lands here too — MIN(token) can
			// pick a bad row out of a context that also holds a good one, and that
			// good row still deserves its reminder.
			return self::OUTCOME_RETRY;
		}

		return self::OUTCOME_SENT;
	}

	/**
	 * Whether the event still invites an answer.
	 *
	 * Two of the three shared predicates (spec §4.2) plus the ended-event rule.
	 *
	 * @since 3.13.0
	 *
	 * @param object $event The Sugar Calendar event.
	 *
	 * @return bool
	 */
	private static function event_permits_reminding( $event ) {

		$post_id = isset( $event->object_id ) ? (int) $event->object_id : 0;

		if ( ! HostState::event_permits_printing( $post_id ) ) {
			return false;
		}

		if ( self::event_has_ended( $event ) ) {
			return false;
		}

		$renderer = Renderer::for_event( (int) $event->id );

		return $renderer !== null && $renderer->mode() === 'after';
	}

	/**
	 * Whether the event has no attendable date left.
	 *
	 * On a recurring event `end` describes the FIRST occurrence, so reading it
	 * alone writes off the whole series the moment occurrence one passes. The
	 * series' own boundary is `recurrence_end`, and a series with none set — the
	 * zero date, which is also what a count-bounded series stores — is treated as
	 * still running: reminding a buyer once too often costs an email, while
	 * suppressing wrongly costs them the reminder entirely.
	 *
	 * @since 3.13.0
	 *
	 * @param object $event The Sugar Calendar event.
	 *
	 * @return bool
	 */
	private static function event_has_ended( $event ) {

		$now = self::now_in_event_time();

		if ( empty( $event->recurrence ) ) {

			$end = isset( $event->end ) ? (string) $event->end : '';

			return $end !== '' && $end < $now;
		}

		$recurrence_end = isset( $event->recurrence_end ) ? (string) $event->recurrence_end : '';

		// '', 0 and '0000-00-00 00:00:00' all mean "no series end", and
		// Event::is_empty_date() is the one place that knows all three shapes.
		if ( $event->is_empty_date( $recurrence_end ) ) {
			return false;
		}

		return $recurrence_end < $now;
	}

	/**
	 * "Now" as a wall-clock MySQL datetime, matching the frame `wp_sc_events.end`
	 * is stored in.
	 *
	 * `end` is floating wall-clock, not UTC; comparing it as UTC misjudges events
	 * as already over by the site's offset. Mirrors core's zone resolution in
	 * Helpers::get_upcoming_events_list_with_recurring().
	 *
	 * @since 3.13.0
	 *
	 * @return string
	 */
	private static function now_in_event_time() {

		$tz = sugar_calendar_get_timezone_type() === 'off' ? 'UTC' : sugar_calendar_get_timezone();

		return (string) sugar_calendar_get_request_time( 'mysql', $tz );
	}

	/**
	 * Whether the order/RSVP itself still invites an answer.
	 *
	 * The third shared predicate. RSVP has no withdrawn state of its own, so the
	 * rsvp context passes here and is gated only by the event.
	 *
	 * @since 3.13.0
	 *
	 * @param array $context One find_stale_pending_contexts() entry.
	 *
	 * @return bool
	 */
	private static function host_permits_reminding( array $context ) {

		if ( (string) $context['context'] !== 'order' ) {
			return true;
		}

		return HostState::order_permits_printing( get_order( (int) $context['context_id'] ) );
	}

	/**
	 * Resolve the recipient, allowing a site to redirect it.
	 *
	 * @since 3.13.0
	 *
	 * @param array $context One find_stale_pending_contexts() entry.
	 *
	 * @return array
	 */
	private static function recipient( array $context ) {

		$resolved = RecipientResolver::for_context(
			(string) $context['context'],
			(int) $context['context_id']
		);

		/**
		 * Filters an abandonment reminder's recipient.
		 *
		 * Return [] to suppress the reminder, or [ 'email' => …, 'name' => … ] to
		 * redirect it (e.g. to a CRM address).
		 *
		 * @since 3.13.0
		 *
		 * @param array  $resolved   [ 'email' => string, 'name' => string ] or [].
		 * @param string $context    'order' or 'rsvp'.
		 * @param int    $context_id Context id.
		 */
		$resolved = (array) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_registration_reminder_recipient',
			$resolved,
			(string) $context['context'],
			(int) $context['context_id']
		);

		if ( empty( $resolved['email'] ) ) {
			return [];
		}

		return [
			'email' => (string) $resolved['email'],
			'name'  => isset( $resolved['name'] ) ? (string) $resolved['name'] : '',
		];
	}

	/**
	 * Whether the context still holds a pending row.
	 *
	 * @since 3.13.0
	 *
	 * @param array $context One find_stale_pending_contexts() entry.
	 *
	 * @return bool
	 */
	private static function still_pending( array $context ) {

		$rows = (string) $context['context'] === 'order'
			? ResponseRepository::get_for_order( (int) $context['context_id'] )
			: ResponseRepository::get_for_rsvp( (int) $context['context_id'] );

		foreach ( $rows as $row ) {
			if ( $row['status'] !== 'complete' ) {
				return true;
			}
		}

		return false;
	}
}
