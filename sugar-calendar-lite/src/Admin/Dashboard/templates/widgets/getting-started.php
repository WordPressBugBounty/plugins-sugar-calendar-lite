<?php
/**
 * Getting Started links.
 *
 * @since 3.18.0
 *
 * @var array $links    Links: `label`, `url`.
 * @var array $view_all `label`, `url`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<ul class="sc-dashboard-getting-started sc-reset-deep">
	<?php foreach ( $links as $guide ) : ?>
		<li class="sc-dashboard-getting-started__item">
			<a class="sc-dashboard-getting-started__link sc-field-text" href="<?php echo esc_url( $guide['url'] ); ?>" target="_blank" rel="noopener noreferrer"><span class="sc:icon-[fa6-regular--file-lines] sc-dashboard-getting-started__icon" aria-hidden="true"></span><?php echo esc_html( $guide['label'] ); ?></a>
		</li>
	<?php endforeach; ?>
</ul>
<a class="sc-dashboard-widget__footer-link sc-buttons-underline sc-reset-deep" href="<?php echo esc_url( $view_all['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $view_all['label'] ); ?><span class="sc:icon-[fa6-solid--arrow-right] sc-dashboard-widget__footer-icon" aria-hidden="true"></span></a>
