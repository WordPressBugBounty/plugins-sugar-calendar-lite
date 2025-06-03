<?php

namespace Sugar_Calendar\Frontend;

use Sugar_Calendar\Helper;
use Sugar_Calendar\Helpers;
use Sugar_Calendar\Common\Editor;

/**
 * Frontend Loader.
 *
 * @since 3.1.0
 */
class Loader {

	/**
	 * Init the Frontend Loader.
	 *
	 * @since 3.1.0
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Frontend hooks.
	 *
	 * @since 3.1.0
	 */
	public function hooks() {

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );

		// Wrap the new event details container.
		add_action( 'sc_event_details', [ $this, 'event_details' ] );

		// Display the event details.
		add_action( 'sugar_calendar_frontend_event_details', [ $this, 'render_event_date' ], 20 );
		add_action( 'sugar_calendar_frontend_event_details', [ $this, 'render_event_time' ], 30 );
		add_action( 'sugar_calendar_frontend_event_details', [ $this, 'render_event_location' ], 40 );
		add_action( 'sugar_calendar_frontend_event_details', [ $this, 'render_event_calendars' ], 50 );

		// Body class hook for single event detail.
		add_filter( 'body_class', [ $this, 'sc_modify_single_event_body_classes' ] );
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since 3.1.0
	 * @since 3.1.2 Support minified assets.
	 */
	public function enqueue_frontend_scripts() {

		if ( ! sc_doing_events() ) {
			return;
		}

		wp_register_style(
			'sc-frontend-single-event',
			SC_PLUGIN_ASSETS_URL . 'css/frontend/single-event' . Helpers\WP::asset_min() . '.css',
			[],
			Helpers::get_asset_version()
		);

		wp_enqueue_style( 'sc-frontend-single-event' );
	}

	/**
	 * Wrap the new event details container.
	 *
	 * @since 3.1.0
	 * @since 3.6.0 Added filter for the get event args and hook for the event object.
	 *
	 * @param int $post_id The post ID.
	 */
	public function event_details( $post_id ) {

		/**
		 * Filters the arguments for getting the event object.
		 *
		 * @since 3.6.0
		 *
		 * @param array $args The arguments for getting the event object. Default `[]`.
		 */
		$sc_get_event_by_obj_args = apply_filters(
			'sugar_calendar_frontend_loader_get_event_by_object_args',
			[]
		);

		/**
		 * Filters the event object for the frontend event details.
		 *
		 * @since 3.6.0
		 *
		 * @param \Sugar_Calendar\Event $event The event object.
		 */
		$event = apply_filters(
			'sugar_calendar_frontend_loader_event_object',
			sugar_calendar_get_event_by_object(
				$post_id,
				'post',
				$sc_get_event_by_obj_args
			)
		);

		if ( empty( $event->object_id ) ) {
			return;
		}
		?>
		<div class="sc-frontend-single-event">

			<?php
			/**
			 * Fires before the event details are output.
			 *
			 * @param \Sugar_Calendar\Event $event The event object.
			 *
			 * @since 3.1.0
			 */
			do_action( 'sugar_calendar_frontend_event_details_before', $event ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			?>

			<div class="sc-frontend-single-event__details">
				<?php
				/**
				 * Fires to display event details.
				 *
				 * @param \Sugar_Calendar\Event $event The event object.
				 *
				 * @since 3.1.0
				 */
				do_action( 'sugar_calendar_frontend_event_details', $event ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the event date.
	 *
	 * @since 3.1.0
	 * @since 3.1.2 Render the time and date inside `<time>` tags.
	 *
	 * @param \Sugar_Calendar\Event $event The event object.
	 */
	public function render_event_date( $event ) {
		?>
		<div class="sc-frontend-single-event__details__date sc-frontend-single-event__details-row">
			<div class="sc-frontend-single-event__details__label">
				<?php echo esc_html( Helpers::get_event_datetime_label( $event ) ); ?>
			</div>
			<div class="sc-frontend-single-event__details__val">
				<?php
				if ( $event->is_multi() ) {
					$output = Helpers::get_multi_day_event_datetime( $event );
				} else {
					$output = '<span class="sc-frontend-single-event__details__val-date">' . Helpers::get_event_datetime( $event ) . '</span>';
				}

				echo wp_kses(
					$output,
					[
						'span' => [
							'class' => true,
						],
						'time' => [
							'data-timezone' => true,
							'datetime'      => true,
							'title'         => true,
						],
					]
				);
				?>
			</div>
			<?php
			/**
			 * Fires after the event date is output.
			 *
			 * @param \Sugar_Calendar\Event $event The event object.
			 *
			 * @since 3.1.0
			 */
			do_action( 'sugar_calendar_frontend_event_details_date', $event ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			?>
		</div>
		<?php
	}

	/**
	 * Render the event time.
	 *
	 * @since 3.1.0
	 *
	 * @param \Sugar_Calendar\Event $event The event object.
	 */
	public function render_event_time( $event ) {

		// If the event is multi-day, we show the time in the date row.
		if ( $event->is_multi() ) {
			return;
		}
		?>
		<div class="sc-frontend-single-event__details__time sc-frontend-single-event__details-row">
			<div class="sc-frontend-single-event__details__label">
				<?php esc_html_e( 'Time:', 'sugar-calendar-lite' ); ?>
			</div>
			<div class="sc-frontend-single-event__details__val">
				<?php
				echo wp_kses(
					Helpers::get_event_datetime( $event, 'time' ),
					[
						'time' => [
							'data-timezone' => true,
							'datetime'      => true,
							'title'         => true,
						],
					]
				);
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the event location.
	 *
	 * @since 3.1.0
	 *
	 * @param \Sugar_Calendar\Event $event The event object.
	 */
	public function render_event_location( $event ) {

		if ( empty( $event->location ) ) {
			return;
		}
		?>
		<div class="sc-frontend-single-event__details__location sc-frontend-single-event__details-row">
			<div class="sc-frontend-single-event__details__label">
				<?php esc_html_e( 'Location:', 'sugar-calendar-lite' ); ?>
			</div>
			<div class="sc-frontend-single-event__details__val">
				<?php echo esc_html( $event->location ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the event calendars.
	 *
	 * @since 3.1.0
	 *
	 * @param \Sugar_Calendar\Event $event The event object.
	 */
	public function render_event_calendars( $event ) {

		$calendars = Helper::get_calendars_of_event( $event );

		if ( empty( $calendars ) ) {
			return;
		}

		$calendar_links = [];

		foreach ( $calendars as $calendar ) {
			$calendar_links[] = sprintf(
				'<a href="%1$s">%2$s</a>',
				get_term_link( $calendar ),
				$calendar->name
			);
		}
		?>
		<div class="sc-frontend-single-event__details__calendar sc-frontend-single-event__details-row">
			<div class="sc-frontend-single-event__details__label">
				<?php esc_html_e( 'Calendar:', 'sugar-calendar-lite' ); ?>
			</div>
			<div class="sc-frontend-single-event__details__val">
				<?php
				echo wp_kses(
					implode( ', ', $calendar_links ),
					[
						'a' => [
							'href' => [],
						],
					]
				);
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Filter for body class.
	 *
	 * @since 3.3.0
	 *
	 * @param array $classes Body classes.
	 *
	 * @return array
	 */
	public function sc_modify_single_event_body_classes( $classes = [] ) {

		// Return if not single event page.
		if ( ! is_singular( sugar_calendar_get_event_post_type_id() ) ) {
			return $classes;
		}

		// If dark mode is enabled.
		if ( Editor\get_single_event_appearance_mode() === 'dark' ) {

			$classes[] = 'single-sc_event-dark';
		}

		// Return the classes.
		return $classes;
	}
}
