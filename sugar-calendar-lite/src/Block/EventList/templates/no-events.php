<?php
/**
 * @var \Sugar_Calendar\Block\EventList\EventListView\AbstractView $context
 */
?>
<div class="sugar-calendar-block__base-container__no-events">
	<div class="sugar-calendar-block__base-container__no-events__msg">
		<?php echo esc_html( $context->get_block()->get_no_events_msg() ); ?>
	</div>
</div>
