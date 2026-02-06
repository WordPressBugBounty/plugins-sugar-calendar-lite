<?php
namespace Sugar_Calendar\Integrations\Elementor\Documents;

use Elementor\Controls_Manager;
use ElementorPro\Modules\ThemeBuilder\Documents\Single_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Event document.
 *
 * @since 3.10.0
 */
class Event extends Single_Base {

	/**
	 * Get properties.
	 *
	 * @since 3.10.0
	 *
	 * @return array
	 */
	public static function get_properties() {

		$properties = parent::get_properties();

		$properties['location']       = 'single';
		$properties['condition_type'] = sugar_calendar_get_event_post_type_id();

		return $properties;
	}

	/**
	 * Get type.
	 *
	 * @since 3.10.0
	 *
	 * @return string
	 */
	public static function get_type() {

		return sugar_calendar_get_event_post_type_id();
	}

	/**
	 * Get title.
	 *
	 * @since 3.10.0
	 *
	 * @return string
	 */
	public static function get_title() {

		return esc_html__( 'Single Event', 'sugar-calendar' );
	}

	/**
	 * Get plural title.
	 *
	 * @since 3.10.0
	 *
	 * @return string
	 */
	public static function get_plural_title() {

		return esc_html__( 'Single Events', 'sugar-calendar' );
	}

	/**
	 * Show the SCE single widgets at the top of the editor panel.
	 *
	 * @since 3.10.0
	 *
	 * @return array
	 */
	protected static function get_editor_panel_categories() {

		$categories = [
			'sugar-calendar-event-elements-single' => [
				'title'  => esc_html__( 'Sugar Calendar Event', 'sugar-calendar-lite' ),
			],
		];

		$categories += parent::get_editor_panel_categories();

		unset( $categories['theme-elements-single'] );

		return $categories;
	}

	/**
	 * Register the latest event ID so we can use it for preview of the content.
	 *
	 * @since 3.10.0
	 */
	protected function register_controls() {

		parent::register_controls();

		$this->update_control(
			'preview_type',
			[
				'type'    => Controls_Manager::HIDDEN,
				'default' => 'single/' . sugar_calendar_get_event_post_type_id(),
			]
		);

		// Get the latest posted event not the latest event.
		$latest_event = get_posts(
			[
				'posts_per_page' => 1,
				'post_type'      => sugar_calendar_get_event_post_type_id(),
			]
		);

		if ( empty( $latest_event ) ) {
			return;
		}

		$latest_event_id = $latest_event[0]->ID;

		$this->update_control(
			'preview_id',
			[
				'default' => $latest_event_id,
			]
		);

		$this->start_controls_section(
			'section_sugar_calendar_event_preview',
			[
				'label' => esc_html__( 'Event Preview', 'sugar-calendar-lite' ),
			]
		);

		$this->add_control(
			'sugar_calendar_event_preview_id',
			[
				'type'    => Controls_Manager::HIDDEN,
				'default' => $latest_event_id,
			]
		);

		$this->end_controls_section();
	}
}
