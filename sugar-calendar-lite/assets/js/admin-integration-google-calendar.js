/* global jQuery */

/**
 * Google Calendar integration admin — danger-zone confirmation and calendar validation.
 *
 * The "Remove" (disconnect) link is a plain nonce'd GET that tears down the
 * connection immediately. Disconnecting is destructive, so intercept the click
 * and require confirmation through a styled jQuery-Confirm dialog (matching the
 * license modals' red/error language) before navigating to the disconnect URL.
 *
 * @param {jQuery} $ jQuery.
 * @since 3.16.0
 */
( function( $ ) {
	'use strict';

	const settings = window.sugar_calendar_admin_integration_google_calendar || {};
	const text = settings.text || {};

	/**
	 * Build the dialog icon HTML.
	 *
	 * jQuery-Confirm renders the `icon` value inside `<i class="…"></i>`;
	 * closing that tag, inserting our SVG, and reopening it swaps in the image
	 * (the same injection the Pro settings modals use).
	 *
	 * @since 3.16.0
	 *
	 * @return {string} Icon markup, or an empty string when no icon is set.
	 */
	function iconHtml() {
		if ( ! settings.icon_url ) {
			return '';
		}

		return '"></i><img src="' + settings.icon_url + '" alt="" width="36" height="36"><i class="';
	}

	/**
	 * Show the styled confirmation dialog, disconnecting only on confirm.
	 *
	 * @since 3.16.0
	 *
	 * @param {string} href Disconnect URL to navigate to on confirm.
	 */
	function confirmRemove( href ) {
		$.confirm( {
			typeAnimated: false,
			draggable: false,
			animateFromElement: false,
			boxWidth: '400px',
			useBootstrap: false,
			type: 'red',
			icon: iconHtml(),
			title: text.title,
			content: text.message,
			buttons: {
				confirm: {
					text: text.confirm,
					btnClass: 'sugar-calendar-btn sugar-calendar-btn-lg sugar-calendar-btn-red',
					action() {
						window.location.href = href;
					},
				},
				cancel: {
					text: text.cancel,
					btnClass: 'sugar-calendar-btn sugar-calendar-btn-lg',
				},
			},
		} );
	}

	const calendarSelect = '.sugar-calendar-google-calendar__calendar-select';
	const errorClass = 'sugar-calendar-google-calendar__calendar-field--error';

	/**
	 * An empty calendar choice turns the help text red; the server check decides the save.
	 *
	 * @since 3.16.0
	 */
	function checkCalendar() {
		const $select = $( this );
		const isEmpty = $select.val() === '';

		$select.attr( 'aria-invalid', isEmpty ? 'true' : null );
		$select.closest( '.sugar-calendar-google-calendar__calendar-field' ).toggleClass( errorClass, isEmpty );
	}

	/**
	 * "Don't Sync Events" locks Sync Settings; a disabled select is not posted, so the saved mode is kept.
	 *
	 * @since 3.16.0
	 */
	function lockSyncSettings() {
		// A custom mapping renders both rows locked without the calendar select; leave them as they are.
		if ( ! $( calendarSelect ).length ) {
			return;
		}

		const off = $( calendarSelect ).val() === settings.sync_off_value;

		$( '#sugar-calendar-setting-google_sync_direction' ).prop( 'disabled', off );
		$( '#sugar-calendar-setting-row-google_sync_direction' )
			.toggleClass( 'sugar-calendar-google-calendar__sync-row--locked', off )
			.attr( 'title', off ? text.sync_locked : null );
	}

	$( function() {
		$( document ).on( 'click', '.sugar-calendar-google-calendar__account-remove', function( event ) {
			event.preventDefault();

			confirmRemove( $( this ).attr( 'href' ) );
		} );

		$( document ).on( 'change', calendarSelect, checkCalendar );
		$( document ).on( 'change', calendarSelect, lockSyncSettings );

		// The server renders the dim; the tooltip text lives here.
		lockSyncSettings();
	} );
}( jQuery ) );
