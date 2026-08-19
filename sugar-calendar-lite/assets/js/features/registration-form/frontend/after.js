/* global jQuery, sc_regform_after */

/**
 * After-checkout registration form controller.
 *
 * Opens a non-dismissible modal on the receipt page and posts the answers to
 * sc_registration_submit. The token in the markup is the credential; there is no
 * nonce, because the resume link arrives without one.
 *
 * @since 3.13.0
 */
( function ( $ ) {
	'use strict';

	var el = {};

	/**
	 * Whether the form has hit a failure no retry can clear.
	 *
	 * Sticky because .always() runs after onDone(), so without it Submit would be
	 * re-enabled again right after showTerminalError() disabled it.
	 *
	 * @since 3.13.0
	 *
	 * @type {boolean}
	 */
	var terminal = false;

	/**
	 * Whether the shared general.js API is available.
	 *
	 * @since 3.13.0
	 *
	 * @return {boolean} True when the shared API is loaded.
	 */
	function hasApi() {
		return !! ( window.SugarCalendar && window.SugarCalendar.RegistrationForm );
	}

	/**
	 * Paint a generic, non-field-specific error.
	 *
	 * The banner is prepended to the form root, outside the step general.js's
	 * clearErrors() walks, so submit() has to clear it explicitly each attempt.
	 *
	 * @since 3.13.0
	 *
	 * @param {string} message Text to show. Falls back to a generic string when empty.
	 */
	function showGenericError( message ) {
		el.$root.prepend(
			$( '<p class="sc-regform__error sc-regform__error--form"></p>' ).text(
				message || sc_regform_after.genericError
			)
		);
	}

	/**
	 * Paint an unrecoverable failure and add a Close button.
	 *
	 * The modal has no close button, no backdrop click and no Escape, so a failure
	 * that repeats on every attempt would trap the visitor. Closing loses the typed
	 * answers, which is why retryable failures never come here.
	 *
	 * @since 3.13.0
	 *
	 * @param {string} message Text to show. Falls back to the terminal string.
	 */
	function showTerminalError( message ) {
		terminal = true;

		el.$submit.prop( 'disabled', true );

		// Install the exit before painting: a throw in showGenericError() must not
		// leave a disabled modal with no Close button.
		ensureClose();

		showGenericError( message || sc_regform_after.terminalError );
	}

	/**
	 * Add the Close button to the footer, once.
	 *
	 * The terminal-error exit. The form modal opens with no close control, no
	 * backdrop click and no Escape, so a failure that repeats on every attempt
	 * needs one; success doesn't, since it closes this modal outright.
	 *
	 * @since 3.13.0
	 *
	 * @return {jQuery} The Close button.
	 */
	function ensureClose() {
		if ( ! el.$close ) {
			el.$close = $( '<button type="button" class="sc-regform__close"></button>' )
				.text( sc_regform_after.closeLabel )
				.on( 'click', function () {
					el.$modal.modal( 'hide' );
				} );

			el.$modal.find( '.modal-footer' ).append( el.$close );
		}

		return el.$close;
	}

	/**
	 * Finish: drop the form modal, then open the confirmation as its own stage.
	 *
	 * The design gives the confirmation a whole screen of its own — the shell
	 * carrying nothing but a centred check and one line — so this is a second
	 * modal shown after the first has gone, not a repaint of the form's shell.
	 * Waiting for `hidden` rather than opening both at once keeps Bootstrap's
	 * backdrop bookkeeping straight: two modals open together leave the
	 * `modal-open` body class and one backdrop behind when the first closes.
	 *
	 * @since 3.13.0
	 */
	function showSuccess() {
		el.$modal.one( 'hidden.bs.modal', openSuccessModal );
		el.$modal.modal( 'hide' );
	}

	/**
	 * Build and open the confirmation modal.
	 *
	 * Dismissible, unlike the form it replaces: nothing is left to lose, which is
	 * why the design gives it no heading row and no × — its own button, the
	 * backdrop and Escape all end it. Removed from the DOM on close, so a second
	 * completion in the same page never stacks two of them.
	 *
	 * @since 3.13.0
	 */
	function openSuccessModal() {
		var $modal = $(
			'<div class="modal sc-regform-modal sc-regform-modal--success" tabindex="-1" role="dialog" aria-modal="true">' +
				'<div class="modal-dialog" role="document">' +
					'<div class="modal-content">' +
						'<div class="modal-body"></div>' +
					'</div>' +
				'</div>' +
			'</div>'
		);

		// The dialog has no heading element to point aria-labelledby at, so the
		// outcome is its accessible name; role="status" announces it on arrival.
		$modal.attr( 'aria-label', sc_regform_after.successTitle );

		$modal.find( '.modal-body' ).append( successStage( $modal ) );

		$( 'body' ).append( $modal );

		$modal.on( 'hidden.bs.modal', function () {
			$modal.remove();
		} );

		$modal.modal( {
			backdrop: true,
			keyboard: true
		} );
	}

	/**
	 * The confirmation itself: check, title, description, one button.
	 *
	 * The button dismisses rather than navigates — on both hosts the page underneath
	 * is already where its label points.
	 *
	 * @since 3.13.0
	 *
	 * @param {jQuery} $modal The modal the button dismisses.
	 *
	 * @return {jQuery} The stage.
	 */
	function successStage( $modal ) {
		return $( '<div class="sc-regform-success"></div>' )
			.append( $( '<div class="sc-regform-success__icon" aria-hidden="true"></div>' ) )
			.append(
				$( '<p class="sc-regform-success__text" role="status"></p>' ).text(
					sc_regform_after.successTitle
				)
			)
			.append(
				$( '<p class="sc-regform-success__description"></p>' ).text(
					sc_regform_after.successDescription
				)
			)
			.append(
				$( '<button type="button" class="sc-regform-success__cta"></button>' )
					.text( sc_regform_after.successCta )
					.on( 'click', function () {
						$modal.modal( 'hide' );
					} )
			);
	}

	/**
	 * Delete the note that asked for the confirmation, so a refresh does not repeat it.
	 *
	 * Consumed here rather than server-side: both landing seams run mid-body, with the
	 * headers already sent. See CompletionFlash.
	 *
	 * @since 3.13.0
	 */
	function consumeFlash() {
		if ( ! sc_regform_after.flashCookie ) {
			return;
		}

		document.cookie = sc_regform_after.flashCookie + '=; path=/; max-age=0';
	}

	/**
	 * Interpret the AJAX response.
	 *
	 * The non-object guard covers a WordPress -1/0 "no such action" body and an
	 * HTML error page, both of which jQuery hands back as a string.
	 *
	 * @since 3.13.0
	 *
	 * @param {*} response The parsed (or unparsed) response body.
	 */
	function handleResponse( response ) {
		if ( ! response || typeof response !== 'object' ) {
			showGenericError();

			return;
		}

		if ( response.success ) {
			showSuccess();

			return;
		}

		// Nothing to paint, but silence in a modal that cannot be dismissed reads
		// as a dead Submit button.
		if ( ! response.data ) {
			showGenericError();

			return;
		}

		if ( response.data.registration && response.data.registration.truncated ) {
			showGenericError( response.data.registration.truncated );

			return;
		}

		if ( response.data.registration && response.data.registration.errors ) {
			var painted = window.SugarCalendar.RegistrationForm.showErrors( response.data.registration.errors );

			// An error map that keys no rendered block (e.g. the schema was
			// switched away from this attendee) paints nothing. Treat it as
			// terminal: the same map comes back every attempt and no visible
			// field is the one being complained about.
			if ( ! painted ) {
				showTerminalError();
			}

			return;
		}

		if ( response.data.message ) {
			// `terminal` is the server saying a retry cannot help
			// (SubmitEndpoint::fail_generic()). Without it (a throttle, a
			// transient write failure) Submit stays usable.
			if ( response.data.terminal ) {
				showTerminalError( response.data.message );
			} else {
				showGenericError( response.data.message );
			}
		}
	}

	/**
	 * Handle the AJAX response without ever leaving Submit stuck disabled.
	 *
	 * jQuery runs a Deferred's done callbacks as one list, so a throw here aborts
	 * the .always() in submit() and leaves Submit disabled with no message. The
	 * finally makes the re-enable unskippable.
	 *
	 * @since 3.13.0
	 *
	 * @param {*} response The parsed (or unparsed) response body.
	 */
	function onDone( response ) {
		try {
			handleResponse( response );
		} catch ( e ) {
			showGenericError();
		} finally {
			el.$submit.prop( 'disabled', terminal );
		}
	}

	/**
	 * Post the answers.
	 *
	 * @since 3.13.0
	 */
	function submit() {
		// general.js exports the API after running its own init(), so a throw in
		// that init lands here with no serialize()/showErrors() available. Nothing
		// but a reload can help, hence terminal.
		if ( ! hasApi() ) {
			showTerminalError();

			return;
		}

		// showGenericError() prepends outside the subtree clearErrors() walks, so
		// without this the banners from successive failed attempts stack up.
		el.$root.find( '.sc-regform__error--form' ).remove();

		var fields = window.SugarCalendar.RegistrationForm.serialize();

		// serialize() returns a query-string fragment whose names already start at
		// registration[...], so it must be appended. Assigning it to a property
		// sends a scalar, which the server's is_array() check silently discards.
		var body = $.param( {
			action: sc_regform_after.action,
			token: el.$root.data( 'token' )
		} );

		if ( fields ) {
			body += '&' + fields;
		}

		el.$submit.prop( 'disabled', true );

		// .always() re-enables Submit on every outcome so a 500 or network drop
		// can't leave it dead in a modal that cannot be dismissed; .fail() paints
		// the message for the transport-level failures.
		$.post( sc_regform_after.ajaxurl, body, onDone )
			.fail( function () {
				showGenericError();
			} )
			.always( function () {
				el.$submit.prop( 'disabled', terminal );
			} );
	}

	/**
	 * Build and open the modal.
	 *
	 * @since 3.13.0
	 */
	function init() {
		// A before-checkout host: its answers were stored inside the checkout, so there
		// is no form here — only the confirmation its landing page asked for.
		if ( sc_regform_after.openSuccess ) {
			consumeFlash();
			openSuccessModal();

			return;
		}

		el.$root = $( '.sc-regform-after' ).first();

		if ( ! el.$root.length || ! el.$root.data( 'token' ) ) {
			return;
		}

		el.$submit = $( '<button type="button" class="sc-regform__submit"></button>' ).text(
			sc_regform_after.submitLabel
		);

		el.$modal = $(
			'<div class="modal sc-regform-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="sc-regform-modal-title">' +
				'<div class="modal-dialog" role="document">' +
					'<div class="modal-content">' +
						'<div class="modal-header"><h5 class="modal-title" id="sc-regform-modal-title"></h5></div>' +
						'<div class="modal-body"></div>' +
						'<div class="modal-footer"></div>' +
					'</div>' +
				'</div>' +
			'</div>'
		);

		el.$modal.find( '.modal-title' ).text( sc_regform_after.title );
		el.$modal.find( '.modal-body' ).append( el.$root );
		el.$modal.find( '.modal-footer' ).append( el.$submit );

		$( 'body' ).append( el.$modal );

		el.$submit.on( 'click', submit );

		// Non-dismissible for UX, not security: closing the tab is a supported
		// outcome and the pending row covers it. No extra focus lock on top of
		// Bootstrap's, so browser-level exit keeps working.
		el.$modal.modal( {
			backdrop: 'static',
			keyboard: false
		} );
	}

	$( init );
}( jQuery ) );
