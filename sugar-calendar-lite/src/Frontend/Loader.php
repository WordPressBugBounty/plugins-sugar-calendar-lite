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

		add_filter( 'the_posts', [ $this, 'inject_archive_event_template_content' ], 10, 2 );

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

		add_filter( 'pre_get_shortlink', [ $this, 'filter_pre_get_shortlink' ], 10, 4 );
	}

	/**
	 * We handle the short link for the virtual event archive page.
	 *
	 * This fixes the PHP warning.
	 *
	 * @since 3.10.0
	 *
	 * @param string $shortlink   The shortlink.
	 * @param int    $id          The post ID.
	 * @param string $context     The context.
	 * @param bool   $allow_slugs Whether to allow slugs.
	 */
	public function filter_pre_get_shortlink( $shortlink, $id, $context, $allow_slugs ) {

		if ( get_queried_object_id() === $this->get_virtual_event_archive_id() ) {
			return home_url();
		}

		return $shortlink;
	}

	/**
	 * Inject the archive event template content if there's no existing `archive-sc_event.php`
	 * file in themes.
	 *
	 * @since 3.10.0
	 *
	 * @param \WP_Post[] $posts Array of post objects.
	 * @param \WP_Query  $query The main WP Query instance.
	 *
	 * @return \WP_Post[]
	 */
	public function inject_archive_event_template_content( $posts, $query ) {

		if ( is_admin() || ! $query->is_main_query() ) {
			return $posts;
		}

		$is_event_archive    = $query->is_post_type_archive( sugar_calendar_get_event_post_type_id() );
		$is_calendar_archive = $query->is_tax( 'sc_event_category' );

		if ( ! $is_event_archive && ! $is_calendar_archive ) {
			return $posts;
		}

		$theme_file = locate_template( [ 'archive-sc_event.php' ] );

		if ( ! empty( $theme_file ) ) {
			return $posts;
		}

		$timezone           = wp_timezone();
		$datetime           = new \DateTime( 'December 1, 2025', $timezone );
		$calendars          = '';
		$virtual_post_title = 'Events';

		if ( $is_calendar_archive ) {
			$calendar_slug    = get_query_var( 'sc_event_category' );
			$calendar_term_id = get_queried_object_id();
			$calendar_term    = get_queried_object();

			if ( ! empty( $calendar_slug ) && ! empty( $calendar_term_id ) ) {
				$calendar_ids = implode( ',', [ $calendar_term_id ] );

				if ( ! empty( $calendar_ids ) ) {
					$calendars = 'calendars=' . $calendar_ids;
				}

				if ( ! empty( $calendar_term ) && ! empty( $calendar_term->name ) ) {
					$virtual_post_title = $calendar_term->name;
				}
			}
		}

		// Create a virtual post.
		$p                 = new \stdClass();
		$p->ID             = $this->get_virtual_event_archive_id();
		$p->post_author    = 1;
		$p->post_date      = $datetime->format( 'Y-m-d H:i:s' );
		$p->post_date_gmt  = $datetime->format( 'Y-m-d H:i:s' );
		$p->post_title     = $virtual_post_title;
		$p->post_content   = '[sugarcalendar_events_list ' . $calendars . ' group_events_by_week=false events_per_page=9 maximum_events_to_show=90]';
		$p->post_status    = 'publish';
		$p->comment_status = 'closed';
		$p->ping_status    = 'closed';
		$p->post_name      = 'events';
		$p->post_type      = 'page';
		$p->filter         = 'raw';

		/**
		 * Filters the virtual post object.
		 *
		 * @since 3.10.0
		 *
		 * @param \stdClass $p The virtual post object.
		 */
		$virtual_post = apply_filters( 'sugar_calendar_frontend_loader_virtual_post', $p );

		// Convert to WP_Post object.
		$wp_post = new \WP_Post( $virtual_post );

		// If we are here, then is using the default archive page.
		// We'll make it a "virtual page".
		$query->is_post_type_archive = false;
		$query->is_archive           = false;
		$query->is_page              = true;
		$query->is_singular          = true;
		$query->is_404               = false;
		$query->found_posts          = 1;
		$query->post_count           = 1;
		$query->max_num_pages        = 1;
		$query->queried_object       = $wp_post;
		$query->queried_object_id    = $wp_post->ID;

		$query->setup_postdata( $wp_post );

		return [ $wp_post ];
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since 3.1.0
	 * @since 3.1.2 Support minified assets.
	 * @since 3.10.0 Register the assets in all pages.
	 */
	public function enqueue_frontend_scripts() {

		wp_register_style(
			'sc-frontend-single-event',
			SC_PLUGIN_ASSETS_URL . 'css/frontend/single-event' . Helpers\WP::asset_min() . '.css',
			[],
			Helpers::get_asset_version()
		);

		wp_register_style(
			'sugar-calendar-frontend-fontawesome',
			SC_PLUGIN_ASSETS_URL . 'css/font-awesome-min.css',
			[],
			'6.5.0'
		);

		if ( ! sc_doing_events() ) {
			return;
		}

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
	 * @since 3.8.2 Add support for conversion format.
	 *
	 * @param Event $event The event object.
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
							'data-timezone'          => true,
							'data-conversion-format' => true,
							'datetime'               => true,
							'title'                  => true,
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
	 * @since 3.8.2 Add support for conversion format.
	 *
	 * @param Event $event The event object.
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
							'data-timezone'          => true,
							'data-conversion-format' => true,
							'datetime'               => true,
							'title'                  => true,
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

	/**
	 * Get the virtual event archive ID.
	 *
	 * @since 3.10.0
	 *
	 * @return int
	 */
	private function get_virtual_event_archive_id() {
		/**
		 * Filters the virtual event archive ID.
		 *
		 * @since 3.10.0
		 *
		 * @param int $virtual_event_archive_id The virtual event archive ID.
		 */
		return absint( apply_filters( 'sugar_calendar_frontend_loader_virtual_event_archive_id', 7575000 ) );
	}
}
