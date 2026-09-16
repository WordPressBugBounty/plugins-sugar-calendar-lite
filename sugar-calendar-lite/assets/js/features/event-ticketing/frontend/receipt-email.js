/* global jQuery */

/**
 * Email Ticket on the receipt, without leaving the page.
 *
 * The link keeps working with this script absent, so everything here is an
 * enhancement over a request that already does the job.
 *
 * @since 3.14.0
 */
( function ( $ ) {

	'use strict';

	var LINK = '.sc-ticket-email';

	var l10n = window.sc_et_receipt_email || {};

	/**
	 * Escape a translated string for use as markup.
	 *
	 * @since 3.14.0
	 *
	 * @param {string} text The string.
	 *
	 * @return {string} Escaped.
	 */
	function escape( text ) {

		return $( '<span/>' ).text( text ).html();
	}

	/**
	 * Whether this link is mid-send or counting down.
	 *
	 * @since 3.14.0
	 *
	 * @param {Object} $link The link.
	 *
	 * @return {boolean} True while it must not be clicked again.
	 */
	function isBusy( $link ) {

		return $link.hasClass( 'is-sending' ) || $link.hasClass( 'is-waiting' );
	}

	/**
	 * Put one state on the link.
	 *
	 * @since 3.14.0
	 *
	 * @param {Object} $link The link.
	 * @param {string} state One of sending, sent, failed, waiting, idle.
	 * @param {string} html  Markup for the label.
	 */
	function setState( $link, state, html ) {

		$link
			.removeClass( 'is-sending is-sent is-failed is-waiting' )
			.addClass( state === 'idle' ? '' : 'is-' + state )
			.html( html );
	}

	/**
	 * The sending label.
	 *
	 * The dots are always in the layout and only their opacity animates, so the
	 * label cannot change width while it runs.
	 *
	 * @since 3.14.0
	 *
	 * @return {string} Markup.
	 */
	function sendingHtml() {

		return escape( l10n.sending || 'Sending' ) +
			'<span class="sc-ticket-email__dots" aria-hidden="true">' +
			'<span>.</span><span>.</span><span>.</span></span>';
	}

	/**
	 * Count down to the next allowed send, then return the link to its label.
	 *
	 * @since 3.14.0
	 *
	 * @param {Object} $link   The link.
	 * @param {number} seconds Seconds remaining.
	 */
	function countDown( $link, seconds ) {

		if ( seconds <= 0 ) {
			setState( $link, 'idle', $link.data( 'sc-label' ) );

			return;
		}

		var label = ( l10n.retry || 'Try again in %s' ).replace( '%s', seconds + 's' );

		setState( $link, 'waiting', escape( label ) );

		window.setTimeout( function () {
			countDown( $link, seconds - 1 );
		}, 1000 );
	}

	/**
	 * Show the success label, and leave it there.
	 *
	 * Nothing moves on its own: the countdown only appears if the visitor clicks
	 * again while the server is still refusing, which is the only moment the wait
	 * is worth telling them about.
	 *
	 * @since 3.14.0
	 *
	 * @param {Object} $link The link.
	 */
	function showSent( $link ) {

		setState(
			$link,
			'sent',
			escape( l10n.sent || 'Email Sent' ) +
				'<span class="sc-ticket-email__check scf:icon-[fa7-solid--check]" aria-hidden="true"></span>'
		);
	}

	/**
	 * Send one ticket.
	 *
	 * @since 3.14.0
	 *
	 * @param {Object} $link The link.
	 */
	function send( $link ) {

		setState( $link, 'sending', sendingHtml() );

		$.post( l10n.ajaxurl, {
			action: 'sc_et_email_ticket',
			ticket_code: $link.data( 'sc-ticket' ),
			nonce: $link.data( 'sc-nonce' )
		} )
			.done( function ( response ) {

				var wait = response && response.data ? parseInt( response.data.retry_after, 10 ) || 0 : 0;

				if ( response && response.success ) {
					showSent( $link );

					return;
				}

				// A refusal carries the seconds left, so the countdown always reflects
				// where the server actually is, including after a reload.
				if ( wait > 0 ) {
					countDown( $link, wait );

					return;
				}

				setState( $link, 'failed', escape( l10n.failed || 'Failed. Retry?' ) );
			} )
			.fail( function () {
				setState( $link, 'failed', escape( l10n.failed || 'Failed. Retry?' ) );
			} );
	}

	$( function () {

		var $links = $( LINK );

		if ( ! $links.length || ! l10n.ajaxurl ) {
			return;
		}

		$links.each( function () {
			$( this ).data( 'sc-label', $( this ).html() );
		} );

		$links.on( 'click', function ( event ) {

			var $link = $( this );

			event.preventDefault();

			if ( isBusy( $link ) ) {
				return;
			}

			send( $link );
		} );
	} );

}( jQuery ) );
