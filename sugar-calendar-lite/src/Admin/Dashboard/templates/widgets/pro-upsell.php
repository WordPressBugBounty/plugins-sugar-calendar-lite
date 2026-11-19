<?php
/**
 * Pro upsell features.
 *
 * @since 3.18.0
 *
 * @var array $features Upsell cards: `title`, `description` and `icon`, a whole Iconify class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The grid holds two columns while each card keeps 280px, otherwise one.
?>
<ul class="sc:self-stretch sc:grid sc:grid-cols-[repeat(auto-fill,minmax(min(100%,max(280px,calc((100%_-_var(--sc-spacing-20,20px))/2))),1fr))] sc:gap-[var(--sc-spacing-20,20px)] sc-reset">
	<?php foreach ( $features as $feature ) : ?>
		<li class="sc:flex sc:items-start sc:gap-5 sc:overflow-hidden sc-reset">
			<div class="sc:size-10 sc:bg-[var(--sc-color-brand-orange-0,#fff3ec)] sc:rounded-sm sc:inline-flex sc:flex-col sc:justify-center sc:items-center sc-reset">
				<i aria-hidden="true" class="sc:text-center sc:text-[var(--sc-color-brand-orange-50,#ff8845)] sc:text-xl sc:font-black sc:uppercase sc:leading-5 sc:tracking-wide <?php echo esc_attr( $feature['icon'] ); ?> sc-reset"></i>
			</div>
			<div class="sc:flex-1 sc:inline-flex sc:flex-col sc:items-start sc-reset">
				<div class="sc:inline-flex sc:items-center sc:gap-2.5 sc-reset">
					<div class="sc:text-[var(--sc-text-primary,#2c3338)] sc:text-[15px] sc:font-semibold sc:leading-[22.50px] sc-reset"><?php echo esc_html( $feature['title'] ); ?></div>
				</div>
				<p class="sc:self-stretch sc:text-[var(--sc-text-secondary,#50575e)] sc:text-[13px] sc:font-normal sc:leading-5 sc:line-clamp-1 sc-reset" title="<?php echo esc_attr( $feature['description'] ); ?>"><?php echo esc_html( $feature['description'] ); ?></p>
			</div>
		</li>
	<?php endforeach; ?>
</ul>
