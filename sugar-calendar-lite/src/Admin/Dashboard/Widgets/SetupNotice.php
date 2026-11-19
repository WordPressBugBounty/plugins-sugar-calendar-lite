<?php

namespace Sugar_Calendar\Admin\Dashboard\Widgets;

use Sugar_Calendar\Admin\Dashboard\AccessContext;
use Sugar_Calendar\Admin\Dashboard\Templates;
use Sugar_Calendar\Admin\Dashboard\WidgetState;
use Sugar_Calendar\SetupChecklist\CompletionDetector;

/**
 * "Finish Setting Up Sugar Calendar", shown while the site has no events.
 *
 * @since 3.18.0
 */
class SetupNotice extends AbstractWidget {

	/**
	 * Column.
	 *
	 * @since 3.18.0
	 */
	const COLUMN = 'main';

	/**
	 * Sort position.
	 *
	 * @since 3.18.0
	 */
	const ORDER = 10;

	/**
	 * Widget identifier.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'setup_notice';
	}

	/**
	 * Widget title; the notice carries its own heading.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_title(): string {

		return '';
	}

	/**
	 * Visible only while the site has no events.
	 *
	 * @since 3.18.0
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 */
	public function get_state( AccessContext $access ): WidgetState {

		return new WidgetState( ! $this->has_events(), 'data' );
	}

	/**
	 * Render the notice.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Range data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 */
	protected function render_body( string $variant, array $data, AccessContext $access ): string {

		return Templates::get_html(
			'widgets/setup-notice',
			[
				'wizard_url' => sugar_calendar()->get_setup_wizard()->get_start_url(),
			]
		);
	}

	/**
	 * Whether the site has any event, by the Setup Checklist's `create-event` rule.
	 *
	 * Asked before the page's numbers reach the widgets, so it reads the rule itself.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	protected function has_events(): bool {

		return CompletionDetector::has_saved_event();
	}
}
