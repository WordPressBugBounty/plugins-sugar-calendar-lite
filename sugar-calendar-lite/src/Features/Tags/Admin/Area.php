<?php

namespace Sugar_Calendar\Features\Tags\Admin;

use Sugar_Calendar\Admin\Area as SugarCalendarArea;
use Sugar_Calendar\Helpers\WP;

use Sugar_Calendar\Features\Tags\Admin\Pages\Tags;
use Sugar_Calendar\Features\Tags\Admin\Pages\TagNew;
use Sugar_Calendar\Features\Tags\Admin\Pages\TagEdit;
use Sugar_Calendar\Features\Tags\Admin\Pages\Event;
use Sugar_Calendar\Features\Tags\Admin\Pages\Events;
use Sugar_Calendar\Features\Tags\Common\Helpers;

/**
 * Admin area for the Tags feature.
 *
 * @since 3.7.0
 */
class Area {

	/**
	 * Event page instance.
	 *
	 * @since 3.7.0
	 *
	 * @var Event
	 */
	public $event_page;

	/**
	 * Events page instance.
	 *
	 * @since 3.7.0
	 *
	 * @var Events
	 */
	public $events_page;

	/**
	 * Init.
	 *
	 * @since 3.7.0
	 */
	public function init() {

		$this->event_page  = new Event();
		$this->events_page = new Events();
	}

	/**
	 * Hooks.
	 *
	 * @since 3.7.0
	 */
	public function hooks() {

		// Register admin menu.
		add_action( 'sugar_calendar_admin_area_add_submenu_page', [ $this, 'admin_area_add_submenu_page' ], 10, 1 );

		// Set the current page ID.
		add_filter( 'sugar_calendar_admin_area_current_page_id', [ $this, 'admin_area_current_page_id' ], 10, 1 );

		// Register admin pages.
		add_filter( 'sugar_calendar_admin_area_pages', [ $this, 'admin_area_pages' ] );

		// Add custom metabox for Tags.
		add_action( 'add_meta_boxes', [ $this->event_page, 'add_tags_meta_box' ], 10, 2 );

		// Save tags meta box.
		add_action( 'save_post', [ $this->event_page, 'save_tags_meta_box' ] );

		add_filter( 'sugar_calendar_admin_events_tables_base_dropdown_taxonomies', [ $this->events_page, 'modify_dropdown_taxonomies' ], 10, 1 );

		// Add "Manage Tags" button to the events table.
		add_action( 'sugar_calendar_admin_events_tables_base_after_extra_tablenav_top', [ $this->events_page, 'add_tags_tablenav' ], 10, 1 );

		// Add bulk edit UI.
		add_action( 'sugar_calendar_admin_events_tables_basic_before_rows', [ $this->events_page, 'add_bulk_edit_tags_form' ], 10, 1 );

		// Add tags column html.
		add_action( 'sugar_calendar_admin_events_tables_base_tags_contents', [ $this->events_page, 'add_tags_column_html' ], 10, 2 );

		// Filter for localize script.
		add_filter( 'sugar_calendar_admin_pages_events_localize_script', [ $this->events_page, 'localize_script' ], 10, 1 );
		add_filter( 'sugar_calendar_admin_events_metaboxes_event_localize_script', [ $this->events_page, 'localize_script' ], 10, 1 );

		// Register AJAX handler for Tags live editing.
		add_action( 'wp_ajax_sugar_calendar_save_event_tags', [ $this->events_page, 'ajax_save_tags' ] );

		// Add tags information to event tooltips.
		add_filter( 'sugar_calendar_admin_events_tables_base_get_pointer_meta', [ $this->events_page, 'add_tags_in_admin_tooltips' ], 10, 2 );
	}

	/**
	 * Register admin menu.
	 *
	 * @since 3.7.0
	 *
	 * @param Area $area Admin area instance.
	 */
	public function admin_area_add_submenu_page( $area ) {

		// Tags add new.
		add_submenu_page(
			SugarCalendarArea::SLUG,
			TagNew::get_title(),
			TagNew::get_title(),
			TagNew::get_capability(),
			TagNew::get_slug(),
			[ $area, 'display' ],
			TagNew::get_priority()
		);

		// Tags edit.
		add_submenu_page(
			SugarCalendarArea::SLUG,
			TagEdit::get_title(),
			TagEdit::get_title(),
			TagEdit::get_capability(),
			TagEdit::get_slug(),
			[ $area, 'display' ],
			TagEdit::get_priority()
		);
	}

	/**
	 * Set the current page ID.
	 *
	 * @since 3.7.0
	 *
	 * @param string|null $page_id Current page id.
	 */
	public function admin_area_current_page_id( $page_id ) {

		global $pagenow, $taxnow;

		// Bail if we're doing an AJAX request.
		if ( WP::is_doing_ajax() ) {
			return $page_id;
		}

		// Set the current page ID if we're on the Tags page.
		if (
			$pagenow === 'edit-tags.php'
			&&
			$taxnow === Helpers::get_tags_taxonomy_id()
		) {
			// Tags page.
			return 'tags';
		}

		if ( isset( $_GET['page'] ) ) {

			$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );

			// Tags pages.
			switch ( $page ) {
				// Create new tag page.
				case TagNew::get_slug():
					$page_id = 'tag_new';
					break;

				// Edit tag page.
				case TagEdit::get_slug():
					$page_id = 'tag_edit';
					break;
			}
		}

		return $page_id;
	}

	/**
	 * Register admin pages.
	 *
	 * @since 3.7.0
	 *
	 * @param array $pages Registered admin pages.
	 *
	 * @return array
	 */
	public function admin_area_pages( $pages ) {

		// Add Tags pages to the admin.
		$pages['tags']     = Tags::class;
		$pages['tag_new']  = TagNew::class;
		$pages['tag_edit'] = TagEdit::class;

		return $pages;
	}
}
