<?php

namespace Sugar_Calendar\Features\RegistrationForm\Admin;

use Sugar_Calendar\Features\RegistrationForm\Frontend\AnswerRequest;
use Sugar_Calendar\Features\RegistrationForm\ResponseRepository;
use Sugar_Calendar\Features\RegistrationForm\ResponseValidator;
use Sugar_Calendar\Features\RegistrationForm\SchemaRepository;
use Sugar_Calendar\Features\RegistrationForm\WriteFailureLog;

/**
 * Validate and persist an organizer's edits to one context's answers.
 *
 * Host-agnostic: callers resolve the respondents and hand over the raw POST.
 * validate() writes nothing and is the pre-flight both admin hosts call first;
 * it enforces required-ness, except on the fields whose control cannot carry an
 * answer back (see without_unanswerable_required()). handle() then writes with
 * required-ness off, since a submission that reaches it has already passed the
 * pre-flight and its job is the type/option integrity of what was posted.
 *
 * @since 3.13.0
 */
class AnswerSaveHandler {

	/**
	 * Collect every reason a respondent's posted answers must be refused.
	 *
	 * Writes nothing, so a submission can be rejected before anything half-saves.
	 *
	 * @since 3.13.0
	 *
	 * @param int   $event_id    The event whose schema governs.
	 * @param array $respondents RespondentResolver respondents.
	 * @param array $posted      The raw POST array.
	 *
	 * @return array<string,array<string,string>> Errors keyed by attendee key then field id.
	 */
	public static function validate( $event_id, array $respondents, array $posted ) {

		$schema = (array) SchemaRepository::get( (int) $event_id );

		if ( empty( $schema['enabled'] ) || empty( $schema['fields'] ) ) {
			return [];
		}

		$submitted = self::submitted( $posted );

		if ( $submitted === [] ) {
			return [];
		}

		$errors = [];

		foreach ( $respondents as $respondent ) {

			$key          = isset( $respondent['attendee_key'] ) ? (string) $respondent['attendee_key'] : '';
			$field_errors = self::validate_respondent( $respondent, $key, $submitted, $schema );

			if ( $field_errors !== [] ) {
				$errors[ $key ] = $field_errors;
			}
		}

		return $errors;
	}

	/**
	 * Collect one respondent's reasons to refuse, without writing anything.
	 *
	 * @since 3.13.0
	 *
	 * @param array  $respondent The respondent.
	 * @param string $key        Its attendee key.
	 * @param array  $submitted  The answers half of the POST.
	 * @param array  $schema     The stored schema.
	 *
	 * @return array Field id => error code.
	 */
	private static function validate_respondent( array $respondent, $key, array $submitted, array $schema ) {

		// Same gate save_respondent() writes behind, so this never refuses a
		// respondent the write path was going to skip anyway.
		if ( ! self::is_editable( $respondent, $key, $submitted ) ) {
			return [];
		}

		$result = ResponseValidator::validate( $schema, $submitted[ $key ], true );

		return self::without_unanswerable_required( $result['errors'], $respondent, $schema );
	}

	/**
	 * Drop the `required` errors the organizer has no way to clear.
	 *
	 * A required answer must be present, matching how the host editors treat an
	 * attendee's name and email. The exception is a field whose control cannot
	 * carry a value back — an unrendered type, or a choice answer that is no
	 * longer an offered option — where demanding one would make the panel
	 * unsaveable. AnswerFields prints its required marker behind the same gate,
	 * so the browser guard and this pre-flight refuse exactly the same edits.
	 *
	 * @since 3.13.0
	 *
	 * @param array $errors     Field id => error code, from ResponseValidator.
	 * @param array $respondent The respondent, carrying its stored answers.
	 * @param array $schema     The stored schema.
	 *
	 * @return array
	 */
	private static function without_unanswerable_required( array $errors, array $respondent, array $schema ) {

		$stored = isset( $respondent['answers'] ) ? (array) $respondent['answers'] : [];
		$fields = self::fields_by_id( $schema );

		foreach ( $errors as $id => $code ) {

			if ( $code === 'required' && ! self::is_answerable( (string) $id, $fields, $stored ) ) {
				unset( $errors[ $id ] );
			}
		}

		return $errors;
	}

	/**
	 * Whether the organizer can supply an answer for one field on this screen.
	 *
	 * Split out of without_unanswerable_required() to keep that method a single
	 * pass over the errors, which is what the phpcs complexity sniff flagged.
	 *
	 * @since 3.13.0
	 *
	 * @param string $id     The field id.
	 * @param array  $fields The schema's fields, keyed by id.
	 * @param array  $stored The respondent's stored answers.
	 *
	 * @return bool
	 */
	private static function is_answerable( $id, array $fields, array $stored ) {

		return AnswerFields::can_carry(
			isset( $fields[ $id ] ) ? $fields[ $id ] : [],
			isset( $stored[ $id ] ) ? $stored[ $id ] : ''
		);
	}

	/**
	 * Persist every respondent's posted answers.
	 *
	 * Each respondent is independent: a failing one is rejected and reported
	 * while the rest still save.
	 *
	 * @since 3.13.0
	 *
	 * @param int    $event_id    The event whose schema governs.
	 * @param string $context     Either 'order' or 'rsvp'.
	 * @param int    $context_id  Order id or RSVP id.
	 * @param array  $respondents RespondentResolver respondents.
	 * @param array  $posted      The raw POST array.
	 *
	 * @return array<string,array<string,string>> Errors keyed by attendee key
	 *                                            then field id. Empty on success.
	 */
	public static function handle( $event_id, $context, $context_id, array $respondents, array $posted ) {

		// get() can return null rather than [] when the meta is absent or corrupt,
		// so this normalises before any array access.
		$schema = (array) SchemaRepository::get( (int) $event_id );

		if ( empty( $schema['enabled'] ) || empty( $schema['fields'] ) ) {
			return [];
		}

		$submitted = self::submitted( $posted );

		if ( $submitted === [] ) {
			return [];
		}

		$errors = [];

		foreach ( $respondents as $respondent ) {

			$field_errors = self::save_respondent( $event_id, $context, $context_id, $respondent, $submitted, $schema );

			if ( $field_errors !== [] ) {
				$errors[ (string) $respondent['attendee_key'] ] = $field_errors;
			}
		}

		return $errors;
	}

	/**
	 * The answers half of the POST, unslashed.
	 *
	 * @since 3.13.0
	 *
	 * @param array $posted The raw POST array.
	 *
	 * @return array
	 */
	private static function submitted( array $posted ) {

		if ( ! isset( $posted[ ResponsesPanel::POST_KEY ] ) || ! is_array( $posted[ ResponsesPanel::POST_KEY ] ) ) {
			return [];
		}

		return (array) wp_unslash( $posted[ ResponsesPanel::POST_KEY ] );
	}

	/**
	 * Validate and persist one respondent's posted answers.
	 *
	 * @since 3.13.0
	 *
	 * @param int    $event_id   Event id.
	 * @param string $context    Context.
	 * @param int    $context_id Context id.
	 * @param array  $respondent The respondent.
	 * @param array  $submitted  The answers half of the POST.
	 * @param array  $schema     The stored schema.
	 *
	 * @return array Field errors for this respondent; empty when it saved or was
	 *               not this request's to touch.
	 */
	private static function save_respondent( $event_id, $context, $context_id, array $respondent, array $submitted, array $schema ) {

		$key = isset( $respondent['attendee_key'] ) ? (string) $respondent['attendee_key'] : '';

		if ( ! self::is_editable( $respondent, $key, $submitted ) ) {
			return [];
		}

		$result = ResponseValidator::validate( $schema, $submitted[ $key ], false );

		if ( ! $result['valid'] ) {
			return $result['errors'];
		}

		self::persist( $event_id, $context, $context_id, $respondent, $result['answers'], $schema );

		return [];
	}

	/**
	 * Whether this respondent's answers may be written from this POST.
	 *
	 * An orphan's answers render read-only, so a POST claiming to carry them
	 * didn't come from this page. is_array (not just isset) guards against a
	 * scalar POST value blanking the stored row.
	 *
	 * @since 3.13.0
	 *
	 * @param array  $respondent The respondent.
	 * @param string $key        Its attendee key.
	 * @param array  $submitted  The answers half of the POST.
	 *
	 * @return bool
	 */
	private static function is_editable( array $respondent, $key, array $submitted ) {

		if ( ! empty( $respondent['is_orphan'] ) ) {
			return false;
		}

		if ( ! preg_match( AnswerRequest::KEY_PATTERN, $key ) ) {
			return false;
		}

		return isset( $submitted[ $key ] ) && is_array( $submitted[ $key ] );
	}

	/**
	 * Write one respondent's answers.
	 *
	 * Updates the existing row when there is one, else inserts (an UPSERT on
	 * context/context_id/attendee_key) — covering both a never-saved respondent
	 * and a row_id that no longer exists.
	 *
	 * @since 3.13.0
	 *
	 * @param int    $event_id   Event id.
	 * @param string $context    Context.
	 * @param int    $context_id Context id.
	 * @param array  $respondent The respondent.
	 * @param array  $answers    Sanitized answers.
	 * @param array  $schema     The stored schema, for the carry-forward below.
	 */
	private static function persist( $event_id, $context, $context_id, array $respondent, array $answers, array $schema ) {

		$row = self::row( $event_id, $context, $context_id, $respondent, self::with_preserved_answers( $respondent, $answers, $schema ) );

		/*
		 * No stored row and no submitted answers means an untouched panel; writing
		 * it would mint a `complete` row for every attendee, which reminders and
		 * the pending gate read as "already answered". Gated on row_id, not the
		 * answers alone, since a deliberate clear of an existing row must still write.
		 */
		if ( empty( $respondent['row_id'] ) && $row['answers'] === [] ) {
			return;
		}

		if ( self::update_existing( $respondent, $row ) ) {
			return;
		}

		$result = ResponseRepository::insert( $row );

		// Fail loud: an edit that did not persist must never look like it did.
		if ( is_wp_error( $result ) ) {
			WriteFailureLog::record( $row, $result );
		}
	}

	/**
	 * Update the respondent's existing row, if it has one that still exists.
	 *
	 * @since 3.13.0
	 *
	 * @param array $respondent The respondent.
	 * @param array $row        The row shape, already carrying merged answers.
	 *
	 * @return bool True when this call handled the write (successfully or by
	 *              recording a failure); false when the caller should insert.
	 */
	private static function update_existing( array $respondent, array $row ) {

		$row_id = empty( $respondent['row_id'] ) ? 0 : (int) $respondent['row_id'];

		if ( $row_id <= 0 ) {
			return false;
		}

		$attendee_id = (int) $row['attendee_id'] > 0 ? (int) $row['attendee_id'] : null;
		$result      = ResponseRepository::update_answers( $row_id, $row['answers'], $attendee_id );

		if ( is_wp_error( $result ) ) {
			WriteFailureLog::record( $row, $result );

			// Reported. Do not then insert: the row exists, the write failed.
			return true;
		}

		// Zero means the row is gone (a concurrent cleanup or delete), not a write
		// failure; returning false sends the caller to insert(), recreating it
		// under the same key.
		return (int) $result > 0;
	}

	/**
	 * Merge back the stored answers this edit could not have carried.
	 *
	 * Because update_answers() replaces the whole blob, and the validator returns
	 * only values the current schema can represent, a removed field, an
	 * unrecognised type, or a stored value that's no longer an offered option
	 * must be merged back or Update destroys it. A value the control CAN
	 * represent that posts empty is a deliberate clear, not lost data.
	 *
	 * @since 3.13.0
	 *
	 * @param array $respondent The respondent, carrying its stored answers.
	 * @param array $answers    Validated answers from this request.
	 * @param array $schema     The stored schema.
	 *
	 * @return array
	 */
	private static function with_preserved_answers( array $respondent, array $answers, array $schema ) {

		$stored = isset( $respondent['answers'] ) ? (array) $respondent['answers'] : [];

		if ( $stored === [] ) {
			return $answers;
		}

		$fields   = self::fields_by_id( $schema );
		$preserve = [];

		foreach ( $stored as $id => $value ) {
			if ( self::should_preserve( (string) $id, $value, $answers, $fields ) ) {
				$preserve[ $id ] = $value;
			}
		}

		return array_merge( $preserve, $answers );
	}

	/**
	 * The schema's fields, keyed by field id.
	 *
	 * @since 3.13.0
	 *
	 * @param array $schema The stored schema.
	 *
	 * @return array<string,array>
	 */
	private static function fields_by_id( array $schema ) {

		$fields = isset( $schema['fields'] ) && is_array( $schema['fields'] ) ? $schema['fields'] : [];
		$map    = [];

		foreach ( $fields as $field ) {
			if ( isset( $field['id'] ) ) {
				$map[ (string) $field['id'] ] = $field;
			}
		}

		return $map;
	}

	/**
	 * Whether one stored answer must survive this write.
	 *
	 * @since 3.13.0
	 *
	 * @param string $id      The stored answer's field id.
	 * @param mixed  $value   The stored value.
	 * @param array  $answers Validated answers from this request.
	 * @param array  $fields  The schema's fields, keyed by id.
	 *
	 * @return bool
	 */
	private static function should_preserve( $id, $value, array $answers, array $fields ) {

		// This request answered the field: the posted value replaces the stored one.
		if ( isset( $answers[ $id ] ) ) {
			return false;
		}

		// The schema no longer defines the field at all.
		if ( ! isset( $fields[ $id ] ) ) {
			return true;
		}

		// AnswerFields owns this test: it is the class whose controls either can or
		// cannot round-trip the value, and the required gate reads the same answer.
		return ! AnswerFields::can_carry( $fields[ $id ], $value );
	}

	/**
	 * The row shape insert() takes — also what the failure log records.
	 *
	 * @since 3.13.0
	 *
	 * @param int    $event_id   Event id.
	 * @param string $context    Context.
	 * @param int    $context_id Context id.
	 * @param array  $respondent The respondent.
	 * @param array  $answers    Answers to store.
	 *
	 * @return array
	 */
	private static function row( $event_id, $context, $context_id, array $respondent, array $answers ) {

		$attendee_id = isset( $respondent['attendee_id'] ) ? $respondent['attendee_id'] : null;

		return [
			'event_id'     => (int) $event_id,
			'context'      => (string) $context,
			'context_id'   => (int) $context_id,
			'attendee_key' => (string) $respondent['attendee_key'],
			'attendee_id'  => $attendee_id === null ? 0 : (int) $attendee_id,
			'answers'      => $answers,
			'status'       => 'complete',
		];
	}
}
