<?php

namespace Sugar_Calendar\Admin\Pages\Integrations;

use Sugar_Calendar\Admin\Pages\SettingsIntegrationsTab;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Integrations\IntegrationsDisabler;
use Sugar_Calendar\Integrations\OAuthRelay\OAuthConnectionModel;
use Sugar_Calendar\Integrations\OAuthRelay\OAuthRelayClient;
use Sugar_Calendar\Integrations\OAuthRelay\Credits\CreditsService;
use Sugar_Calendar\Vendor\ProductApi\Auth\AuthOptions;
use Sugar_Calendar\Vendor\ProductApi\Auth\SiteRegistration;
use Sugar_Calendar\Vendor\ProductApi\Options;
use Sugar_Calendar\Vendor\ProductApi\ProductApi;

/**
 * Zoom Integration page.
 *
 * Provides metadata (slug/name/icon/status) consumed by
 * SettingsIntegrationsTab for sidebar + title-bar rendering, and a
 * `render_content()` method that fills the content panel body with a
 * description paragraph and the "Account Connection" settings row.
 *
 * Matches Figma node 11623:19857 in file w3az83btQvXzV4XPJXPHKS.
 *
 * Connection statuses are active|auth_error|not_connected, each with a
 * dedicated state renderer.
 *
 * NOTE: This class is intentionally named `Zoom` (not `ZoomIntegration`)
 * to avoid a name collision with the main glue class
 * Sugar_Calendar\Integrations\Zoom\ZoomIntegration that lands in a
 * later phase.
 *
 * @since 3.12.0
 */
class Zoom extends AbstractIntegrationPage {

	/**
	 * Base documentation URL. Each entry point adds its own UTM params.
	 *
	 * @since 3.12.0
	 */
	const DOCS_URL = 'https://sugarcalendar.com/docs/events/using-the-zoom-integration/';

	/**
	 * Memoized zoom connection row for this request.
	 *
	 * `false` = not yet loaded; `null` = loaded, no row exists.
	 *
	 * @since 3.12.0
	 *
	 * @var array|null|false
	 */
	private $connection_row = false;

	/**
	 * Lazily load and cache the zoom connection row.
	 *
	 * @since 3.12.0
	 *
	 * @return array|null Connection row, or null when not connected.
	 */
	protected function get_connection_row() {

		if ( $this->connection_row === false ) {
			$this->connection_row = OAuthConnectionModel::find_by_provider( 'zoom' );
		}

		return $this->connection_row;
	}

	/**
	 * Register hooks for this page.
	 *
	 * Called by SettingsIntegrationsTab::hooks() once per request when
	 * Zoom is the active integration.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function hooks() {

		add_action( 'admin_init', [ $this, 'handle_connect' ] );
		add_action( 'admin_init', [ $this, 'handle_disconnect' ] );
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
	 * Documentation URL shown via the Integrations tab help button.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_help_url() {

		return Helpers::get_utm_url(
			self::DOCS_URL,
			[
				'content' => 'Help',
				'medium'  => 'plugin-settings-integrations-zoom',
			]
		);
	}

	/**
	 * Resolve the current connection status.
	 *
	 * @since 3.12.0
	 *
	 * @return string One of: not_connected, active, auth_error.
	 */
	public function get_status() {

		$row = $this->get_connection_row();

		if ( $row === null ) {
			return 'not_connected';
		}

		return $row['status'] === 'auth_error' ? 'auth_error' : 'active';
	}

	/**
	 * Render the content panel body.
	 *
	 * Called by SettingsIntegrationsTab::render_content_panel(). Renders
	 * the inline stub notice (if present), the description paragraph,
	 * and the state-specific Account Connection row.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function render_content() {

		$this->maybe_render_notice();
		$this->render_description();

		switch ( $this->get_status() ) {
			case 'active':
				$this->render_state_active();
				break;
			case 'auth_error':
				$this->render_state_auth_error();
				break;
			case 'not_connected':
			default:
				$this->render_state_not_connected();
				break;
		}
	}

	/**
	 * Render the description paragraph with inline docs link.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	protected function render_description() {

		$documentation_link_url = Helpers::get_utm_url(
			self::DOCS_URL,
			[
				'content' => 'our documentation',
				'medium'  => 'settings-integrations-zoom',
			]
		);
		?>
		<p class="sugar-calendar-zoom__description">
			<?php
			printf(
				/* translators: %s - Documentation link URL. */
				wp_kses(
					__( 'Connect your Zoom account to automatically generate meeting links for your online events. If you need help, please refer to <a href="%s" target="_blank" rel="noopener">our documentation</a>.', 'sugar-calendar-lite' ),
					[
						'a' => [
							'href'   => [],
							'target' => [],
							'rel'    => [],
						],
					]
				),
				esc_url( $documentation_link_url )
			);
			?>
		</p>
		<?php
	}

	/**
	 * Build the URL the Connect button points at.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_connect_url() {

		$args = [
			'action'   => 'sc_integration_connect',
			'provider' => 'zoom',
		];

		// In E2E test mode the relay-stub filter reads the desired OAuth
		// outcome from the request that fires it — which is the
		// sc_integration_connect request, not this Settings-page render.
		// Carry the flag forward so the stub still sees it. This branch is
		// inert in production (the flag is never present off the test stack).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( defined( 'SC_OAUTH_RELAY_TEST_MODE' ) && SC_OAUTH_RELAY_TEST_MODE && isset( $_GET['sc_e2e_oauth_outcome'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['sc_e2e_oauth_outcome'] = sanitize_key( wp_unslash( $_GET['sc_e2e_oauth_outcome'] ) );
		}

		return wp_nonce_url(
			add_query_arg(
				$args,
				SettingsIntegrationsTab::get_integration_url( 'zoom' )
			),
			'sc_oauth_connect_zoom'
		);
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
	 * Render an Account Connection settings row.
	 *
	 * @since 3.12.0
	 *
	 * @param string $control_html Inline HTML for the right-hand control.
	 *
	 * @return void
	 */
	protected function render_account_connection_row( $control_html ) {

		// Render through the shared row helper (emits .sugar-calendar-setting-row)
		// rather than hand-rolled markup; the integration-panel row look is applied
		// in CSS. $control_html is pre-escaped by the caller and goes in the field.
		UI::field_wrapper(
			[
				'label' => __( 'Account Connection', 'sugar-calendar-lite' ),
			],
			$control_html
		);
	}

	/**
	 * Render the "not connected" state (Connect with Zoom button).
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	protected function render_state_not_connected() {

		$connect_url = $this->get_connect_url();

		ob_start();
		?>
		<a href="<?php echo esc_url( $connect_url ); ?>" class="sugar-calendar-zoom__connect-button">
			<?php $this->render_connect_button_icon(); ?>
			<span class="sugar-calendar-zoom__connect-button-label">
				<?php esc_html_e( 'Connect with Zoom', 'sugar-calendar-lite' ); ?>
			</span>
		</a>
		<?php
		$this->render_account_connection_row( ob_get_clean() );
	}

	/**
	 * Render the "active" (connected) state: the account card inside the
	 * "Account Connection" label row (matching the not-connected state).
	 *
	 * Avatar + account email + "Connected on {date}" + a single red Remove
	 * action (the existing disconnect handler). Credits are NOT rendered here
	 * (see the get_credits() side-effect note below).
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	protected function render_state_active() {

		$row            = $this->get_connection_row();
		$disconnect_url = $this->get_disconnect_url();

		// Side-effect only: warms the create-gate transient and fires the
		// low-credit alert email. The value is intentionally discarded — the
		// card shows no credits UI.
		( new CreditsService() )->get_credits();

		$email  = $row ? (string) $row['account_email'] : '';
		$avatar = ( $row && ! empty( $row['account_avatar'] ) )
			? (string) $row['account_avatar']
			: $this->get_icon_url();

		$connected_on = ( $row && ! empty( $row['connected_at'] ) )
			? date_i18n( get_option( 'date_format' ), strtotime( (string) $row['connected_at'] . ' UTC' ) )
			: '';

		ob_start();
		?>
		<div class="sugar-calendar-zoom__account-card">
			<div class="sugar-calendar-zoom__account-row">
				<div class="sugar-calendar-zoom__account-identity">
					<span class="sugar-calendar-zoom__account-avatar">
						<img src="<?php echo esc_url( $avatar ); ?>" width="40" height="40" alt="" />
					</span>
					<span class="sugar-calendar-zoom__account-meta">
						<span class="sugar-calendar-zoom__account-email">
							<?php echo esc_html( $email ); ?>
						</span>
						<?php if ( $connected_on !== '' ) : ?>
							<span class="sugar-calendar-zoom__account-since">
								<?php
								printf(
									/* translators: %s - date the Zoom account was connected. */
									esc_html__( 'Connected on %s', 'sugar-calendar-lite' ),
									esc_html( $connected_on )
								);
								?>
							</span>
						<?php endif; ?>
					</span>
				</div>
				<a href="<?php echo esc_url( $disconnect_url ); ?>" class="sugar-calendar-zoom__account-remove">
					<?php esc_html_e( 'Remove', 'sugar-calendar-lite' ); ?>
				</a>
			</div>
		</div>
		<?php
		$this->render_account_connection_row( ob_get_clean() );
	}

	/**
	 * Render the "auth error" state.
	 *
	 * A broken connection shows the same "Connect with Zoom" button as the
	 * not-connected state; the title-bar badge ("Reconnect required") is the
	 * differentiator. OAuthConnectionErrorNotice covers every other admin page.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	protected function render_state_auth_error() {

		$this->render_state_not_connected();
	}

	/**
	 * Build the Disconnect-action URL.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_disconnect_url() {

		return wp_nonce_url(
			add_query_arg(
				[
					'action'   => 'sc_oauth_disconnect',
					'provider' => 'zoom',
				],
				SettingsIntegrationsTab::get_integration_url( 'zoom' )
			),
			'sc_oauth_disconnect_zoom'
		);
	}

	/**
	 * Handle the Connect button click: register the site with the relay if
	 * needed, then redirect to the relay's authorization URL.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function handle_connect() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['action'] ) || $_GET['action'] !== 'sc_integration_connect' ) {
			return;
		}

		if ( empty( $_GET['provider'] ) || $_GET['provider'] !== 'zoom' ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'sugar-calendar-lite' ) );
		}

		check_admin_referer( 'sc_oauth_connect_zoom' );

		// The Lite "Disable Integrations" kill-switch renders the connect UI
		// inert (aria-disabled), but that is a visual hint, not a boundary — the
		// nonced link is still reachable. Gate here so the editor's read-only
		// Online state and the connect flow agree: no connecting while disabled.
		if ( IntegrationsDisabler::is_disabled() ) {
			wp_safe_redirect( SettingsIntegrationsTab::get_integration_url( 'zoom' ) );
			exit;
		}

		// The relay return URL MUST carry action=sc_oauth_callback so the
		// callback handler fires when the relay redirects back. provider +
		// nonce are added by OAuthRelayClient::get_authorization_url().
		$return_url = add_query_arg(
			[ 'action' => 'sc_oauth_callback' ],
			SettingsIntegrationsTab::get_integration_url( 'zoom' )
		);

		$relay = new OAuthRelayClient();

		// Register the site with the Product API relay before starting OAuth.
		// Skipped under the E2E test-mode constant (the whole relay is stubbed).
		if ( ! ( defined( 'SC_OAUTH_RELAY_TEST_MODE' ) && SC_OAUTH_RELAY_TEST_MODE ) ) {
			if ( ! $this->maybe_register_site() ) {
				$redirect = add_query_arg(
					'sc_notice',
					'zoom_relay_unavailable',
					SettingsIntegrationsTab::get_integration_url( 'zoom' )
				);

				wp_safe_redirect( $redirect );
				exit;
			}
		}

		$auth_url = $relay->get_authorization_url( 'zoom', $return_url, 'integrations' );

		// The authorization URL points at the Product API relay — an external
		// host that wp_safe_redirect() would reject, silently falling back to
		// the dashboard. The URL is code-built from the api_url config, not
		// user input, so a plain redirect is safe here (Bookings does the same).
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		wp_redirect( $auth_url );
		exit;
	}

	/**
	 * Register the installation with the Product API relay if it is not
	 * already registered.
	 *
	 * Mirrors the Bookings IntegrationsController::connect_integration()
	 * registration core: reset a stale registration if the site URL changed,
	 * clear the rate-limit transient (admin-initiated connects must not be
	 * blocked), then register if needed. On failure the error is logged and
	 * false is returned so the caller can surface the relay-unavailable
	 * notice instead of starting OAuth.
	 *
	 * @since 3.12.0
	 *
	 * @return bool True when the site is registered (already or freshly), false on failure.
	 */
	protected function maybe_register_site() {

		// The Product API container is only configured in Pro (includes/pro/Pro.php).
		// On Lite the Integrations tab still renders, so a Connect click would reach
		// ProductApi::get() with no configured container and fatal. Degrade to the
		// relay-unavailable notice (false) instead — matching every other Lite-reachable
		// ProductApi consumer (CreditsService, IncomingWebhookHandler, register_webhook).
		try {
			$registration = ProductApi::get(
				SiteRegistration::class
			);

			// Reset stale registration when the site URL has changed.
			$this->maybe_reset_stale_registration( $registration );

			if ( $registration->is_registered() ) {
				return true;
			}

			// Clear the rate limit — admin-initiated connects should not be blocked.
			ProductApi::get( Options::class )
				->delete_transient( 'rate_limit_auth_register_site' );

			$result = $registration->register();

			if ( is_wp_error( $result ) ) {
				error_log( '[SC Zoom] site registration failed: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

				return false;
			}

			return true;
		} catch ( \Throwable $e ) {
			error_log( '[SC Zoom] relay unavailable (Product API not configured): ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			return false;
		}
	}

	/**
	 * Reset a stale site registration when the site URL has changed.
	 *
	 * When the site URL changes, the relay still associates the old domain
	 * with the existing installation ID. Clearing the stored credentials and
	 * generating a fresh installation ID forces a clean re-registration with
	 * the new domain. Ported from Bookings
	 * IntegrationsController::maybe_reset_stale_registration().
	 *
	 * @since 3.12.0
	 *
	 * @param SiteRegistration $registration SiteRegistration instance.
	 *
	 * @return void
	 */
	protected function maybe_reset_stale_registration( $registration ) {

		// Already registered with the current URL — nothing to do.
		if ( $registration->is_registered() ) {
			return;
		}

		$auth_options = ProductApi::get(
			AuthOptions::class
		);

		// No credentials stored — first-time registration, no reset needed.
		if ( empty( $auth_options->get( 'site_id' ) ) ) {
			return;
		}

		// Credentials exist but the current URL isn't registered → domain
		// changed. Clear auth credentials so register() starts fresh.
		$auth_options->update(
			[
				'site_id'          => '',
				'signing_secret'   => '',
				'verification_key' => '',
				'site_urls'        => [],
			]
		);

		// Generate a new installation ID so the relay creates a fresh site
		// entry instead of returning stale credentials for the old domain.
		$options = ProductApi::get(
			Options::class
		);

		$options->update(
			[
				'wp_installation_id' => substr( wp_hash( wp_generate_uuid4() ), 0, 30 ),
			]
		);

		// Clear the rate limit so the fresh registration isn't blocked by
		// previous attempts.
		$options->delete_transient( 'rate_limit_auth_register_site' );
	}

	/**
	 * Handle the Disconnect button click.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function handle_disconnect() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['action'] ) || $_GET['action'] !== 'sc_oauth_disconnect' ) {
			return;
		}

		if ( empty( $_GET['provider'] ) || $_GET['provider'] !== 'zoom' ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'sugar-calendar-lite' ) );
		}

		check_admin_referer( 'sc_oauth_disconnect_zoom' );

		$row = OAuthConnectionModel::find_by_provider( 'zoom' );

		if ( $row !== null ) {

			// Best-effort relay-side teardown (3a): stop the relay forwarding
			// Zoom webhooks for this account. Log-only on failure — disconnect
			// must never be blocked by relay availability. (No token-revoke
			// endpoint exists; the relay's stored tokens are simply orphaned.)
			$unregistered = ( new OAuthRelayClient() )->unregister_webhook(
				'zoom',
				(string) $row['account_id']
			);

			if ( is_wp_error( $unregistered ) ) {
				error_log( '[SC Zoom] webhook unregister on disconnect failed: ' . $unregistered->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			// The connection is site-wide, so remove every zoom row — a second
			// admin may have left a stale row that would otherwise keep the
			// reconnect notice firing after disconnect.
			OAuthConnectionModel::delete_by_provider( 'zoom' );
		}

		$redirect = add_query_arg(
			'sc_notice',
			'zoom_disconnected',
			SettingsIntegrationsTab::get_integration_url( 'zoom' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render inline notices set by the ?sc_notice= query param.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	protected function maybe_render_notice() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = isset( $_GET['sc_notice'] ) ? sanitize_key( $_GET['sc_notice'] ) : '';

		$messages = [
			'zoom_connected'         => [ 'success', __( 'Zoom connected successfully.', 'sugar-calendar-lite' ) ],
			'zoom_disconnected'      => [ 'success', __( 'Zoom disconnected.', 'sugar-calendar-lite' ) ],
			'zoom_relay_unavailable' => [ 'error', __( 'Could not connect to Zoom. Please try again.', 'sugar-calendar-lite' ) ],
		];

		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}

		list( $level, $message ) = $messages[ $notice ];
		?>
		<div class="notice notice-<?php echo esc_attr( $level ); ?> is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}
}
