<?php

namespace Sugar_Calendar\Integrations\OAuthRelay;

use Sugar_Calendar\Integrations\Admin\CreateMeetingAjax;
use Sugar_Calendar\Integrations\Admin\RemoveMeetingAjax;
use Sugar_Calendar\Integrations\Admin\MeetingRemovedNotice;
use Sugar_Calendar\Integrations\Admin\OnlineMeetingSection;
use Sugar_Calendar\Integrations\Admin\OutOfCreditsNotice;
use Sugar_Calendar\Integrations\EventMeetingManager;
use Sugar_Calendar\Integrations\Frontend\OnlineEventSchemaProvider;
use Sugar_Calendar\Integrations\Frontend\OnlineMeetingDisplay;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Integrations\IntegrationCapabilityRegistry;
use Sugar_Calendar\Integrations\Zoom\ZoomIntegration;
use Sugar_Calendar\Integrations\Zoom\ZoomWebhookEventHandler;
use Sugar_Calendar\Vendor\ProductApi\ProductApi;

/**
 * Boot the OAuthRelay subsystem.
 *
 * Registers the connections-table migration via filter so SCE's
 * Migrations orchestrator picks it up on admin_init, and wires the
 * admin_notices renderer that surfaces auth_error connections (admin
 * only).
 *
 * Also boots the Zoom integration, which registers the Zoom capability
 * with the registry and wires its OAuth callback handler, and the
 * provider-agnostic webhook-ingest REST endpoint (REST requests, not
 * admin-only).
 *
 * @since 3.12.0
 */
class Loader {

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 */
	public function init() {

		// Configure the Product API relay on Lite so free installs can connect
		// integrations (Zoom, etc.). Pro configures it in includes/pro/Pro.php
		// with the pro license; this fills the gap on Lite. The relay accepts the
		// 'lite' tier for OAuth (auth/start is public; auth/token skips license
		// validation for lite), so a free install completes the Connect flow.
		$this->maybe_configure_relay_for_lite();

		add_filter( 'sugar_calendar_migrations_get_migrations', [ $this, 'register_migrations' ] );

		if ( is_admin() ) {
			( new OAuthConnectionErrorNotice() )->init();

			// Global out-of-credits notice — renders on every admin page from the
			// cached (no-HTTP) credits gate; dismissal is per-user, per-period.
			( new OutOfCreditsNotice() )->hooks();
		}

		// Boot the Zoom integration on every request: the capability must
		// register with the singleton registry regardless of context
		// (Segments 2/3 consume it from front-end/REST), even though the
		// callback handler it wires only acts on admin_init.
		$relay   = new OAuthRelayClient();
		$credits = new Credits\CreditsService();

		( new ZoomIntegration( $relay, $credits ) )->init();

		// Zoom webhook event handler capability (3a). Registered on every
		// request — IncomingWebhookHandler resolves it on unauthenticated
		// REST hits.
		IntegrationCapabilityRegistry::instance()->register(
			new ZoomWebhookEventHandler()
		);

		// Event meeting lifecycle (update / switch / removal on save + trash/delete
		// cleanup). Create is now explicit (the Create-Meeting AJAX endpoint).
		$registry = IntegrationCapabilityRegistry::instance();
		$manager  = new EventMeetingManager( $registry, $credits );
		$manager->hooks();

		// Explicit "Create {Provider} Meeting" AJAX endpoint (admin-ajax). Shares the
		// manager's provision path so the button and the lifecycle agree.
		( new CreateMeetingAjax( $registry, $manager ) )->hooks();

		// Explicit "Remove" AJAX endpoint (admin-ajax). Shares the manager's detach
		// path so the button and the on-save removal reconcile agree.
		( new RemoveMeetingAjax( $manager ) )->hooks();

		// "Online" event-editor metabox section. Booted unconditionally so its
		// `online_provider` meta registration (on the `sugar_calendar_meta_data`
		// filter) runs before init:10 on every request; its section-render and
		// save hooks only fire in the admin editor / on event saves anyway.
		( new OnlineMeetingSection() )->hooks();

		// Public Join Link on the single-event front-end page (Show to = Everyone).
		// Booted unconditionally; the action only fires on the single-event page.
		( new OnlineMeetingDisplay() )->hooks();

		// Register the online Event JSON-LD provider with the core aggregator.
		// Booted here (not in core) so the online concern stays colocated with
		// OnlineMeetingDisplay; the filter runs on every request, the closure
		// only constructs the node when wp_head fires on a single event page.
		add_filter(
			'sugar_calendar_structured_data_providers', // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			static function ( $providers ) {

				$providers[] = new OnlineEventSchemaProvider();

				return $providers;
			}
		);

		// Event-editor notice when a provider meeting was deleted externally (the
		// webhook reset leaves a breadcrumb; this renders + clears it). Booted
		// unconditionally; its hooks self-gate to the admin context.
		( new MeetingRemovedNotice() )->hooks();

		// Webhook ingest (3a): provider-agnostic REST endpoint. Booted on
		// every request — the route must register for unauthenticated REST
		// hits, not just admin.
		( new Webhooks\IncomingWebhookHandler() )->hooks();

		// Keep registered webhook URLs current across REST-base-URL changes
		// and surface a warning when the REST API is unreachable (3a).
		( new Webhooks\WebhookUrlMonitor(
			IntegrationCapabilityRegistry::instance()
		) )->hooks();
	}

	/**
	 * Configure the Product API relay for Lite installs.
	 *
	 * Pro configures the relay in includes/pro/Pro.php (with the pro license and
	 * event tracking). On Lite that file never loads, so without this the relay
	 * is unconfigured and every call throws "ProductApi is not configured" —
	 * surfacing as "Could not connect to Zoom". Here we configure it with the
	 * Lite profile (no license; is_pro = false → get_license_type() resolves to
	 * 'lite'), which the relay accepts for the full OAuth flow.
	 *
	 * Guarded on ! is_pro() because ProductApi::configure() throws if called
	 * twice and Pro already configures it. Event tracking (with_events()) is
	 * enabled so the SMTP promo-page install click can be tracked on Lite (see
	 * SmtpProductEvents); the four Pro product events stay Pro-only because
	 * ProductEvents itself boots only from Pro.php. The try/catch keeps a misconfigured
	 * or already-configured edge case from fataling — the relay calls have their
	 * own guards and degrade to WP_Error / null.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	private function maybe_configure_relay_for_lite() {

		if ( sugar_calendar()->is_pro() ) {
			return;
		}

		try {
			ProductApi::configure(
				array_merge(
					self::base_relay_config(),
					[
						'license_key'   => '',
						'license_valid' => false,
						'is_pro'        => false,
					]
				)
			)
				->with_events()
				->boot();
		} catch ( \Throwable $e ) {
			// Lite has no ProductApi; configuring the relay is a no-op here.
			unset( $e );
		}
	}

	/**
	 * The relay-config keys shared by both editions' `ProductApi::configure()` call.
	 *
	 * `includes/pro/Pro.php` (Pro) and `maybe_configure_relay_for_lite()` (Lite)
	 * differ only in the license/`is_pro` fields. Every other key — most critically
	 * `plugin_slug`, which must equal the relay deployment's `PRODUCT_NAME` or
	 * site-ownership verification fails — must stay identical across both call
	 * sites, so it's defined once here rather than duplicated.
	 *
	 * @since 3.12.0
	 *
	 * @return array
	 */
	public static function base_relay_config() {

		return [
			'api_url'        => untrailingslashit( defined( 'SC_PRODUCT_API_BASE_URL' ) ? SC_PRODUCT_API_BASE_URL : 'https://events.sugarcalendarapi.com' ),
			'site_url'       => get_site_url(),
			'user_agent'     => Helpers::get_default_user_agent(),
			'environment'    => wp_get_environment_type(),
			'plugin_slug'    => 'sugar-calendar-events',
			'plugin_version' => SC_PLUGIN_VERSION,
		];
	}

	/**
	 * Add OAuthConnectionsTableMigration to the migration list.
	 *
	 * @since 3.12.0
	 *
	 * @param array $migrations Existing migrations.
	 *
	 * @return array
	 */
	public function register_migrations( $migrations ) {

		$migrations[] = OAuthConnectionsTableMigration::class;

		return $migrations;
	}
}
