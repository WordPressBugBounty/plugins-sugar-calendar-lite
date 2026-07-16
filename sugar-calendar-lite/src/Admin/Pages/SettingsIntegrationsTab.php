<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Helpers as Sugar_Calendar_Helpers;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Integrations\IntegrationsDisabler;
use Sugar_Calendar\Integrations\OAuthRelay\Credits\CreditsService;
use Sugar_Calendar\Integrations\OAuthRelay\Credits\CreditsSignalRenderer;

/**
 * Integrations Settings tab.
 *
 * Renders a two-column layout (sidebar + content panel) matching the
 * Figma design (file w3az83btQvXzV4XPJXPHKS, node 11623:19857). Sidebar
 * lists every registered integration as well as "coming soon"
 * placeholders; content panel renders the active integration's
 * `render_content()` framed by a title bar with a status badge and a
 * Save Settings button footer.
 *
 * Per-integration page classes live under
 * Sugar_Calendar\Admin\Pages\Integrations.
 *
 * @since 3.12.0
 */
class SettingsIntegrationsTab extends Settings {

	/**
	 * Page tab slug.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public static function get_tab_slug() {

		return 'integrations';
	}

	/**
	 * Page label.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Integrations', 'sugar-calendar-lite' );
	}

	/**
	 * Page menu priority.
	 *
	 * Sits between Feeds (50) and Misc (70) in the Settings tab strip.
	 *
	 * @since 3.12.0
	 *
	 * @return int
	 */
	public static function get_priority() {

		return 60;
	}

	/**
	 * Resolve the current integration slug from the URL.
	 *
	 * @since 3.12.0
	 *
	 * @return string|null
	 */
	public static function get_current_integration() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['integration'] ) ? sanitize_key( $_GET['integration'] ) : null;
	}

	/**
	 * Whether the current request is for this Integrations tab page.
	 *
	 * Single source of truth for the page check OAuthConnectionErrorNotice and
	 * WebhookUrlMonitor each used to re-derive independently from hardcoded
	 * 'sugarcalendar-settings' / 'integrations' literals.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public static function is_current_page() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';

		return $page === static::get_slug() && $section === static::get_tab_slug();
	}

	/**
	 * Build the URL for a specific integration.
	 *
	 * @since 3.12.0
	 *
	 * @param string $integration Integration slug.
	 *
	 * @return string
	 */
	public static function get_integration_url( $integration ) {

		return add_query_arg(
			[
				'page'        => static::get_slug(),
				'section'     => static::get_tab_slug(),
				'integration' => $integration,
			],
			WP::admin_url( 'admin.php' )
		);
	}

	/**
	 * Map of integration slugs → real page-class instances.
	 *
	 * The first integration is the default when no `?integration=` is
	 * set in the URL.
	 *
	 * @since 3.12.0
	 *
	 * @return array<string, object>
	 */
	private function get_integrations() {

		static $integrations = null;

		if ( $integrations === null ) {
			$integrations = [
				'google-maps' => new Integrations\GoogleMaps(),
				'zoom'        => new Integrations\Zoom(),
			];

			/**
			 * Filter the registered (active) integrations on the
			 * Settings → Integrations tab.
			 *
			 * Each value must implement Integrations\IntegrationPageInterface
			 * (get_slug/get_name/get_icon_url/get_status/render_content).
			 * Extending Integrations\AbstractIntegrationPage is recommended —
			 * it supplies no-op defaults for the optional hooks()/handle_post()/
			 * get_help_url()/is_local() this tab also probes for.
			 *
			 * @since 3.12.0
			 *
			 * @param array<string, object> $integrations Slug → instance.
			 */
			$integrations = (array) apply_filters( 'sugar_calendar_admin_settings_integrations', $integrations );
		}

		return $integrations;
	}

	/**
	 * Placeholder sidebar entries shown alongside the real integrations.
	 *
	 * These are non-clickable, render a Coming Soon badge, and exist
	 * purely to mirror the Figma sidebar list. They are not part of the
	 * filterable integrations array because they have no behavior.
	 *
	 * @since 3.12.0
	 *
	 * @return array<int, array{slug:string,name:string,icon:string}>
	 */
	private function get_placeholders() {

		$image_base = SC_PLUGIN_ASSETS_URL . 'images/integrations/';

		return [
			[
				'slug' => 'google-calendar',
				'name' => esc_html__( 'Google Calendar / Meets', 'sugar-calendar-lite' ),
				'icon' => $image_base . 'integration-google-calendar.png',
			],
			[
				'slug' => 'sms',
				'name' => esc_html__( 'SMS', 'sugar-calendar-lite' ),
				'icon' => $image_base . 'integration-sms.png',
			],
			[
				'slug' => 'whatsapp',
				'name' => esc_html__( 'WhatsApp', 'sugar-calendar-lite' ),
				'icon' => $image_base . 'integration-whatsapp.png',
			],
			[
				'slug' => 'zapier',
				'name' => esc_html__( 'Zapier', 'sugar-calendar-lite' ),
				'icon' => SC_PLUGIN_ASSETS_URL . 'images/addon-icons/zapier.png',
			],
		];
	}

	/**
	 * Resolve the active integration instance.
	 *
	 * Returns the integration matching the current `?integration=`
	 * slug, falling back to the first registered integration.
	 *
	 * @since 3.12.0
	 *
	 * @return object|null
	 */
	private function get_active_integration() {

		$integrations = $this->get_integrations();

		if ( empty( $integrations ) ) {
			return null;
		}

		$slug = self::get_current_integration();

		if ( $slug !== null && isset( $integrations[ $slug ] ) ) {
			return $integrations[ $slug ];
		}

		return reset( $integrations );
	}

	/**
	 * Register page hooks.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function hooks() {

		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );

		$active = $this->get_active_integration();

		if ( $active !== null && method_exists( $active, 'hooks' ) ) {
			$active->hooks();
		}

		add_filter( 'sugar_calendar_helpers_ui_help_url', [ $this, 'help_url' ] );
	}

	/**
	 * Delegate a settings save to the active integration.
	 *
	 * Called by Area::handle_post() (the panel form posts the
	 * `sugar-calendar-submit` button + `sugar-calendar` nonce). Integrations
	 * that need to persist settings expose handle_post(); others (e.g. Zoom,
	 * which uses Connect/Disconnect action URLs) simply don't.
	 *
	 * @since 3.12.0
	 *
	 * @param array $post_data Posted `sugar-calendar` settings array (unslashed by
	 *                         Area::handle_post(); each integration's handle_post()
	 *                         is responsible for sanitizing its own inputs).
	 *
	 * @return void
	 */
	public function handle_post( $post_data = [] ) {

		$active = $this->get_active_integration();

		if ( $active === null ) {
			return;
		}

		// When integrations are disabled (Lite), Product-API integrations are
		// inert — ignore their saves server-side too (the dimmed form can still
		// be submitted via keyboard). Local integrations (e.g. Google Maps) stay
		// functional while disabled, so they are exempt.
		if ( $this->integration_is_inert( $active ) ) {
			return;
		}

		if ( method_exists( $active, 'handle_post' ) ) {
			$active->handle_post( $post_data );
		}
	}

	/**
	 * Display the page.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function display() {

		?>
		<div id="sugar-calendar-settings" class="wrap sugar-calendar-admin-wrap">

			<?php UI::tabs( $this->get_tabs(), static::get_tab_slug() ); ?>

			<div class="sugar-calendar-admin-content">
				<h1 class="screen-reader-text"><?php esc_html_e( 'Settings', 'sugar-calendar-lite' ); ?></h1>

				<?php $this->render_header(); ?>
				<?php $this->render_disabled_banner(); ?>
				<?php $this->render_panel(); ?>

				<?php
				// phpcs:ignore WPForms.Comments.PHPDocHooks.RequiredHookDocumentation,WPForms.PHP.ValidateHooks.InvalidHookName
				do_action( 'sugar_calendar_admin_page_after' );
				?>

			</div>
		</div>
		<?php
	}

	/**
	 * Render the page header (title + description).
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	private function render_header() {

		?>
		<div class="sugar-calendar-integrations-header">
			<div class="sugar-calendar-integrations-header__content">
				<h2 class="sugar-calendar-integrations-header__title">
					<?php esc_html_e( 'Configure Integrations', 'sugar-calendar-lite' ); ?>
				</h2>
				<p class="sugar-calendar-integrations-header__description">
					<?php esc_html_e( 'Connect Zoom, calendar, and automation tools to streamline your event workflow.', 'sugar-calendar-lite' ); ?>
				</p>
			</div>
			<?php ( new CreditsSignalRenderer() )->render(); ?>
		</div>
		<?php
	}

	/**
	 * Whether the Lite "Disable Integrations" kill-switch is on.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	private function is_disabled_state() {

		return IntegrationsDisabler::is_disabled();
	}

	/**
	 * Whether an integration is a local (non Product-API) one, exempt from the
	 * disabled state.
	 *
	 * @since 3.12.0
	 *
	 * @param object $integration Integration instance.
	 *
	 * @return bool
	 */
	private function integration_is_local( $integration ) {

		return method_exists( $integration, 'is_local' ) && $integration->is_local();
	}

	/**
	 * Whether an integration is currently inert: the Lite kill-switch is on
	 * and the integration is not a local (exempt) one.
	 *
	 * Single source of truth for the "disabled and not exempt" rule shared by
	 * the save guard, the sidebar item, and the content panel.
	 *
	 * @since 3.12.0
	 *
	 * @param object $integration Integration instance.
	 *
	 * @return bool
	 */
	private function integration_is_inert( $integration ) {

		return $this->is_disabled_state() && ! $this->integration_is_local( $integration );
	}

	/**
	 * Render the Lite "integrations disabled" info banner.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	private function render_disabled_banner() {

		if ( ! $this->is_disabled_state() ) {
			return;
		}

		$misc_url = add_query_arg(
			[
				'page'    => static::get_slug(),
				'section' => 'misc',
			],
			WP::admin_url( 'admin.php' )
		);
		?>
		<div class="sugar-calendar-integrations-disabled-notice">
			<span class="sugar-calendar-integrations-disabled-notice__icon" aria-hidden="true">
				<?php
				// Static plugin info-circle icon (assets/images/icons/info-circle.svg),
				// inlined with currentColor so it inherits the notice's accent.
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->get_info_icon_svg();
				?>
			</span>
			<p class="sugar-calendar-integrations-disabled-notice__text">
				<strong><?php esc_html_e( 'Integrations are disabled', 'sugar-calendar-lite' ); ?></strong><br>
				<?php
				printf(
					wp_kses(
						/* translators: %s - Settings > Misc URL. */
						__( 'Please enable Integrations from <a href="%s">Settings &gt; Misc</a> to connect Sugar Calendar Events with your favorite tools!', 'sugar-calendar-lite' ),
						[ 'a' => [ 'href' => [] ] ]
					),
					esc_url( $misc_url )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Inline info-circle icon for the disabled banner.
	 *
	 * Mirrors assets/images/icons/info-circle.svg but uses currentColor (so the
	 * notice can tint it) and an even-odd fill so the "i" knocks out cleanly.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	private function get_info_icon_svg(): string {

		return '<svg width="16" height="16" viewBox="0 0 40 41" fill="currentColor" xmlns="http://www.w3.org/2000/svg">'
			. '<path fill-rule="evenodd" clip-rule="evenodd" d="M26.5714 32.8214C26.5714 33.2834 26.212 33.6428 25.75 33.6428H14.25C13.7879 33.6428 13.4286 33.2834 13.4286 32.8214V28.7142C13.4286 28.2522 13.7879 27.8928 14.25 27.8928H16.7143V19.6785H14.25C13.7879 19.6785 13.4286 19.3191 13.4286 18.8571V14.7499C13.4286 14.2879 13.7879 13.9285 14.25 13.9285H22.4643C22.9263 13.9285 23.2857 14.2879 23.2857 14.7499V27.8928H25.75C26.212 27.8928 26.5714 28.2522 26.5714 28.7142V32.8214ZM23.2857 9.82136C23.2857 10.2834 22.9263 10.6428 22.4643 10.6428H17.5357C17.0737 10.6428 16.7143 10.2834 16.7143 9.82136V5.71422C16.7143 5.25216 17.0737 4.89279 17.5357 4.89279H22.4643C22.9263 4.89279 23.2857 5.25216 23.2857 5.71422V9.82136ZM39.7143 20.4999C39.7143 9.616 30.8839 0.785645 20 0.785645C9.11606 0.785645 0.285706 9.616 0.285706 20.4999C0.285706 31.3839 9.11606 40.2142 20 40.2142C30.8839 40.2142 39.7143 31.3839 39.7143 20.4999Z"/>'
			. '</svg>';
	}

	/**
	 * Render the two-column panel (sidebar + content).
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	private function render_panel() {

		$active = $this->get_active_integration();

		if ( $active === null ) {
			?>
			<div class="notice notice-info">
				<p><?php esc_html_e( 'No integrations available.', 'sugar-calendar-lite' ); ?></p>
			</div>
			<?php
			return;
		}

		// Cached, fail-open out-of-credits read (transient only, no HTTP). The
		// header credits signal renders first (see display()) and warms this
		// transient via a fresh read, so a second fresh read here would be
		// wasteful — reuse the warmed cache for the sidebar icon + panel badge.
		$out_of_credits = ( new CreditsService() )->is_out_of_credits();
		?>
		<form class="sugar-calendar-integrations-panel" method="post" action="">
			<?php $this->render_sidebar( $active, $out_of_credits ); ?>
			<?php $this->render_content_panel( $active, $out_of_credits ); ?>
			<?php wp_nonce_field( \Sugar_Calendar\Admin\Area::SLUG ); ?>
		</form>
		<?php
	}

	/**
	 * Render the sidebar navigation.
	 *
	 * Real integrations first, then non-clickable placeholders.
	 *
	 * @since 3.12.0
	 *
	 * @param object $active         Active integration instance.
	 * @param bool   $out_of_credits Whether the account is out of credits.
	 *
	 * @return void
	 */
	private function render_sidebar( $active, $out_of_credits = false ) {

		$active_slug = $active->get_slug();
		?>
		<details class="sugar-calendar-integrations-sidebar" open>
			<?php $this->render_sidebar_summary( $active ); ?>

			<div class="sugar-calendar-integrations-sidebar__list">
				<?php foreach ( $this->get_integrations() as $integration ) : ?>
					<?php $this->render_sidebar_item( $integration, $integration->get_slug() === $active_slug, $out_of_credits ); ?>
				<?php endforeach; ?>

				<?php foreach ( $this->get_placeholders() as $placeholder ) : ?>
					<?php $this->render_sidebar_placeholder( $placeholder ); ?>
				<?php endforeach; ?>
			</div>
		</details>
		<?php
	}

	/**
	 * Render the disclosure summary (mobile-only nav header).
	 *
	 * Mirrors the active integration's row (icon, name, status) and adds a
	 * chevron. Hidden on desktop via CSS; on mobile it is the tappable
	 * header that expands the full integration list. The parent `<details>`
	 * is rendered `open` so desktop and no-JS clients see the full list; a
	 * small script collapses it on mobile (see `enqueue_assets()`).
	 *
	 * @since 3.12.0
	 *
	 * @param object $active Active integration instance.
	 *
	 * @return void
	 */
	private function render_sidebar_summary( $active ) {

		$status      = $active->get_status();
		$show_status = $status !== 'not_connected';
		?>
		<summary class="sugar-calendar-integrations-sidebar__summary">

			<?php $this->render_sidebar_icon_and_name( $active->get_icon_url(), $active->get_name() ); ?>

			<?php if ( $show_status ) : ?>
				<span class="sugar-calendar-integrations-sidebar__status <?php echo esc_attr( $this->get_sidebar_status_class( $status ) ); ?>">
					<?php echo $this->get_sidebar_status_icon_svg( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inline SVG markup, no user input. ?>
				</span>
			<?php endif; ?>

			<span class="sugar-calendar-integrations-sidebar__chevron" aria-hidden="true">
				<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</span>
		</summary>
		<?php
	}

	/**
	 * Render a single active-integration sidebar item.
	 *
	 * @since 3.12.0
	 *
	 * @param object $integration    Integration instance.
	 * @param bool   $is_active      Whether this is the active integration.
	 * @param bool   $out_of_credits Whether the account is out of credits.
	 *
	 * @return void
	 */
	private function render_sidebar_item( $integration, $is_active, $out_of_credits = false ) {

		$classes = [ 'sugar-calendar-integrations-sidebar__item' ];

		if ( $is_active ) {
			$classes[] = 'sugar-calendar-integrations-sidebar__item--active';
		}

		$is_disabled = $this->integration_is_inert( $integration );

		if ( $is_disabled ) {
			$classes[] = 'sugar-calendar-integrations-sidebar__item--disabled';
		}

		// Credit-using (non-local) integrations get a red ✕ when out of credits.
		$is_credit_blocked = $out_of_credits && ! $this->integration_is_local( $integration );

		$status      = $integration->get_status();
		$show_status = $status !== 'not_connected';
		?>
		<a href="<?php echo esc_url( self::get_integration_url( $integration->get_slug() ) ); ?>"
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			<?php echo $is_disabled ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>

			<?php $this->render_sidebar_icon_and_name( $integration->get_icon_url(), $integration->get_name() ); ?>

			<?php if ( $is_credit_blocked ) : ?>
				<span class="sugar-calendar-integrations-sidebar__status is-out-of-credits"
					aria-label="<?php esc_attr_e( 'Out of usage', 'sugar-calendar-lite' ); ?>">
					<?php echo $this->get_sidebar_status_icon_svg( 'auth_error' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inline SVG markup, no user input. ?>
				</span>
			<?php elseif ( $show_status ) : ?>
				<span class="sugar-calendar-integrations-sidebar__status <?php echo esc_attr( $this->get_sidebar_status_class( $status ) ); ?>">
					<?php echo $this->get_sidebar_status_icon_svg( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inline SVG markup, no user input. ?>
				</span>
			<?php endif; ?>
		</a>
		<?php
	}

	/**
	 * Render the icon + name markup shared by the sidebar summary, item, and
	 * placeholder renderers.
	 *
	 * @since 3.12.0
	 *
	 * @param string $icon_url Icon URL.
	 * @param string $name     Display name.
	 *
	 * @return void
	 */
	private function render_sidebar_icon_and_name( $icon_url, $name ) {

		?>
		<img
			class="sugar-calendar-integrations-sidebar__icon"
			src="<?php echo esc_url( $icon_url ); ?>"
			alt="<?php echo esc_attr( $name ); ?>"
			width="40"
			height="40"
		/>

		<span class="sugar-calendar-integrations-sidebar__name">
			<?php echo esc_html( $name ); ?>
		</span>
		<?php
	}

	/**
	 * Render a single non-clickable placeholder sidebar item.
	 *
	 * @since 3.12.0
	 *
	 * @param array{slug:string,name:string,icon:string} $placeholder Placeholder data.
	 *
	 * @return void
	 */
	private function render_sidebar_placeholder( $placeholder ) {

		?>
		<div class="sugar-calendar-integrations-sidebar__item sugar-calendar-integrations-sidebar__item--coming-soon"
			data-integration="<?php echo esc_attr( $placeholder['slug'] ); ?>">

			<?php $this->render_sidebar_icon_and_name( $placeholder['icon'], $placeholder['name'] ); ?>

			<span class="sugar-calendar-integrations-sidebar__badge-coming-soon">
				<?php esc_html_e( 'Coming Soon', 'sugar-calendar-lite' ); ?>
			</span>
		</div>
		<?php
	}

	/**
	 * Get the CSS class for the sidebar status indicator.
	 *
	 * @since 3.12.0
	 *
	 * @param string $status Integration status.
	 *
	 * @return string
	 */
	private function get_sidebar_status_class( $status ) {

		return $this->get_status_meta()[ $status ]['sidebar_class'] ?? 'sugar-calendar-integrations-sidebar__status--disconnected';
	}

	/**
	 * Per-status metadata: sidebar dot class, badge color class, badge label.
	 *
	 * Single source of truth for get_sidebar_status_class(), get_status_badge_class(),
	 * and get_status_badge_label() — previously three independent maps over the same
	 * status keys that could silently drift apart. The sidebar dot is intentionally
	 * binary (connected vs not — auth_error/disabled/not_connected all share one
	 * class) while the badge distinguishes all three; that divergence is a deliberate
	 * UX difference, not an inconsistency to remove.
	 *
	 * @since 3.12.0
	 *
	 * @return array<string, array{sidebar_class:string, badge_class:string, badge_label:string}>
	 */
	private function get_status_meta() {

		return [
			'active'        => [
				'sidebar_class' => 'sugar-calendar-integrations-sidebar__status--connected',
				'badge_class'   => 'green',
				'badge_label'   => esc_html__( 'Connected', 'sugar-calendar-lite' ),
			],
			'auth_error'    => [
				'sidebar_class' => 'sugar-calendar-integrations-sidebar__status--disconnected',
				'badge_class'   => 'yellow',
				'badge_label'   => esc_html__( 'Reconnect required', 'sugar-calendar-lite' ),
			],
			'disabled'      => [
				'sidebar_class' => 'sugar-calendar-integrations-sidebar__status--disconnected',
				'badge_class'   => 'red',
				'badge_label'   => esc_html__( 'Connection lost', 'sugar-calendar-lite' ),
			],
			'not_connected' => [
				'sidebar_class' => 'sugar-calendar-integrations-sidebar__status--disconnected',
				'badge_class'   => 'neutral',
				'badge_label'   => esc_html__( 'Not Connected', 'sugar-calendar-lite' ),
			],
		];
	}

	/**
	 * Get the inline SVG icon for a sidebar status.
	 *
	 * @since 3.12.0
	 *
	 * @param string $status Integration status.
	 *
	 * @return string SVG markup.
	 */
	private function get_sidebar_status_icon_svg( $status ) {

		if ( $status === 'active' ) {
			return '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M8 0a8 8 0 100 16A8 8 0 008 0zm3.78 5.97l-4.5 4.5a.75.75 0 01-1.06 0L4.22 8.47a.75.75 0 011.06-1.06L6.75 8.88l3.97-3.97a.75.75 0 011.06 1.06z"/></svg>';
		}

		// Font Awesome 6 solid "times-circle" (circle-xmark) — a centered X in a filled circle.
		return '<svg width="16" height="16" viewBox="0 0 512 512" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c9.4-9.4 24.6-9.4 33.9 0l47 47 47-47c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9l-47 47 47 47c9.4 9.4 9.4 24.6 0 33.9s-24.6 9.4-33.9 0l-47-47-47 47c-9.4 9.4-24.6 9.4-33.9 0s-9.4-24.6 0-33.9l47-47-47-47c-9.4-9.4-9.4-24.6 0-33.9z"/></svg>';
	}

	/**
	 * Render the content panel for the active integration.
	 *
	 * @since 3.12.0
	 *
	 * @param object $integration    Active integration instance.
	 * @param bool   $out_of_credits Whether the account is out of credits.
	 *
	 * @return void
	 */
	private function render_content_panel( $integration, $out_of_credits = false ) {

		$status      = $integration->get_status();
		$badge_class = $this->get_status_badge_class( $status );
		$badge_label = $this->get_status_badge_label( $status );

		$content_classes = [ 'sugar-calendar-integrations-content' ];

		if ( $this->integration_is_inert( $integration ) ) {
			$content_classes[] = 'sugar-calendar-integrations-content--disabled';
		}

		// When out of tokens, the status badge is replaced by a single "Out of
		// Tokens" badge (active credit-using, non-local integrations only).
		$show_out_of_tokens = $out_of_credits && ! $this->integration_is_local( $integration );
		?>
		<div class="<?php echo esc_attr( implode( ' ', $content_classes ) ); ?>">
			<div class="sugar-calendar-integrations-content__title-bar">
				<h2 class="sugar-calendar-integrations-content__title">
					<?php echo esc_html( $integration->get_name() ); ?>
				</h2>
				<?php if ( $show_out_of_tokens ) : ?>
					<span class="sugar-calendar-badge sugar-calendar-badge--out-of-tokens">
						<?php esc_html_e( 'Out of Tokens', 'sugar-calendar-lite' ); ?>
					</span>
				<?php else : ?>
					<span class="sugar-calendar-badge sugar-calendar-badge--<?php echo esc_attr( $badge_class ); ?>">
						<?php echo esc_html( $badge_label ); ?>
					</span>
				<?php endif; ?>
			</div>

			<div class="sugar-calendar-integrations-content__body">
				<?php $integration->render_content(); ?>

				<hr class="sugar-calendar-integrations-content__divider" />

				<div>
					<button type="submit" name="sugar-calendar-submit" class="sugar-calendar-integrations-save-button">
						<?php esc_html_e( 'Save Settings', 'sugar-calendar-lite' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Map an integration status to a badge color class.
	 *
	 * @since 3.12.0
	 *
	 * @param string $status Integration status.
	 *
	 * @return string Color modifier (neutral|green|yellow|red).
	 */
	private function get_status_badge_class( $status ) {

		return $this->get_status_meta()[ $status ]['badge_class'] ?? 'neutral';
	}

	/**
	 * Map an integration status to a human-readable badge label.
	 *
	 * @since 3.12.0
	 *
	 * @param string $status Integration status.
	 *
	 * @return string
	 */
	private function get_status_badge_label( $status ) {

		return $this->get_status_meta()[ $status ]['badge_label'] ?? esc_html__( 'Not Connected', 'sugar-calendar-lite' );
	}

	/**
	 * Enqueue page assets.
	 *
	 * Integrations CSS loads on the whole tab. Active-integration CSS
	 * loads based on the resolved active integration's slug.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		parent::enqueue_assets();

		wp_enqueue_style(
			'sugar-calendar-admin-settings-integrations',
			SC_PLUGIN_ASSETS_URL . 'css/admin-settings-integrations' . WP::asset_min() . '.css',
			[ 'sugar-calendar-admin-settings' ],
			Sugar_Calendar_Helpers::get_asset_version()
		);

		// Shared Integration Usage assets — the header credits indicator/popover
		// (rendered on this tab) and the usage card on the License & Usage tab.
		wp_enqueue_style(
			'sugar-calendar-admin-integration-usage',
			SC_PLUGIN_ASSETS_URL . 'css/admin-integration-usage' . WP::asset_min() . '.css',
			[ 'sugar-calendar-admin-settings' ],
			Sugar_Calendar_Helpers::get_asset_version()
		);

		wp_enqueue_script(
			'sugar-calendar-admin-integration-usage',
			SC_PLUGIN_ASSETS_URL . 'js/admin-integration-usage' . WP::asset_min() . '.js',
			[],
			Sugar_Calendar_Helpers::get_asset_version(),
			true
		);

		wp_enqueue_script(
			'sugar-calendar-admin-settings-integrations',
			SC_PLUGIN_ASSETS_URL . 'js/admin-settings-integrations' . WP::asset_min() . '.js',
			[],
			Sugar_Calendar_Helpers::get_asset_version(),
			true
		);

		$active = $this->get_active_integration();

		// Each integration ships its own admin-settings-{slug}.css.
		if ( $active !== null ) {
			$slug = $active->get_slug();

			wp_enqueue_style(
				'sugar-calendar-admin-settings-' . $slug,
				SC_PLUGIN_ASSETS_URL . 'css/admin-settings-' . $slug . WP::asset_min() . '.css',
				[ 'sugar-calendar-admin-settings' ],
				Sugar_Calendar_Helpers::get_asset_version()
			);

			if ( $slug === 'zoom' ) {
				$this->enqueue_zoom_disconnect_confirm();
			}
		}
	}

	/**
	 * Enqueue the Zoom "Remove" (disconnect) confirmation dialog assets.
	 *
	 * Disconnecting is destructive, so the Remove link is gated behind a styled
	 * jQuery-Confirm dialog before it navigates. Scoped to the Zoom panel only.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	private function enqueue_zoom_disconnect_confirm() {

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
	 * Filter the help URL on the Integrations tab.
	 *
	 * @since 3.12.0
	 *
	 * @param string $help_url Default help URL.
	 *
	 * @return string
	 */
	public function help_url( $help_url ) {

		$active = $this->get_active_integration();

		// Let the active integration own its docs URL; fall back to the generic
		// Integrations docs when it doesn't expose one (AbstractIntegrationPage's
		// default get_help_url() returns null for that case).
		if ( $active !== null && method_exists( $active, 'get_help_url' ) ) {
			$active_help_url = $active->get_help_url();

			if ( $active_help_url ) {
				return $active_help_url;
			}
		}

		return Helpers::get_utm_url(
			'https://sugarcalendar.com/docs/',
			[
				'content' => 'Help',
				'medium'  => 'plugin-settings-integrations',
			]
		);
	}

	/**
	 * Suppress legacy section rendering — display() handles output.
	 *
	 * @since 3.12.0
	 *
	 * @param string $section Settings section.
	 */
	protected function display_tab( $section = '' ) {

		// Intentionally empty.
	}
}
