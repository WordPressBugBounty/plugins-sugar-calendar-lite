<div class="sugar-calendar-block__calendar-month__header">
	<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial: these are file-local view variables, not real globals; prefixing them (e.g. sugar_calendar_*) would hurt readability with no benefit.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

	$day_name_ctr = 0;
	$day_names    = sc_get_calendar_day_names( 'mid', sc_get_week_start_day() );

	foreach ( $day_names as $day ) {
		++$day_name_ctr;

		printf(
			'<div class="sugar-calendar-block__calendar-month__cell sugar-calendar-block__calendar-month__header__day %1$s">' .
			'<span class="sugar-calendar-block__calendar-month__header__day__text">%2$s</span><span class="sugar-calendar-block__calendar-month__header__day__text-short">%3$s</span></div>',
			$day_name_ctr % 7 === 0 ? 'sugar-calendar-block__calendar-month__header__day-eow' : '',
			esc_html( $day ),
			esc_html( mb_substr( $day, 0, 1 ) )
		);
	}
	?>
</div>
