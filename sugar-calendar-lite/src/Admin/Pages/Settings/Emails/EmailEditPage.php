<?php

namespace Sugar_Calendar\Admin\Pages\Settings\Emails;

use Sugar_Calendar\Emails\EmailInterface;
use Sugar_Calendar\Emails\EmailRegistry;
use Sugar_Calendar\Helpers\UI;

/**
 * Renders one per-email edit screen.
 *
 * Callers own their own storage, so every value and POST key arrives through
 * $args — core passes ticketing settings, the RSVP add-on passes its own
 * options. Only the heading and the toggle state are resolved here, from
 * `Sugar_Calendar\Emails\EmailRegistry`.
 *
 * @since 3.14.0
 */
class EmailEditPage {

	/**
	 * Render the edit screen for a registered email.
	 *
	 * Null-tolerant so the deprecated per-id wrappers stay safe when a
	 * definition is absent, for example an unlicensed add-on that never
	 * registered.
	 *
	 * @since 3.14.0
	 *
	 * @param EmailInterface|null $email Registered definition, or null.
	 */
	public static function render_email( ?EmailInterface $email ) { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.typeDeclarationFound -- Sugar Calendar requires PHP 7.4+.

		$editor = $email === null ? null : $email->editor();

		if ( $editor === null ) {
			return;
		}

		$slugs = $email->option_slugs();

		self::render(
			array_merge(
				$editor,
				[
					'email_id'     => $email->id(),
					'subject_name' => isset( $slugs['subject'] ) ? $slugs['subject'] : '',
					'message_name' => isset( $slugs['message'] ) ? $slugs['message'] : '',
					'smart_tags'   => $email->smart_tags(),
				]
			)
		);
	}

	/**
	 * Render an edit screen.
	 *
	 * Also emits a hidden `email_enabled_present_{$email_id}` marker so the save
	 * path can tell "this screen rendered the Status toggle" from "the admin
	 * unchecked it" — see the comment beside it.
	 *
	 * @since 3.14.0
	 *
	 * @param array $args {
	 *     Screen arguments.
	 *
	 *     @type string $email_id            Registry id; supplies the heading.
	 *     @type string $title               Optional heading override.
	 *     @type bool   $status_value        Status toggle state. Omit (null) to
	 *                                       read the stored flag via EmailStatus.
	 *     @type bool   $status_disabled     Render the Status toggle disabled —
	 *                                       for emails that have no sender, or a
	 *                                       sender with no automatic caller. Omit
	 *                                       to derive it through
	 *                                       EmailsList::status_state(), which is
	 *                                       what keeps this in step with the list
	 *                                       row.
	 *     @type string $subject_id          Control id for the subject input.
	 *     @type string $subject_name        Flat POST key for the subject.
	 *     @type string $subject_value       Current subject.
	 *     @type string $subject_placeholder Subject placeholder.
	 *     @type string $subject_description Subject help text.
	 *     @type string $message_editor_id   TinyMCE instance id (smart-tags key).
	 *     @type string $message_name        Flat POST key for the message.
	 *     @type string $message_value       Current message.
	 *     @type string $message_label       Message field label.
	 *     @type array  $smart_tags          From the definition's smart_tags().
	 * }
	 */
	public static function render( $args ) {

		$args = wp_parse_args(
			$args,
			[
				'email_id'            => '',
				'title'               => '',
				'status_value'        => null,
				'status_disabled'     => null,
				'subject_id'          => '',
				'subject_name'        => '',
				'subject_value'       => '',
				'subject_placeholder' => '',
				'subject_description' => '',
				'message_editor_id'   => '',
				'message_name'        => '',
				'message_value'       => '',
				'message_label'       => esc_html__( 'Email Message', 'sugar-calendar-lite' ),
				'smart_tags'          => [],
			]
		);

		// The heading comes from the registry, but a registry miss must NOT
		// suppress the fields. SingleEmailConfig::init() has already authorised
		// this screen against a DIFFERENT list (the valid-ids filter), and the
		// two lists are maintained on separate release cycles — so bailing here
		// would turn any skew into a blank page with no notice and no log line.
		// Degrade to "no heading", never to "no form".
		$email = EmailRegistry::get( $args['email_id'] );
		$title = $args['title'] !== '' ? $args['title'] : ( $email === null ? '' : $email->title() );

		// Resolve the toggle from the listing unless the caller was explicit.
		// Deriving is the default because the list row resolves it the same way —
		// through EmailsList::status_state(), one shared definition. Requiring
		// every caller to remember the argument is how the two screens end up
		// disagreeing about the same email. An unknown or hidden id degrades to a
		// disabled toggle, matching the heading's degrade-don't-bail rule.
		$status = EmailsList::status_state( $email );

		$status_disabled = $args['status_disabled'] === null
			? $status['disabled']
			: (bool) $args['status_disabled'];

		$status_value = $args['status_value'] === null
			? $status['value']
			: (bool) $args['status_value'];

		if ( $title !== '' ) {
			printf(
				'<h4 class="sugar-calendar-settings-emails-edit__heading">%1$s</h4>',
				esc_html( $title )
			);
		}

		// Presence marker for the Status toggle below.
		//
		// A checkbox posts nothing when it is unchecked — and an edit screen that
		// never rendered one posts nothing either. Those two states are NOT the
		// same, and the save path cannot tell them apart from the toggle key
		// alone: it would read "no toggle on this screen" as "the admin switched
		// this email off" and write an explicit `false`.
		printf(
			'<input type="hidden" name="sugar-calendar[%1$s]" value="1">',
			esc_attr( sanitize_key( 'email_enabled_present_' . $args['email_id'] ) )
		);

		// The field name matches the list row's (`email_enabled_{id}`) so one key
		// exists per email across both screens. The *option* is separately named
		// `sugar_calendar_email_status` — do not conflate the two.
		UI::toggle_control(
			[
				'id'          => 'email_enabled_' . $args['email_id'],
				'name'        => 'email_enabled_' . $args['email_id'],
				'value'       => $status_value,
				'disabled'    => $status_disabled,
				'label'       => esc_html__( 'Status', 'sugar-calendar-lite' ),
				'description' => $status['description'],
			]
		);

		UI::text_input(
			[
				'id'          => $args['subject_id'] !== '' ? $args['subject_id'] : $args['subject_name'],
				'name'        => $args['subject_name'],
				'value'       => $args['subject_value'],
				'placeholder' => $args['subject_placeholder'],
				'label'       => esc_html__( 'Email Subject', 'sugar-calendar-lite' ),
				'description' => $args['subject_description'],
			]
		);

		UI::wysiwyg_editor(
			[
				'id'            => $args['message_editor_id'],
				'name'          => $args['message_name'],
				'value'         => $args['message_value'],
				'label'         => $args['message_label'],
				'media_buttons' => true,
				'rows'          => 15,
				'smart_tags'    => $args['smart_tags'],
			]
		);
	}

	/**
	 * Render a non-submitting preview action beside the Settings save button.
	 *
	 * @since 3.14.0
	 *
	 * @param string $route    Preview creator route.
	 * @param string $email_id Email id.
	 */
	public static function render_preview_button( $route, $email_id ) {

		UI::button(
			[
				'class'  => 'sugar-calendar-settings-emails__preview-button',
				'type'   => 'secondary',
				'text'   => esc_html__( 'Preview', 'sugar-calendar-lite' ),
				'submit' => false,
				'data'   => [
					'sc-email-preview' => $route,
					'email-id'         => $email_id,
				],
			]
		);
	}
}
