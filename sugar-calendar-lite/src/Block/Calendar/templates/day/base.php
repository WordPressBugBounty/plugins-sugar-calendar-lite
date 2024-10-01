<?php
use Sugar_Calendar\Block\Calendar\CalendarView\Week;
use Sugar_Calendar\Block\Calendar\CalendarView\Day;
use Sugar_Calendar\Block\Common\Template;

/**
 * @var Day\Day $context
 */
?>
<div class="sugar-calendar-block__calendar-day">
	<?php
	Template::load( 'day/all-day-events-row', $context );
	?>
	<div class="sugar-calendar-block__calendar-day__time-grid">
		<div class="sugar-calendar-block__calendar-day__time-grid__hours-col">
			<?php
			foreach ( Day\Day::get_division_by_hour() as $hour_name ) {
				?>
				<div class="sugar-calendar-block__calendar-day__time-label-cell">
					<?php echo esc_html( $hour_name ); ?>
				</div>
			<?php
			}
			?>
		</div>
		<div class="sugar-calendar-block__calendar-day__time-grid__events-col">
			<?php
			foreach ( Day\Day::get_division_by_hour() as $hour_int => $hour_name ) {
				?>
				<div class="sugar-calendar-block__calendar-day__event-slot">
					<?php
					foreach ( range( 0, 55, 5 ) as $div ) {
						?>
						<div class="sugar-calendar-block__calendar-day__event-slot__min-div">
							<?php
							// Check if there are events in the current time slot.
							if (
								! empty( $context->get_events()[ $hour_int ] ) &&
								! empty( $context->get_events()[ $hour_int ][ $div ] )
							) {
								/**
								 * @var \Sugar_Calendar\Event $time_slot_event
								 */
								foreach ( $context->get_events()[ $hour_int ][ $div ] as $time_slot_event ) {
									$event_cell = new Week\EventCell(
										$time_slot_event,
										$context->get_block()->get_datetime(),
										[
											'block_attributes' => $context->get_block()->get_attributes(),
											'week_day_ctr' => 0, // We don't need this since we are displaying the day view.
											'is_ajax'      => $context->get_block()->is_ajax(),
										]
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
			?>
		</div>
	</div>
</div>
