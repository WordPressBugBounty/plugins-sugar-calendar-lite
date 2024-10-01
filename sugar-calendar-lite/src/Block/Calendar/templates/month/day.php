<?php
use Sugar_Calendar\Block\Calendar\CalendarView\Month;

/**
 * @var Month\Day $context
 */
?>

<div class="<?php echo esc_attr( implode( ' ', $context->get_classes() ) ); ?>">

	<div class="sugar-calendar-block__calendar-month__body__day__number">
		<?php echo esc_html( $context->get_date_info()['day'] ); ?>
	</div>

	<div
		data-weekday="<?php echo esc_attr( $context->get_calendar_info()['start_of_week_ctr'] ); ?>"
		class="<?php echo esc_attr( implode( ' ', $context->get_container_classes() ) ); ?>">

		<?php
		/**
		 * @var \Sugar_Calendar\Event[] $events
		 */
		foreach ( $context->get_events() as $event ) {

			if ( empty( $event ) ) {
				continue;
			}

			// If `$event` is string, then it's a spacer.
			if ( is_string( $event ) ) {
				$spacer = explode( '-', $event );

				echo wp_kses(
					sprintf(
						'<div class="sugar-calendar-block__calendar-month__body__day__events-container__spacer_%1$s sugar-calendar-block__calendar-month__spacer-eventid-%2$d"></div>',
						esc_attr( $spacer[0] ), // spacer type.
						absint( $spacer[1] ) // event object id.
					),
					[
						'div' => [
							'class' => true,
						],
					]
				);

				continue;
			}

			$event_cell = new Month\EventCell(
				$event,
				$context->get_date(),
				$context->get_calendar_info()
			);

			$event_cell->render();
		}
		?>
	</div>
</div>
