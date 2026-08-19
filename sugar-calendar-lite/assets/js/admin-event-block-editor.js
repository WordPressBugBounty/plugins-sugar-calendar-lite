/* globals sugar_calendar_admin_event_block_editor */
/**
 * Event editor tweaks that must run before the block editor mounts.
 *
 * Loaded in the head, so the DOM is not available here — anything needing the
 * DOM belongs in the footer scripts instead.
 *
 * @since 3.13.0
 */
( function ( wp, settings ) {

	'use strict';

	if ( ! wp || ! wp.data || ! wp.data.dispatch || ! settings ) {
		return;
	}

	/**
	 * Open the meta box pane by default, at a usable height.
	 *
	 * Defaults, not values — a user's own resize or collapse still wins.
	 *
	 * @since 3.13.0
	 */
	function setMetaBoxesPaneDefaults() {

		var config = settings.meta_boxes_pane;

		if ( ! config ) {
			return;
		}

		// Never let the pane swallow the editor top bar and the event title.
		var height = Math.min(
			parseInt( config.default_height, 10 ),
			window.innerHeight - parseInt( config.reserved_height, 10 )
		);

		wp.data.dispatch( 'core/preferences' ).setDefaults( 'core/edit-post', {
			metaBoxesMainIsOpen: true,
			metaBoxesMainOpenHeight: height
		} );
	}

	setMetaBoxesPaneDefaults();

} )( window.wp, window.sugar_calendar_admin_event_block_editor );
