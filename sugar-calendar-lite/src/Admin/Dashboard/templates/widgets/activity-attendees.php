<?php
/**
 * Activity widget, Attendees panel.
 *
 * Each column takes its Figma share of the 781px table (170/190/190/231), so a long value wraps in
 * its own cell, and the email may break anywhere. Below 600px Date is hidden; Email then drops its
 * right padding itself: the recipe's `:last-child` rule still matches the hidden Date. Date keeps
 * Figma's 12px right padding, which that rule takes off, and the event link Figma's 20px line height.
 *
 * @since 3.18.0
 *
 * @var string $variant `data`, `no-events`, `no-registrations` or `empty-range`.
 * @var array  $rows    Latest attendees in the range: `name`, `url`, `event`, `event_url` (empty for a deleted event), `email`, `date`.
 * @var string $cards   Inline cards HTML.
 * @var array  $overlay Popup for the empty variants: `title`, `text`, `cta`, `disclaimer`.
 */

use Sugar_Calendar\Admin\Dashboard\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_data = $variant === 'data';

$graph = Templates::get_html(
	'widgets/activity-graph',
	[
		'metric'     => 'attendees',
		'switch'     => false,
		'teaser'     => [],
		'label'      => __( 'Attendees per day for the selected period', 'sugar-calendar-lite' ),
		'decorative' => ! $is_data,
	]
);

echo $graph; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes its own output.
?>
<?php if ( ! $is_data ) : ?>
	<?php echo Templates::get_html( 'widget-overlay', $overlay ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes its own output. ?>
<?php else : ?>
	<div class="sc-dashboard-registrations-orders__details">
		<div class="sc-dashboard-registrations-orders__table-section">
			<?php // The side padding, given back by the margin, keeps the edge links' focus rings inside the scroll box's clip. ?>
			<div class="sc:overflow-x-auto sc:px-[4px] sc:-mx-[4px]">
				<table class="sc-dashboard-registrations-orders__table">
					<thead>
						<tr class="sc-dashboard-registrations-orders__row">
							<th class="sc-dashboard-registrations-orders__th sc-field-label sc:w-[21.77%]" scope="col"><?php esc_html_e( 'Attendee', 'sugar-calendar-lite' ); ?></th>
							<th class="sc-dashboard-registrations-orders__th sc-field-label sc:w-[24.33%]" scope="col"><?php esc_html_e( 'Event', 'sugar-calendar-lite' ); ?></th>
							<th class="sc-dashboard-registrations-orders__th sc-field-label sc:w-[24.33%] sc:max-phone:pr-0" scope="col"><?php esc_html_e( 'Email', 'sugar-calendar-lite' ); ?></th>
							<th class="sc-dashboard-registrations-orders__th sc-field-label sc:w-[29.57%] sc:pr-[var(--sc-padding-12,12px)] sc:max-phone:hidden" scope="col"><?php esc_html_e( 'Date', 'sugar-calendar-lite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr class="sc-dashboard-registrations-orders__row">
								<td class="sc-dashboard-registrations-orders__td sc-field-text"><a class="sc-dashboard-registrations-orders__link sc-field-label" href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['name'] ); ?></a></td>
								<td class="sc-dashboard-registrations-orders__td sc-field-text">
									<?php if ( $row['event_url'] !== '' ) : ?>
										<a class="sc-dashboard-registrations-orders__link sc-field-text sc:leading-[var(--sc-typography-line-height-20,20px)]" href="<?php echo esc_url( $row['event_url'] ); ?>"><?php echo esc_html( $row['event'] ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $row['event'] ); ?>
									<?php endif; ?>
								</td>
								<td class="sc-dashboard-registrations-orders__td sc-field-text sc:max-phone:pr-0 sc:wrap-anywhere">
									<?php if ( $row['email'] !== '' ) : ?>
										<a class="sc-dashboard-registrations-orders__email" href="<?php echo esc_url( 'mailto:' . $row['email'] ); ?>"><?php echo esc_html( $row['email'] ); ?></a>
									<?php endif; ?>
								</td>
								<td class="sc-dashboard-registrations-orders__td sc-field-text sc:pr-[var(--sc-padding-12,12px)] sc:max-phone:hidden"><?php echo esc_html( $row['date'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php echo $cards; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The inline-card template escapes its own output. ?>
		</div>
	</div>
<?php endif; ?>
