<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial: these are file-local view variables, not real globals; prefixing them (e.g. sugar_calendar_*) would hurt readability with no benefit.
namespace Sugar_Calendar\Block\Calendar\CalendarView;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

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
				],
				$context->get_block()
			);

			$event_cell->render();
		}
		?>
	</div>
</div>
