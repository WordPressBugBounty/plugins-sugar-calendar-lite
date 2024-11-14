<?php
use Sugar_Calendar\Helper;
use Sugar_Calendar\Block\EventList\EventListView\EventView;

/**
 * @var \Sugar_Calendar\Block\EventList\EventListView\PlainView $context
 */

$view_class = 'sugar-calendar-event-list-block__plainview sugar-calendar-block__events-display-container';

// If block header is not to be displayed.
if ( ! $context->get_block()->should_render_block_header() ) {
	$view_class .= ' sugar-calendar-block__events-display-container__no-header';
}
?>
<div class="<?php echo esc_attr( $view_class ); ?>">
	<?php
	$events = $context->get_block()->get_events();

	if ( $context->get_block()->should_group_events_by_week() ) {
		$period = $context->get_block()->get_week_period();
	} else {
		$period = $context->get_block()->get_upcoming_period();
	}

	foreach ( $period as $day ) {

		if ( ! isset( $events[ $day->format( 'Y-m-d' ) ] ) ) {
			continue;
		}

		foreach ( $events[ $day->format( 'Y-m-d' ) ] as $event ) {

			// We should only display an event once when grouping by week.
			if (
				$context->get_block()->should_group_events_by_week()
				&&
				in_array( $event->id, $context->get_block()->get_displayed_events(), true )
			) {
				continue;
			}

			$context->get_block()->add_displayed_event( $event->id );

			$event_view = new EventView( $event, $context->get_block() );
			?>
			<div
				data-eventdays="<?php echo esc_attr( wp_json_encode( $event_view->get_event_days() ) ); ?>"
				data-daydiv="<?php echo esc_attr( wp_json_encode( Helper::get_time_day_division_of_event( $event ) ) ); ?>"
				class="sugar-calendar-event-list-block__plainview__event">
				<h4 class="sugar-calendar-event-list-block__event__title">
					<?php $event_view->render_title(); ?>
				</h4>
				<div class="sugar-calendar-event-list-block__plainview__event__time sugar-calendar-event-list-block__event__datetime">
					<?php $event_view->render_date_time_with_icons(); ?>
				</div>
				<?php
				if ( $event_view->should_display_description() ) {
					?>
					<div class="sugar-calendar-event-list-block__plainview__event__desc">
						<?php echo esc_html( $event_view->get_description_excerpt() ); ?>
					</div>
					<?php
				}
				?>
			</div>
			<?php
		}
	}
	?>
</div>
