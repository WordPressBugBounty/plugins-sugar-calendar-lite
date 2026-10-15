<?php

namespace Sugar_Calendar\Integrations;

use Sugar_Calendar\Integrations\OAuthRelay\OAuthConnectionModel;
use Sugar_Calendar\Integrations\OAuthRelay\OAuthRelayClient;
use Sugar_Calendar\Options;
use Sugar_Calendar\UsageTracking\SendUsageTask;

/**
 * Tears down external integrations when the Lite "Disable Integrations"
 * kill-switch is turned on.
 *
 * Shared by Admin\Pages\SettingsMiscTab (admin save) and the Setup Wizard
 * REST write path (SetupWizard\RestApi) so both perform identical teardown.
 * Mirrors the per-connection teardown in
 * Admin\Pages\Integrations\Zoom::handle_disconnect().
 *
 * @since 3.12.0
 */
class IntegrationsDisabler {

	/**
	 * Whether the Lite "Disable Integrations" kill-switch is on.
	 *
	 * The Misc option only takes effect on Lite — Pro always has integrations
	 * available. Single source of truth for the predicate; consumers that
	 * previously re-derived this (Admin\Pages\SettingsIntegrationsTab,
	 * Integrations\Admin\OnlineMeetingSection) now call this instead.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public static function is_disabled() {

		return ! sugar_calendar()->is_pro()
		       && Options::get( 'disable_integrations', false );
	}

	/**
	 * Disconnect every stored OAuth connection and stop usage tracking.
	 *
	 * @since 3.12.0
	 * @since 3.16.0 Fires sugar_calendar_integrations_before_disable.
	 *
	 * @return void
	 *
	 * @phpstan-ignore sugarCalendar.methodLength
	 */
	public function disable() {

		/**
		 * Fires before the stored connections are deleted, while their tokens can still reach the relay.
		 *
		 * @since 3.16.0
		 */
		do_action( 'sugar_calendar_integrations_before_disable' ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		foreach ( OAuthConnectionModel::get_all() as $connection ) {

			// Best-effort relay-side teardown. Log-only on failure — disabling
			// must never be blocked by relay availability (no revoke endpoint
			// exists; relay tokens are simply orphaned).
			// Best-effort unregister; a failure must not block the disable/delete.
			( new OAuthRelayClient() )->unregister_webhook(
				(string) $connection['provider'],
				(string) $connection['account_id']
			);

			OAuthConnectionModel::delete( (int) $connection['id'] );
		}

		// Cancel the scheduled usage-tracking task. This is REQUIRED, not a
		// backup: is_enabled() (derived from disable_integrations) only gates
		// whether SendUsageTask is RE-registered at the next boot — it does NOT
		// unschedule an already-scheduled action. This cancel() is the only
		// thing that stops the pending task, on both the Misc and wizard paths.
		// Do not remove it.
		( new SendUsageTask() )->cancel();
	}
}
