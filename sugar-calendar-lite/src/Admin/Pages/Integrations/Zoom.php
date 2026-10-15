<?php

namespace Sugar_Calendar\Admin\Pages\Integrations;

use Sugar_Calendar\Helpers as Sugar_Calendar_Helpers;
use Sugar_Calendar\Helpers\WP;

/**
 * Zoom Integration page.
 *
 * Matches Figma node 11623:19857 in file w3az83btQvXzV4XPJXPHKS.
 *
 * NOTE: This class is intentionally named `Zoom` (not `ZoomIntegration`)
 * to avoid a name collision with the main glue class
 * Sugar_Calendar\Integrations\Zoom\ZoomIntegration.
 *
 * @since 3.12.0
 * @since 3.16.0 The Connect / Remove flow moved to AbstractOAuthIntegrationPage.
 */
class Zoom extends AbstractOAuthIntegrationPage {

	/**
	 * Base documentation URL. Each entry point adds its own UTM params.
	 *
	 * @since 3.12.0
	 */
	const DOCS_URL = 'https://sugarcalendar.com/docs/events/using-the-zoom-integration/';

	/**
	 * Relay provider slug.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	public function get_provider() {

		return 'zoom';
	}

	/**
	 * Integration slug (URL key).
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_slug() {

		return 'zoom';
	}

	/**
	 * Display name shown in the sidebar and content title bar.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_name() {

		return esc_html__( 'Zoom', 'sugar-calendar-lite' );
	}

	/**
	 * Sidebar icon URL.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_icon_url() {

		return SC_PLUGIN_ASSETS_URL . 'images/integrations/integration-zoom.png';
	}

	/**
	 * Enqueue the "Remove" confirmation dialog: disconnecting is destructive.
	 *
	 * @since 3.12.0
	 * @since 3.16.0 Moved here from SettingsIntegrationsTab.
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style( 'sugar-calendar-admin-confirm' );

		wp_enqueue_script(
			'sugar-calendar-admin-integration-zoom',
			SC_PLUGIN_ASSETS_URL . 'js/admin-integration-zoom' . WP::asset_min() . '.js',
			[ 'jquery', 'sugar-calendar-vendor-jquery-confirm' ],
			Sugar_Calendar_Helpers::get_asset_version(),
			true
		);

		wp_localize_script(
			'sugar-calendar-admin-integration-zoom',
			'sugar_calendar_admin_integration_zoom',
			[
				'icon_url' => SC_PLUGIN_ASSETS_URL . 'images/icons/exclamation-circle.svg',
				'text'     => [
					'title'   => esc_html__( 'Remove Zoom connection?', 'sugar-calendar-lite' ),
					'message' => esc_html__( "Links on existing events stay active. New online events won't get Zoom links until you reconnect.", 'sugar-calendar-lite' ),
					'confirm' => esc_html__( 'Remove', 'sugar-calendar-lite' ),
					'cancel'  => esc_html__( 'Cancel', 'sugar-calendar-lite' ),
				],
			]
		);
	}

	/**
	 * The sentence under the title.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	protected function get_description_text() {

		/* translators: %s - Documentation link URL. */
		return __( 'Connect your Zoom account to automatically generate meeting links for your online events. If you need help, please refer to <a href="%s" target="_blank" rel="noopener">our documentation</a>.', 'sugar-calendar-lite' );
	}

	/**
	 * Label of the Connect button.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	protected function get_connect_label() {

		return __( 'Connect with Zoom', 'sugar-calendar-lite' );
	}

	/**
	 * Render the Zoom icon inside the Connect button.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	protected function render_connect_button_icon() {

		?>
		<span class="sugar-calendar-zoom__connect-button-icon">
			<img
				src="<?php echo esc_url( $this->get_icon_url() ); ?>"
				alt=""
				width="20"
				height="20"
			/>
		</span>
		<?php
	}

	/**
	 * Inline notices this page shows.
	 *
	 * @since 3.16.0
	 *
	 * @return array
	 */
	protected function get_notice_messages() {

		return [
			'zoom_connected'         => [ 'success', __( 'Zoom connected successfully.', 'sugar-calendar-lite' ) ],
			'zoom_disconnected'      => [ 'success', __( 'Zoom disconnected.', 'sugar-calendar-lite' ) ],
			'zoom_relay_unavailable' => [ 'error', __( 'Could not connect to Zoom. Please try again.', 'sugar-calendar-lite' ) ],
			'zoom_scopes_missing'    => [ 'error', __( 'Some permissions were not approved. Connect again and accept all of them.', 'sugar-calendar-lite' ) ],
		];
	}
}
