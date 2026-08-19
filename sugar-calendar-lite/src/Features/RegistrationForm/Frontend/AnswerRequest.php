<?php

namespace Sugar_Calendar\Features\RegistrationForm\Frontend;

use Sugar_Calendar\Features\RegistrationForm\SchemaValidator;

/**
 * Normalises the registration answers out of $_POST.
 *
 * The trust boundary for every front-end collection path: erases the
 * slashed/unslashed difference between the ticketing checkout's two
 * validation passes, keeps attendee keys as strings so an error map's JSON
 * shape doesn't depend on which keys happen to be sequential, and caps
 * volume before validation runs.
 *
 * @since 3.13.0
 */
class AnswerRequest {

	/**
	 * $_POST key holding the answers.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const POST_KEY = 'registration';

	/**
	 * Attendee key for the purchaser-level (main attendee) response.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const MAIN_KEY = 'main';

	/**
	 * Hidden field the controller stamps with the number of answer leaves it sent.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const COUNT_FIELD = 'sc_regform_leaf_count';

	/**
	 * Accepted attendee-key shape.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const KEY_PATTERN = '/^(main|a[0-9]{1,5})$/';

	/**
	 * Normalise the posted answers.
	 *
	 * @since 3.13.0
	 *
	 * @param array $post    The POST array ($_POST or a copy).
	 * @param bool  $slashed Whether $post still carries WordPress's added slashes.
	 *
	 * @return array Answers keyed by attendee key, then by field id.
	 */
	public static function from_post( array $post, $slashed ) {

		if ( ! isset( $post[ self::POST_KEY ] ) || ! is_array( $post[ self::POST_KEY ] ) ) {
			return [];
		}

		$raw = $post[ self::POST_KEY ];

		if ( $slashed ) {
			$raw = wp_unslash( $raw );
		}

		$max = self::max_attendees();
		$out = [];

		foreach ( $raw as $key => $answers ) {

			if ( count( $out ) >= $max ) {
				break;
			}

			$key = (string) $key;

			if ( ! preg_match( self::KEY_PATTERN, $key ) || ! is_array( $answers ) ) {
				continue;
			}

			$out[ $key ] = self::normalize_answers( $answers );
		}

		return $out;
	}

	/**
	 * Build the attendee list the applicability predicate consumes.
	 *
	 * Indexes are only prefixed, never renumbered, so both the 1-based core
	 * modal and the 0-based multi-ticket add-on round-trip to the same rows.
	 *
	 * @since 3.13.0
	 *
	 * @param array $post    The POST array ($_POST or a copy).
	 * @param bool  $slashed Whether $post still carries WordPress's added slashes.
	 *
	 * @return array[] Each entry: [ 'key' => 'a{n}', 'ticket_type' => int ].
	 */
	public static function attendees_from_post( array $post, $slashed ) {

		if ( ! isset( $post['attendees'] ) || ! is_array( $post['attendees'] ) ) {
			return [];
		}

		$raw = $slashed ? wp_unslash( $post['attendees'] ) : $post['attendees'];
		$max = self::max_attendees();
		$out = [];

		foreach ( $raw as $index => $attendee ) {

			if ( count( $out ) >= $max ) {
				break;
			}

			// A non-numeric index would collapse to 'a0' under an int cast and
			// collide with a real row, so reject it rather than normalise it.
			if ( ! is_array( $attendee ) || ! is_numeric( $index ) ) {
				continue;
			}

			$out[] = [
				'key'         => 'a' . (int) $index,
				'ticket_type' => isset( $attendee['ticket_type'] ) ? (int) $attendee['ticket_type'] : 0,
			];
		}

		return $out;
	}

	/**
	 * Whether the POST carried fewer answer leaves than the browser sent.
	 *
	 * Catches max_input_vars silently truncating the POST, so the caller can
	 * fail loud instead of surfacing it as an unclearable validation error.
	 *
	 * @since 3.13.0
	 *
	 * @param array $post    The POST array ($_POST or a copy).
	 * @param array $answers The output of from_post() for the same POST.
	 *
	 * @return bool
	 */
	public static function leaf_count_mismatch( array $post, array $answers ) {

		if ( ! isset( $post[ self::COUNT_FIELD ] ) || ! is_numeric( $post[ self::COUNT_FIELD ] ) ) {
			return false;
		}

		$expected = (int) $post[ self::COUNT_FIELD ];
		$received = 0;

		foreach ( $answers as $fields ) {
			foreach ( $fields as $value ) {
				$received += is_array( $value ) ? count( $value ) : 1;
			}
		}

		return $received < $expected;
	}

	/**
	 * Maximum number of attendee blocks accepted in one submission.
	 *
	 * @since 3.13.0
	 *
	 * @return int
	 */
	public static function max_attendees() {

		/**
		 * Filter the maximum number of attendee answer blocks per submission.
		 *
		 * @since 3.13.0
		 *
		 * @param int $max Maximum attendee blocks.
		 */
		return max( 1, (int) apply_filters( 'sugar_calendar_registration_form_max_attendees', 100 ) ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Maximum number of answered fields accepted per attendee block.
	 *
	 * Companion to max_attendees(): bounds a per-attendee loop that would
	 * otherwise be limited only by max_input_vars.
	 *
	 * @since 3.13.0
	 *
	 * @return int
	 */
	public static function max_fields() {

		/**
		 * Filter the maximum number of answered fields accepted per attendee block.
		 *
		 * @since 3.13.0
		 *
		 * @param int $max Maximum fields.
		 */
		return max( 1, (int) apply_filters( 'sugar_calendar_registration_form_max_fields', 100 ) ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Maximum accepted length of a single answer.
	 *
	 * @since 3.13.0
	 *
	 * @return int
	 */
	public static function max_answer_length() {

		/**
		 * Filter the maximum accepted length of a single answer.
		 *
		 * @since 3.13.0
		 *
		 * @param int $max Maximum characters.
		 */
		return max( 1, (int) apply_filters( 'sugar_calendar_registration_form_max_answer_length', 5000 ) ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Normalise one attendee's answers.
	 *
	 * @since 3.13.0
	 *
	 * @param array $answers Raw answers keyed by field id.
	 *
	 * @return array
	 */
	private static function normalize_answers( array $answers ) {

		$max        = self::max_answer_length();
		$max_fields = self::max_fields();
		$out        = [];

		foreach ( $answers as $field_id => $value ) {

			if ( count( $out ) >= $max_fields ) {
				break;
			}

			$field_id = (string) $field_id;

			if ( ! preg_match( SchemaValidator::FIELD_ID_PATTERN, $field_id ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$out[ $field_id ] = self::normalize_choice_list( $value, $max );

				continue;
			}

			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$out[ $field_id ] = self::cap( (string) $value, $max );
		}

		return $out;
	}

	/**
	 * Normalise a checkbox answer to a list of capped scalars.
	 *
	 * @since 3.13.0
	 *
	 * @param array $values Raw values.
	 * @param int   $max    Maximum characters per value.
	 *
	 * @return string[]
	 */
	private static function normalize_choice_list( array $values, $max ) {

		$out = [];

		foreach ( $values as $value ) {
			if ( is_scalar( $value ) ) {
				$out[] = self::cap( (string) $value, $max );
			}
		}

		return $out;
	}

	/**
	 * Truncate a value to the configured maximum length.
	 *
	 * @since 3.13.0
	 *
	 * @param string $value Value.
	 * @param int    $max   Maximum characters.
	 *
	 * @return string
	 */
	private static function cap( $value, $max ) {

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}
}
