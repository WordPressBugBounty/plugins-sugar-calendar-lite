<?php

namespace Sugar_Calendar\Admin\Pages\Integrations;

use Sugar_Calendar\Admin\Pages\SettingsIntegrationsTab;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Integrations\IntegrationsDisabler;
use Sugar_Calendar\Integrations\OAuthRelay\OAuthConnectionModel;
use Sugar_Calendar\Integrations\OAuthRelay\OAuthRelayClient;
use Sugar_Calendar\Integrations\OAuthRelay\Credits\CreditsService;

/**
 * A Settings → Integrations page whose account is connected through the Product API relay.
 *
 * Holds the Connect / Remove flow, the status, the notices and the account card once.
 * A page tells it which provider it is and fills the seams: get_description_text(),
 * get_connect_label(), get_notice_messages(), render_settings_rows(), on_disconnect().
 *
 * @since 3.16.0
 */
abstract class AbstractOAuthIntegrationPage extends AbstractIntegrationPage {

	/**
	 * Base documentation URL. Each entry point adds its own UTM params.
	 *
	 * @since 3.16.0
	 */
	const DOCS_URL = '';

	/**
	 * Memoized connection row for this request.
	 *
	 * `false` = not yet loaded; `null` = loaded, no row exists.
	 *
	 * @since 3.16.0
	 *
	 * @var array|null|false
	 */
	private $connection_row = false;

	/**
	 * Relay provider slug: the key on the connection row and the relay app.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	abstract public function get_provider();

	/**
	 * The sentence under the title, with a %s for the documentation link.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	abstract protected function get_description_text();

	/**
	 * Label of the Connect button.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	abstract protected function get_connect_label();

	/**
	 * Inline notices this page shows, keyed by the `sc_notice` query value: [ level, message ].
	 *
	 * @since 3.16.0
	 *
	 * @return array
	 */
	abstract protected function get_notice_messages();

	/**
	 * Settings rows rendered above the account card while connected. Default: none.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	protected function render_settings_rows() {}

	/**
	 * Provider clean-up on Remove, before the connection rows are deleted. Default: none.
	 *
	 * @since 3.16.0
	 *
	 * @param array|null $row The connection row being removed, or null when none existed.
	 *
	 * @return void
	 */
	protected function on_disconnect( $row ) {}

	/**
	 * Lazily load and cache the connection row.
	 *
	 * @since 3.16.0
	 *
	 * @return array|null Connection row, or null when not connected.
	 */
	protected function get_connection_row() {

		if ( $this->connection_row === false ) {
			$this->connection_row = OAuthConnectionModel::find_by_provider( $this->get_provider() );
		}

		return $this->connection_row;
	}

	/**
	 * Register the Connect and Remove handlers. Runs only while this page is active.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	public function hooks() {

		add_action( 'admin_init', [ $this, 'handle_connect' ] );
		add_action( 'admin_init', [ $this, 'handle_disconnect' ] );
	}

	/**
	 * Documentation URL shown via the Integrations tab help button.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	public function get_help_url() {

		return Helpers::get_utm_url(
			static::DOCS_URL,
			[
				'content' => 'Help',
				'medium'  => 'plugin-settings-integrations-' . $this->get_slug(),
			]
		);
	}

	/**
	 * Resolve the current connection status.
	 *
	 * @since 3.16.0
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
	 * Render the content panel body: notices, description, then the state.
	 *
	 * @since 3.16.0
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
	 * @since 3.16.0
	 *
	 * @return void
	 */
	protected function render_description() {

		$documentation_link_url = Helpers::get_utm_url(
			static::DOCS_URL,
			[
				'content' => 'our documentation',
				'medium'  => 'settings-integrations-' . $this->get_slug(),
			]
		);
		?>
		<p class="sugar-calendar-<?php echo esc_attr( $this->get_slug() ); ?>__description">
			<?php
			printf(
				wp_kses(
					$this->get_description_text(),
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
	 * @since 3.16.0
	 *
	 * @return string
	 */
	protected function get_connect_url() {

		$args = [
			'action'   => 'sc_integration_connect',
			'provider' => $this->get_provider(),
		];

		// E2E only: carry the relay-stub outcome flag into the connect request.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( defined( 'SC_OAUTH_RELAY_TEST_MODE' ) && SC_OAUTH_RELAY_TEST_MODE && isset( $_GET['sc_e2e_oauth_outcome'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['sc_e2e_oauth_outcome'] = sanitize_key( wp_unslash( $_GET['sc_e2e_oauth_outcome'] ) );
		}

		return wp_nonce_url(
			add_query_arg(
				$args,
				SettingsIntegrationsTab::get_integration_url( $this->get_slug() )
			),
			'sc_oauth_connect_' . $this->get_provider()
		);
	}

	/**
	 * Build the Remove-action URL.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	protected function get_disconnect_url() {

		return wp_nonce_url(
			add_query_arg(
				[
					'action'   => 'sc_oauth_disconnect',
					'provider' => $this->get_provider(),
				],
				SettingsIntegrationsTab::get_integration_url( $this->get_slug() )
			),
			'sc_oauth_disconnect_' . $this->get_provider()
		);
	}

	/**
	 * Render the provider icon inside the Connect button.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	abstract protected function render_connect_button_icon();

	/**
	 * Render an Account Connection settings row.
	 *
	 * @since 3.16.0
	 *
	 * @param string $control_html Inline HTML for the right-hand control, escaped by the caller.
	 *
	 * @return void
	 */
	protected function render_account_connection_row( $control_html ) {

		UI::field_wrapper(
			[
				'label' => __( 'Account Connection', 'sugar-calendar-lite' ),
			],
			$control_html
		);
	}

	/**
	 * Render the "not connected" state: the Connect button.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	protected function render_state_not_connected() {

		$connect_url = $this->get_connect_url();
		$slug        = $this->get_slug();

		ob_start();
		?>
		<a href="<?php echo esc_url( $connect_url ); ?>" class="sugar-calendar-<?php echo esc_attr( $slug ); ?>__connect-button">
			<?php $this->render_connect_button_icon(); ?>
			<span class="sugar-calendar-<?php echo esc_attr( $slug ); ?>__connect-button-label">
				<?php echo esc_html( $this->get_connect_label() ); ?>
			</span>
		</a>
		<?php
		$this->render_account_connection_row( ob_get_clean() );
	}

	/**
	 * Render the "active" state: the page's settings rows, then the account card.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	protected function render_state_active() {

		$row            = $this->get_connection_row();
		$disconnect_url = $this->get_disconnect_url();
		$slug           = $this->get_slug();

		// Side-effect only: warms the credits transient and fires the low-credit alert.
		( new CreditsService() )->get_credits();

		$this->render_settings_rows();

		$email  = $row ? (string) $row['account_email'] : '';
		$avatar = ( $row && ! empty( $row['account_avatar'] ) )
			? (string) $row['account_avatar']
			: $this->get_icon_url();

		$connected_on = ( $row && ! empty( $row['connected_at'] ) )
			? date_i18n( get_option( 'date_format' ), strtotime( (string) $row['connected_at'] . ' UTC' ) )
			: '';

		ob_start();
		?>
		<div class="sugar-calendar-<?php echo esc_attr( $slug ); ?>__account-card">
			<div class="sugar-calendar-<?php echo esc_attr( $slug ); ?>__account-row">
				<div class="sugar-calendar-<?php echo esc_attr( $slug ); ?>__account-identity">
					<span class="sugar-calendar-<?php echo esc_attr( $slug ); ?>__account-avatar">
						<img src="<?php echo esc_url( $avatar ); ?>" width="40" height="40" alt="" />
					</span>
					<span class="sugar-calendar-<?php echo esc_attr( $slug ); ?>__account-meta">
						<span class="sugar-calendar-<?php echo esc_attr( $slug ); ?>__account-email">
							<?php echo esc_html( $email ); ?>
						</span>
						<?php if ( $connected_on !== '' ) : ?>
							<span class="sugar-calendar-<?php echo esc_attr( $slug ); ?>__account-since">
								<?php
								printf(
									/* translators: %s - date the account was connected. */
									esc_html__( 'Connected on %s', 'sugar-calendar-lite' ),
									esc_html( $connected_on )
								);
								?>
							</span>
						<?php endif; ?>
					</span>
				</div>
				<a href="<?php echo esc_url( $disconnect_url ); ?>" class="sugar-calendar-<?php echo esc_attr( $slug ); ?>__account-remove">
					<?php esc_html_e( 'Remove', 'sugar-calendar-lite' ); ?>
				</a>
			</div>
		</div>
		<?php
		$this->render_account_connection_row( ob_get_clean() );
	}

	/**
	 * Render the "auth error" state: the same Connect button, the title-bar badge tells the difference.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	protected function render_state_auth_error() {

		$this->render_state_not_connected();
	}

	/**
	 * Handle the Connect button click: register the site with the relay if
	 * needed, then redirect to the relay's authorization URL.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	public function handle_connect() {

		$provider = $this->get_provider();
		$page_url = SettingsIntegrationsTab::get_integration_url( $this->get_slug() );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['action'] ) || $_GET['action'] !== 'sc_integration_connect' ) {
			return;
		}

		if ( empty( $_GET['provider'] ) || $_GET['provider'] !== $provider ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'sugar-calendar-lite' ) );
		}

		check_admin_referer( 'sc_oauth_connect_' . $provider );

		// The kill-switch only greys the button out; the nonced link is still reachable.
		if ( IntegrationsDisabler::is_disabled() ) {
			wp_safe_redirect( $page_url );
			exit;
		}

		// The return URL must carry action=sc_oauth_callback so the callback handler fires.
		$return_url = add_query_arg( [ 'action' => 'sc_oauth_callback' ], $page_url );

		$relay = new OAuthRelayClient();

		// Register the site with the relay before starting OAuth. Skipped under the E2E stub.
		if ( ! ( defined( 'SC_OAUTH_RELAY_TEST_MODE' ) && SC_OAUTH_RELAY_TEST_MODE ) ) {
			if ( ! $this->maybe_register_site() ) {
				wp_safe_redirect( add_query_arg( 'sc_notice', $provider . '_relay_unavailable', $page_url ) );
				exit;
			}
		}

		$auth_url = $relay->get_authorization_url( $provider, $return_url, 'integrations' );

		// The relay is an external host wp_safe_redirect() would reject; the URL is code-built.
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		wp_redirect( $auth_url );
		exit;
	}

	/**
	 * Handle the Remove click: relay clean-up, the page's own clean-up, then drop the rows.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	public function handle_disconnect() {

		$provider = $this->get_provider();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['action'] ) || $_GET['action'] !== 'sc_oauth_disconnect' ) {
			return;
		}

		if ( empty( $_GET['provider'] ) || $_GET['provider'] !== $provider ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'sugar-calendar-lite' ) );
		}

		check_admin_referer( 'sc_oauth_disconnect_' . $provider );

		$row = $this->get_connection_row();

		if ( $row !== null ) {
			// Best-effort: disconnect must never be blocked by relay availability.
			( new OAuthRelayClient() )->unregister_webhook( $provider, (string) $row['account_id'] );
		}

		$this->on_disconnect( $row );

		if ( $row !== null ) {
			// Site-wide connection: remove every row for the provider, not just the current user's.
			OAuthConnectionModel::delete_by_provider( $provider );

			/**
			 * Fires after an integration disconnects. Moved here from the Zoom page.
			 *
			 * @since 3.16.0
			 *
			 * @param string $provider Provider slug.
			 * @param array  $context  Provider details; Stripe passes `mode` (live|sandbox).
			 */
			do_action( 'sugar_calendar_integration_disconnected', $provider, [] ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		}

		wp_safe_redirect(
			add_query_arg(
				'sc_notice',
				$provider . '_disconnected',
				SettingsIntegrationsTab::get_integration_url( $this->get_slug() )
			)
		);
		exit;
	}

	/**
	 * Render the inline notice named by the ?sc_notice= query param, if the page knows it.
	 *
	 * @since 3.16.0
	 *
	 * @return void
	 */
	protected function maybe_render_notice() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice   = isset( $_GET['sc_notice'] ) ? sanitize_key( $_GET['sc_notice'] ) : '';
		$messages = $this->get_notice_messages();

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
