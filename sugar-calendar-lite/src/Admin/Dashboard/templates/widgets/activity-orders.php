<?php
/**
 * Activity widget, Orders panel.
 *
 * The live metric switch sits in the panel, not in `__details` as in the recipe, so the metric
 * selection in admin-dashboard-activity.js (`[data-panel="orders"] > …`) skips the teaser's switch.
 *
 * @since 3.18.0
 *
 * @var string $variant      `data`, `reconnect`, `connect`, `empty` or `empty-range`.
 * @var bool   $has_data     Whether the variant shows the orders: `data` or `reconnect`.
 * @var string $metric       Selected metric: `payments` or `sales`.
 * @var array  $metrics      Total Payments and Total Sales switch, for activity-orders-metrics; `has_data` only.
 * @var array  $rows         Latest orders in the range for activity-orders-table.
 * @var string $cards        Inline cards HTML.
 * @var string $orders_url   The ticketing Orders screen.
 * @var array  $overlay      Popup for `connect`, `empty` and `empty-range`.
 * @var array  $teaser       `connect` only: `series`, `metrics` and `rows` of example data.
 */

use Sugar_Calendar\Admin\Dashboard\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$graph = Templates::get_html(
	'widgets/activity-graph',
	[
		'metric'     => $has_data ? $metric : 'payments',
		'switch'     => $has_data,
		'teaser'     => $variant === 'connect' ? $teaser['series'] : [],
		'label'      => __( 'Orders per day for the selected period', 'sugar-calendar-lite' ),
		'decorative' => ! $has_data,
	]
);

echo $graph; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes its own output.
?>
<?php if ( $has_data ) : ?>
	<?php echo Templates::get_html( 'widgets/activity-orders-metrics', $metrics ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes its own output. ?>

	<div class="sc-dashboard-registrations-orders__details">
		<div class="sc-dashboard-registrations-orders__table-section">
			<?php echo Templates::get_html( 'widgets/activity-orders-table', [ 'rows' => $rows ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes its own output. ?>
			<?php echo $cards; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The inline-card template escapes its own output. ?>
		</div>

		<div class="sc-dashboard-registrations-orders__footer sc:max-phone:flex-wrap">
			<p class="sc-dashboard-registrations-orders__footer-text sc-field-text"><?php esc_html_e( 'Get access to all ticket payments and order details', 'sugar-calendar-lite' ); ?></p>
			<a class="sc-button sc-button--primary" href="<?php echo esc_url( $orders_url ); ?>"><?php esc_html_e( 'View All Orders', 'sugar-calendar-lite' ); ?></a>
		</div>
	</div>
<?php else : ?>
	<?php if ( $variant === 'connect' ) : ?>
		<?php // The recipe fades example data through `data-state` on its root, which holds both tabs here. ?>
		<div class="sc-dashboard-registrations-orders__details sc:opacity-40" aria-hidden="true" inert>
			<?php echo Templates::get_html( 'widgets/activity-orders-metrics', $teaser['metrics'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes its own output. ?>

			<div class="sc-dashboard-registrations-orders__table-section">
				<?php echo Templates::get_html( 'widgets/activity-orders-table', [ 'rows' => $teaser['rows'] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes its own output. ?>
			</div>
		</div>
	<?php endif; ?>

	<?php echo Templates::get_html( 'widget-overlay', $overlay ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes its own output. ?>
<?php endif; ?>
