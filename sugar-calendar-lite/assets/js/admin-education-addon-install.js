/* globals jQuery, sugar_calendar_admin_education_addon_install */
( function( $, settings ) {

	'use strict';

	$( document ).on( 'click', '.sugar-calendar-education-addon-install', function( e ) {
		e.preventDefault();

		var $btn = $( this );

		if ( $btn.prop( 'disabled' ) ) {
			return;
		}

		var action = $btn.data( 'action' );
		var plugin = $btn.data( 'plugin' );
		var task   = action === 'install' ? 'install_addon' : 'activate_addon';

		// Disable button and show spinner inside button (preserving width).
		var originalWidth = $btn.width();
		var originalText  = $btn.html();

		$btn.prop( 'disabled', true );
		$btn.width( originalWidth ).html( '<span class="spinner is-active" style="float: none; margin: 0; vertical-align: middle; filter: brightness(0) invert(1);"></span>' );

		// Remove any previous error message.
		$btn.siblings( '.sugar-calendar-education-addon-install-error' ).remove();

		$.post( settings.ajax_url, {
			task: task,
			plugin: plugin,
			type: 'addon',
		} )
		.done( function( res ) {
			if ( res.success ) {
				var url = new URL( window.location.href );
				url.searchParams.set( 'sc-rsvp-activated', '1' );
				window.location.href = url.toString();
			} else {
				$btn.prop( 'disabled', false );
				$btn.html( originalText );
				$btn.after( '<p class="sugar-calendar-education-addon-install-error" style="color: #d63638; margin-top: 8px;">' + settings.error_message + '</p>' );
			}
		} )
		.fail( function() {
			$btn.prop( 'disabled', false );
			$btn.html( originalText );
			$btn.after( '<p class="sugar-calendar-education-addon-install-error" style="color: #d63638; margin-top: 8px;">' + settings.error_message + '</p>' );
		} );
	} );

} )( jQuery, sugar_calendar_admin_education_addon_install );
