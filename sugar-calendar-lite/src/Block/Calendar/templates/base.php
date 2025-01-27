<?php
use Sugar_Calendar\Block\Common\Template;

/**
 * @var \Sugar_Calendar\Block\Calendar\CalendarView\Block $context
 */
?>

<div id="<?php echo esc_attr( $context->get_block_id() ); ?>"
	class="<?php echo esc_attr( implode( ' ', $context->get_classes() ) ); ?>"
	data-attributes="<?php echo esc_attr( wp_json_encode( $context->get_attributes() ) ); ?>"
	data-accentcolor="<?php echo esc_attr( $context->get_default_accent_color() ); ?>"
	data-ogday="<?php echo esc_attr( $context->get_day_num_without_zero() ); ?>"
	data-ogmonth="<?php echo esc_attr( $context->get_month_num_without_zero() ); ?>"
	data-ogyear="<?php echo esc_attr( $context->get_year() ); ?>"
	data-appearance="<?php echo esc_attr( $context->get_appearance_mode() ); ?>"
	style="--accent-color: <?php echo esc_attr( $context->get_default_accent_color() ); ?>"
>
	<?php
		Template::load( 'form', $context, 'common' );
		Template::load( 'popovers', $context, 'common' );
		Template::load( 'event-popover' );
		Template::load( 'controls', $context, 'common' );
	?>

	<div class="sugar-calendar-block__base-container">
		<?php $context->get_view()->render_base(); ?>
	</div>

	<div class="sugar-calendar-block__mobile_event_list">
		<div class="sugar-calendar-block__mobile_event_list__date"></div>
		<div class="sugar-calendar-block__mobile_event_list__events_container">
			<div class="sugar-calendar-block__calendar-month__body__day__events-container"></div>
		</div>
	</div>
</div>
