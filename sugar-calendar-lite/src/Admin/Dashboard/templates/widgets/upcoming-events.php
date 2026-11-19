<?php
/**
 * Upcoming events.
 *
 * @since 3.18.0
 *
 * @var array  $events        Rows: `month`, `day`, `name`, `url`, `meta`, `is_recurring`, `is_featured`, `count` (`sold`, `capacity`, `is_sold_out`; empty when there is nothing to count).
 * @var string $new_event_url Add New Event screen.
 */

use Sugar_Calendar\Features\FeaturedEvents\Common\Markup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( empty( $events ) ) : ?>
	<div class="sc-dashboard-upcoming-events sc-reset-deep" data-state="no-data">
		<div class="sc-dashboard-upcoming-events__empty">
			<span class="sc-dashboard-upcoming-events__empty-icon-wrap">
				<span class="sc:icon-[fa6-solid--calendar-check] sc-dashboard-upcoming-events__empty-icon" aria-hidden="true"></span>
			</span>
			<p class="sc-dashboard-upcoming-events__empty-text sc-field-label"><?php esc_html_e( 'No Upcoming Events!', 'sugar-calendar-lite' ); ?></p>
		</div>
		<a class="sc-button sc-button--tertiary" href="<?php echo esc_url( $new_event_url ); ?>"><?php esc_html_e( 'Add New Event', 'sugar-calendar-lite' ); ?></a>
	</div>
<?php else : ?>
	<div class="sc-dashboard-upcoming-events sc-reset-deep">
		<ul class="sc-dashboard-upcoming-events__list">
			<?php foreach ( $events as $event ) : ?>
				<li class="sc-dashboard-upcoming-events__event">
					<span class="sc-dashboard-upcoming-events__date">
						<span class="sc-dashboard-upcoming-events__month sc-badge-lg"><?php echo esc_html( $event['month'] ); ?></span>
						<span class="sc-dashboard-upcoming-events__day sc-section-title"><?php echo esc_html( $event['day'] ); ?></span>
					</span>
					<div class="sc-dashboard-upcoming-events__text">
						<?php // One line: whitespace beside the star would widen its 4px gap. ?>
						<a class="sc-dashboard-upcoming-events__title sc-field-label" href="<?php echo esc_url( $event['url'] ); ?>" title="<?php echo esc_attr( $event['name'] ); ?>"><?php echo $event['is_featured'] ? Markup::get_star( 'dashboard' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup::get_star() escapes its own output. ?><?php echo esc_html( $event['name'] ); ?></a>
						<span class="sc-dashboard-upcoming-events__meta sc-field-description">
							<?php if ( $event['is_recurring'] ) : ?>
								<span class="sc:icon-[fa6-solid--repeat] sc-dashboard-upcoming-events__meta-icon" aria-hidden="true"></span>
								<span class="sc:sr-only"><?php esc_html_e( 'Recurring event.', 'sugar-calendar-lite' ); ?></span>
							<?php endif; ?>
							<span class="sc-dashboard-upcoming-events__meta-text" title="<?php echo esc_attr( $event['meta'] ); ?>"><?php echo esc_html( $event['meta'] ); ?></span>
						</span>
					</div>
					<?php if ( ! empty( $event['count'] ) ) : ?>
						<span class="sc-dashboard-upcoming-events__count sc-field-description-small"<?php echo $event['count']['is_sold_out'] ? ' data-sold-out' : ''; ?>>
							<span class="sc-dashboard-upcoming-events__count-current"><?php echo esc_html( number_format_i18n( $event['count']['sold'] ) ); ?></span>/<?php echo $event['count']['capacity'] > 0 ? esc_html( number_format_i18n( $event['count']['capacity'] ) ) : '&infin;'; ?>
							<?php if ( $event['count']['is_sold_out'] ) : ?>
								<span class="sc:sr-only"><?php esc_html_e( 'Sold out.', 'sugar-calendar-lite' ); ?></span>
							<?php endif; ?>
						</span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<a class="sc-button sc-button--tertiary" href="<?php echo esc_url( $new_event_url ); ?>"><?php esc_html_e( 'Add New Event', 'sugar-calendar-lite' ); ?></a>
	</div>
<?php endif; ?>
