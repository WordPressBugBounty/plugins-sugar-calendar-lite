<?php
use Sugar_Calendar\Helper;
use Sugar_Calendar\Block\EventList\EventListView\EventView;

/**
 * @var \Sugar_Calendar\Block\EventList\EventListView\ListView $context
 */
?>
<div class="sugar-calendar-event-list-block__listview sugar-calendar-block__events-display-container">
	<?php
	$events = $context->get_block()->get_events();

	foreach ( $context->get_block()->get_week_period() as $day ) {
		foreach ( $events[ $day->format( 'Y-m-d' ) ] as $event ) {

			if ( in_array( $event->id, $context->get_block()->get_displayed_events(), true ) ) {
				// We should only display an event once.
				continue;
			}

			$context->get_block()->add_displayed_event( $event->id );

			$event_view = new EventView( $event, $context->get_block() );
			?>
			<div
				data-eventdays="<?php echo esc_attr( wp_json_encode( $event_view->get_event_days() ) ); ?>"
				data-daydiv="<?php echo esc_attr( wp_json_encode( Helper::get_time_day_division_of_event( $event ) ) ); ?>"
				class="sugar-calendar-event-list-block__listview__event">
				<div class="sugar-calendar-event-list-block__listview__event__day">
					<div class="sugar-calendar-event-list-block__listview__event__day__block">
						<div class="sugar-calendar-event-list-block__listview__event__day__block-name">
							<?php echo esc_html( $day->format( 'D' ) ); ?>
						</div>
						<div class="sugar-calendar-event-list-block__listview__event__day__block-num">
							<?php echo esc_html( $day->format( 'd' ) ); ?>
						</div>
					</div>
				</div>
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
					<?php
					$event_image = get_the_post_thumbnail_url( $event->object_id );

					if ( $event_view->should_display_featured_image() && $event_image ) {
						?>
						<div class="sugar-calendar-event-list-block__listview__event__body__image">
							<div style="background-image: url('<?php echo esc_url( $event_image ); ?>');" class="sugar-calendar-event-list-block__listview__event__body__image__container">
							</div>
						</div>
						<?php
					}
					?>
				</div>
			</div>
			<?php
		}
	}
	?>
</div>
