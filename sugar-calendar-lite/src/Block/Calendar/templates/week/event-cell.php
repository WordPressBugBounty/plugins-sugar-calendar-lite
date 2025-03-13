<?php

use Sugar_Calendar\Helper;

/**
 * @var Sugar_Calendar\Block\Calendar\CalendarView\Week\EventCell $context
 */
?>

<div
	data-daydate="<?php echo esc_attr( $context->get_event_day_duration() ); ?>"
	data-eventurl="<?php echo esc_url( Helper::get_event_frontend_url( $context->get_event() ) ); ?>"
	data-eventid="<?php echo esc_attr( $context->get_event()->id ); ?>"
	data-eventobjid="<?php echo esc_attr( $context->get_event()->object_id ); ?>"
	data-calendarsinfo="<?php echo esc_attr( wp_json_encode( $context->get_calendars_info() ) ); ?>"
	data-daydiv="<?php echo esc_attr( wp_json_encode( Helper::get_time_day_division_of_event( $context->get_event() ) ) ); ?>"
	style="<?php echo esc_attr( $context->get_style() ); ?>"
	class="<?php echo esc_attr( implode( ' ', $context->get_classes() ) ); ?>">

	<div style="background: <?php echo esc_attr( $context->get_color() ); ?>" class="sugar-calendar-block__event-cell__bg-overlay"></div>

	<div class="sugar-calendar-block__calendar-week__event-cell__container">

		<div class="sugar-calendar-block__event-cell__time <?php echo esc_attr( Helper::get_event_time_recur_class( $context->get_event() ) ); ?>">
			<?php
			echo wp_kses(
				$context->get_event()->get_event_time(),
				[
					'time' => [
						'datetime'      => true,
						'title'         => true,
						'data-timezone' => true,
					],
				]
			);
			?>
		</div>
		<div class="sugar-calendar-block__event-cell__title">
			<?php echo esc_html( $context->get_event_title() ); ?>
		</div>

		<div class="sugar-calendar-block__event-cell__desc">
			<?php echo esc_html( Helper::get_event_excerpt( $context->get_event() ) ); ?>
		</div>
	</div>
</div>
