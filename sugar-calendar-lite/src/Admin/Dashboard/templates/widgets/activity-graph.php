<?php
/**
 * One Activity graph in the recipe's chart slot.
 *
 * Chart.js sizes the canvas from its container, so the slot takes a fixed height (the recipe
 * gives only a min-height) and the canvas fills it; without both the canvas grows on each resize.
 *
 * @since 3.18.0
 *
 * @var string $metric     Series key the canvas draws: `attendees`, `payments` or `sales`.
 * @var bool   $switch     Whether the Orders metric switch retargets this canvas.
 * @var array  $teaser     Static example rows, faded like the rest of the example data. Empty draws the range series.
 * @var string $label      Accessible name for the graph.
 * @var bool   $decorative Whether the graph sits behind a popup, hidden from assistive tech.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="sc-dashboard-registrations-orders__chart sc:h-[343px]<?php echo ! empty( $teaser ) ? ' sc:opacity-40' : ''; ?>"<?php echo $decorative ? ' aria-hidden="true"' : ''; ?>>
	<canvas class="sugar-calendar-dashboard-activity__chart sc:size-full"
			data-metric="<?php echo esc_attr( $metric ); ?>"
			<?php echo $switch ? 'data-metric-switch' : ''; ?>
			<?php if ( ! empty( $teaser ) ) : ?>
				data-teaser="<?php echo esc_attr( wp_json_encode( $teaser ) ); ?>"
			<?php endif; ?>
			role="img"
			aria-label="<?php echo esc_attr( $label ); ?>"></canvas>
</div>
