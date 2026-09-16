<?php

namespace Sugar_Calendar\Features\FeaturedEvents\Admin;

use Sugar_Calendar\Features\FeaturedEvents\Admin\Pages\Event;
use Sugar_Calendar\Features\FeaturedEvents\Admin\Pages\Events;

/**
 * Admin area for the Featured Events feature.
 *
 * @since 3.14.0
 */
class Area {

	/**
	 * Event page instance.
	 *
	 * @since 3.14.0
	 *
	 * @var Event
	 */
	public $event_page;

	/**
	 * Events page instance.
	 *
	 * @since 3.14.0
	 *
	 * @var Events
	 */
	public $events_page;

	/**
	 * Init.
	 *
	 * @since 3.14.0
	 */
	public function init() {

		$this->event_page  = new Event();
		$this->events_page = new Events();
	}

	/**
	 * Hooks.
	 *
	 * @since 3.14.0
	 */
	public function hooks() {

		// The "Featured Event" toggle on the event editor's General tab.
		add_action( 'sugar_calendar_admin_meta_box_duration_section', [ $this->event_page, 'display_toggle' ] );

		// Persist the toggle.
		add_filter( 'sugar_calendar_event_to_save', [ $this->event_page, 'save' ] );

		// The star before the title in the list table and on the month, week and day chips.
		add_filter( 'sugar_calendar_admin_events_tables_base_event_link_before_title', [ $this->events_page, 'add_star_to_event_link' ], 10, 3 );

		// The star before the title in the event pop-out header.
		add_filter( 'sugar_calendar_admin_events_tables_base_pointer_before_title', [ $this->events_page, 'add_star_to_pointer' ], 10, 2 );

		// Warm the event meta cache so core's per-row location and color reads don't each query.
		add_filter( 'sugar_calendar_admin_events_tables_base_all_items', [ $this->events_page, 'prime_meta_cache' ], 20 );

		// The "Featured" status link, and the filtering behind it.
		add_filter( 'sugar_calendar_admin_events_tables_base_views', [ $this->events_page, 'add_status_link' ], 10, 3 );
		add_filter( 'sugar_calendar_admin_events_tables_base_items_filter', [ $this->events_page, 'clear_items_filter' ], 10, 2 );

		// Priority 20: after Pro's recurring feature has merged its occurrence rows in.
		add_filter( 'sugar_calendar_admin_events_tables_base_filtered_ids', [ $this->events_page, 'filter_row_ids' ], 20 );
		add_filter( 'sugar_calendar_admin_events_tables_basic_items_display_before', [ $this->events_page, 'filter_list_items' ], 20 );
	}
}
