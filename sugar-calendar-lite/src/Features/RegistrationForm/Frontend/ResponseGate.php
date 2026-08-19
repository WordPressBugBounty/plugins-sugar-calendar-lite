<?php

namespace Sugar_Calendar\Features\RegistrationForm\Frontend;

use Sugar_Calendar\Features\RegistrationForm\ResponseValidator;

/**
 * Validates a whole submission, one ResponseValidator pass per applicable
 * attendee, into the single error map the front-end controller paints.
 *
 * Applicability is passed in from Renderer::applicable_attendees() rather
 * than re-derived, so the gate can never disagree with what the form
 * actually rendered.
 *
 * @since 3.13.0
 */
class ResponseGate {

	/**
	 * Error id used with Checkout::add_error().
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const ERROR_ID = 'sc_registration_incomplete';

	/**
	 * Error id used when the POST arrived truncated.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const TRUNCATED_ERROR_ID = 'sc_registration_truncated';

	/**
	 * Selector the host attaches its own error node to.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const SELECTOR = '.sc-regform-step';

	/**
	 * Validate every applicable attendee's answers.
	 *
	 * @since 3.13.0
	 *
	 * @param array $schema     Stored (validated) schema.
	 * @param array $answers    AnswerRequest::from_post() output.
	 * @param array $applicable Renderer::applicable_attendees() output; each
	 *                          entry's `key` must already match AnswerRequest::KEY_PATTERN.
	 *
	 * @return array { valid: bool, errors: array<string,array>, answers: array<string,array> }
	 */
	public static function validate( array $schema, array $answers, array $applicable ) {

		$errors    = [];
		$sanitized = [];

		foreach ( $applicable as $attendee ) {

			$key = isset( $attendee['key'] ) ? (string) $attendee['key'] : '';

			if ( $key === '' ) {
				continue;
			}

			// isset, not empty: a legitimate "0" answer must not be treated as missing.
			$posted = isset( $answers[ $key ] ) && is_array( $answers[ $key ] ) ? $answers[ $key ] : [];

			$result = ResponseValidator::validate( $schema, $posted, true );

			if ( ! empty( $result['errors'] ) ) {
				$errors[ $key ] = $result['errors'];
			}

			$sanitized[ $key ] = $result['answers'];
		}

		return [
			'valid'   => $errors === [],
			'errors'  => $errors,
			'answers' => $sanitized,
		];
	}

	/**
	 * The message handed to Checkout::add_error() on a failed pass.
	 *
	 * Fixed and content-free: both ticketing controllers render it via innerHTML
	 * on the payment page, so no field data may reach it.
	 *
	 * @since 3.13.0
	 *
	 * @return string
	 */
	public static function error_message() {

		return esc_html__( 'Please complete the registration details before continuing.', 'sugar-calendar-lite' );
	}

	/**
	 * The message used when the submission arrived incomplete at the server.
	 *
	 * A POST truncated by max_input_vars is not something the buyer can fix by
	 * filling the form in again, so it must not reuse the validation copy.
	 *
	 * @since 3.13.0
	 *
	 * @return string
	 */
	public static function truncation_message() {

		return esc_html__( 'Your registration details could not be submitted in full. Please reduce the number of attendees or contact the organizer.', 'sugar-calendar-lite' );
	}
}
