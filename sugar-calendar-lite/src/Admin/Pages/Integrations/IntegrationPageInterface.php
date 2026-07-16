<?php

namespace Sugar_Calendar\Admin\Pages\Integrations;

/**
 * Contract for a Settings → Integrations detail page (e.g. Zoom, GoogleMaps).
 *
 * Consumed by SettingsIntegrationsTab for sidebar rendering, title-bar status
 * badge, and the content panel body. These 5 methods are always called
 * unconditionally by the dispatcher — every integration page must implement
 * them. Optional behaviors (hooks/handle_post/get_help_url/is_local) live on
 * AbstractIntegrationPage with no-op defaults, not on this interface.
 *
 * @since 3.12.0
 */
interface IntegrationPageInterface {

	/**
	 * Integration slug (URL key).
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_slug();

	/**
	 * Display name shown in the sidebar and content title bar.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Sidebar icon URL.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_icon_url();

	/**
	 * Resolve the current connection/configuration status.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_status();

	/**
	 * Render the content panel body.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function render_content();
}
