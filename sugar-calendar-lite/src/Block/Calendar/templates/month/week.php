<?php

use Sugar_Calendar\Block\Calendar\CalendarView\Month;
use Sugar_Calendar\Event;

/**
 * @var Month\Week $context
 */
?>
<div class="sugar-calendar-block__calendar-month__body__week">
	<?php
	// Get the start of the week.
	$start_of_week_ctr = sc_get_week_start_day();
	$days_of_week_ctr  = 1;

	$formatted_events = $context->get_formatted_events();
	$events_display   = [];

	foreach ( $formatted_events as $cal_date => $cal_date_events ) {

		$day = new Month\Day(
			$cal_date,
			$cal_date_events,
			[
				'start_of_week_ctr'            => $start_of_week_ctr % 7,
				'days_of_week_ctr'             => $days_of_week_ctr,
				'events_displayed_in_the_week' => $events_display, // The events that was already been displayed.
				'month'                        => $context->get_calendar_info()['month'],
				'year'                         => $context->get_calendar_info()['year'],
				'from_ajax'                    => ! empty( $context->get_calendar_info()['from_ajax'] ) && $context->get_calendar_info()['from_ajax'],
				'accentColor'                  => ! empty( $context->get_calendar_info()['accentColor'] ) ? $context->get_calendar_info()['accentColor'] : '',
			]
		);

		++$start_of_week_ctr;
		++$days_of_week_ctr;

		$day->render();

		foreach ( $cal_date_events as $cal_event ) {

			if (
				$cal_event instanceof Event &&
				! array_key_exists( $cal_event->id, $events_display )
			) {
				$events_display[ $cal_event->id ] = true;
			}
		}
	}
	?>
</div>
