<?php
use Sugar_Calendar\Helper;
use Sugar_Calendar\Block\EventList\EventListView\EventView;

/**
 * @var \Sugar_Calendar\Block\EventList\EventListView\ListView $context
 */

$view_class = 'sugar-calendar-event-list-block__listview sugar-calendar-block__events-display-container';

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

			// Get the event featured image.
			$event_image = get_the_post_thumbnail_url(
				$event->object_id,
				/**
				 * Filter the size of the event image in the Event List block (list view).
				 *
				 * @since 3.5.0
				 *
				 * @param string $size Image size. Accepts any registered image size name, or an array of width and height values in pixels (in that order).
				 */
				apply_filters( 'sugar_calendar_block_list_listview_image_size', 'medium' )
			);

			// Get image display position.
			$image_display_position = $event_view->get_image_display_position();

			?>
			<div
				data-eventdays="<?php echo esc_attr( wp_json_encode( $event_view->get_event_days() ) ); ?>"
				data-daydiv="<?php echo esc_attr( wp_json_encode( Helper::get_time_day_division_of_event( $event ) ) ); ?>"
				data-imageposition="<?php echo esc_attr( $image_display_position ); ?>"
				class="sugar-calendar-event-list-block__listview__event">
				<?php if ( $event_view->should_display_date_cards() ) : ?>
					<div class="sugar-calendar-event-list-block__listview__event__day">
						<div class="sugar-calendar-event-list-block__listview__event__day__block">
							<div class="sugar-calendar-event-list-block__listview__event__day__block-name">
								<?php echo esc_html( \Sugar_Calendar\Helpers::get_weekday_abbrev( $day ) ); ?>
							</div>
							<div class="sugar-calendar-event-list-block__listview__event__day__block-num">
								<?php echo esc_html( $day->format( 'd' ) ); ?>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<div class="sugar-calendar-event-list-block__listview__event__body">

					<div class="sugar-calendar-event-list-block__listview__event__body__content">
						<h4 class="sugar-calendar-event-list-block__event__title">
							<?php $event_view->render_title(); ?>
						</h4>
						<div class="sugar-calendar-event-list-block__listview__event__body__content__time sugar-calendar-event-list-block__event__datetime">
							<?php $event_view->render_date_time_with_icons(); ?>
						</div>
						<?php
						if ( $event_view->should_display_description() ) {
							?>
							<div class="sugar-calendar-event-list-block__listview__event__body__content__desc sugar-calendar-event-list-block__event__desc">
								<?php echo esc_html( $event_view->get_description_excerpt() ); ?>
							</div>
							<?php
						}
						?>
					</div>

					<?php if ( $event_view->should_display_featured_image() && $event_image ) : ?>
						<div class="sugar-calendar-event-list-block__listview__event__body__image">
							<div style="background-image: url('<?php echo esc_url( $event_image ); ?>');" class="sugar-calendar-event-list-block__listview__event__body__image__container">
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}
	}
	?>
</div>
