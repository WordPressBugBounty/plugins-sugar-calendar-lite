/* global jQuery */

/**
 * Color-scheme control: MiniColors pickers plus the Use Legacy Template dimming.
 *
 * @since 3.14.0
 */
( function ( $ ) {

	'use strict';

	var SCOPE = '.sugar-calendar-color-scheme';

	/**
	 * Dim the control while Use Legacy Template is on.
	 *
	 * Reads the toggle live rather than the saved value, so the note and the
	 * dimming appear the moment it is switched, before any save.
	 *
	 * @since 3.14.0
	 */
	function trackLegacyToggle() {

		var $toggle = $( '#sugar-calendar-setting-use_legacy_template' );
		var $scheme = $( SCOPE );

		if ( ! $toggle.length || ! $scheme.length ) {
			return;
		}

		var sync = function () {

			var legacy = $toggle.is( ':checked' );

			$scheme.toggleClass( SCOPE.slice( 1 ) + '--disabled', legacy );

			// `inert` over `disabled`: it blocks the mouse and the tab order just the
			// same, but inert fields still submit, so the values are untouched.
			$scheme.children().prop( 'inert', legacy );
		};

		$toggle.on( 'change', sync );

		sync();
	}

	$( function () {

		var $inputs = $( SCOPE + ' .sugar-calendar-color-picker' );

		if ( ! $inputs.length || typeof $.fn.minicolors !== 'function' ) {
			return;
		}

		$inputs.each( function () {

			var $input = $( this );

			$input.minicolors( {
				defaultValue: $input.data( 'default-color' ) || ''
			} );
		} );

		trackLegacyToggle();
	} );

}( jQuery ) );
