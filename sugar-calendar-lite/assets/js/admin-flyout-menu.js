/* global jQuery */

/**
 * Sugar Calendar Admin Flyout Menu.
 *
 * @since 3.9.0
 */

( function( $ ) {

	'use strict';

	/**
	 * Sugar Calendar Flyout Menu functionality.
	 *
	 * @since 3.9.0
	 */
	var SugarCalendarFlyoutMenu = {

		/**
		 * Initialize the flyout menu.
		 *
		 * @since 3.9.0
		 */
		init: function() {

			SugarCalendarFlyoutMenu.initFlyoutMenu();
		},

		/**
		 * Initialize the flyout menu functionality.
		 *
		 * @since 3.9.0
		 */
		initFlyoutMenu: function() {

			// Flyout Menu Elements.
			var $flyoutMenu = $( '#sugar-calendar-flyout' );

			if ( $flyoutMenu.length === 0 ) {
				return;
			}

			var $head = $flyoutMenu.find( '.sc-flyout-head' );

			// Get mascot image element for state changes.
			var $mascotImg = $head.find( 'img' );
			var basePath = $mascotImg.attr( 'src' ).replace( 'mascot.svg', '' );

			// Handle hover states.
			$head.on( 'mouseenter', function() {
				$mascotImg.attr( 'src', basePath + 'mascot-hover.svg' );
			} );

			$head.on( 'mouseleave', function() {
				if ( ! $flyoutMenu.hasClass( 'opened' ) ) {
					$mascotImg.attr( 'src', basePath + 'mascot.svg' );
				}
			} );

			// Click on the menu head icon.
			$head.on( 'click', function( e ) {
				e.preventDefault();
				$mascotImg.attr( 'src', basePath + 'mascot-clicked.svg' );
				$flyoutMenu.toggleClass( 'opened' );
			} );

			// Keyboard support for head button.
			$head.on( 'keydown', function( e ) {
				// Enter or Space key.
				if ( e.which === 13 || e.which === 32 ) {
					e.preventDefault();
					$mascotImg.attr( 'src', basePath + 'mascot-clicked.svg' );
					$flyoutMenu.toggleClass( 'opened' );
				}
			} );

			// Close on Escape key.
			$( document ).on( 'keydown', function( e ) {
				if ( e.which === 27 && $flyoutMenu.hasClass( 'opened' ) ) {
					$flyoutMenu.removeClass( 'opened' );
					$mascotImg.attr( 'src', basePath + 'mascot.svg' );
				}
			} );

			// Close on outside click.
			$( document ).on( 'click', function( e ) {
				if ( $flyoutMenu.hasClass( 'opened' ) && ! $flyoutMenu.is( e.target ) && $flyoutMenu.has( e.target ).length === 0 ) {
					$flyoutMenu.removeClass( 'opened' );
					$mascotImg.attr( 'src', basePath + 'mascot.svg' );
				}
			} );

			// Page elements and other values.
			var $wpfooter = $( '#wpfooter' );

			// Hide flyout when footer is visible to prevent overlap.
			$( window ).on( 'scroll', SugarCalendarFlyoutMenu.throttle( function() {

				// Get the boundaries.
				var wpfooterTop = $wpfooter.length > 0 ? $wpfooter.offset().top : 0,
					wpfooterBottom = wpfooterTop + ( $wpfooter.length > 0 ? $wpfooter.height() : 0 ),
					$overlap = $( '.sugar-calendar-admin-content, .wrap' ).last(),
					overlapBottom = $overlap.length > 0 ? $overlap.offset().top + $overlap.height() + 85 : 0,
					viewTop = $( window ).scrollTop(),
					viewBottom = viewTop + $( window ).height();

				if ( wpfooterBottom <= viewBottom && wpfooterTop >= viewTop && overlapBottom > viewBottom ) {
					$flyoutMenu.addClass( 'out' );
				} else {
					$flyoutMenu.removeClass( 'out' );
				}
			}, 50 ) );

			$( window ).trigger( 'scroll' );
		},

		/**
		 * Throttle function execution.
		 *
		 * @since 3.9.0
		 *
		 * @param {Function} func Function to throttle.
		 * @param {number}   wait Delay in milliseconds.
		 *
		 * @return {Function} Throttled function.
		 */
		throttle: function( func, wait ) {
			var timeout;
			return function() {
				var context = this, args = arguments;
				var later = function() {
					timeout = null;
					func.apply( context, args );
				};
				if ( ! timeout ) {
					timeout = setTimeout( later, wait );
				}
			};
		}
	};

	// Initialize on document ready.
	$( SugarCalendarFlyoutMenu.init );

}( jQuery ) );
