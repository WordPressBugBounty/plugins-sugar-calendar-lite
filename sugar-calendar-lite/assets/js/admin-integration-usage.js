( function () {
	'use strict';

	function initPopover() {
		var toggle = document.querySelector( '[data-credits-toggle]' );
		var popover = document.getElementById( 'sugar-calendar-credits-popover' );
		if ( ! toggle || ! popover ) {
			return;
		}
		toggle.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var open = popover.classList.toggle( 'is-visible' );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
		document.addEventListener( 'click', function ( e ) {
			if ( ! popover.contains( e.target ) && ! toggle.contains( e.target ) ) {
				popover.classList.remove( 'is-visible' );
				toggle.setAttribute( 'aria-expanded', 'false' );
			}
		} );
	}

	function initTooltips() {
		document.querySelectorAll( '[data-usage-segment]' ).forEach( function ( seg ) {
			seg.addEventListener( 'mouseenter', function () {
				var bar = seg.closest( '.sugar-calendar-usage-card__bar' );
				if ( ! bar ) {
					return;
				}
				var tooltip = bar.querySelector( '[data-usage-tooltip]' );
				if ( ! tooltip ) {
					return;
				}

				var dot = tooltip.querySelector( '[data-tooltip-dot]' );
				var name = tooltip.querySelector( '[data-tooltip-name]' );
				var credits = tooltip.querySelector( '[data-tooltip-credits]' );

				if ( dot ) {
					dot.style.background = seg.getAttribute( 'data-color' );
				}
				if ( name ) {
					name.textContent = seg.getAttribute( 'data-name' );
				}
				if ( credits ) {
					credits.textContent = seg.getAttribute( 'data-credits' ) || '';
				}

				var barRect = bar.getBoundingClientRect();
				var segRect = seg.getBoundingClientRect();

				tooltip.style.left = ( segRect.left - barRect.left + segRect.width + 8 ) + 'px';
				tooltip.style.top = ( segRect.top - barRect.top + segRect.height / 2 ) + 'px';
				tooltip.style.display = 'flex';
			} );
			seg.addEventListener( 'mouseleave', function () {
				var bar = seg.closest( '.sugar-calendar-usage-card__bar' );
				if ( ! bar ) {
					return;
				}
				var tooltip = bar.querySelector( '[data-usage-tooltip]' );
				if ( tooltip ) {
					tooltip.style.display = 'none';
				}
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initPopover();
		initTooltips();
	} );
} )();
