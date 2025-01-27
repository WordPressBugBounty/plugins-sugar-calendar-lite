<?php
use Sugar_Calendar\Helper;
use Sugar_Calendar\Block\Calendar\CalendarView\Week;

/**
 * @var Week\Week $context
 */
$multi_and_all_day_events = [
	'all_day'   => __( 'All Day', 'sugar-calendar-lite' ),
	'multi_day' => __( 'Multi-day', 'sugar-calendar-lite' ),
];

foreach ( $multi_and_all_day_events as $key => $label ) {
	?>
	<div class="sugar-calendar-block__calendar-week__all-day">
		<div class="sugar-calendar-block__calendar-week__time-label-cell">
			<div>
				<?php echo esc_html( $label ); ?>
			</div>
		</div>
		<?php
		$week_day_ctr   = 0;
		$events_display = [];

		foreach ( $context->get_block()->get_week_period() as $week_day ) {
			$weekday_num = absint( $week_day->format( 'w' ) );
			// Get weekday number.
			$weekday_abbrev = Helper::get_weekday_abbrev_by_number( $weekday_num );

			if ( empty( $weekday_abbrev ) ) {
				continue;
			}

			++$week_day_ctr;

			$weekday_col_classes   = [];
			$weekday_col_classes[] = 'sugar-calendar-block__calendar-week__event-slot';
			$weekday_col_classes[] = 'sugar-calendar-block__calendar-week__event-slot--all-day';
			$weekday_col_classes[] = "sugar-calendar-block__calendar-week__event-slot--all-day--{$weekday_num}";

			if (
				( $context->is_current_day_within_the_week() && $week_day->format( 'Y-m-d' ) === gmdate( 'Y-m-d' ) )
				||
				( ! $context->is_current_day_within_the_week() && $context->get_block()->is_ajax() && $week_day_ctr === 1 )
			) {
				$weekday_col_classes[] = 'sugar-calendar-block__calendar-week__event-slot--all-day--active';
			}
			?>
			<div
				data-weekday="<?php echo esc_attr( $weekday_num ); ?>"
				class="<?php echo esc_attr( implode( ' ', $weekday_col_classes ) ); ?>">

				<?php
				foreach ( $context->get_day_events_by_type( $week_day, $key ) as $event ) {

					// If `$event` is string, then it's a spacer.
					if ( is_string( $event ) ) {
						$spacer = explode( '-', $event );

						echo wp_kses(
							sprintf(
								'<div class="sugar-calendar-block__calendar-week__all-day__spacer_%1$s"></div>',
								esc_attr( $spacer[0] ) // spacer type.
							),
							[
								'div' => [
									'class' => true,
								],
							]
						);

						continue;
					}

					$event_cell = new Week\EventCell(
						$event,
						$week_day,
						[
							'block_attributes'             => $context->get_block()->get_attributes(),
							'is_all_day'                   => true,
							'week_day_ctr'                 => $week_day_ctr,
							'is_ajax'                      => $context->get_block()->is_ajax(),
							'events_displayed_in_the_week' => $events_display,
						]
					);

					$event_cell->render();

					if ( ! array_key_exists( $event->id, $events_display ) ) {
						$events_display[ $event->id ] = true;
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
