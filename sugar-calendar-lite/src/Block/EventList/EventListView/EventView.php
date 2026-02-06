<?php

namespace Sugar_Calendar\Block\EventList\EventListView;

use DatePeriod;
use DateTime;
use DateInterval;
use Sugar_Calendar\Helper;
use Sugar_Calendar\Helpers;
use Sugar_Calendar\Options;

class EventView {

	/**
	 * Event object.
	 *
	 * @since 3.1.0
	 *
	 * @var \Sugar_Calendar\Event
	 */
	private $event;

	/**
	 * Block object.
	 *
	 * @since 3.1.0
	 *
	 * @var Block
	 */
	private $block;

	/**
	 * Constructor.
	 *
	 * @since 3.1.0
	 *
	 * @param \Sugar_Calendar\Event $event Event object.
	 * @param Block                 $block Block object.
	 */
	public function __construct( $event, $block ) {

		$this->event = $event;
		$this->block = $block;
	}

	/**
	 * Get the array containing the days of the event.
	 * 0 - Sunday
	 * 6 - Saturday.
	 *
	 * @since 3.1.0
	 *
	 * @return int[]
	 */
	public function get_event_days() {

		if ( ! $this->event->is_multi() ) {
			return [ $this->event->start_date( 'w' ) ];
		}

		$event_days = [];

		$event_period = new DatePeriod(
			new DateTime( $this->event->start_date( 'Y-m-d' ) ),
			new DateInterval( 'P1D' ),
			// +1 day to include the end date with PHP < 8.2 support.
			new DateTime( $this->event->end_date( 'Y-m-d' ) . ' +1 day' )
		);

		foreach ( $event_period as $day ) {
			$event_days[] = $day->format( 'w' );
		}

		return $event_days;
	}

	/**
	 * Render the title.
	 *
	 * @since 3.1.0
	 * @since 3.6.0 Used the Helper class to get the frontend URL.
	 */
	public function render_title() {

		if ( Helpers::is_on_admin_editor() ) {
			echo esc_html( $this->event->title );

			return;
		}

		printf(
			'<a href="%1$s"%3$s>%2$s</a>',
			esc_url( Helper::get_event_frontend_url( $this->event ) ),
			esc_html( $this->event->title ),
			Helper::get_event_frontend_url_open_in_new_tab( $this->event ) ? ' target="_blank"' : ''
		);
	}

	/**
	 * Render the date and time with icons.
	 *
	 * @since 3.1.0
	 */
	public function render_date_time_with_icons() {

		if ( $this->event->is_multi() ) {
			$time_display = $this->get_multiday_date_time_display();
		} else {
			$time_display = $this->get_date_time_display();
		}

		echo wp_kses(
			$time_display,
			[
				'span' => [
					'class' => true,
				],
				'time' => [
					'datetime'               => true,
					'title'                  => true,
					'data-timezone'          => true,
					'data-conversion-format' => true,
				],
			]
		);

		echo $this->get_icons_display(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get the multi-day date and time to display.
	 *
	 * @since 3.1.0
	 *
	 * @return string
	 */
	private function get_multiday_date_time_display() {
		/**
		 * Filters the date format to use in the event list block body.
		 *
		 * @since 3.6.0
		 *
		 * @param string $date_format Date format.
		 */
		$date_format = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_block_event_list_body_date_format',
			Options::get( 'date_format', 'F j, Y' )
		);

		$start_date = sugar_calendar_format_date_i18n( $date_format, $this->event->start );
		$end_date   = sugar_calendar_format_date_i18n( $date_format, $this->event->end );

		if ( $this->event->is_all_day() ) {
			return sprintf(
				'%1$s - %2$s',
				$start_date,
				$end_date
			);
		}

		/**
		 * Filters the time format to use in the event list block body.
		 *
		 * @since 3.6.0
		 *
		 * @param string $time_format Time format.
		 */
		$time_format = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_block_event_list_body_time_format',
			Options::get( 'time_format', 'g:i a' )
		);

		$output = sprintf(
			/* translators: 1: start date, 2: "at" text, 3: start time, 4: end date, 5: "at" text, 6: end time. */
			'%1$s %2$s %3$s - %4$s %5$s %6$s',
			$start_date,
			esc_html__( 'at', 'sugar-calendar-lite' ),
			'<span class="sc-frontend-single-event__details__val-time">' .
				Helpers::get_event_time_output(
					$this->event,
					Helpers::maybe_remove_timezone_format( $time_format, 'time', $this->event ),
					'start',
					false,
					( ! Helpers::event_has_multiple_timezones( $this->event ) ) // Hide the conversion timezone if the event has same timezone.
				) .
			'</span>',
			$end_date,
			esc_html__( 'at', 'sugar-calendar-lite' ),
			'<span class="sc-frontend-single-event__details__val-time">' . Helpers::get_event_time_output( $this->event, $time_format, 'end' ) . '</span>'
		);

		return $output;
	}

	/**
	 * Get the date and time to display.
	 *
	 * @since 3.1.0
	 * @since 3.7.0 Make the output string filterable.
	 * @since 3.8.2 Add support for timezone conversion format.
	 *
	 * @return string
	 */
	private function get_date_time_display() {

		$event_date = Helpers::get_event_time_output(
			$this->event,
			/**
			 * Filters the date format to use in the event list block body.
			 *
			 * @since 3.6.0
			 *
			 * @param string $date_format Date format.
			 */
			apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
				'sugar_calendar_block_event_list_body_date_format',
				Options::get( 'date_format', 'F j, Y' )
			)
		);

		if ( $this->event->is_all_day() ) {
			return $event_date;
		}

		/**
		 * Filters the time format to use in the event list block body.
		 *
		 * @since 3.6.0
		 *
		 * @param string $time_format Time format.
		 */
		$time_format = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_block_event_list_body_time_format',
			Options::get( 'time_format', 'g:i a' )
		);

		/*
		 * translators: 1: start date, 2. at, 3: start time, 4: end time
		 */
		$output = sprintf(
			'%1$s <span>%2$s</span> %3$s - %4$s',
			'<span class="sc-frontend-single-event__details__val-date">' . $event_date . '</span>',
			esc_html__( 'at', 'sugar-calendar-lite' ),
			'<span class="sc-frontend-single-event__details__val-time">' .
				Helpers::get_event_time_output(
					$this->event,
					Helpers::maybe_remove_timezone_format( $time_format, 'time', $this->event ),
					'start',
					false,
					( ! Helpers::event_has_multiple_timezones( $this->event ) ) // Hide the conversion timezone if the event has same timezone.
				) .
			'</span>',
			'<span class="sc-frontend-single-event__details__val-time">' . Helpers::get_event_time_output( $this->event, $time_format, 'end' ) . '</span>'
		);

		/**
		 * Filter the output date time display.
		 *
		 * @since 3.7.0
		 *
		 * @param string                $output      Output string.
		 * @param \Sugar_Calendar\Event $event       Event object.
		 * @param string                $event_date  The event date.
		 * @param string                $time_format Time format.
		 */
		return apply_filters(
			'sugar_calendar_block_event_list_event_list_view_event_view_dt_display',
			$output,
			$this->event,
			$event_date,
			$time_format
		);
	}

	/**
	 * Get the icons to display.
	 *
	 * @since 3.1.0
	 *
	 * @return string
	 */
	private function get_icons_display() {

		// In dark mode?
		$is_dark_mode = $this->block->get_appearance_mode() === 'dark';

		$icons = [];

		if ( ! empty( $this->event->recurrence ) ) {

			// Icons: recur, recur-dark.
			$icons[] = Helpers::get_svg_url(
				sprintf(
					'recur%s',
					$is_dark_mode ? '-dark' : ''
				)
			);
		}

		// Icons: calendar-day, calendar-day-dark, calendar-multiday, calendar-multiday-dark.
		$icons[] = Helpers::get_svg_url(
			sprintf(
				'%1$s%2$s',
				$this->event->is_multi() ? 'calendar-multiday' : 'calendar-day',
				$is_dark_mode ? '-dark' : ''
			)
		);

		/**
		 * Filters the icons to display in the event list block.
		 *
		 * @since 3.1.0
		 *
		 * @param string[]              $icons SVG urls of the icons.
		 * @param \Sugar_Calendar\Event $event Event object.
		 */
		$icons = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_event_list_block_icons',
			$icons,
			$this->event
		);

		if ( empty( $icons ) ) {
			return '';
		}

		$output = '';

		foreach ( $icons as $icon ) {
			$output .= sprintf(
				'<img src="%1$s" alt="%2$s">',
				esc_url( $icon ),
				esc_attr( basename( $icon, '.svg' ) )
			);
		}

		return $output;
	}

	/**
	 * Whether or not we should display the featured image.
	 *
	 * @since 3.1.0
	 *
	 * @return bool
	 */
	public function should_display_featured_image() {

		return ! empty( $this->block->get_settings_attributes()['showFeaturedImages'] );
	}

	/**
	 * Get image display position.
	 *
	 * @since 3.4.0
	 *
	 * @return string
	 */
	public function get_image_display_position() {

		$position = 'default';

		if ( ! empty( $this->block->get_settings_attributes()['imagePosition'] ) ) {
			$position = $this->block->get_settings_attributes()['imagePosition'];
		}

		return $position;
	}

	/**
	 * Whether or not we should display the description.
	 *
	 * @since 3.1.0
	 *
	 * @return bool
	 */
	public function should_display_description() {

		return ! empty( $this->block->get_settings_attributes()['showDescriptions'] );
	}

	/**
	 * Whether or not we should display the date cards.
	 *
	 * @since 3.4.0
	 *
	 * @return bool
	 */
	public function should_display_date_cards() {

		return ! empty( $this->block->get_settings_attributes()['showDateCards'] );
	}

	/**
	 * Get the description excerpt.
	 *
	 * @since 3.1.0
	 * @since 3.10.0 Use the user-defined excerpt if it exists.
	 *
	 * @return string
	 */
	public function get_description_excerpt() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		add_filter( 'excerpt_length', [ $this, 'filter_event_description' ], PHP_INT_MAX );
		add_filter( 'excerpt_more', [ $this, 'filter_event_more_string' ], PHP_INT_MAX );

		$excerpt = Helpers::get_event_excerpt( $this->event->object_id );

		remove_filter( 'excerpt_length', [ $this, 'filter_event_description' ], PHP_INT_MAX );
		remove_filter( 'excerpt_more', [ $this, 'filter_event_more_string' ], PHP_INT_MAX );

		return $excerpt;
	}

	/**
	 * Filter the number of words in the event description (Event List block).
	 *
	 * @since 3.10.0
	 *
	 * @param int $number Number of words passed to the filter.
	 *
	 * @return int
	 */
	public function filter_event_description( $number ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		/**
		 * Filters the number of words in the event description for the Event List block.
		 *
		 * @since 3.10.0
		 *
		 * @param int   $length Desired excerpt length.
		 * @param Event $event  Event object.
		 * @param Block $block  Block instance.
		 */
		return apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_event_list_excerpt_length',
			55,
			$this->event,
			$this->block
		);
	}

	/**
	 * Filters the string after the event description excerpt (Event List block).
	 *
	 * @since 3.10.0
	 *
	 * @param string $excerpt_more The string after the event description excerpt.
	 *
	 * @return string
	 */
	public function filter_event_more_string( $excerpt_more ) {

		/**
		 * Filters the string after the event description excerpt in the Event List block.
		 *
		 * @since 3.10.0
		 *
		 * @param string $excerpt_more  The string after the excerpt.
		 * @param Event  $event         Event object.
		 * @param Block  $block         Block instance.
		 */
		return apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_event_list_excerpt_more_string',
			$excerpt_more,
			$this->event,
			$this->block
		);
	}
}
