/* global jQuery */

/**
 * Embed / Preview button on the two ticket page settings.
 *
 * Neither state is a link: Preview carries the values on screen, which are not
 * known until the click, and Embed writes to the page before anything opens.
 *
 * @since 3.14.0
 */
( function ( $ ) {

	'use strict';

	// Read straight from the colour control's markup rather than depending on the
	// script that upgrades it: the hex inputs hold the current value either way.
	var COLORS = '.sugar-calendar-color-scheme';

	var ACTION = '.sugar-calendar-setting-page-action';

	var l10n = window.sc_et_page_shortcode || {};

	// Page ids this session has read back from the server, keyed `setting:id`. Beats
	// the render-time map, which cannot know about an edit made in another tab.
	var confirmed = {};

	/**
	 * The setting key behind one page-action button.
	 *
	 * @since 3.14.0
	 *
	 * @param {Object} $button The button.
	 *
	 * @return {string} Setting key, or '' when the row has no select.
	 */
	function settingKey( $button ) {

		var name = selectFor( $button ).attr( 'name' ) || '';

		return name.replace( /^sugar-calendar\[/, '' ).replace( /\]$/, '' );
	}

	/**
	 * The page dropdown in the same row as one button.
	 *
	 * @since 3.14.0
	 *
	 * @param {Object} $button The button.
	 *
	 * @return {Object} The select.
	 */
	function selectFor( $button ) {

		return $button.closest( '.sugar-calendar-setting-row' ).find( 'select' );
	}

	/**
	 * Whether a page is believed to hold its setting's shortcode.
	 *
	 * @since 3.14.0
	 *
	 * @param {string} key Setting key.
	 * @param {string} id  Page id.
	 *
	 * @return {boolean} True when the shortcode is there.
	 */
	function isEmbedded( key, id ) {

		var flag = confirmed[ key + ':' + id ];

		if ( typeof flag === 'boolean' ) {
			return flag;
		}

		var ids = ( l10n.embedded || {} )[ key ] || [];

		return ids.map( String ).indexOf( String( id ) ) !== -1;
	}

	/**
	 * Label one button for a state.
	 *
	 * @since 3.14.0
	 *
	 * @param {Object}  $button  The button.
	 * @param {boolean} embedded Whether the page holds the shortcode.
	 */
	function setState( $button, embedded ) {

		$button
			.toggleClass( 'is-preview', embedded )
			.toggleClass( 'is-embed', ! embedded )
			.text( embedded ? l10n.labels.preview : l10n.labels.embed );
	}

	/**
	 * Put a spinner where the label goes while a request is out.
	 *
	 * Clearing it is left to setState(), which writes the label back — so the button
	 * is never seen holding a stale one.
	 *
	 * @since 3.14.0
	 *
	 * @param {Object}  $button The button.
	 * @param {boolean} busy    Whether a request is in flight.
	 */
	function setBusy( $button, busy ) {

		$button.prop( 'disabled', busy );

		if ( busy ) {
			$button.html( '<i class="sugar-calendar-loading-spinner" aria-hidden="true"></i>' );
		}
	}

	/**
	 * One page-action request.
	 *
	 * @since 3.14.0
	 *
	 * @param {string} action Ajax action.
	 * @param {string} key    Setting key.
	 * @param {string} id     Page id.
	 *
	 * @return {Object} The jQuery promise.
	 */
	function request( action, key, id ) {

		return $.post( l10n.ajaxurl, {
			action: action,
			nonce: l10n.nonce,
			key: key,
			page_id: id
		} );
	}

	/**
	 * The Preview URL for a page, carrying the values on screen.
	 *
	 * `?page_id=` resolves any page and keeps its query string through the canonical
	 * redirect, so the link follows an unsaved choice without knowing its permalink.
	 *
	 * @since 3.14.0
	 *
	 * @param {string} id Page id.
	 *
	 * @return {string} URL carrying the preview arg, nonce and current settings.
	 */
	function previewUrl( id ) {

		var url = l10n.home + '?page_id=' + encodeURIComponent( id ) +
			'&sc_preview=1&_wpnonce=' + encodeURIComponent( l10n.preview );

		$( COLORS + ' .sugar-calendar-color-picker' ).add( '#sugar-calendar-setting-use_legacy_template' ).each( function () {

			var name = $( this ).attr( 'name' ) || '';
			var key  = name.replace( /^sugar-calendar\[/, '' ).replace( /\]$/, '' );

			if ( ! key ) {
				return;
			}

			var value = this.type === 'checkbox' ? ( this.checked ? '1' : '0' ) : $( this ).val();

			url += '&' + encodeURIComponent( 'sc_preview_settings[' + key + ']' ) + '=' + encodeURIComponent( value );
		} );

		return url;
	}

	/**
	 * Add the shortcode to the selected page, then relabel the button.
	 *
	 * @since 3.14.0
	 *
	 * @param {Object} $button The button.
	 * @param {string} key     Setting key.
	 * @param {string} id      Page id.
	 */
	function embed( $button, key, id ) {

		setBusy( $button, true );

		request( 'sc_et_embed_page_shortcode', key, id )
			.done( function ( response ) {

				if ( ! response || ! response.success ) {
					window.alert( l10n.error ); // eslint-disable-line no-alert

					return;
				}

				confirmed[ key + ':' + id ] = true;
			} )
			.fail( function () {
				window.alert( l10n.error ); // eslint-disable-line no-alert
			} )
			.always( function () {

				// A late answer must not touch a row that has moved on since: that
				// change owns both the label and the spinner now.
				if ( String( selectFor( $button ).val() ) !== String( id ) ) {
					return;
				}

				// Labelling from the state covers the failed request too.
				setState( $button, isEmbedded( key, id ) );
				setBusy( $button, false );
			} );
	}

	/**
	 * Re-read the page and correct the label when the map was stale.
	 *
	 * A failed check leaves the map's answer standing: embedding is idempotent, so a
	 * wrong label costs one no-op click rather than a duplicate shortcode.
	 *
	 * @since 3.14.0
	 *
	 * @param {Object} $button The button.
	 * @param {string} key     Setting key.
	 * @param {string} id      Page id.
	 */
	function confirmState( $button, key, id ) {

		request( 'sc_et_check_page_shortcode', key, id )
			.done( function ( response ) {

				if ( ! response || ! response.success ) {
					return;
				}

				confirmed[ key + ':' + id ] = !! response.data.embedded;
			} )
			.always( function () {

				// A late answer must not touch a row that has moved on since: that
				// change owns both the label and the spinner now.
				if ( String( selectFor( $button ).val() ) !== String( id ) ) {
					return;
				}

				// Labelled before it reappears, so the button is only ever seen in its
				// settled state. A failed check falls back to the render-time map.
				setState( $button, isEmbedded( key, id ) );
				setBusy( $button, false );
			} );
	}

	/**
	 * Wire the Embed / Preview buttons.
	 *
	 * Neither state is a link: Preview needs the unsaved values at click time, and
	 * Embed writes before it opens anything.
	 *
	 * @since 3.14.0
	 */
	function trackPageActions() {

		var $buttons = $( ACTION );

		if ( ! $buttons.length || ! l10n.labels ) {
			return;
		}

		$buttons.on( 'click', function ( event ) {

			event.preventDefault();

			var $button = $( this );
			var id      = selectFor( $button ).val();
			var key     = settingKey( $button );

			if ( ! id || ! key ) {
				return;
			}

			if ( $button.hasClass( 'is-preview' ) ) {
				window.open( previewUrl( id ), '_blank' );

				return;
			}

			embed( $button, key, id );
		} );

		$buttons.each( function () {

			var $button = $( this );
			var timer;

			selectFor( $button ).on( 'change', function () {

				var id  = $( this ).val();
				var key = settingKey( $button );

				clearTimeout( timer );

				if ( ! id || ! key ) {
					return;
				}

				// Answered already this session, so the label is settled and there is
				// nothing to wait for.
				if ( typeof confirmed[ key + ':' + id ] === 'boolean' ) {
					setState( $button, confirmed[ key + ':' + id ] );
					setBusy( $button, false );

					return;
				}

				// Straight to the spinner. Showing the map's guess first would flash a
				// label that the check is about to replace.
				setBusy( $button, true );

				timer = setTimeout( function () {
					confirmState( $button, key, id );
				}, 300 );
			} );
		} );
	}

	$( function () {
		trackPageActions();
	} );

}( jQuery ) );
