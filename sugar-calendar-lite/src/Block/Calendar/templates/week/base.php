<?php
use Sugar_Calendar\Helper;
use Sugar_Calendar\Block\Calendar\CalendarView;
use Sugar_Calendar\Block\Common\Template;

/**
 * @var CalendarView\Week\Week $context
 */
?>
<div class="sugar-calendar-block__calendar-week">
	<?php
	Template::load( 'week/header', $context );
	Template::load( 'week/multi-and-all-day-events-rows', $context );
	?>
	<div class="sugar-calendar-block__calendar-week__time-grid">
		<div class="sugar-calendar-block__calendar-week__time-grid__hours-col">
			<?php
			foreach ( CalendarView\Day\Day::get_division_by_hour() as $hour_name ) {
				?>
				<div class="sugar-calendar-block__calendar-week__time-label-cell">
					<?php echo esc_html( $hour_name ); ?>
				</div>
			<?php
			}
			?>
		</div>

		<?php
		$week_day_ctr = 0;

		foreach ( $context->get_block()->get_week_period() as $week_day ) {
			$weekday_num = absint( $week_day->format( 'w' ) );

			if ( empty( Helper::get_weekday_abbrev_by_number( $weekday_num ) ) ) {
				continue;
			}

			++$week_day_ctr;

			$weekday_col_classes   = [];
			$weekday_col_classes[] = 'sugar-calendar-block__calendar-week__time-grid__day-col';
			$weekday_col_classes[] = "sugar-calendar-block__calendar-week__time-grid__day-col-{$weekday_num}";

			if (
				( $context->is_current_day_within_the_week() && $week_day->format( 'Y-m-d' ) === gmdate( 'Y-m-d' ) )
				||
				( ! $context->is_current_day_within_the_week() && $context->get_block()->is_ajax() && $week_day_ctr === 1 )
			) {
				$weekday_col_classes[] = 'sugar-calendar-block__calendar-week__time-grid__day-col--active';
			}
			?>
			<div
				data-weekday="<?php echo esc_attr( $weekday_num ); ?>"
				class="<?php echo esc_attr( implode( ' ', $weekday_col_classes ) ); ?>">
				<?php
				$day = new CalendarView\Week\Day(
					$context->get_events_by_day( $week_day ),
					$week_day,
					[
						'block_attributes' => $context->get_block()->get_attributes(),
						'week_day_ctr'     => $week_day_ctr,
						'is_ajax'          => $context->get_block()->is_ajax(),
					]
				);

				$day->render();
				?>
			</div>
			<?php
		}
		?>
	</div>
</div>
