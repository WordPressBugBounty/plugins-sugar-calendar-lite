<?php

namespace Sugar_Calendar\Features\RegistrationForm\Admin;

use Sugar_Calendar\Features\RegistrationForm\SchemaValidator;

/**
 * Renders one admin form control for one registration-form field.
 *
 * The only place this feature's admin surface echoes a stored answer value,
 * so escaping stays centralized here rather than risking a second renderer
 * forgetting one (see the #540 stored-XSS fix). Controls are native per type
 * since a checkbox answer is an array a single <select> can't represent.
 *
 * @since 3.13.0
 */
class AnswerFields {

	/**
	 * Render the control for one field.
	 *
	 * An unrecognized type renders nothing, since it can only come from a
	 * hand-edited meta value with no validation rule to check it against.
	 *
	 * @since 3.13.0
	 *
	 * @param array  $field       One field from the stored schema.
	 * @param mixed  $value       The stored answer: string, array, or null.
	 * @param string $name_prefix POST prefix, e.g. 'sc_regform_admin[main]'.
	 */
	public static function render( array $field, $value, $name_prefix ) {

		$name     = self::input_name( $field, $name_prefix );
		$id       = self::input_id( $field, $name_prefix );
		$required = self::required_attr( $field, $value );

		switch ( self::type( $field ) ) {
			case 'short_text':
				self::text( $name, $value, $id, $required );
				break;

			case 'long_text':
				self::textarea( $name, $value, $id, $required );
				break;

			case 'dropdown':
				self::select( $name, $field, $value, $id, $required );
				break;

			case 'radio':
				self::radios( $name, $field, $value, $required );
				break;

			case 'checkbox':
				self::checkboxes( $name, $field, $value, $required );
				break;
		}
	}

	/**
	 * The attribute marking a required field's controls for answers.js.
	 *
	 * Not the `required` attribute: that would let the browser word the message
	 * and would fire on a control this class renders empty on purpose.
	 *
	 * Printed only when the control can carry this value back, which is the same
	 * gate AnswerSaveHandler::validate() applies — see can_carry().
	 *
	 * @since 3.13.0
	 *
	 * @param array $field One field from the stored schema.
	 * @param mixed $value The stored answer.
	 *
	 * @return string An attribute to print, or ''.
	 */
	private static function required_attr( array $field, $value ) {

		if ( empty( $field['required'] ) || ! self::can_carry( $field, $value ) ) {
			return '';
		}

		return ' data-sc-regform-required="1"';
	}

	/**
	 * Whether the control this class renders can carry a value back on submit.
	 *
	 * False for a type render() has no case for (nothing is rendered, so nothing
	 * can be posted) and for a choice answer that is no longer an offered option
	 * (the control shows nothing selected). Otherwise true, including for an
	 * empty value: a text input and a choice list both post a real empty.
	 *
	 * Also the required-enforcement gate on both sides of the round trip. A field
	 * this returns false for cannot be answered here — the organizer has no
	 * control for it, or the stored answer survives the write untouched
	 * (AnswerSaveHandler::with_preserved_answers()) — so demanding an answer
	 * would leave the panel unsaveable.
	 *
	 * @since 3.13.0
	 *
	 * @param array $field One field from the stored schema.
	 * @param mixed $value The stored answer.
	 *
	 * @return bool
	 */
	public static function can_carry( array $field, $value ) {

		$type = self::type( $field );

		if ( in_array( $type, SchemaValidator::TEXT_TYPES, true ) ) {
			return true;
		}

		if ( ! in_array( $type, SchemaValidator::CHOICE_TYPES, true ) ) {
			return false;
		}

		return self::options_cover( $field, $value );
	}

	/**
	 * Whether every part of a choice answer is still an offered option.
	 *
	 * @since 3.13.0
	 *
	 * @param array $field One schema field.
	 * @param mixed $value The stored answer.
	 *
	 * @return bool
	 */
	private static function options_cover( array $field, $value ) {

		$options = SchemaValidator::field_options( $field );
		$values  = is_array( $value ) ? array_map( 'strval', $value ) : [ (string) $value ];

		foreach ( $values as $one ) {

			// '' is not an answer, so it cannot be an unrepresentable one.
			if ( $one !== '' && ! in_array( $one, $options, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * One field's declared type, or '' when it has none.
	 *
	 * Split out of render() to keep that method under the complexity ceiling.
	 *
	 * @since 3.13.0
	 *
	 * @param array $field One field from the stored schema.
	 *
	 * @return string
	 */
	private static function type( array $field ) {

		return isset( $field['type'] ) ? (string) $field['type'] : '';
	}

	/**
	 * The POST name one field's control writes into.
	 *
	 * @since 3.13.0
	 *
	 * @param array  $field       One field from the stored schema.
	 * @param string $name_prefix POST prefix, e.g. 'sc_regform_admin[main]'.
	 *
	 * @return string
	 */
	private static function input_name( array $field, $name_prefix ) {

		return $name_prefix . '[' . ( isset( $field['id'] ) ? (string) $field['id'] : '' ) . ']';
	}

	/**
	 * The DOM id of one field's control, for a <label for> to point at.
	 *
	 * Returns '' for radio/checkbox groups, which have no single control to label.
	 * Derived from the POST name so it stays unique per respondent and per field.
	 *
	 * @since 3.13.0
	 *
	 * @param array  $field       One field from the stored schema.
	 * @param string $name_prefix POST prefix, e.g. 'sc_regform_admin[main]'.
	 *
	 * @return string
	 */
	public static function input_id( array $field, $name_prefix ) {

		if ( in_array( self::type( $field ), [ 'radio', 'checkbox' ], true ) ) {
			return '';
		}

		return sanitize_html_class(
			str_replace( [ '[', ']' ], [ '-', '' ], self::input_name( $field, $name_prefix ) )
		);
	}

	/**
	 * Render an answer whose field is no longer in the schema.
	 *
	 * Read-only and never posted back: there is no field definition left to
	 * validate an edit against.
	 *
	 * @since 3.13.0
	 *
	 * @param string $field_id The orphaned field id.
	 * @param mixed  $value    The stored answer.
	 */
	public static function render_orphan( $field_id, $value ) {

		// A row of the host's .form-table, like every other answer.
		printf(
			'<tr class="sc-regform-admin-field--orphan"><th scope="row">%1$s <em>%2$s</em></th><td><span class="sc-regform-admin-field__static">%3$s</span></td></tr>',
			esc_html( (string) $field_id ),
			esc_html__( '(deleted field)', 'sugar-calendar-lite' ),
			esc_html( self::flatten( $value ) )
		);
	}

	/**
	 * Render one answer as static text, for a respondent that cannot be edited.
	 *
	 * Kept here rather than in ResponsesPanel so this stays the only escape site
	 * for an answer value.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $value The stored answer.
	 */
	public static function render_static( $value ) {

		printf(
			'<span class="sc-regform-admin-field__static">%1$s</span>',
			esc_html( self::flatten( $value ) )
		);
	}

	/**
	 * Join a possibly multi-value answer for display.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $value The stored answer.
	 *
	 * @return string
	 */
	private static function flatten( $value ) {

		if ( is_array( $value ) ) {
			return implode( ', ', array_map( 'strval', $value ) );
		}

		return (string) $value;
	}

	/**
	 * A single-line text input.
	 *
	 * @since 3.13.0
	 *
	 * @param string $name     Input name.
	 * @param mixed  $value    Stored answer.
	 * @param string $id       DOM id.
	 * @param string $required The required marker attribute, or ''.
	 */
	private static function text( $name, $value, $id, $required ) {

		printf(
			'<input type="text" id="%3$s" class="regular-text" name="%1$s" value="%2$s"%4$s />',
			esc_attr( $name ),
			esc_attr( self::flatten( $value ) ),
			esc_attr( $id ),
			$required // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal attribute from required_attr().
		);
	}

	/**
	 * A multi-line textarea.
	 *
	 * @since 3.13.0
	 *
	 * @param string $name     Input name.
	 * @param mixed  $value    Stored answer.
	 * @param string $id       DOM id.
	 * @param string $required The required marker attribute, or ''.
	 */
	private static function textarea( $name, $value, $id, $required ) {

		printf(
			'<textarea id="%3$s" class="large-text" rows="3" name="%1$s"%4$s>%2$s</textarea>',
			esc_attr( $name ),
			esc_textarea( self::flatten( $value ) ),
			esc_attr( $id ),
			$required // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal attribute from required_attr().
		);
	}

	/**
	 * A dropdown with an empty first option.
	 *
	 * The empty option is what lets an admin clear an answer; required-ness is
	 * not enforced on admin edits.
	 *
	 * @since 3.13.0
	 *
	 * @param string $name     Input name.
	 * @param array  $field    The field.
	 * @param mixed  $value    Stored answer.
	 * @param string $id       DOM id.
	 * @param string $required The required marker attribute, or ''.
	 */
	private static function select( $name, array $field, $value, $id, $required ) {

		$current = self::flatten( $value );

		printf(
			'<select id="%2$s" name="%1$s"%3$s>',
			esc_attr( $name ),
			esc_attr( $id ),
			$required // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal attribute from required_attr().
		);

		printf( '<option value="">%1$s</option>', esc_html__( 'Select', 'sugar-calendar-lite' ) );

		foreach ( SchemaValidator::field_options( $field ) as $option ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $option ),
				selected( $option, $current, false ),
				esc_html( $option )
			);
		}

		echo '</select>';
	}

	/**
	 * One radio per option.
	 *
	 * @since 3.13.0
	 *
	 * @param string $name     Input name.
	 * @param array  $field    The field.
	 * @param mixed  $value    Stored answer.
	 * @param string $required The required marker attribute, or ''.
	 */
	private static function radios( $name, array $field, $value, $required ) {

		$current = self::flatten( $value );

		foreach ( SchemaValidator::field_options( $field ) as $option ) {
			printf(
				'<label class="sc-regform-admin-choice"><input type="radio" name="%1$s" value="%2$s"%3$s%5$s /> %4$s</label>',
				esc_attr( $name ),
				esc_attr( $option ),
				checked( $option, $current, false ),
				esc_html( $option ),
				$required // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal attribute from required_attr().
			);
		}
	}

	/**
	 * One checkbox per option, posting as an array.
	 *
	 * @since 3.13.0
	 *
	 * @param string $name     Input name.
	 * @param array  $field    The field.
	 * @param mixed  $value    Stored answer.
	 * @param string $required The required marker attribute, or ''.
	 */
	private static function checkboxes( $name, array $field, $value, $required ) {

		$current = is_array( $value ) ? array_map( 'strval', $value ) : [ (string) $value ];

		foreach ( SchemaValidator::field_options( $field ) as $option ) {
			printf(
				'<label class="sc-regform-admin-choice"><input type="checkbox" name="%1$s[]" value="%2$s"%3$s%5$s /> %4$s</label>',
				esc_attr( $name ),
				esc_attr( $option ),
				checked( in_array( $option, $current, true ), true, false ),
				esc_html( $option ),
				$required // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal attribute from required_attr().
			);
		}
	}
}
