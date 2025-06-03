<?php
/**
 * @var \Sugar_Calendar\Block\Common\AbstractBlock $context
 */

// Block attributes.
$attributes = $context->get_attributes();

// Show block headers?.
$should_show_header = $context->should_render_block_header();

// Exit early if headers are not shown.
if ( ! $should_show_header ) {
	return;
}

// Display control elements.
$should_show_filters               = $context->should_render_filters();
$should_show_display_mode_settings = $context->should_render_display_mode_settings();
$should_show_search                = $context->should_render_search();
$should_show_left_controls         = $context->should_render_block_left_controls();

// Parent control class.
$control_class = 'sugar-calendar-block__controls';

// Add class if all controls are hidden.
if (
	! $should_show_filters
	&&
	! $should_show_display_mode_settings
	&&
	! $should_show_search
) {
	$control_class .= ' sugar-calendar-block__controls--right-hidden';
}

?>
<div class="<?php echo esc_attr( $control_class ); ?>">

	<div class="sugar-calendar-block__controls__left">

		<?php if ( $should_show_left_controls ) : ?>
			<button class="sugar-calendar-block__controls__left__date">
				<span class="sugar-calendar-block__view-heading"><?php echo esc_html( $context->get_heading() ); ?></span>
				<span class="sugar-calendar-block__view-heading-mobile"><?php echo esc_html( $context->get_heading( true ) ); ?></span>
				<?php
				$heading_year_style = '';

				if ( $context->get_display_mode() !== 'month' ) {
					$heading_year_style = 'display: none;';
				}
				?>
				<span style="<?php echo esc_attr( $heading_year_style ); ?>" class="sugar-calendar-block__view-heading--year">
					<?php echo esc_html( $context->get_additional_heading() ); ?>
				</span>

				<svg width="13" height="8" viewBox="0 0 13 8" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M12.0586 1.34375C12.1953 1.45312 12.1953 1.67188 12.0586 1.80859L6.34375 7.52344C6.20703 7.66016 6.01562 7.66016 5.87891 7.52344L0.164062 1.80859C0.0273438 1.67188 0.0273438 1.45312 0.164062 1.34375L0.683594 0.796875C0.820312 0.660156 1.03906 0.660156 1.14844 0.796875L6.125 5.74609L11.0742 0.796875C11.1836 0.660156 11.4023 0.660156 11.5391 0.796875L12.0586 1.34375Z" fill="currentColor"/>
				</svg>
			</button>

			<div class="sugar-calendar-block__controls__left__pagination">
				<button
					class="sugar-calendar-block__controls__left__pagination__prev"
					aria-label="<?php echo esc_attr( $context->get_previous_pagination_display() ); ?>"
				>
					<svg width="6" height="11" viewBox="0 0 6 11" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M5.41406 10.6094C5.29688 10.7266 5.13281 10.7266 5.01562 10.6094L0.09375 5.71094C0 5.59375 0 5.42969 0.09375 5.3125L5.01562 0.414062C5.13281 0.296875 5.29688 0.296875 5.41406 0.414062L5.88281 0.859375C5.97656 0.976562 5.97656 1.16406 5.88281 1.25781L1.64062 5.5L5.88281 9.76562C5.97656 9.85938 5.97656 10.0469 5.88281 10.1641L5.41406 10.6094Z" fill="currentColor"/>
					</svg>
				</button>
				<div class="sugar-calendar-block__controls__left__pagination__divider"></div>
				<button class="sugar-calendar-block__controls__left__pagination__current">
					<?php echo esc_html( $context->get_current_pagination_display() ); ?>
				</button>
				<div class="sugar-calendar-block__controls__left__pagination__divider"></div>
				<button
					class="sugar-calendar-block__controls__left__pagination__next"
					aria-label="<?php echo esc_attr( $context->get_next_pagination_display() ); ?>"
				>
					<svg width="6" height="11" viewBox="0 0 6 11" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M0.5625 0.414062C0.679688 0.296875 0.84375 0.296875 0.960938 0.414062L5.88281 5.3125C5.97656 5.42969 5.97656 5.59375 5.88281 5.71094L0.960938 10.6094C0.84375 10.7266 0.679688 10.7266 0.5625 10.6094L0.09375 10.1641C0 10.0469 0 9.85938 0.09375 9.76562L4.33594 5.5L0.09375 1.25781C0 1.16406 0 0.976562 0.09375 0.859375L0.5625 0.414062Z" fill="currentColor"/>
					</svg>
				</button>
			</div>
		<?php endif; ?>
	</div>

	<div class="sugar-calendar-block__controls__right">

		<?php if ( $should_show_filters ) : ?>
			<div class="sugar-calendar-block__controls__right__settings">
				<button
					class="sugar-calendar-block__controls__right__settings__btn sugar-calendar-block__controls__settings__btn"
					aria-label="<?php esc_attr_e( 'Filters', 'sugar-calendar-lite' ); ?>"
				>
					<svg width="14" height="13" viewBox="0 0 14 13" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
						<path d="M13.5625 1.71875C13.7812 1.71875 14 1.9375 14 2.15625V2.59375C14 2.83984 13.7812 3.03125 13.5625 3.03125H7.875V3.6875C7.875 3.93359 7.65625 4.125 7.4375 4.125H6.5625C6.31641 4.125 6.125 3.93359 6.125 3.6875V3.03125H0.4375C0.191406 3.03125 0 2.83984 0 2.59375V2.15625C0 1.9375 0.191406 1.71875 0.4375 1.71875H6.125V1.0625C6.125 0.84375 6.31641 0.625 6.5625 0.625H7.4375C7.65625 0.625 7.875 0.84375 7.875 1.0625V1.71875H13.5625ZM13.5625 10.4688C13.7812 10.4688 14 10.6875 14 10.9062V11.3438C14 11.5898 13.7812 11.7812 13.5625 11.7812H4.375V12.4375C4.375 12.6836 4.15625 12.875 3.9375 12.875H3.0625C2.81641 12.875 2.625 12.6836 2.625 12.4375V11.7812H0.4375C0.191406 11.7812 0 11.5898 0 11.3438V10.9062C0 10.6875 0.191406 10.4688 0.4375 10.4688H2.625V9.8125C2.625 9.59375 2.81641 9.375 3.0625 9.375H3.9375C4.15625 9.375 4.375 9.59375 4.375 9.8125V10.4688H13.5625ZM13.5625 6.09375C13.7812 6.09375 14 6.3125 14 6.53125V6.96875C14 7.21484 13.7812 7.40625 13.5625 7.40625H11.375V8.0625C11.375 8.30859 11.1562 8.5 10.9375 8.5H10.0625C9.81641 8.5 9.625 8.30859 9.625 8.0625V7.40625H0.4375C0.191406 7.40625 0 7.21484 0 6.96875V6.53125C0 6.3125 0.191406 6.09375 0.4375 6.09375H9.625V5.4375C9.625 5.21875 9.81641 5 10.0625 5H10.9375C11.1562 5 11.375 5.21875 11.375 5.4375V6.09375H13.5625Z" fill="currentColor"/>
					</svg>
				</button>
			</div>
		<?php endif; ?>

		<?php if ( $should_show_display_mode_settings ) : ?>
			<div class="sugar-calendar-block__controls__right__view">
				<button class="sugar-calendar-block__controls__right__view__btn sugar-calendar-block__controls__settings__btn">
					<span><?php echo esc_html( $context->get_display_mode_string() ); ?></span>
					<svg width="13" height="8" viewBox="0 0 13 8" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
						<path d="M12.0586 1.34375C12.1953 1.45312 12.1953 1.67188 12.0586 1.80859L6.34375 7.52344C6.20703 7.66016 6.01562 7.66016 5.87891 7.52344L0.164062 1.80859C0.0273438 1.67188 0.0273438 1.45312 0.164062 1.34375L0.683594 0.796875C0.820312 0.660156 1.03906 0.660156 1.14844 0.796875L6.125 5.74609L11.0742 0.796875C11.1836 0.660156 11.4023 0.660156 11.5391 0.796875L12.0586 1.34375Z" fill="currentColor"/>
					</svg>
				</button>
			</div>
		<?php endif; ?>

		<?php if ( $should_show_search ) : ?>
			<div class="sugar-calendar-block__controls__right__search">
				<svg class="sugar-calendar-block__controls__right__search__icon" width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M13.8906 13.5742C14.0273 13.7109 14.0273 13.9297 13.8906 14.0391L13.2617 14.668C13.1523 14.8047 12.9336 14.8047 12.7969 14.668L9.48828 11.3594C9.43359 11.2773 9.40625 11.1953 9.40625 11.1133V10.7578C8.39453 11.6055 7.10938 12.125 5.6875 12.125C2.54297 12.125 0 9.58203 0 6.4375C0 3.32031 2.54297 0.75 5.6875 0.75C8.80469 0.75 11.375 3.32031 11.375 6.4375C11.375 7.85938 10.8281 9.17188 9.98047 10.1562H10.3359C10.418 10.1562 10.5 10.2109 10.582 10.2656L13.8906 13.5742ZM5.6875 10.8125C8.09375 10.8125 10.0625 8.87109 10.0625 6.4375C10.0625 4.03125 8.09375 2.0625 5.6875 2.0625C3.25391 2.0625 1.3125 4.03125 1.3125 6.4375C1.3125 8.87109 3.25391 10.8125 5.6875 10.8125Z" fill="currentColor"/>
				</svg>
				<input
					class="sugar-calendar-block__controls__right__search__field"
					type="text"
					autocomplete="off"
					placeholder="<?php esc_attr_e( 'Search Events', 'sugar-calendar-lite' ); ?>"
				/>
				<svg class="sugar-calendar-block__controls__right__search__clear" width="20" height="20" viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
					<path fill-rule="evenodd" clip-rule="evenodd" d="M9.99994 10.8839L13.0935 13.9775L13.9774 13.0936L10.8838 10L13.9774 6.90641L13.0935 6.02253L9.99994 9.11612L6.90634 6.02252L6.02246 6.90641L9.11606 10L6.02247 13.0936L6.90635 13.9775L9.99994 10.8839Z" fill="currentColor"/>
				</svg>
			</div>
		<?php endif; ?>
	</div>
</div>
