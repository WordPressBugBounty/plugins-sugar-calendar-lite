<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial: these are file-local view variables, not real globals; prefixing them (e.g. sugar_calendar_*) would hurt readability with no benefit.
use Sugar_Calendar\Helper;
use Sugar_Calendar\Block\Calendar\CalendarView\Week;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * @var Week\Week $context
 */
// All-day and multi-day events share one "All Day" section: multi-day spanning
// bars in a top band, per-day all-day cards in a band below (joined via CSS).
// Render each band only when it actually has events, so an empty band never
// reserves a ~50px row of its own. Always keep the all-day band when there are
// no multi-day events, so the "All Day" label/row still shows on empty weeks.
// The label always sits on the top (first) band.
$has_multi_day = $context->has_multi_day_events();
$has_all_day   = $context->has_all_day_events();

$bands = [];

if ( $has_multi_day ) {
	$bands[] = 'multi_day';
}

if ( $has_all_day || ! $has_multi_day ) {
	$bands[] = 'all_day';
}

foreach ( $bands as $band_index => $band ) {
	$band_classes   = [ 'sugar-calendar-block__calendar-week__all-day' ];
	$band_classes[] = "sugar-calendar-block__calendar-week__all-day--{$band}";

	// A lone all-day band has no multi-day band above it to carry the section's
	// top border, so tag it for the border restore in CSS.
	if ( count( $bands ) === 1 ) {
		$band_classes[] = 'sugar-calendar-block__calendar-week__all-day--solo';
	}

	// Only the top band shows the section's single "All Day" label.
	$label = ( $band_index === 0 ) ? __( 'All Day', 'sugar-calendar-lite' ) : '';
	?>
	<div class="<?php echo esc_attr( implode( ' ', $band_classes ) ); ?>">
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
				foreach ( $context->get_day_events_by_type( $week_day, $band ) as $event ) {

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
						],
						$context->get_block()
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
