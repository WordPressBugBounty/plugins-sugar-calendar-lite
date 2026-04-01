<?php

namespace Sugar_Calendar\Integrations\WPBakery;

/**
 * WPBakery integration.
 *
 * @since 3.11.0
 */
class WPBakery {

	/**
	 * Initialize the WPBakery integration.
	 *
	 * @since 3.11.0
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.11.0
	 */
	private function hooks() {

		// Check if WPBakery is active.
		if ( ! defined( 'WPB_VC_VERSION' ) ) {
			return;
		}

		// Register WPBakery-specific hooks here.
		add_filter( 'sc_event_supports', [ $this, 'add_editor_support' ] );
		add_filter( 'sugar_calendar_helpers_get_event_excerpt', [ $this, 'filter_event_excerpt' ], 10, 2 );
	}

	/**
	 * Add editor support for WPBakery.
	 *
	 * @since 3.11.0
	 *
	 * @param array $supports Array of what is `sc_event` support.
	 *
	 * @return array
	 */
	public function add_editor_support( $supports ) {

		$supports[] = 'editor';

		return $supports;
	}

	/**
	 * Filter the event excerpt for WPBakery content.
	 *
	 * @since 3.11.0
	 *
	 * @param string $excerpt 
	 * @param int    $event_object_id
	 *
	 * @return string
	 */
	public function filter_event_excerpt( $excerpt, $event_object_id ) {

		// Check if the excerpt is a WPBakery content.
		if ( strpos( $excerpt, '[vc_' ) !== false ) {
			$post_obj = get_post( $event_object_id );
			// Remove WPBakery shortcode tags but keep the inner content.
			$content = preg_replace( '/\[\/?vc_[^\]]*\]/', '', $post_obj->post_content );

			return wp_trim_excerpt( $content );
		}

		return $excerpt;
	}
}
