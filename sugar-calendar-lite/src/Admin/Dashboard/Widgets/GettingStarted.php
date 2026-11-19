<?php

namespace Sugar_Calendar\Admin\Dashboard\Widgets;

use Sugar_Calendar\Admin\Dashboard\AccessContext;
use Sugar_Calendar\Admin\Dashboard\Templates;
use Sugar_Calendar\Admin\Dashboard\WidgetState;
use Sugar_Calendar\Helpers\Helpers;

/**
 * Popular documentation links.
 *
 * @since 3.18.0
 */
class GettingStarted extends AbstractWidget {

	/**
	 * Column.
	 *
	 * @since 3.18.0
	 */
	const COLUMN = 'sidebar';

	/**
	 * Sort position.
	 *
	 * @since 3.18.0
	 */
	const ORDER = 40;

	/**
	 * Widget identifier.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'getting_started';
	}

	/**
	 * Widget title.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_title(): string {

		return __( 'Getting Started', 'sugar-calendar-lite' );
	}

	/**
	 * Always visible.
	 *
	 * @since 3.18.0
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 */
	public function get_state( AccessContext $access ): WidgetState {

		return new WidgetState( true, 'data' );
	}

	/**
	 * Render the links.
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
			'widgets/getting-started',
			[
				'links'    => $this->get_links(),
				'view_all' => [
					'label' => __( 'View All Documentation', 'sugar-calendar-lite' ),
					'url'   => $this->utm( 'https://sugarcalendar.com/docs/', 'View All Documentation' ),
				],
			]
		);
	}

	/**
	 * The five guides, each with its own UTM content.
	 *
	 * @since 3.18.0
	 *
	 * @return array Links: `label`, `url`.
	 */
	private function get_links(): array {

		$links = [
			[ __( 'Creating Your First Event (Complete Guide)', 'sugar-calendar-lite' ), 'https://sugarcalendar.com/docs/events/creating-events/', 'Creating Your First Event' ],
			[ __( 'Setting Up Event Ticketing', 'sugar-calendar-lite' ), 'https://sugarcalendar.com/docs/events/setting-up-event-ticketing-with-sugar-calendar-lite/', 'Setting Up Event Ticketing' ],
			[ __( 'Displaying Your Calendar on Your Site', 'sugar-calendar-lite' ), 'https://sugarcalendar.com/docs/events/displaying-the-calendar/', 'Displaying Your Calendar' ],
			[ __( 'Collecting RSVPs for Events', 'sugar-calendar-lite' ), 'https://sugarcalendar.com/docs/events/rsvp-addon/', 'Collecting RSVPs' ],
			[ __( 'Importing Events from Other Plugins', 'sugar-calendar-lite' ), 'https://sugarcalendar.com/docs/events/importing-and-exporting-events/#import-events', 'Importing Events' ],
		];

		return array_map(
			function ( $link ) {
				return [
					'label' => $link[0],
					'url'   => $this->utm( $link[1], $link[2] ),
				];
			},
			$links
		);
	}

	/**
	 * A docs URL with the Dashboard's UTM tags.
	 *
	 * @since 3.18.0
	 *
	 * @param string $url     Docs URL.
	 * @param string $content UTM content.
	 *
	 * @return string
	 */
	private function utm( string $url, string $content ): string {

		return Helpers::get_utm_url(
			$url,
			[
				'medium'  => self::UTM_MEDIUM,
				'content' => 'Getting Started - ' . $content,
			]
		);
	}
}
