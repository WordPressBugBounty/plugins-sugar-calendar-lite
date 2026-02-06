<?php
namespace Sugar_Calendar\Integrations\Elementor\Documents;

use Elementor\Core\DocumentTypes\Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Event Post document.
 *
 * @since 3.10.0
 */
class Event_Post extends Post {

	/**
	 * Get properties.
	 *
	 * @since 3.10.0
	 *
	 * @return array
	 */
	public static function get_properties() {

		$properties        = parent::get_properties();
		$properties['cpt'] = [
			sugar_calendar_get_event_post_type_id(),
		];

		return $properties;
	}

	/**
	 * Get name.
	 *
	 * @since 3.10.0
	 *
	 * @return string
	 */
	public function get_name() {

		return 'sugar-calendar-event-post';
	}

	/**
	 * Get title.
	 *
	 * @since 3.10.0
	 *
	 * @return string
	 */
	public static function get_title() {

		return esc_html__( 'Event Post', 'sugar-calendar' );
	}

	/**
	 * Get plural title.
	 *
	 * @since 3.10.0
	 *
	 * @return string
	 */
	public static function get_plural_title() {

		return esc_html__( 'Event Posts', 'sugar-calendar' );
	}
}
