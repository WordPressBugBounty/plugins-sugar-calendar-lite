<?php

namespace Sugar_Calendar\Features\RegistrationForm;

/**
 * Structural + completeness validation for the registration form schema.
 *
 * Pure array-in/array-out (no WP classes) so it is unit-testable and shared
 * verbatim by the save handler and future consumers. Completeness rules apply
 * only when the form is enabled; a disabled form is a draft and may be incomplete.
 *
 * @since 3.13.0
 */
class SchemaValidator {

	/**
	 * Allowed field types.
	 *
	 * @since 3.13.0
	 *
	 * @var string[]
	 */
	const TYPES = [ 'short_text', 'long_text', 'checkbox', 'radio', 'dropdown' ];

	/**
	 * Field types that carry option rows.
	 *
	 * @since 3.13.0
	 *
	 * @var string[]
	 */
	const CHOICE_TYPES = [ 'checkbox', 'radio', 'dropdown' ];

	/**
	 * Field types that carry a free-text answer.
	 *
	 * The complement of CHOICE_TYPES within TYPES, declared rather than derived so
	 * a new type forces a decision about which list it belongs to.
	 *
	 * @since 3.13.0
	 *
	 * @var string[]
	 */
	const TEXT_TYPES = [ 'short_text', 'long_text' ];

	/**
	 * Allowed `show` values.
	 *
	 * @since 3.13.0
	 *
	 * @var string[]
	 */
	const SHOW_VALUES = [ 'before_checkout', 'after_checkout' ];

	/**
	 * Allowed `collect` values.
	 *
	 * @since 3.13.0
	 *
	 * @var string[]
	 */
	const COLLECT_VALUES = [ 'main_attendee', 'each_attendee' ];

	/**
	 * Field id shape. Ids are minted by the editor and immutable.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const FIELD_ID_PATTERN = '/^f_[a-z0-9]{4,20}$/';

	/**
	 * One field's option strings.
	 *
	 * The single reading of a field's `options` key; every consumer must
	 * normalise it the same way or a stored answer's validity disagrees between them.
	 *
	 * @since 3.13.0
	 *
	 * @param array $field One field from a stored schema.
	 *
	 * @return string[]
	 */
	public static function field_options( array $field ) {

		$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : [];

		return array_map( 'strval', $options );
	}

	/**
	 * Validate and sanitize a raw schema array.
	 *
	 * @since 3.13.0
	 *
	 * @param array $raw Raw (decoded) schema.
	 *
	 * @return array { valid: bool, schema: array, errors: array<string,string> }
	 */
	public static function validate( array $raw ) {

		$errors  = [];
		$enabled = ! empty( $raw['enabled'] );

		$schema = [
			'version'      => 1,
			'enabled'      => $enabled,
			'show'         => self::resolve_enum( $raw['show'] ?? '', self::SHOW_VALUES, 'before_checkout' ),
			'collect'      => self::resolve_enum( $raw['collect'] ?? '', self::COLLECT_VALUES, 'main_attendee' ),
			// Absent reads false, so no event saved before this feature becomes
			// editable without the organizer asking for it.
			'allow_edit'   => ! empty( $raw['allow_edit'] ),
			'ticket_types' => self::normalize_ticket_types( $raw ),
			'fields'       => [],
		];

		$seen_ids = [];

		foreach ( (array) ( $raw['fields'] ?? [] ) as $field ) {

			if ( ! is_array( $field ) ) {
				continue;
			}

			$result = self::validate_field( $field, $seen_ids, $enabled );

			if ( $result['error_key'] !== null ) {
				$errors[ $result['error_key'] ] = $result['error'];
			}

			if ( $result['field'] !== null ) {
				$schema['fields'][] = $result['field'];
			}
		}

		if ( self::missing_ticket_types( $schema, $enabled ) ) {
			$errors['_form'] = 'no_ticket_types';
		}

		return [
			'valid'  => $errors === [],
			'schema' => $schema,
			'errors' => $errors,
		];
	}

	/**
	 * Fall back to a default when a raw value is not one of the allowed values.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed  $value    Raw value.
	 * @param array  $allowed  Allowed values.
	 * @param string $fallback Fallback value.
	 *
	 * @return string
	 */
	private static function resolve_enum( $value, array $allowed, $fallback ) {

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Normalize the raw `ticket_types` value to 'all' or a list of int ids.
	 *
	 * @since 3.13.0
	 *
	 * @param array $raw Raw (decoded) schema.
	 *
	 * @return string|int[]
	 */
	private static function normalize_ticket_types( array $raw ) {

		if ( ! isset( $raw['ticket_types'] ) || ! is_array( $raw['ticket_types'] ) ) {
			return 'all';
		}

		// Filter for numeric BEFORE intval(), which maps '', 'abc' and null to 0 —
		// and 0 is now a legitimate id (general admission), so those would pass the
		// no_ticket_types check and save a form targeting general buyers by accident.
		$ids = array_filter(
			array_map( 'intval', array_filter( $raw['ticket_types'], 'is_numeric' ) ),
			[ self::class, 'is_valid_ticket_type_id' ]
		);

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Whether a normalized ticket type id could name a real ticket type.
	 *
	 * 0 is the general (default) ticket type, which general-admission attendees
	 * post as their ticket_type; only negative ids can't exist.
	 *
	 * @since 3.13.0
	 *
	 * @param int $id Normalized ticket type id.
	 *
	 * @return bool
	 */
	private static function is_valid_ticket_type_id( $id ) {

		return $id >= 0;
	}

	/**
	 * Validate and sanitize a single raw field row.
	 *
	 * @since 3.13.0
	 *
	 * @param array $field    Raw field row.
	 * @param array $seen_ids Field ids already seen, by reference (tracks duplicates).
	 * @param bool  $enabled  Whether the schema is enabled (gates completeness rules).
	 *
	 * @return array { error_key: string|null, error: string|null, field: array|null }
	 */
	private static function validate_field( array $field, array &$seen_ids, $enabled ) {

		$id = isset( $field['id'] ) ? (string) $field['id'] : '';

		if ( ! preg_match( self::FIELD_ID_PATTERN, $id ) ) {
			return [
				'error_key' => '_form',
				'error'     => 'invalid_id',
				'field'     => null,
			];
		}

		if ( isset( $seen_ids[ $id ] ) ) {
			return [
				'error_key' => $id,
				'error'     => 'duplicate_id',
				'field'     => null,
			];
		}

		$seen_ids[ $id ] = true;

		$type = isset( $field['type'] ) ? (string) $field['type'] : '';

		if ( ! in_array( $type, self::TYPES, true ) ) {
			return [
				'error_key' => $id,
				'error'     => 'invalid_type',
				'field'     => null,
			];
		}

		$is_choice = in_array( $type, self::CHOICE_TYPES, true );
		$label     = sanitize_text_field( $field['label'] ?? '' );
		$options   = $is_choice ? self::sanitize_options( $field ) : [];
		$error     = self::completeness_error( $enabled, $is_choice, $label, $options );

		return [
			'error_key' => $error !== null ? $id : null,
			'error'     => $error,
			'field'     => [
				'id'       => $id,
				'type'     => $type,
				'label'    => $label,
				'required' => ! empty( $field['required'] ),
				'options'  => $options,
			],
		];
	}

	/**
	 * The completeness error for an enabled field, if any.
	 *
	 * Completeness rules only apply when the schema is enabled; a disabled
	 * form is a draft and may be incomplete.
	 *
	 * @since 3.13.0
	 *
	 * @param bool     $enabled   Whether the schema is enabled.
	 * @param bool     $is_choice Whether the field is a choice type.
	 * @param string   $label     Sanitized label.
	 * @param string[] $options   Sanitized options.
	 *
	 * @return string|null
	 */
	private static function completeness_error( $enabled, $is_choice, $label, array $options ) {

		if ( ! $enabled ) {
			return null;
		}

		if ( $label === '' ) {
			return 'missing_title';
		}

		return ( $is_choice && $options === [] ) ? 'no_options' : null;
	}

	/**
	 * Sanitize a choice field's options, dropping blank ones.
	 *
	 * @since 3.13.0
	 *
	 * @param array $field Raw field row.
	 *
	 * @return string[]
	 */
	private static function sanitize_options( array $field ) {

		$options = [];

		foreach ( (array) ( $field['options'] ?? [] ) as $option ) {
			$option = sanitize_text_field( $option );

			if ( $option !== '' ) {
				$options[] = $option;
			}
		}

		return $options;
	}

	/**
	 * Whether an enabled per-attendee schema has no ticket types selected.
	 *
	 * @since 3.13.0
	 *
	 * @param array $schema  Schema built so far.
	 * @param bool  $enabled Whether the schema is enabled.
	 *
	 * @return bool
	 */
	private static function missing_ticket_types( array $schema, $enabled ) {

		return $enabled
			&& $schema['collect'] === 'each_attendee'
			&& is_array( $schema['ticket_types'] )
			&& $schema['ticket_types'] === [];
	}
}
