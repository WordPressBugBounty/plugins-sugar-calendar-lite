<?php

namespace Sugar_Calendar\Features\RegistrationForm\Admin;

/**
 * User-facing copy for the Registration Form editor.
 *
 * Single source of truth shared by two renderers: the React app's bootstrap
 * payload on Pro (MetaboxSection::get_bootstrap_data()) and the static Lite
 * education mock (LiteEducationSection::render()). Kept in one place so the
 * picture Lite users see can never drift from the editor they are buying.
 *
 * @since 3.13.0
 */
class EditorStrings {

	/**
	 * All editor strings, keyed for the JS bootstrap payload.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	public static function all() {

		return [
			'section_label'          => __( 'Registration Form', 'sugar-calendar-lite' ),
			'toggle_on'              => __( 'On', 'sugar-calendar-lite' ),
			'toggle_off'             => __( 'Off', 'sugar-calendar-lite' ),
			'toggle_helper'          => __( 'Collect additional details from your attendees when they register for the event.', 'sugar-calendar-lite' ),
			'when_label'             => __( 'When to fill the form', 'sugar-calendar-lite' ),
			'when_helper'            => __( 'Specify when attendee must fill the form.', 'sugar-calendar-lite' ),
			'before_checkout'        => __( 'Before Checkout', 'sugar-calendar-lite' ),
			'after_checkout'         => __( 'After Checkout', 'sugar-calendar-lite' ),
			'collect_label'          => __( 'Collect data for', 'sugar-calendar-lite' ),
			'collect_helper'         => __( 'Specify who to collect registration data from.', 'sugar-calendar-lite' ),
			'main_attendee'          => __( 'Main Attendee Only', 'sugar-calendar-lite' ),
			'each_attendee'          => __( 'Each Attendee', 'sugar-calendar-lite' ),
			'ticket_label'           => __( 'Collect for ticket type', 'sugar-calendar-lite' ),
			'ticket_helper'          => __( 'Which ticket types would need to fill the registration form.', 'sugar-calendar-lite' ),
			// Shown in place of the targeting radios when the event has no second ticket
			// type to target. <tickets> becomes a button that opens the Tickets tab; the
			// tag must stay paired and unrenamed in translation.
			'ticket_empty'           => __( 'Multiple tickets not found', 'sugar-calendar-lite' ),
			'ticket_empty_helper'    => __( 'Add <tickets>multiple tickets</tickets> to then define which ticket types would need to fill the registration form.', 'sugar-calendar-lite' ),
			// Same row, but a ticket type HAS been added and is only waiting on a save:
			// it has no id yet, so no answer could reference it. Asking for the update is
			// the whole message — no link, since the update button is already on screen.
			'ticket_unsaved'         => __( 'New ticket type not saved yet', 'sugar-calendar-lite' ),
			'ticket_unsaved_helper'  => __( 'Update the event to then define which ticket types would need to fill the registration form.', 'sugar-calendar-lite' ),
			'all_types'              => __( 'All Types', 'sugar-calendar-lite' ),
			'custom'                 => __( 'Custom', 'sugar-calendar-lite' ),
			'select_types'           => __( 'Select ticket types', 'sugar-calendar-lite' ),
			'edit_after_label'       => __( 'Edit after submission', 'sugar-calendar-lite' ),
			'edit_after_helper'      => __( 'Allow attendees to edit forms post-submission', 'sugar-calendar-lite' ),
			// Shown in place of the helper when "When to fill" is Before checkout: those
			// forms are answered inline and never mint the resume token an edit needs.
			'edit_after_unavailable' => __( 'Available only when the form is filled after checkout.', 'sugar-calendar-lite' ),
			'title_error'            => __( 'Please add a question title for this field', 'sugar-calendar-lite' ),
			'options_error'          => __( 'Please add at least one option for this field', 'sugar-calendar-lite' ),
			'ticket_types_error'     => __( 'Please select at least one ticket type', 'sugar-calendar-lite' ),
			// The tags are interpolated by the React notice (NoHostNotice): <b> emphasises
			// the lead-in, <tickets>/<rsvp> become buttons that open the matching metabox
			// tab. Translators must keep them paired and unrenamed.
			'no_host_notice'         => __( '<b>Heads up:</b> attendees won\'t see this form until you enable <tickets>Tickets</tickets> or <rsvp>RSVP</rsvp> for this event', 'sugar-calendar-lite' ),
			'block_notice'           => __( 'Registration Form has incomplete fields. Please review the Registration Form tab before saving.', 'sugar-calendar-lite' ),
			'block_notice_action'    => __( 'Review Form', 'sugar-calendar-lite' ),
			'add_new_field'          => __( 'Add New Field', 'sugar-calendar-lite' ),
			'add_option'             => __( 'Add Option', 'sugar-calendar-lite' ),
			'required'               => __( 'Required', 'sugar-calendar-lite' ),
			'question_placeholder'   => __( 'Add Question Title*', 'sugar-calendar-lite' ),
			'duplicate_field'        => __( 'Duplicate field', 'sugar-calendar-lite' ),
			'delete_field'           => __( 'Delete field', 'sugar-calendar-lite' ),
			'remove_option'          => __( 'Remove option', 'sugar-calendar-lite' ),
			'option_aria'            => __( 'Option', 'sugar-calendar-lite' ),
			'type_short_text'        => __( 'Text Field', 'sugar-calendar-lite' ),
			'type_long_text'         => __( 'Long Text Field', 'sugar-calendar-lite' ),
			'type_radio'             => __( 'Multiple Choices', 'sugar-calendar-lite' ),
			'type_checkbox'          => __( 'Checkboxes', 'sugar-calendar-lite' ),
			'type_dropdown'          => __( 'Dropdowns', 'sugar-calendar-lite' ),
		];
	}
}
