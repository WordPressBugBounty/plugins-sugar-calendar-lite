<?php

use Sugar_Calendar\Helper;

/**
 * @var \Sugar_Calendar\Block\Calendar\CalendarView\Week\Week $context
 */
?>
<div class="sugar-calendar-block__calendar-week__header">
	<div class="sugar-calendar-block__calendar-week__header__spacer"></div>
	<?php
	$week_day_ctr = 0;

	foreach ( $context->get_block()->get_week_period() as $week_day ) {
		$week_day_num   = absint( $week_day->format( 'w' ) );
		$weekday_abbrev = Helper::get_weekday_abbrev_by_number( $week_day_num );

		if ( empty( $weekday_abbrev ) ) {
			continue;
		}

		++$week_day_ctr;

		$active_col_class = '';

		/*
		 * We add an active day only if it's the current day
		 * OR
		 * the first day of the week if the current day is not within the week.
		 */
		if (
			( $context->is_current_day_within_the_week() && $week_day->format( 'Y-m-d' ) === gmdate( 'Y-m-d' ) )
			||
			( ! $context->is_current_day_within_the_week() && $context->get_block()->is_ajax() && $week_day_ctr === 1 )
		) {
			$active_col_class = 'sugar-calendar-block__calendar-week__header__cell--active';
		}
		?>
		<div
			data-weekdaynum="<?php echo esc_attr( $week_day_num ); ?>"
			class="sugar-calendar-block__calendar-week__header__cell <?php echo esc_attr( $active_col_class ); ?>">
			<div class="sugar-calendar-block__calendar-week__header__cell__name">
				<?php echo esc_html( $weekday_abbrev ); ?>
			</div>

			<div class="sugar-calendar-block__calendar-week__header__cell__name-mobile">
				<?php echo esc_html( mb_substr( $weekday_abbrev, 0, 1 ) ); ?>
			</div>

			<div class="sugar-calendar-block__calendar-week__header__cell__num">
				<?php echo esc_html( $week_day->format( 'j' ) ); ?>
			</div>
		</div>
		<?php
	}
	?>
</div>
