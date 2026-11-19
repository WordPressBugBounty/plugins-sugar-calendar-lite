<?php
/**
 * Orders metric switch: Total Payments and Total Sales, as inline Stat Cards.
 *
 * The cards are buttons here, so the recipe's `div` text column is a `span`, and the button's
 * centred text and default cursor are reset with utilities.
 *
 * @since 3.18.0
 *
 * @var array  $metrics  Metric id => `label`, `value` (formatted), `delta` (float|null), `icon`.
 * @var string $selected Selected metric id.
 */

use Sugar_Calendar\Admin\Dashboard\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="sc-dashboard-registrations-orders__stats sugar-calendar-dashboard-orders-metrics sc:max-phone:grid-cols-1" role="group" aria-label="<?php esc_attr_e( 'Orders graph', 'sugar-calendar-lite' ); ?>">
	<?php foreach ( $metrics as $metric_id => $metric ) : ?>
		<?php $is_selected = $metric_id === $selected; ?>
		<button type="button"
				class="sc-dashboard-stat-card sugar-calendar-dashboard-orders-metric sc:text-left sc:cursor-pointer<?php echo $is_selected ? ' is-selected' : ''; ?>"
				data-type="revenue"
				data-inline
				<?php echo $is_selected ? 'data-active' : ''; ?>
				data-metric="<?php echo esc_attr( $metric_id ); ?>"
				aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>">
			<span class="sc-dashboard-stat-card__icon-wrap">
				<span class="sc:icon-[<?php echo esc_attr( $metric['icon'] ); ?>] sc-dashboard-stat-card__icon" aria-hidden="true"></span>
			</span>

			<span class="sc-dashboard-stat-card__text">
				<span class="sc-dashboard-stat-card__label sc-field-text"><?php echo esc_html( $metric['label'] ); ?></span>
				<span class="sc-dashboard-stat-card__value sc-title-bar" title="<?php echo esc_attr( wp_strip_all_tags( $metric['value'] ) ); ?>"><?php echo wp_kses_post( $metric['value'] ); ?></span>
			</span>

			<?php if ( $metric['delta'] !== null ) : ?>
				<?php echo Templates::get_html( 'delta', [ 'delta' => $metric['delta'] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes its own output. ?>
			<?php endif; ?>
		</button>
	<?php endforeach; ?>
</div>
