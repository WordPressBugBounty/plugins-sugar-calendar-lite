/**
 * External dependencies
 */
import React from 'react';

/**
 * WordPress dependencies
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
import SidebarStack from '../components/SidebarStack';
import Toaster from '../components/Toaster';
import useStore from '../store';
import { __ } from '@wordpress/i18n';
import $ from 'jquery';
import NotifyForm from '../features/NotifyForm';
import '../styles/admin/email-notifications.scss';

/* globals sugar_calendar_admin_email_notifications */

function notifyEventAttendees( eventIDs, type ) {
	eventIDs = Array.isArray( eventIDs ) ? eventIDs : [ eventIDs ];

	const store = useStore.getState();

	// Reset accumulated event IDs so each sidebar open starts fresh.
	useStore.setState( { accumulatedEventIds: [] } );
	store.addAccumulatedEventIds( eventIDs );
	const allEventIds = useStore.getState().accumulatedEventIds;

	store.openSidebar( 'notify-attendees-form', {
		title: __( 'Notify Attendees', 'sugar-calendar-lite' ),
		content: NotifyForm,
		loadData: () => $.post(
			sugar_calendar_admin_email_notifications?.ajax_url,
			{
				task: 'email_notifications_get_form_data',
				event_ids: allEventIds,
				type: allEventIds.length > 1 ? '' : ( type || '' ),
			} ),
	} );
}

domReady( () => {
	const root = createRoot(
		document.getElementById( 'sugar-calendar-lite-sidebar' )
	);

	root.render(
		<>
			<SidebarStack />
			<Toaster />
		</>
	);

	$( document ).on( 'click', '#sugar-calendar-btn-notify-attendees', function( e ) {
		e.preventDefault();

		const type = $( this ).data( 'type' ) || '';

		notifyEventAttendees( [], type );
	} );

	$( document ).on( 'click', '#sugar-calendar-events .sugar-calendar-tablenav #doaction', function( e ) {
		if ( $( '#bulk-action-selector-top' ).val() === 'notify_attendees' ) {
			e.preventDefault();

			const $inputs = $( '.sugar-calendar-table-events--list  #the-list input:checked' );
			const ids = $inputs.map( function() {
				return $( this ).parents( '[data-event-id]' ).attr( 'data-event-id' );
			} ).get();

			notifyEventAttendees( ids );
		}
	} );

	$( document ).on( 'click', '[data-sc-notify-event-attendees]', function( e ) {
		e.preventDefault();

		const $el = $( this );
		const eventID = $el.attr( 'data-sc-notify-event-attendees' ) || $el.parents( '[data-event-id]' ).attr( 'data-event-id' );
		const type = $el.data( 'type' ) || $el.parents( '[data-type]' ).attr( 'data-type' ) || '';

		notifyEventAttendees( eventID, type );
	} );
} );
