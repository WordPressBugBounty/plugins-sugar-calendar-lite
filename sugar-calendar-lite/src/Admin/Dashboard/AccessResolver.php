<?php

namespace Sugar_Calendar\Admin\Dashboard;

use Sugar_Calendar\Admin\Pages\Dashboard as DashboardPage;

/**
 * Builds the Dashboard access context once per request.
 *
 * @since 3.18.0
 */
class AccessResolver {

	/**
	 * Cached context.
	 *
	 * @since 3.18.0
	 *
	 * @var AccessContext|null
	 */
	private $context;

	/**
	 * Get the access context, building it on first call.
	 *
	 * @since 3.18.0
	 *
	 * @return AccessContext
	 */
	public function get_context(): AccessContext {

		if ( $this->context === null ) {
			$this->context = new AccessContext( $this->get_data() );
		}

		return $this->context;
	}

	/**
	 * Raw context data.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	protected function get_data(): array {

		$user_id     = get_current_user_id();
		$preferences = new UserPreferences( $user_id );

		return [
			'is_pro'          => sugar_calendar()->is_pro(),
			'can_manage'      => current_user_can( DashboardPage::get_capability() ),
			'user_id'         => $user_id,
			'dismissals'      => $preferences->get_dismissals(),
			'widget_settings' => $preferences->get_widget_settings(),
		];
	}
}
