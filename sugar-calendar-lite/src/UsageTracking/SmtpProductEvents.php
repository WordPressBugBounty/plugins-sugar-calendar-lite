<?php

namespace Sugar_Calendar\UsageTracking;

/**
 * Tracks WP Mail SMTP promo-page install clicks.
 *
 * Runs in both editions: Lite is gated by the Settings → Misc
 * "disable integrations" toggle via AbstractProductEvents::is_enabled();
 * Pro forces tracking on.
 *
 * @since 3.12.0
 */
class SmtpProductEvents extends AbstractProductEvents {

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 */
	protected function hooks() {

		add_action( 'sugar_calendar_admin_pages_smtp_install_clicked', [ $this, 'track_install_clicked' ] );
	}

	/**
	 * Track a click on the promo page's install/activate button.
	 *
	 * @since 3.12.0
	 *
	 * @param string $type Button variant: 'install' or 'activate'.
	 */
	public function track_install_clicked( $type ) {

		if ( ! $this->is_allowed() ) {
			return;
		}

		if ( ! in_array( $type, [ 'install', 'activate' ], true ) ) {
			return;
		}

		$this->track_event(
			'smtp_install_clicked',
			[
				'type' => $type,
			]
		);
	}
}
