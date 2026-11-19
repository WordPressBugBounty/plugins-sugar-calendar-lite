<?php
/**
 * One integration or add-on card.
 *
 * @since 3.18.0
 *
 * @var array $card `slug`, `name`, `logo`, `state`, `action`, `url`, `target_blank`, `badge`, `task`, `message`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="sc-dashboard-integration-card sc-dashboard-integration-card--<?php echo esc_attr( $card['slug'] ); ?><?php echo $card['state'] === 'upgrade' ? ' sc-dashboard-integration-card--upgrade' : ''; ?>">
	<span class="sc-dashboard-integration-card__logo" aria-hidden="true"><img class="sc-dashboard-integration-card__logo-img" src="<?php echo esc_url( $card['logo'] ); ?>" alt=""></span>
	<div class="sc-dashboard-integration-card__text">
		<span class="sc-dashboard-integration-card__name sc-field-label" title="<?php echo esc_attr( $card['name'] ); ?>"><?php echo esc_html( $card['name'] ); ?></span>

		<?php if ( $card['url'] !== '' ) : ?>
			<a class="sc-dashboard-integration-card__action sc-buttons-small-underline"
					href="<?php echo esc_url( $card['url'] ); ?>"
					<?php echo $card['target_blank'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $card['action'] ); ?></a>
		<?php elseif ( $card['task'] !== '' ) : ?>
			<button type="button" class="sc-dashboard-integration-card__action sc-buttons-small-underline" data-addon="<?php echo esc_attr( $card['slug'] ); ?>" data-action="<?php echo esc_attr( $card['task'] ); ?>">
				<span data-label><?php echo esc_html( $card['action'] ); ?></span>
				<span class="sc:sr-only"><?php echo esc_html( $card['name'] ); ?></span>
			</button>
		<?php endif; ?>

		<p class="sc-dashboard-integration-card__message sc-field-description-small" data-status role="status" aria-live="polite" <?php echo $card['message'] === '' ? 'hidden' : ''; ?>><?php echo esc_html( $card['message'] ); ?></p>
	</div>

	<?php if ( ! empty( $card['badge'] ) ) : ?>
		<span class="sc-badge sc-badge-sm sc-badge--<?php echo esc_attr( $card['badge']['color'] ); ?>" data-size="sm" data-position="corner"><?php echo esc_html( $card['badge']['text'] ); ?></span>
	<?php endif; ?>
</div>
