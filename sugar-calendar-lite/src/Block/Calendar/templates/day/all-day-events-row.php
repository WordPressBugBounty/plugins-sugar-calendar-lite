<?php
namespace Sugar_Calendar\Block\Calendar\CalendarView;

/**
 * @var Day\Day $context
 */
?>

<div class="sugar-calendar-block__calendar-day__all-day">
	<div class="sugar-calendar-block__calendar-day__time-label-cell">
		<?php echo esc_html__( 'ALL-DAY', 'sugar-calendar-lite' ); ?>
	</div>
	<div class="sugar-calendar-block__calendar-day__event-slot--all-day sugar-calendar-block__calendar-day__event-slot">
		<?php
		foreach ( $context->get_all_day_events() as $all_day_event ) {
			$event_cell = new Week\EventCell(
				$all_day_event,
				$context->get_block()->get_datetime(),
				[
					'block_attributes' => $context->get_block()->get_attributes(),
					'week_day_ctr'     => 0, // We don't need this since we are displaying the day view.
					'is_all_day'       => true,
					'is_ajax'          => $context->get_block()->is_ajax(),
				]
			);

			$event_cell->render();
		}
		?>
	</div>
</div>
