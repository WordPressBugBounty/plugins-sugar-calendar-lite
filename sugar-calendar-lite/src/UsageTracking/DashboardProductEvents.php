<?php

namespace Sugar_Calendar\UsageTracking;

/**
 * Product Events for the admin Dashboard.
 *
 * @since 3.18.0
 */
class DashboardProductEvents extends AbstractProductEvents {

	/**
	 * Site option holding the GMT date of the last reported view.
	 *
	 * @since 3.18.0
	 */
	const VIEWED_OPTION = 'sugar_calendar_dashboard_viewed_on';

	/**
	 * Register hooks.
	 *
	 * @since 3.18.0
	 */
	protected function hooks() {

		add_action( 'sugar_calendar_admin_pages_dashboard_viewed', [ $this, 'track_viewed' ] );
		add_action( 'sugar_calendar_admin_dashboard_ajax_action', [ $this, 'track_action' ], 10, 2 );
	}

	/**
	 * The Dashboard was opened; report it once per day.
	 *
	 * @since 3.18.0
	 */
	public function track_viewed() {

		if ( ! $this->is_allowed() ) {
			return;
		}

		$today = gmdate( 'Y-m-d' );

		if ( get_option( self::VIEWED_OPTION ) === $today ) {
			return;
		}

		update_option( self::VIEWED_OPTION, $today, false );

		$this->track_event( 'dashboard_viewed', [] );
	}

	/**
	 * A Dashboard action succeeded.
	 *
	 * @since 3.18.0
	 *
	 * @param string $action The action: `growth_tool`, `addon` or `card_dismissed`.
	 * @param string $item   The tool key, add-on slug or card id.
	 */
	public function track_action( $action, $item ) {

		$this->track_event(
			'dashboard_action',
			[
				'action' => sanitize_key( $action ),
				'item'   => sanitize_key( $item ),
			]
		);
	}
}
