/* global jQuery */

/**
 * Notify the authenticated checklist endpoint after an admin archive render.
 *
 * @since 3.15.0
 */
( function( $ ) {
	'use strict';

	const settings = window.sugar_calendar_setup_checklist_archive || {};

	if ( ! settings.ajax_url || ! settings.nonce ) {
		return;
	}

	$.post( settings.ajax_url, {
		action: 'sugar_calendar_setup_checklist',
		task: 'archive_observed',
		nonce: settings.nonce,
	} );
}( jQuery ) );
