/* global jQuery */

/**
 * Zoom integration admin — danger-zone confirmation.
 *
 * The "Remove" (disconnect) link is a plain nonce'd GET that tears down the
 * connection immediately. Disconnecting is destructive, so intercept the click
 * and require confirmation through a styled jQuery-Confirm dialog (matching the
 * license modals' red/error language) before navigating to the disconnect URL.
 *
 * @since 3.12.0
 */
( function ( $ ) {

	'use strict';

	var settings = window.sugar_calendar_admin_integration_zoom || {};
	var text     = settings.text || {};

	/**
	 * Build the dialog icon HTML.
	 *
	 * jQuery-Confirm renders the `icon` value inside `<i class="…"></i>`;
	 * closing that tag, inserting our SVG, and reopening it swaps in the image
	 * (the same injection the Pro settings modals use).
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
	 * @param {string} href Disconnect URL to navigate to on confirm.
	 */
	function confirmRemove( href ) {

		$.confirm( {
			typeAnimated:       false,
			draggable:          false,
			animateFromElement: false,
			boxWidth:           '400px',
			useBootstrap:       false,
			type:               'red',
			icon:               iconHtml(),
			title:              text.title,
			content:            text.message,
			buttons: {
				confirm: {
					text:     text.confirm,
					btnClass: 'sugar-calendar-btn sugar-calendar-btn-lg sugar-calendar-btn-red',
					action:   function () {
						window.location.href = href;
					}
				},
				cancel: {
					text:     text.cancel,
					btnClass: 'sugar-calendar-btn sugar-calendar-btn-lg'
				}
			}
		} );
	}

	$( function () {

		$( document ).on( 'click', '.sugar-calendar-zoom__account-remove', function ( event ) {

			event.preventDefault();

			confirmRemove( $( this ).attr( 'href' ) );
		} );
	} );

}( jQuery ) );
