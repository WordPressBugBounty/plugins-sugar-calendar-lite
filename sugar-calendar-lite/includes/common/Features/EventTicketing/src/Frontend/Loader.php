<?php

namespace Sugar_Calendar\AddOn\Ticketing\Frontend;

use Sugar_Calendar\AddOn\Ticketing\Helpers\Helpers;
use Sugar_Calendar\Helpers\WP;
use function Sugar_Calendar\AddOn\Ticketing\Common\Assets\get_url;
use Sugar_Calendar\AddOn\Ticketing\Renderer;
use Sugar_Calendar\Helpers as BaseHelpers;

/**
 * Frontend Loader.
 *
 * @since 3.1.0
 */
class Loader {

	/**
	 * Init the frontend.
	 *
	 * @since 3.1.0
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Front-end related hooks.
	 *
	 * @since 3.1.0
	 */
	private function hooks() {

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );
		add_action( 'sugar_calendar_frontend_event_details_before', [ $this, 'render_single_event_ticket_button' ], 5 );
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since 3.1.0
	 */
	public function enqueue_frontend_scripts() {

		wp_register_style(
			'sc-event-ticketing-frontend-single-event',
			get_url( 'css' ) . '/frontend/single-event' . WP::asset_min() . '.css',
			[ 'sc-et-bootstrap', 'sc-et-general' ],
			BaseHelpers::get_asset_version()
		);

		if ( ! is_singular( sugar_calendar_get_event_post_type_id() ) ) {
			return;
		}

		/**
		 * Filter the Event object to be used in the enqueueing of the Event Ticketing assets.
		 *
		 * @since 3.6.0
		 *
		 * @param \Sugar_Calendar\Event $event The Event object.
		 */
		$event = apply_filters(
			'sugar_calendar_add_on_ticketing_frontend_loader_event_object',
			sugar_calendar_get_event_by_object( get_the_ID() )
		);

		if ( ! Helpers::is_event_ticketing_enabled( $event ) ) {
			return;
		}

		wp_enqueue_style( 'sc-event-ticketing-frontend-single-event' );
	}

	/**
	 * Render the event ticket button.
	 *
	 * @since 3.1.0
	 * @since 3.2.0 Added a check to determine if the tickets should be displayed.
	 * @since 3.10.0 Use Renderer class.
	 *
	 * @param \Sugar_Calendar\Event $event The event object.
	 */
	public function render_single_event_ticket_button( $event ) {

		$renderer = new Renderer( $event );

		$renderer->maybe_render_buy_now_button();
	}
}
