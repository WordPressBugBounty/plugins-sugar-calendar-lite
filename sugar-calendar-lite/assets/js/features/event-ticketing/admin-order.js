/* global jQuery */

/**
 * Order details page — confirmation dialogs for its consequential actions
 * (Delete, Resend Email Receipt).
 *
 * Driven by a `data-sc-confirm` attribute naming a dialog in the localized
 * `dialogs` map, so copy, icon and color treatment stay in PHP and a new
 * confirmed action needs no change here.
 *
 * @since 3.13.0
 */
( function ( $ ) {

	'use strict';

	var settings = window.sugar_calendar_ticketing_admin_order || {};
	var dialogs  = settings.dialogs || {};

	/**
	 * The element whose confirmed click is allowed straight through.
	 *
	 * Cleared as soon as that click is seen, so the next click on the same button
	 * asks again.
	 *
	 * @type {Element|null}
	 */
	var approved = null;

	/**
	 * Build the dialog icon markup.
	 *
	 * jQuery-Confirm renders the `icon` value inside `<i class="…"></i>`; closing
	 * that tag, inserting the SVG and reopening it swaps in the image.
	 *
	 * @param {string} url Icon URL.
	 *
	 * @return {string} Icon markup, or an empty string when there is no icon.
	 */
	function iconHtml( url ) {

		if ( ! url ) {
			return '';
		}

		return '"></i><img src="' + url + '" alt="" width="36" height="36"><i class="';
	}

	/**
	 * Ask for confirmation, running the action only when it is given.
	 *
	 * Falls back to the native confirm when jQuery-Confirm is not on the page:
	 * losing the dialog must not turn a guarded action into an unguarded one.
	 *
	 * @param {Object}   dialog  One entry of the localized dialogs map.
	 * @param {Function} approve Called when the action is confirmed.
	 */
	function ask( dialog, approve ) {

		if ( ! $.confirm ) {
			// eslint-disable-next-line no-alert
			if ( window.confirm( dialog.message ) ) {
				approve();
			}

			return;
		}

		$.confirm( {
			typeAnimated:       false,
			draggable:          false,
			animateFromElement: false,
			boxWidth:           '400px',
			useBootstrap:       false,
			// Orange by default so the box's rule and icon match the primary button,
			// which is $color-brand-orange-50 — the token jconfirm-type-orange uses.
			type:               dialog.type || 'orange',
			icon:               iconHtml( dialog.icon_url ),
			title:              dialog.title,
			content:            dialog.message,
			buttons:            {
				confirm: {
					text:     dialog.confirm,
					btnClass: dialog.type === 'red'
						? 'sugar-calendar-btn sugar-calendar-btn-lg sugar-calendar-btn-red'
						: 'sugar-calendar-btn sugar-calendar-btn-lg sugar-calendar-btn-primary',
					action:   approve
				},
				cancel: {
					text:     dialog.cancel,
					btnClass: 'sugar-calendar-btn sugar-calendar-btn-lg'
				}
			}
		} );
	}

	$( function () {

		$( document ).on( 'click', '[data-sc-confirm]', function ( event ) {

			var button = this;
			var dialog = dialogs[ $( button ).attr( 'data-sc-confirm' ) ];

			// An unknown key means the copy was never localized; skip only the
			// dialog, not the click, since an unguarded click is worse than a missing one.
			if ( ! dialog ) {
				return;
			}

			if ( approved === button ) {
				approved = null;

				return;
			}

			event.preventDefault();

			ask( dialog, function () {

				approved = button;

				/*
				 * A native re-click, not form.submit(): the admin_init handlers key on
				 * this button's name (sc_et_delete_order, sc_et_resend_receipt), and a
				 * programmatic submit wouldn't post it.
				 */
				button.click();
			} );
		} );
	} );

}( jQuery ) );
