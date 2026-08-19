/* global jQuery, sc_regform_admin_answers */

/**
 * Registration Form answers: client-side guard for the admin editors.
 *
 * Refuses a save that leaves a required answer blank, the same rule the hosts
 * apply to an attendee's name and email, before the round trip — so the
 * organizer gets an inline message instead of a reload whose "updated" notice
 * contradicts the error under the field. Shared by every host that renders
 * ResponsesPanel: the RSVP editor and the order editor.
 *
 * A host whose form also carries controls the server applies regardless of the
 * panels opts out through `blocksSave` (the order editor's Status), leaving the
 * server the only authority: it refuses the panels, names them in its notice, and
 * prints the same message under each failing field.
 *
 * @since 3.13.0
 */
( function( $ ) {

	'use strict';

	/**
	 * Controls belonging to a required field.
	 *
	 * Set by AnswerFields, which omits it for a field the control cannot carry an
	 * answer back for — the server exempts exactly those, so selecting on the
	 * attribute keeps the two guards aligned with no rule duplicated here.
	 *
	 * @since 3.13.0
	 *
	 * @type {string}
	 */
	const CONTROL = '[data-sc-regform-required]';

	/**
	 * Class of the message printed under a failing answer, matching the one the
	 * server prints for a refused save so both look the same.
	 *
	 * @since 3.13.0
	 *
	 * @type {string}
	 */
	const MESSAGE_CLASS = 'sc-regform-admin-field__error';

	/**
	 * The primary save control of a host form.
	 *
	 * A host form can also carry Delete or Resend controls, and an answer that
	 * would be erased is no reason to refuse those.
	 *
	 * @since 3.13.0
	 *
	 * @type {string}
	 */
	const SAVE_SUBMITTER = '#publish, #save-post, .button-primary';

	/**
	 * Localized copy, with a fallback so a stale cached script still says something.
	 *
	 * @since 3.13.0
	 *
	 * @type {Object}
	 */
	const strings = $.extend(
		{ requiredError: 'This answer is required.', blocksSave: true },
		typeof sc_regform_admin_answers !== 'undefined' ? sc_regform_admin_answers : {}
	);

	/**
	 * The last submit control the user pressed.
	 *
	 * Fallback for browsers without SubmitEvent.submitter, where every submit
	 * would otherwise look like a save.
	 *
	 * @since 3.13.0
	 *
	 * @type {Element|null}
	 */
	let lastSubmitter = null;

	$( function() {

		$( document ).on( 'click', 'input[type="submit"], button[type="submit"]', function() {
			lastSubmitter = this;
		} );

		$( document ).on( 'submit', 'form', onSubmit );

		// A corrected answer drops its message immediately, rather than keeping a
		// stale one until the next save attempt.
		$( document ).on( 'input change', CONTROL, function() {
			clearMessage( $( this ) );
		} );
	} );

	/**
	 * Whether this submit is a save rather than a Delete or Resend.
	 *
	 * A programmatic submit carries no submitter (sc-rsvp re-submits the post form
	 * itself after its own checks) and counts as a save. A form's hidden
	 * Enter-key submit isn't the primary control but shares its name, so names are
	 * compared as well as the control itself.
	 *
	 * @since 3.13.0
	 *
	 * @param {jQuery}       $form     The submitting form.
	 * @param {Element|null} submitter The control that submitted it.
	 *
	 * @return {boolean} Whether to validate this submit.
	 */
	function isSave( $form, submitter ) {

		if ( ! submitter ) {
			return true;
		}

		if ( $( submitter ).is( SAVE_SUBMITTER ) ) {
			return true;
		}

		const name = submitter.name || '';

		return name !== '' && $form.find( SAVE_SUBMITTER ).toArray().some( function( control ) {
			return control.name === name;
		} );
	}

	/**
	 * The POST name shared by one field's controls.
	 *
	 * Checkboxes post as `name[]`; the group is the name without it.
	 *
	 * @since 3.13.0
	 *
	 * @param {Element} control One control.
	 *
	 * @return {string} The group key.
	 */
	function groupKey( control ) {

		return ( control.name || '' ).replace( /\[\]$/, '' );
	}

	/**
	 * Every control posting under one group key.
	 *
	 * @since 3.13.0
	 *
	 * @param {string} key The group key.
	 *
	 * @return {jQuery} The group's controls.
	 */
	function group( key ) {

		return $( document.body ).find( CONTROL ).filter( function() {
			return groupKey( this ) === key;
		} );
	}

	/**
	 * Whether a group currently holds an answer.
	 *
	 * @since 3.13.0
	 *
	 * @param {string} key The group key.
	 *
	 * @return {boolean} Whether anything is answered.
	 */
	function hasValue( key ) {

		return group( key ).toArray().some( function( control ) {

			if ( control.type === 'radio' || control.type === 'checkbox' ) {
				return control.checked;
			}

			return String( control.value || '' ).trim().length > 0;
		} );
	}

	/**
	 * Validate every required answer the form carries, and block a save that
	 * leaves one blank, unless the host opted out of being blocked.
	 *
	 * @since 3.13.0
	 *
	 * @param {Event} e The submit event.
	 */
	function onSubmit( e ) {

		// Marking without blocking would only paint a page that is already leaving,
		// so an opted-out host skips the pass entirely.
		if ( ! strings.blocksSave ) {
			return;
		}

		const $form = $( e.target );

		if ( $form.find( CONTROL ).length <= 0 ) {
			return;
		}

		if ( ! isSave( $form, ( e.originalEvent && e.originalEvent.submitter ) || lastSubmitter ) ) {
			return;
		}

		// Includes anything the server printed for the previous attempt: it
		// describes a submission this one replaces.
		$form.find( '.' + MESSAGE_CLASS ).remove();

		const blank = [];

		$form.find( CONTROL ).each( function() {

			const key = groupKey( this );

			if ( blank.indexOf( key ) !== -1 || hasValue( key ) ) {
				return;
			}

			blank.push( key );
		} );

		if ( blank.length <= 0 ) {
			return;
		}

		blank.forEach( markGroup );

		e.preventDefault();

		group( blank[ 0 ] ).first().trigger( 'focus' );
	}

	/**
	 * Print the message for one blank group.
	 *
	 * Appended to the field's cell, where the server puts its own, so the host's
	 * invalid-cell styling applies to both.
	 *
	 * @since 3.13.0
	 *
	 * @param {string} key The group key.
	 */
	function markGroup( key ) {

		const $controls = group( key );
		const $message = $( '<p></p>' ).addClass( MESSAGE_CLASS ).text( strings.requiredError );
		const $cell = $controls.first().closest( 'td' );

		$controls.attr( 'aria-invalid', 'true' );

		if ( $cell.length > 0 ) {
			$cell.append( $message );

			return;
		}

		$message.insertAfter( $controls.last() );
	}

	/**
	 * Drop one answer's message and invalid flag.
	 *
	 * @since 3.13.0
	 *
	 * @param {jQuery} $control The control being corrected.
	 */
	function clearMessage( $control ) {

		const $controls = group( groupKey( $control.get( 0 ) ) );
		const $cell = $control.closest( 'td' );

		$controls.removeAttr( 'aria-invalid' );

		if ( $cell.length > 0 ) {
			$cell.find( '.' + MESSAGE_CLASS ).remove();

			return;
		}

		$controls.nextAll( '.' + MESSAGE_CLASS ).remove();
	}
}( jQuery ) );
