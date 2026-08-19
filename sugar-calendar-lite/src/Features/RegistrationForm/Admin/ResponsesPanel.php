<?php

namespace Sugar_Calendar\Features\RegistrationForm\Admin;

use Sugar_Calendar\Helpers\UI;

/**
 * The registration-answers block for one respondent.
 *
 * Rendered inside a host's own panel, below a divider from the host's own
 * fields, using core's UI helpers so RSVP answers don't depend on ticketing
 * markup. render() emits table rows only, and must run between a
 * UI::form_table_open()/form_table_close() pair the host already opened.
 *
 * @since 3.13.0
 */
class ResponsesPanel {

	/**
	 * POST array this block's inputs write into.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const POST_KEY = 'sc_regform_admin';

	/**
	 * Render one respondent's answers.
	 *
	 * @since 3.13.0
	 *
	 * @param array  $schema     The stored schema. An empty one renders nothing.
	 * @param array  $respondent A RespondentResolver respondent.
	 * @param array  $errors     Field-id => error code, from a rejected save.
	 * @param string $post_key   Override for the POST namespace, when a host's panels
	 *                           aren't keyed by attendee key (e.g. the RSVP editor
	 *                           keys by panel: main / id{n} / new{n}).
	 */
	public static function render( array $schema, array $respondent, array $errors = [], $post_key = '' ) {

		$fields = isset( $schema['fields'] ) && is_array( $schema['fields'] ) ? $schema['fields'] : [];

		// The same two-part guard shipped Renderer::for_event() uses. `enabled` is
		// half of "does this event have a form"; dropping it would let the admin and
		// the front end disagree.
		if ( empty( $schema['enabled'] ) || $fields === [] ) {
			return;
		}

		$answers   = isset( $respondent['answers'] ) ? (array) $respondent['answers'] : [];
		$read_only = ! empty( $respondent['is_orphan'] );
		$prefix    = self::POST_KEY . '[' . self::key( $respondent, $post_key ) . ']';

		AnswerValidation::enqueue();

		// A marker <tr>, not a <div>: the host already opened a .form-table, and a
		// <div> between rows is invalid markup browsers hoist out, taking the
		// answers with it. Empty by design; the divider border is drawn in CSS.
		echo '<tr class="sc-regform-admin-answers"><td colspan="2"></td></tr>';

		foreach ( $fields as $field ) {
			self::field( $field, $answers, $prefix, $read_only, $errors );
		}

		self::orphans( $fields, $answers );
	}

	/**
	 * The POST key this block's inputs are namespaced under.
	 *
	 * @since 3.13.0
	 *
	 * @param array  $respondent A RespondentResolver respondent.
	 * @param string $post_key   An explicit override — see render()'s docblock.
	 *
	 * @return string
	 */
	private static function key( array $respondent, $post_key ) {

		if ( $post_key !== '' ) {
			return (string) $post_key;
		}

		return isset( $respondent['attendee_key'] ) ? (string) $respondent['attendee_key'] : '';
	}

	/**
	 * Render one field's row: label, control, and any error.
	 *
	 * @since 3.13.0
	 *
	 * @param array  $field     One schema field.
	 * @param array  $answers   This respondent's answers.
	 * @param string $prefix    POST prefix.
	 * @param bool   $read_only Whether to render a static value instead of a control.
	 * @param array  $errors    Field-id => error code.
	 */
	private static function field( array $field, array $answers, $prefix, $read_only, array $errors ) {

		$id = isset( $field['id'] ) ? (string) $field['id'] : '';

		// Strict isset: "0" is a legitimate answer that empty() would eat.
		$value = isset( $answers[ $id ] ) ? $answers[ $id ] : '';

		self::open_field_row( $field, $prefix, $read_only );

		if ( $read_only ) {
			// Delegated, not inlined: AnswerFields is the one place an answer value
			// is echoed, so there is one flatten and one escape site in this feature.
			AnswerFields::render_static( $value );
		} else {
			AnswerFields::render( $field, $value, $prefix );
		}

		$message = self::error_message( isset( $errors[ $id ] ) ? (string) $errors[ $id ] : '' );

		if ( $message !== '' ) {
			printf(
				'<p class="sc-regform-admin-field__error">%1$s</p>',
				esc_html( $message )
			);
		}

		UI::form_table_row_close();
	}

	/**
	 * The organizer-facing message for one answer error code.
	 *
	 * `invalid_option` is a stale or crafted schema mismatch; `required` fires on
	 * any blank a control could have carried an answer in (see
	 * AnswerSaveHandler::validate()).
	 *
	 * @since 3.13.0
	 *
	 * @param string $code Error code.
	 *
	 * @return string Empty for a code with no message, which includes ''.
	 */
	private static function error_message( $code ) {

		// phpcs:disable WPForms.Formatting.EmptyLineBeforeReturn.AddEmptyLineBeforeReturnStatement

		switch ( $code ) {
			case 'invalid_option':
				return __( 'Choose one of the field\'s options.', 'sugar-calendar-lite' );

			case 'required':
				return self::required_error_message();
		}
		// phpcs:enable WPForms.Formatting.EmptyLineBeforeReturn.AddEmptyLineBeforeReturnStatement

		return '';
	}

	/**
	 * The message for a required answer a submission left blank.
	 *
	 * Shared with AnswerValidation, which hands it to the script that catches the
	 * same edit before the round trip, so the two cannot word it differently.
	 *
	 * @since 3.13.0
	 *
	 * @return string
	 */
	public static function required_error_message() {

		return __( 'This answer is required.', 'sugar-calendar-lite' );
	}

	/**
	 * Open one field's form-table row.
	 *
	 * A required question must carry an answer to save, matching how the hosts treat
	 * an attendee's name and email; the one exemption is in AnswerFields::can_carry().
	 * A read-only row gets no `for`.
	 *
	 * @since 3.13.0
	 *
	 * @param array  $field     One schema field.
	 * @param string $prefix    POST prefix.
	 * @param bool   $read_only Whether this respondent's answers are read-only.
	 */
	private static function open_field_row( array $field, $prefix, $read_only ) {

		UI::form_table_row_open(
			isset( $field['label'] ) ? (string) $field['label'] : '',
			$read_only ? '' : AnswerFields::input_id( $field, $prefix ),
			! empty( $field['required'] )
		);
	}

	/**
	 * Render answers whose fields are gone from the schema.
	 *
	 * @since 3.13.0
	 *
	 * @param array $fields  The schema's fields.
	 * @param array $answers This respondent's answers.
	 */
	private static function orphans( array $fields, array $answers ) {

		$known = [];

		foreach ( $fields as $field ) {
			if ( isset( $field['id'] ) ) {
				$known[] = (string) $field['id'];
			}
		}

		foreach ( $answers as $field_id => $value ) {

			if ( in_array( (string) $field_id, $known, true ) ) {
				continue;
			}

			AnswerFields::render_orphan( (string) $field_id, $value );
		}
	}
}
