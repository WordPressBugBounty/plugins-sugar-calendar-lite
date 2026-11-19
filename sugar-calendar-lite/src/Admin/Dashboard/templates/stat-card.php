<?php
/**
 * Stat card (the `dashboard/stat-card` recipe).
 *
 * Two-up follows the row's width, not the viewport: four across stops fitting a five-digit value
 * below 1160px. Tailwind emits container rules after `max-phone:`, so `phone:` keeps two-up off phones.
 *
 * @since 3.18.0
 *
 * @var array $card `label`, `value` (formatted; may hold a currency entity), `delta` (float|null), `icon`, `type` (the recipe's `data-type`).
 */

use Sugar_Calendar\Admin\Dashboard\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<section class="sc-dashboard-widget sc:flex-1 sc:min-w-0 sc:phone:@max-[1160px]:basis-[calc(50%-10px)] sc:max-phone:basis-full sc-reset-deep">
	<div class="sc-dashboard-widget__body" data-flush>
		<div class="sc-dashboard-stat-card" data-type="<?php echo esc_attr( $card['type'] ); ?>">
			<span class="sc-dashboard-stat-card__icon-wrap">
				<span class="sc:icon-[<?php echo esc_attr( $card['icon'] ); ?>] sc-dashboard-stat-card__icon" aria-hidden="true"></span>
			</span>
			<div class="sc-dashboard-stat-card__text">
				<span class="sc-dashboard-stat-card__label sc-field-text"><?php echo esc_html( $card['label'] ); ?></span>
				<span class="sc-dashboard-stat-card__value sc-prompt-header" title="<?php echo esc_attr( wp_strip_all_tags( $card['value'] ) ); ?>"><?php echo wp_kses_post( $card['value'] ); ?></span>
			</div>
			<?php if ( $card['delta'] !== null ) : ?>
				<?php echo Templates::get_html( 'delta', [ 'delta' => $card['delta'] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes its own output. ?>
			<?php endif; ?>
		</div>
	</div>
</section>
