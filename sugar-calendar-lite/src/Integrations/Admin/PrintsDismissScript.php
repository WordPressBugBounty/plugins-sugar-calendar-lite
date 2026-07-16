<?php

namespace Sugar_Calendar\Integrations\Admin;

/**
 * Shared one-time inline dismiss-button listener for a notice selector.
 *
 * WP core adds the `.notice-dismiss` button to `.is-dismissible` notices;
 * this fetch()-POSTs to the notice's `data-sc-dismiss-url` so the dismissal
 * persists server-side. Used by MeetingRemovedNotice and OutOfCreditsNotice,
 * each scoped to its own notice selector via $selector.
 *
 * @since 3.12.0
 */
trait PrintsDismissScript {

	/**
	 * Print the one-time inline dismiss listener for $selector.
	 *
	 * @since 3.12.0
	 *
	 * @param string $selector CSS selector matching the notice's wrapper element
	 *                         (e.g. '.notice[data-sc-meeting-removed]').
	 *
	 * @return void
	 */
	private function print_dismiss_script( string $selector ) {

		static $printed = false;

		if ( $printed ) {
			return;
		}

		$printed = true;
		?>
		<script>
		document.addEventListener( 'click', function( e ) {
			var dismiss = e.target.closest( '<?php echo esc_js( $selector ); ?> .notice-dismiss' );
			if ( ! dismiss ) {
				return;
			}
			var notice = dismiss.closest( '<?php echo esc_js( $selector ); ?>' );
			if ( ! notice ) {
				return;
			}
			var url = notice.dataset.scDismissUrl;
			if ( ! url ) {
				return;
			}
			fetch( url, { method: 'POST', credentials: 'same-origin' } );
		} );
		</script>
		<?php
	}
}
