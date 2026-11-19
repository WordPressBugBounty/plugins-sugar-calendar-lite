<?php
/**
 * Dismissible Product Education Banner inside a widget body.
 *
 * @since 3.18.0
 *
 * @var array $card `id`, `icon`, `title`, `text`, `cta` (`label`, `url`, `target_blank`; empty for a card with no button).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$target = empty( $card['cta']['target_blank'] ) ? '' : ' target="_blank" rel="noopener noreferrer"';
?>
<div class="sc-dashboard-product-education-banner sugar-calendar-dashboard-notice" data-notice="<?php echo esc_attr( $card['id'] ); ?>">
	<span class="sc-dashboard-product-education-banner__icon-wrap">
		<span class="sc:icon-[<?php echo esc_attr( $card['icon'] ); ?>] sc-dashboard-product-education-banner__icon" aria-hidden="true"></span>
	</span>

	<div class="sc-dashboard-product-education-banner__text">
		<p class="sc-dashboard-product-education-banner__title sc-field-label"><?php echo esc_html( $card['title'] ); ?></p>
		<p class="sc-dashboard-product-education-banner__description sc-field-text">
			<?php echo esc_html( $card['text'] ); ?>
			<?php if ( ! empty( $card['cta'] ) ) : ?>
				<a class="sc-dashboard-product-education-banner__link sc-buttons-underline" href="<?php echo esc_url( $card['cta']['url'] ); ?>"<?php echo $target; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A static attribute string built above. ?>><?php echo esc_html( $card['cta']['label'] ); ?><span class="sc:icon-[fa6-solid--arrow-right] sc-dashboard-product-education-banner__link-icon" aria-hidden="true"></span></a>
			<?php endif; ?>
		</p>
	</div>

	<button type="button" class="sc-dashboard-product-education-banner__dismiss sugar-calendar-dashboard-notice__dismiss" title="<?php esc_attr_e( 'Dismiss', 'sugar-calendar-lite' ); ?>">
		<span class="sc:icon-[fa6-solid--xmark] sc-dashboard-product-education-banner__dismiss-icon" aria-hidden="true"></span>
	</button>
</div>
