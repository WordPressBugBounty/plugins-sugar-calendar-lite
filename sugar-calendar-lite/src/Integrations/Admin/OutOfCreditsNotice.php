<?php

namespace Sugar_Calendar\Integrations\Admin;

use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Options;
use Sugar_Calendar\Integrations\OAuthRelay\Credits\CreditsPresenter;
use Sugar_Calendar\Integrations\OAuthRelay\Credits\CreditsService;

/**
 * Global dismissible notice shown on every admin page when integration
 * credits are exhausted.
 *
 * The gate reads the CACHED (transient-only, fail-open) credits state, so the
 * notice never makes an HTTP call on a page render — it surfaces only once the
 * Settings panel (License & Usage / Integrations) has warmed the tier-scoped
 * transient with a maxed reading.
 *
 * Dismissal is per-user, keyed on the credit period token (`resets_at`, or
 * `'lifetime'` for flat/Lite). A new period yields a different token, so a
 * fresh month re-surfaces the notice automatically.
 *
 * @since 3.12.0
 */
class OutOfCreditsNotice {

	use PrintsDismissScript;

	/**
	 * Nonce action for the dismiss AJAX request.
	 *
	 * @since 3.12.0
	 *
	 * @var string
	 */
	const DISMISS_NONCE = 'sc_dismiss_out_of_credits_notice';

	/**
	 * User-meta key holding the dismissed period token.
	 *
	 * @since 3.12.0
	 *
	 * @var string
	 */
	const DISMISSED_META = 'sc_out_of_credits_dismissed_period';

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function hooks() {

		add_action( 'admin_notices', [ $this, 'maybe_render' ] );
		add_action( 'wp_ajax_' . self::DISMISS_NONCE, [ $this, 'ajax_dismiss' ] );
	}

	/**
	 * Render the notice on every admin page when out of credits and the
	 * current user has not dismissed it for the current period.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function maybe_render() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// The "Disable Integrations" kill-switch tears down every integration, so
		// the out-of-credits notice is moot — never show it while it's on.
		if ( Options::get( 'disable_integrations', false ) ) {
			return;
		}

		$service = new CreditsService();

		// Cached, fail-open read — no HTTP on every admin page.
		if ( ! $service->is_out_of_credits() ) {
			return;
		}

		$token = ( new CreditsPresenter( $service->get_cached_credits() ) )->period_token();

		if ( (string) get_user_meta( get_current_user_id(), self::DISMISSED_META, true ) === $token ) {
			return;
		}

		$settings_url = add_query_arg(
			[
				'page'    => 'sugarcalendar-settings',
				'section' => 'license-usage',
			],
			WP::admin_url( 'admin.php' )
		);

		$dismiss_url = add_query_arg(
			[
				'action' => self::DISMISS_NONCE,
				'period' => rawurlencode( $token ),
				'nonce'  => wp_create_nonce( self::DISMISS_NONCE ),
			],
			admin_url( 'admin-ajax.php' )
		);

		printf(
			'<div class="notice notice-warning is-dismissible sugar-calendar-out-of-credits-notice" data-sc-dismiss-url="%1$s"><p><strong>%2$s</strong> %3$s <a href="%4$s">%5$s</a></p></div>',
			esc_url( $dismiss_url ),
			esc_html__( "You've hit your usage limit!", 'sugar-calendar-lite' ),
			esc_html__( 'Integrations will be disabled until your usage limit is restored.', 'sugar-calendar-lite' ),
			esc_url( $settings_url ),
			esc_html__( 'View usage details', 'sugar-calendar-lite' )
		);

		$this->print_dismiss_script( '.sugar-calendar-out-of-credits-notice' );
	}

	/**
	 * Persist the dismissal for the current period when the admin dismisses
	 * the notice.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function ajax_dismiss() {

		if ( ! check_ajax_referer( self::DISMISS_NONCE, 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'bad_nonce' ], 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}

		// Use the period token the notice was rendered with, passed through the
		// dismiss URL. Recomputing here would read get_cached_credits() afresh; if
		// the transient expired between render and click it returns null and the
		// presenter falls back to 'lifetime' — storing the wrong token so the
		// dismissal would not stick.
		$period = isset( $_REQUEST['period'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['period'] ) ) : '';

		if ( $period === '' ) {
			// Fallback: recompute from cache (may be 'lifetime' if expired).
			$period = ( new CreditsPresenter( ( new CreditsService() )->get_cached_credits() ) )->period_token();
		}

		update_user_meta( get_current_user_id(), self::DISMISSED_META, $period );

		wp_send_json_success();
	}
}
