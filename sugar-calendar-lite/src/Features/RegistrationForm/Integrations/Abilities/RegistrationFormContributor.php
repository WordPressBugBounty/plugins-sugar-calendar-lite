<?php
/**
 * The `registration_form` slice of the event write abilities.
 *
 * @since 3.14.0
 */

namespace Sugar_Calendar\Features\RegistrationForm\Integrations\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Features\RegistrationForm\SchemaRepository;
use Sugar_Calendar\Features\RegistrationForm\SchemaValidator;

/**
 * RegistrationFormContributor — lets an ability attach a registration form to an event.
 *
 * Hands the object to `SchemaRepository::save()` and lets `SchemaValidator` accept or reject it.
 * The schema property is offered only on a paid plan, so the model never proposes a form the
 * save would refuse.
 *
 * @since 3.14.0
 */
class RegistrationFormContributor {

	/**
	 * Input property name.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const PROPERTY = 'registration_form';

	/**
	 * How many questions one form may take through an ability.
	 *
	 * @since 3.14.0
	 *
	 * @var int
	 */
	const MAX_FIELDS = 12;

	/**
	 * Register the hooks.
	 *
	 * @since 3.14.0
	 *
	 * @return void
	 */
	public static function hooks() {

		add_filter( 'sugar_calendar_integrations_abilities_event_input_schema', [ self::class, 'add_schema' ], 10, 2 );
		add_action( 'sugar_calendar_events_event_writer_after_write', [ self::class, 'apply' ], 10, 3 );
	}

	/**
	 * Widen the input schema, on a paid plan only.
	 *
	 * Create-only: field ids are re-derived from the label on every apply, and stored answers
	 * are keyed by that id, so patching a label on update would orphan existing responses.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $properties Properties so far.
	 * @param mixed $partial    Whether this is the update variant.
	 *
	 * @return mixed
	 */
	public static function add_schema( $properties, $partial ) {

		if ( ! is_array( $properties ) || ! empty( $partial ) || ! sugar_calendar()->is_pro() ) {
			return $properties;
		}

		$properties[ self::PROPERTY ] = [
			'type'                 => 'object',
			'additionalProperties' => false,
			'description'          => __( 'Questions to ask people when they register.', 'sugar-calendar-lite' ),
			'properties'           => [
				'enabled' => [
					'type'    => 'boolean',
					'default' => true,
				],
				'fields'  => [
					'type'     => 'array',
					'maxItems' => self::MAX_FIELDS,
					'items'    => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'type', 'label' ],
						'properties'           => [
							'type'     => [
								'type' => 'string',
								'enum' => SchemaValidator::TYPES,
							],
							'label'    => [
								'type'      => 'string',
								'minLength' => 1,
								'maxLength' => 200,
							],
							'required' => [
								'type'    => 'boolean',
								'default' => false,
							],
							'options'  => [
								'type'        => 'array',
								'maxItems'    => 30,
								'items'       => [ 'type' => 'string' ],
								'description' => __( 'Choices, for a checkbox, radio or dropdown question.', 'sugar-calendar-lite' ),
							],
						],
					],
				],
			],
		];

		return $properties;
	}

	/**
	 * Save the form.
	 *
	 * @since 3.14.0
	 *
	 * @param int   $event_id   Sugar Calendar event id.
	 * @param array $normalized Normalized input.
	 * @param array $result     Result so far, by reference.
	 *
	 * @return void
	 */
	public static function apply( $event_id, $normalized, &$result ) {

		$extra = isset( $normalized['extra'] ) ? (array) $normalized['extra'] : [];

		if ( empty( $extra[ self::PROPERTY ] ) ) {
			return;
		}

		$input = (array) $extra[ self::PROPERTY ];

		$saved = SchemaRepository::save(
			(int) $event_id,
			[
				'enabled' => ! isset( $input['enabled'] ) || ! empty( $input['enabled'] ),
				'fields'  => self::mint_field_ids( isset( $input['fields'] ) ? (array) $input['fields'] : [] ),
			]
		);

		if ( is_wp_error( $saved ) ) {
			$result['errors'][] = sprintf(
				/* translators: %s: reason the form could not be saved. */
				__( 'The event was created, but the registration form was not saved: %s', 'sugar-calendar-lite' ),
				$saved->get_error_message()
			);

			return;
		}

		$result['applied'][] = __( 'Added the registration form.', 'sugar-calendar-lite' );
	}

	/**
	 * Mint a field id for each raw field.
	 *
	 * The editor mints `f_…` ids and SchemaValidator requires that format, so a
	 * field arriving from an ability has to get one before it reaches save().
	 * Deterministic (label + position), so resubmitting the same form doesn't churn ids.
	 *
	 * @since 3.14.0
	 *
	 * @param array $fields Raw field rows, no `id`.
	 *
	 * @return array
	 */
	private static function mint_field_ids( array $fields ) {

		foreach ( $fields as $index => &$field ) {

			if ( ! is_array( $field ) ) {
				continue;
			}

			$label       = isset( $field['label'] ) ? (string) $field['label'] : '';
			$field['id'] = 'f_' . substr( md5( $label . '|' . $index ), 0, 12 );
		}

		return $fields;
	}
}
