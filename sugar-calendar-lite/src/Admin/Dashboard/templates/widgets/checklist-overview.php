<?php
/**
 * Setup Checklist Overview.
 *
 * @since 3.18.0
 *
 * @var array  $sections      Groups: `title`, `complete`, `items`, `hidden_open_count`.
 * @var string $checklist_url Setup Checklist page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="sc-dashboard-event-setup-checkup sc-reset-deep">
	<?php foreach ( $sections as $section ) : ?>
		<div class="sc-dashboard-event-setup-checkup__group">
			<div class="sc-dashboard-event-setup-checkup__group-head">
				<h3 class="sc-dashboard-event-setup-checkup__group-title sc-field-label"><?php echo esc_html( $section['title'] ); ?></h3>
				<span class="sc-badge sc-badge-sm sc-badge--<?php echo $section['complete'] ? 'green' : 'platinum'; ?>" data-size="sm">
					<?php echo $section['complete'] ? esc_html__( 'Complete', 'sugar-calendar-lite' ) : esc_html__( 'Incomplete', 'sugar-calendar-lite' ); ?>
				</span>
			</div>

			<?php if ( ! $section['complete'] ) : ?>
				<ul class="sc-dashboard-event-setup-checkup__items">
					<?php foreach ( $section['items'] as $item ) : ?>
						<li class="sc-dashboard-event-setup-checkup__item"<?php echo $item['complete'] ? ' data-done' : ''; ?>>
							<span class="sc:icon-[<?php echo $item['complete'] ? 'fa6-solid--circle-check' : 'fa6-regular--circle'; ?>] sc-dashboard-event-setup-checkup__item-icon" aria-hidden="true"></span>
							<?php if ( $item['url'] !== '' ) : ?>
								<a class="sc-dashboard-event-setup-checkup__item-label sc-field-label" href="<?php echo esc_url( $item['url'] ); ?>"<?php echo $item['external'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $item['title'] ); ?></a>
							<?php else : ?>
								<?php // The recipe keeps the label an anchor with no href when a step has no CTA. ?>
								<a class="sc-dashboard-event-setup-checkup__item-label sc-field-label"><?php echo esc_html( $item['title'] ); ?></a>
							<?php endif; ?>
							<?php if ( $item['complete'] ) : ?>
								<?php // `screen-reader-text` would be undone by the reset's `all: revert`; the utility outranks it. ?>
								<span class="sc:sr-only"><?php esc_html_e( 'Done.', 'sugar-calendar-lite' ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>

				<?php if ( $section['hidden_open_count'] > 0 ) : ?>
					<p class="sc-dashboard-event-setup-checkup__more sc-field-text">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d - number of unfinished checklist steps not shown. */
								_n( '+%d More Step', '+%d More Steps', $section['hidden_open_count'], 'sugar-calendar-lite' ),
								$section['hidden_open_count']
							)
						);
						?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>

<a class="sc-dashboard-widget__footer-link sc-buttons-underline sc-reset-deep" href="<?php echo esc_url( $checklist_url ); ?>">
	<?php esc_html_e( 'View Setup Checklist', 'sugar-calendar-lite' ); ?>
	<span class="sc:icon-[fa6-solid--arrow-right] sc-dashboard-widget__footer-icon" aria-hidden="true"></span>
</a>
