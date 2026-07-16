<?php

namespace Sugar_Calendar\Integrations\OAuthRelay;

use Sugar_Calendar\Admin\Pages\SettingsIntegrationsTab;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OAuth Connection Error Notice.
 *
 * Displays an admin notice when any OAuth connection has an
 * authentication error and needs to be reconnected.
 *
 * Adapted from Bookings: replaces the Notices service dependency with
 * a direct admin_notices echo. The integrations settings page is
 * skipped because that page has its own inline reconnect UI.
 *
 * @since 3.12.0
 */
class OAuthConnectionErrorNotice {

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 */
	public function init() {

		// Both render points emit the notice inline, in its final on-page
		// position, so core's common.js never relocates it (which is the on-load
		// "jump"). WordPress prints admin_notices at the top of the content, then
		// common.js moves them down into .sugar-calendar-admin-content on DOMReady
		// — so admin_notices is deliberately NOT used here.
		//  - SC admin pages: at the top of .sugar-calendar-admin-content (the same
		//    spot core relocates notices to), via the content-top action.
		//  - Classic event editor (no such content hook): top of the edit form.
		// The notice therefore appears on SC's own admin screens and the event
		// editor — the places its reconnect CTA is actionable — and not on
		// unrelated wp-admin pages.
		add_action( 'sugar_calendar_admin_page_content_top', [ $this, 'maybe_display_notice' ] );
		add_action( 'edit_form_top', [ $this, 'maybe_display_editor_notice' ] );
	}

	/**
	 * Display the notice on the classic event editor form.
	 *
	 * Scoped to the event post types so it does not appear on unrelated
	 * post-type editors.
	 *
	 * @since 3.12.0
	 *
	 * @param WP_Post $post Post being edited.
	 */
	public function maybe_display_editor_notice( $post ) {

		if (
			! $post instanceof WP_Post ||
			! in_array( $post->post_type, [ 'sc_event', 'sc_recurring_event' ], true )
		) {
			return;
		}

		$this->maybe_display_notice();
	}

	/**
	 * Display notice if any OAuth connections have auth errors.
	 *
	 * @since 3.12.0
	 */
	public function maybe_display_notice() {

		$errored_connections = $this->get_errored_connections();

		if ( empty( $errored_connections ) ) {
			return;
		}

		$this->display_notice( $errored_connections, true );
	}

	/**
	 * Errored connections to surface on the current request, or an empty array
	 * when the notice should not show.
	 *
	 * @since 3.12.0
	 *
	 * @return array<int, array>
	 */
	private function get_errored_connections() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return [];
		}

		// Skip on the integrations settings page — the inline reconnect UI is sufficient.
		if ( SettingsIntegrationsTab::is_current_page() ) {
			return [];
		}

		return OAuthConnectionModel::get_all_in_auth_error();
	}

	/**
	 * Display the error notice linking to the integrations settings page.
	 *
	 * @since 3.12.0
	 *
	 * @param array<int, array> $connections Errored connection rows.
	 * @param bool              $inline      Whether to add the `inline` class so
	 *                                       core does not relocate the notice.
	 */
	private function display_notice( array $connections, $inline = false ) {

		$providers = array_unique(
			array_map(
				static function ( $c ) {
					return ucfirst( (string) ( $c['provider'] ?? '' ) );
				},
				$connections
			)
		);

		// Reconnect link points to the Integrations settings page for the
		// first errored provider. The page itself surfaces the per-provider
		// Reconnect CTA.
		$first_provider = (string) ( $connections[0]['provider'] ?? 'zoom' );
		$reconnect_url  = SettingsIntegrationsTab::get_integration_url( $first_provider );

		$message = sprintf(
			/* translators: %1$s - comma-separated provider names; %2$s - URL to Integrations settings page. */
			esc_html__( 'Your %1$s connection has lost authorization. Please visit %2$s to reconnect your account.', 'sugar-calendar-lite' ),
			esc_html( implode( ', ', $providers ) ),
			'<a href="' . esc_url( $reconnect_url ) . '">' . esc_html__( 'Integrations Settings', 'sugar-calendar-lite' ) . '</a>'
		);

		// `inline` (event editor only) keeps core's common.js from moving the
		// notice below the heading; it is already rendered in its final position.
		$classes = 'notice notice-error' . ( $inline ? ' inline' : '' );
		?>
		<div class="<?php echo esc_attr( $classes ); ?>">
			<p><?php echo wp_kses( $message, [ 'a' => [ 'href' => [] ] ] ); ?></p>
		</div>
		<?php
	}
}
