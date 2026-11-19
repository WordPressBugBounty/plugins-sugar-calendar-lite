<?php
/**
 * License and version strip (the `dashboard/license-plugin-version` recipe).
 *
 * @since 3.18.0
 *
 * @var string $type    Recipe type: `lite`, `pro`, `pro-update`, `pro-expired-license` or `no-license`.
 * @var string $icon    Font Awesome icon name, such as `fa6-solid--crown`; empty for none.
 * @var string $name    Edition name.
 * @var string $version Plugin version.
 * @var array  $cta     `label`, `url`, `style`, `target_blank`; empty for none.
 * @var string $notice  Notice under the strip; empty for none.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="sc-dashboard-license-plugin-version sc-reset-deep" data-type="<?php echo esc_attr( $type ); ?>">
	<?php // Wraps only in a column narrower than 400px, e.g. the two-column sidebar at tablet widths. ?>
	<div class="sc-dashboard-license-plugin-version__head sc:flex-wrap sc:h-auto sc:min-h-[60px] sc:py-[10px]">
		<?php if ( $icon !== '' ) : ?>
			<span class="sc:icon-[<?php echo esc_attr( $icon ); ?>] sc-dashboard-license-plugin-version__icon" aria-hidden="true"></span>
		<?php endif; ?>
		<span class="sc-dashboard-license-plugin-version__name sc-field-label"><?php echo esc_html( $name ); ?></span>
		<span class="sc-dashboard-license-plugin-version__version sc-field-description"><?php echo esc_html( $version ); ?></span>
		<?php if ( ! empty( $cta ) ) : ?>
			<a class="sc-button sc-button--<?php echo esc_attr( $cta['style'] ); ?>"
					data-size="sm"
					href="<?php echo esc_url( $cta['url'] ); ?>"
					<?php echo $cta['target_blank'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $cta['label'] ); ?></a>
		<?php endif; ?>
	</div>
	<?php if ( $notice !== '' ) : ?>
		<p class="sc-dashboard-license-plugin-version__message sc-field-description"><?php echo esc_html( $notice ); ?></p>
	<?php endif; ?>
</div>
