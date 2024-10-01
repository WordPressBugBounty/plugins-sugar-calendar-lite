<?php

namespace Sugar_Calendar\Block\EventList\EventListView;

use Sugar_Calendar\Block\Common\AbstractBlock;
use Sugar_Calendar\Block\Common\Template;
use Sugar_Calendar\Options;

class Block extends AbstractBlock {

	/**
	 * The Block key.
	 *
	 * @since 3.1.0
	 *
	 * @var string
	 */
	const KEY = 'event_list';

	/**
	 * Array containing the events.
	 *
	 * @since 3.1.0
	 *
	 * @var \Sugar_Calendar\Event[]
	 */
	private $events = null;

	/**
	 * Contains the event IDs that were already displayed.
	 *
	 * @since 3.1.0
	 *
	 * @var string[]
	 */
	private $displayed_events = [];

	/**
	 * Constructor.
	 *
	 * @since 3.1.0
	 *
	 * @param array $attributes Block attributes.
	 */
	public function __construct( $attributes ) {

		parent::__construct( $attributes );
	}

	/**
	 * Return the block HTML.
	 *
	 * @since 3.0.0
	 *
	 * @return false|string
	 */
	public function get_html() {

		ob_start();

		Template::load( 'base', $this, self::KEY );

		return ob_get_clean();
	}

	/**
	 * Get the display options.
	 *
	 * @since 3.1.0
	 *
	 * @return array
	 */
	public function get_display_options() {

		return [
			'list'  => esc_html__( 'List', 'sugar-calendar' ),
			'grid'  => esc_html__( 'Grid', 'sugar-calendar' ),
			'plain' => esc_html__( 'Plain', 'sugar-calendar' ),
		];
	}

	/**
	 * Get the classes for the block.
	 *
	 * @since 3.1.0
	 *
	 * @return array
	 */
	public function get_classes() {

		return [
			'sugar-calendar-event-list-block',
			sprintf(
				'sugar-calendar-event-list-block__%s-view',
				$this->get_display_mode()
			),
		];
	}

	/**
	 * Get the data for the list view.
	 *
	 * @since 3.1.0
	 *
	 * @return \Sugar_Calendar\Event[]
	 *
	 * @throws Exception When the date for the calendar was not created.
	 */
	public function get_events() {

		if ( $this->should_not_load_events() ) {
			$this->events = [];

			return [];
		}

		if ( ! is_null( $this->events ) ) {
			return $this->events;
		}

		$this->events = $this->get_week_events();

		return $this->events;
	}

	/**
	 * Get the displayed events.
	 *
	 * @since 3.1.0
	 *
	 * @return string[]
	 */
	public function get_displayed_events() {

		return $this->displayed_events;
	}

	/**
	 * Add a displayed event.
	 *
	 * @since 3.1.0
	 *
	 * @param string $event_id Event ID.
	 *
	 * @return void
	 */
	public function add_displayed_event( $event_id ) {

		$this->displayed_events[] = $event_id;
	}

	/**
	 * Get the current pagination text.
	 *
	 * @since 3.1.0
	 *
	 * @return string
	 */
	public function get_current_pagination_display() {

		return __( 'This Week', 'sugar-calendar' );
	}

	/**
	 * Get the block styles.
	 *
	 * @since 3.1.0
	 *
	 * @return string
	 */
	public function get_styles() {

		$styles = [
			'--accent-color' => $this->get_default_accent_color(),
			'--links-color'  => $this->attributes['linksColor'],
		];

		$output = '';

		foreach ( $styles as $key => $val ) {
			$output .= sprintf( '%1$s: %2$s;', $key, $val );
		}

		return $output;
	}

	/**
	 * Get the settings/attributes for the block.
	 *
	 * @since 3.1.0
	 *
	 * @return array
	 */
	public function get_settings_attributes() {

		return empty( $this->get_attributes()['attributes'] ) ? $this->get_attributes() : $this->get_attributes()['attributes'];
	}

	/**
	 * Get the no events message.
	 *
	 * @since 3.1.0
	 *
	 * @return string
	 */
	public function get_no_events_msg() {

		if ( ! empty( $this->get_search_term() ) || ! empty( $this->get_calendars() ) ) {
			return __( 'There are no events scheduled that match your criteria.', 'sugar-calendar' );
		}

		return __( 'There are no events scheduled this week.', 'sugar-calendar' );
	}
}
