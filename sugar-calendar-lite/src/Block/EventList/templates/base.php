<?php
use Sugar_Calendar\Block\Common\Template;
use Sugar_Calendar\Block\EventList\EventListView;

/**
 * @var EventListView\Block $context
 */

// Label for groupped events.
if ( $context->should_group_events_by_week() ) {

	$previous_button_label = __( 'Previous Week', 'sugar-calendar-lite' );
	$next_button_label     = __( 'Next Week', 'sugar-calendar-lite' );

} else {

	$previous_button_label = __( 'Previous', 'sugar-calendar-lite' );
	$next_button_label     = __( 'Next', 'sugar-calendar-lite' );
}

?>
<div id="<?php echo esc_attr( $context->get_block_id() ); ?>"
	class="<?php echo esc_attr( implode( ' ', $context->get_classes() ) ); ?>"
	data-attributes="<?php echo esc_attr( wp_json_encode( $context->get_attributes() ) ); ?>"
	data-ogday="<?php echo esc_attr( $context->get_day_num_without_zero() ); ?>"
	data-ogmonth="<?php echo esc_attr( $context->get_month_num_without_zero() ); ?>"
	data-ogyear="<?php echo esc_attr( $context->get_year() ); ?>"
	data-appearance="<?php echo esc_attr( $context->get_appearance_mode() ); ?>"
	style="<?php echo esc_attr( $context->get_styles() ); ?>"
>
	<?php
	Template::load( 'form', $context, 'common' );

	if ( $context->get_display_mode() !== EventListView\PlainView::DISPLAY_MODE ) {
		Template::load( 'popovers', $context, 'common' );
		Template::load( 'controls', $context, 'common' );
	}
	?>

	<div class="sugar-calendar-event-list-block__base-container sugar-calendar-block__base-container">
		<?php $context->get_view()->render_base(); ?>
	</div>

	<?php if ( $context->should_render_block_footer() ) : ?>
		<div class="sugar-calendar-event-list-block__footer">
			<div class="sugar-calendar-event-list-block__footer__prev">
				<button class="sugar-calendar-event-list-block__footer__prev_btn"
					<?php
						echo (
								$context->should_render_block_footer()
								&&
								! $context->should_group_events_by_week()
							)
							? 'disabled'
							: '';
					?>
				>
					<svg width="6" height="11" viewBox="0 0 6 11" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M5.41406 10.6094C5.29688 10.7266 5.13281 10.7266 5.01562 10.6094L0.09375 5.71094C0 5.59375 0 5.42969 0.09375 5.3125L5.01562 0.414062C5.13281 0.296875 5.29688 0.296875 5.41406 0.414062L5.88281 0.859375C5.97656 0.976562 5.97656 1.16406 5.88281 1.25781L1.64062 5.5L5.88281 9.76562C5.97656 9.85938 5.97656 10.0469 5.88281 10.1641L5.41406 10.6094Z" fill="currentColor"></path>
					</svg>
					<?php echo esc_html( $previous_button_label ); ?>
				</button>
			</div>

			<div class="sugar-calendar-event-list-block__footer__next">
				<button class="sugar-calendar-event-list-block__footer__next_btn">
					<?php echo esc_html( $next_button_label ); ?>
					<svg width="6" height="11" viewBox="0 0 6 11" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M0.5625 0.414062C0.679688 0.296875 0.84375 0.296875 0.960938 0.414062L5.88281 5.3125C5.97656 5.42969 5.97656 5.59375 5.88281 5.71094L0.960938 10.6094C0.84375 10.7266 0.679688 10.7266 0.5625 10.6094L0.09375 10.1641C0 10.0469 0 9.85938 0.09375 9.76562L4.33594 5.5L0.09375 1.25781C0 1.16406 0 0.976562 0.09375 0.859375L0.5625 0.414062Z" fill="currentColor"></path>
					</svg>
				</button>
			</div>
		</div>
	<?php endif; ?>
</div>
