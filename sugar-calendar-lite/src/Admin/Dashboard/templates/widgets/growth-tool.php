<?php
/**
 * One Recommended Growth Tools tile.
 *
 * @since 3.18.0
 *
 * @var array $tile `key`, `title`, `description`, `icon` (URL), `state` (`install`, `activate` or `installed`) and its `label`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<li class="sc-dashboard-recommended-growth-tools__tool">
	<div class="sc-dashboard-recommended-growth-tools__tool-head">
		<span class="sc-dashboard-recommended-growth-tools__logo" aria-hidden="true"><img class="sc-dashboard-recommended-growth-tools__logo-img" src="<?php echo esc_url( $tile['icon'] ); ?>" alt=""></span>
		<span class="sc-dashboard-recommended-growth-tools__name sc-field-label" title="<?php echo esc_attr( $tile['title'] ); ?>"><?php echo esc_html( $tile['title'] ); ?></span>
	</div>

	<p class="sc-dashboard-recommended-growth-tools__description sc-field-description-small"><?php echo esc_html( $tile['description'] ); ?></p>

	<?php if ( $tile['state'] === 'installed' ) : ?>
		<span class="sc-dashboard-recommended-growth-tools__installed sc-buttons-small">
			<?php echo esc_html( $tile['label'] ); ?>
			<span class="sc:icon-[fa6-regular--circle-check] sc-dashboard-recommended-growth-tools__installed-icon" aria-hidden="true"></span>
		</span>
	<?php else : ?>
		<?php // The reset reverts WP's `.screen-reader-text` inside `sc-reset-deep`; `sc:sr-only` outranks it. ?>
		<button type="button" class="sc-button sc-button--tertiary" data-size="sm" data-key="<?php echo esc_attr( $tile['key'] ); ?>" data-action="<?php echo esc_attr( $tile['state'] ); ?>">
			<span data-label><?php echo esc_html( $tile['label'] ); ?></span>
			<span class="sc:sr-only"><?php echo esc_html( $tile['title'] ); ?></span>
		</button>
	<?php endif; ?>

	<?php // In every state: a failed install can still leave the plugin active, with a message to show. ?>
	<p class="sc-dashboard-recommended-growth-tools__status sc-field-description-small" data-status role="status" aria-live="polite" hidden></p>
</li>
