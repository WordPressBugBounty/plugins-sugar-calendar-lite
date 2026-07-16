( function () {
	'use strict';

	function initSubmitGuard() {
		var form = document.querySelector( '.sugar-calendar-integrations-panel' );
		if ( ! form ) {
			return;
		}
		form.addEventListener( 'submit', function ( event ) {
			var button = event.submitter;
			if ( button ) {
				// Deferred: disabling the submitter synchronously inside its own
				// submit handler excludes its name/value from the browser's form
				// data set, so the server never sees `sugar-calendar-submit` and
				// silently skips handle_post(). Deferring by one tick lets the
				// browser finish constructing the submission first.
				setTimeout( function () {
					button.disabled = true;
				}, 0 );
			}
		} );
	}

	// Re-enable on pageshow: covers a bfcache restore (browser Back to this
	// page after a prior submit left the button disabled) and any aborted
	// navigation, so the button never gets stuck disabled with no reload.
	window.addEventListener( 'pageshow', function () {
		var button = document.querySelector( '.sugar-calendar-integrations-save-button' );
		if ( button ) {
			button.disabled = false;
		}
	} );

	document.addEventListener( 'DOMContentLoaded', initSubmitGuard );
} )();
