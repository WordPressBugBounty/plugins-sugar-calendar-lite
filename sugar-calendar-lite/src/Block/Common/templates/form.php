<?php
use Sugar_Calendar\Options;

/**
 * @var \Sugar_Calendar\Block\Calendar\CalendarView\Block $context
 */
?>
<form class="sugar-calendar-block-settings">
	<input type="hidden" name="sc_calendar_id" value="<?php echo esc_attr( $context->get_block_id() ); ?>" />
	<input type="hidden" name="sc_month" value="<?php echo esc_attr( $context->get_month_num_without_zero() ); ?>" />
	<input type="hidden" name="sc_year" value="<?php echo esc_attr( $context->get_year() ); ?>" />
	<input type="hidden" name="sc_day" value="<?php echo esc_attr( $context->get_day_num_without_zero() ); ?>" />
	<input type="hidden" name="sc_calendars" value="" />
	<input type="hidden" name="sc_display" value="<?php echo esc_attr( $context->get_display_mode() ); ?>" />
	<input type="hidden" name="sc_search" value="" />
	<input type="hidden" name="sc_visitor_tz_convert" value="<?php echo esc_attr( absint( Options::get( 'timezone_convert' ) ) ); ?>">
	<input type="hidden" name="sc_calendars_filter" value="<?php echo esc_attr( implode( ',', $context->get_calendars() ) ); ?>" />
	<input type="hidden" name="sc_venues_filter" value="<?php echo esc_attr( implode( ',', $context->get_venues() ) ); ?>" />
	<input type="hidden" name="sc_speakers_filter" value="<?php echo esc_attr( implode( ',', $context->get_speakers() ) ); ?>" />
	<input type="hidden" name="sc_tags_filter" value="<?php echo esc_attr( implode( ',', $context->get_tags() ) ); ?>" />
</form>
