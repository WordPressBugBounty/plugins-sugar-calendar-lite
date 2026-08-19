<?php

namespace Sugar_Calendar\Features\RegistrationForm;

/**
 * Validates one respondent's answers against a stored schema.
 *
 * `$enforce_required = false` is the admin-write mode: type/option integrity
 * is still enforced, required-ness is not (master spec §7.4, deliberate).
 * `AnswerSaveHandler::validate()` passes true and filters for just the
 * `required` errors when an edit must not erase a stored answer.
 *
 * @since 3.13.0
 */
class ResponseValidator {

	/**
	 * Validate and sanitize a single respondent's answers.
	 *
	 * @since 3.13.0
	 *
	 * @param array $schema           Stored (validated) schema.
	 * @param array $answers          Posted answers keyed by field id.
	 * @param bool  $enforce_required Whether missing required answers error.
	 *
	 * @return array { valid: bool, answers: array, errors: array<string,string> }
	 */
	public static function validate( array $schema, array $answers, $enforce_required = true ) {

		$errors    = [];
		$sanitized = [];

		foreach ( (array) ( $schema['fields'] ?? [] ) as $field ) {

			$id = $field['id'];

			if ( ! self::has_answer( $answers, $id ) ) {
				if ( $enforce_required && ! empty( $field['required'] ) ) {
					$errors[ $id ] = 'required';
				}

				continue;
			}

			list( $error, $value ) = self::validate_answer( $field, $answers[ $id ] );

			if ( $error !== null ) {
				$errors[ $id ] = $error;

				continue;
			}

			$sanitized[ $id ] = $value;
		}

		return [
			'valid'   => $errors === [],
			'answers' => $sanitized,
			'errors'  => $errors,
		];
	}

	/**
	 * Whether an answer was actually submitted for a field.
	 *
	 * Strict isset: the string "0" is a legitimate answer that empty() would eat.
	 *
	 * @since 3.13.0
	 *
	 * @param array  $answers Posted answers keyed by field id.
	 * @param string $id      Field id.
	 *
	 * @return bool
	 */
	private static function has_answer( array $answers, $id ) {

		return isset( $answers[ $id ] ) && $answers[ $id ] !== '' && $answers[ $id ] !== [];
	}

	/**
	 * Sanitize and type-check one field's answer.
	 *
	 * @since 3.13.0
	 *
	 * @param array $field One field definition from the schema.
	 * @param mixed $value The raw answer for that field.
	 *
	 * @return array [ error_code|null, sanitized_value|null ]
	 */
	private static function validate_answer( array $field, $value ) {

		$type = $field['type'];

		if ( $type !== 'checkbox' && ! is_scalar( $value ) ) {
			return [ 'invalid_option', null ];
		}

		if ( in_array( $type, SchemaValidator::CHOICE_TYPES, true ) ) {
			return self::validate_choice_answer( $type, $field, $value );
		}

		return $type === 'long_text'
			? [ null, sanitize_textarea_field( $value ) ]
			: [ null, sanitize_text_field( $value ) ]; // short_text.
	}

	/**
	 * Sanitize and type-check a checkbox/radio/dropdown answer against its options.
	 *
	 * @since 3.13.0
	 *
	 * @param string $type  Field type: 'checkbox', 'radio', or 'dropdown'.
	 * @param array  $field One field definition from the schema.
	 * @param mixed  $value The raw answer for that field.
	 *
	 * @return array [ error_code|null, sanitized_value|null ]
	 */
	private static function validate_choice_answer( $type, array $field, $value ) {

		// Via SchemaValidator so option membership matches what AnswerFields rendered.
		$options = SchemaValidator::field_options( $field );

		if ( $type === 'checkbox' ) {
			$value = array_map( 'sanitize_text_field', (array) $value );

			return array_diff( $value, $options ) === []
				? [ null, $value ]
				: [ 'invalid_option', null ];
		}

		$value = sanitize_text_field( $value );

		return in_array( $value, $options, true )
			? [ null, $value ]
			: [ 'invalid_option', null ];
	}
}
