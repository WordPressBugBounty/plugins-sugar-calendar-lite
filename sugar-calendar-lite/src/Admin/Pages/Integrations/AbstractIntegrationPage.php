<?php

namespace Sugar_Calendar\Admin\Pages\Integrations;

/**
 * Base class for a Settings → Integrations detail page.
 *
 * Implements IntegrationPageInterface's 5 required methods as abstract (every
 * subclass must define them) and gives the 4 optional hooks SettingsIntegrationsTab
 * probes via method_exists() sensible no-op defaults, so a subclass only
 * overrides what it actually needs:
 *
 * - hooks()        — register admin_init/other handlers. Default: no-op.
 * - handle_post()  — persist settings posted from the panel form. Default: no-op.
 * - get_help_url() — docs link for the tab's help button. Default: null (the
 *                    dispatcher falls back to the generic Integrations docs URL).
 * - is_local()     — exempt from the Lite "Disable Integrations" kill-switch.
 *                    Default: false.
 *
 * SettingsIntegrationsTab still guards these 4 with method_exists() rather than
 * an instanceof check against this class, so a third-party integration added
 * via the `sugar_calendar_admin_settings_integrations` filter is not required
 * to extend this class — only the 5 IntegrationPageInterface methods are a
 * hard requirement there.
 *
 * @since 3.12.0
 */
abstract class AbstractIntegrationPage implements IntegrationPageInterface {

	/**
	 * Register hooks for this page. Override when the integration needs
	 * admin_init (or other) handlers — e.g. an OAuth connect/disconnect flow.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function hooks() {}

	/**
	 * Persist settings posted from the panel form. Override when the
	 * integration saves via POST (e.g. an API key field).
	 *
	 * @since 3.12.0
	 *
	 * @param array $post_data Posted `sugar-calendar` settings array.
	 *
	 * @return void
	 */
	public function handle_post( $post_data = [] ) {}

	/**
	 * Documentation URL for the tab's help button. Override to point at the
	 * integration's own docs; the dispatcher falls back to the generic
	 * Integrations docs URL when this returns null.
	 *
	 * @since 3.12.0
	 *
	 * @return string|null
	 */
	public function get_help_url() {

		return null;
	}

	/**
	 * Whether this is a local (non Product-API) integration, exempt from the
	 * Lite "Disable Integrations" kill-switch.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public function is_local() {

		return false;
	}
}
