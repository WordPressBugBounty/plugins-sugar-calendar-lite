<?php

use Sugar_Calendar\Block\Calendar\CalendarView\Day;
use Sugar_Calendar\Block\Calendar\CalendarView\Week;

/**
 * @var Week\Day $context
 */
foreach ( Day\Day::get_division_by_hour() as $hour_int => $hour_name ) {
	?>
	<div class="sugar-calendar-block__calendar-week__event-slot">
		<?php
		foreach ( range( 0, 55, 5 ) as $div ) {
			?>
			<div class="sugar-calendar-block__calendar-week__event-slot__min-div">
				<?php
				// Check if there are events in the current time slot.
				if (
					! empty( $context->get_events()[ $hour_int ] ) &&
					! empty( $context->get_events()[ $hour_int ][ $div ] )
				) {
					$event_cell_args = [
						'block_attributes' => $context->get_args()['block_attributes'],
						'week_day_ctr'     => $context->get_args()['week_day_ctr'],
						'is_ajax'          => ! empty( $context->get_args()['is_ajax'] ) && $context->get_args()['is_ajax'],
					];

					/**
					 * @var \Sugar_Calendar\Event $time_slot_event
					 */
					foreach ( $context->get_events()[ $hour_int ][ $div ] as $time_slot_event ) {
						$event_cell = new Week\EventCell(
							$time_slot_event,
							$context->get_date(),
							$event_cell_args
						);

						$event_cell->render();
					}
				}
				?>
			</div>
			<?php
		}
		?>
	</div>
	<?php
}
