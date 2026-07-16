<?php

namespace Sugar_Calendar\Integrations\OAuthRelay\Credits;

use Sugar_Calendar\Helpers\Helpers;

/**
 * Integration credit-usage alert email.
 *
 * Data-driven and model-agnostic: the dedup is keyed on the relay's
 * `resets_at` ("period token"). With no reset date it alerts once
 * (lifetime/additive); with a reset date it re-alerts each cycle. Dropping
 * below the threshold clears the marker so a genuine refill re-alerts. A
 * future Pro subclass (resolved via the `sugar_calendar_credit_alert_email`
 * filter) may override copy only — no behavioral split is needed.
 *
 * @since 3.12.0
 */
class CreditAlertEmail {

	/**
	 * Option storing the period token of the last alert sent.
	 *
	 * @since 3.12.0
	 *
	 * @var string
	 */
	const OPTION_KEY = 'sugar_calendar_integration_credit_alert_sent';

	/**
	 * Usage percentage that triggers the alert.
	 *
	 * @since 3.12.0
	 *
	 * @var int
	 */
	const THRESHOLD = 80;

	/**
	 * Send the alert when usage crosses the threshold, once per period.
	 *
	 * @since 3.12.0
	 *
	 * @param array $credits Formatted credits data from CreditsService.
	 *
	 * @return void
	 */
	public function maybe_send( array $credits ): void {

		$percentage = (int) ( $credits['percentage'] ?? 0 );
		$token      = $this->period_token( $credits );

		// Below threshold: clear the marker so the next genuine climb (after a
		// monthly reset or an additive top-up) re-alerts. Model-agnostic.
		if ( $percentage < self::THRESHOLD ) {
			delete_option( self::OPTION_KEY );

			return;
		}

		// Already alerted for this period.
		if ( get_option( self::OPTION_KEY ) === $token ) {
			return;
		}

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		$from_name    = (string) apply_filters( 'sugar_calendar_credit_alert_email_from_name', '' );
		$from_address = (string) apply_filters( 'sugar_calendar_credit_alert_email_from_address', '' );

		if ( $from_name !== '' && $from_address !== '' ) {
			$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_address );
		}

		$sent = wp_mail(
			get_option( 'admin_email' ),
			$this->get_subject( $credits ),
			$this->get_body( $credits ),
			$headers
		);

		if ( $sent ) {
			update_option( self::OPTION_KEY, $token, false );
		}
	}

	/**
	 * Period token: the reset date when present, else the lifetime sentinel.
	 *
	 * @since 3.12.0
	 *
	 * @param array $credits Formatted credits data.
	 *
	 * @return string
	 */
	protected function period_token( array $credits ): string {

		return ! empty( $credits['resets_at'] ) ? (string) $credits['resets_at'] : 'lifetime';
	}

	/**
	 * Email subject.
	 *
	 * @since 3.12.0
	 *
	 * @param array $credits Formatted credits data.
	 *
	 * @return string
	 */
	protected function get_subject( array $credits ): string {

		return sprintf(
			/* translators: %1$d - percentage of integration credits used. */
			esc_html__( 'Heads up: %1$d%% of your Sugar Calendar integration credits are used', 'sugar-calendar-lite' ),
			(int) ( $credits['percentage'] ?? 0 )
		);
	}

	/**
	 * Build the HTML email body.
	 *
	 * @since 3.12.0
	 *
	 * @param array $credits Formatted credits data.
	 *
	 * @return string
	 */
	protected function get_body( array $credits ): string {

		$name      = esc_html( $this->get_admin_display_name() );
		$pct       = (int) ( $credits['percentage'] ?? 0 );
		$used      = esc_html( number_format_i18n( (int) ( $credits['used'] ?? 0 ) ) );
		$total     = esc_html( number_format_i18n( (int) ( $credits['total'] ?? 0 ) ) );
		$remaining = esc_html( number_format_i18n( (int) ( $credits['remaining'] ?? 0 ) ) );
		$cta_url   = esc_url( $this->get_cta_url() );

		$lines   = [];
		$lines[] = '<p>' . sprintf(
			/* translators: %1$s - admin display name. */
			esc_html__( 'Hi %1$s,', 'sugar-calendar-lite' ),
			$name
		) . '</p>';
		$lines[] = '<p>' . sprintf(
			/* translators: %1$d - percent used; %2$s - credits used; %3$s - total credits; %4$s - credits remaining. */
			esc_html__( 'You\'ve used %1$d%% of your Sugar Calendar integration credits (%2$s of %3$s). %4$s remaining.', 'sugar-calendar-lite' ),
			$pct,
			$used,
			$total,
			$remaining
		) . '</p>';

		if ( ! empty( $credits['reset_date'] ) ) {
			$lines[] = '<p>' . sprintf(
				/* translators: %1$s - date the credits reset. */
				esc_html__( 'Your credits reset on %1$s.', 'sugar-calendar-lite' ),
				esc_html( (string) $credits['reset_date'] )
			) . '</p>';
		}

		$lines[] = '<p>' . esc_html__( 'When they run out, new Zoom meetings won\'t be created for your events until you add more credits.', 'sugar-calendar-lite' ) . '</p>';
		$lines[] = '<p><a href="' . $cta_url . '">' . esc_html__( 'Get more credits', 'sugar-calendar-lite' ) . '</a></p>';

		return implode( "\n", $lines );
	}

	/**
	 * Greeting name — the admin user's display name, or a generic fallback.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_admin_display_name(): string {

		$user = get_user_by( 'email', get_option( 'admin_email' ) );

		if ( $user && ! empty( $user->display_name ) ) {
			return $user->display_name;
		}

		return __( 'there', 'sugar-calendar-lite' );
	}

	/**
	 * The "get more credits" call-to-action URL.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_cta_url(): string {

		return Helpers::get_utm_url(
			'https://sugarcalendar.com/account/',
			[
				'source'  => 'WordPress',
				'medium'  => 'credit-alert-email',
				'content' => 'integration-credits',
			]
		);
	}
}
