<?php

namespace Sugar_Calendar\Admin\SetupChecklist;

use Sugar_Calendar\Helpers\Helpers;

/**
 * Renders checklist upsell and Growth Tool content.
 *
 * @since 3.15.0
 */
class PromotionsRenderer {

	/**
	 * Render the Lite upsell group contents.
	 *
	 * @since 3.15.0
	 *
	 * @param array $cards Upsell cards.
	 */
	public function render_upsell( $cards ) {

		?>
		<ul class="sugar-calendar-checklist__promo-grid">
			<?php foreach ( $cards as $card ) : ?>
				<li class="sugar-calendar-checklist__promo-card">
					<span class="sugar-calendar-checklist__promo-icon">
						<span class="<?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>
					</span>
					<div class="sugar-calendar-checklist__text">
						<h3 class="sugar-calendar-checklist__promo-title"><?php echo esc_html( $card['title'] ); ?></h3>
						<p class="sugar-calendar-checklist__promo-description"><?php echo esc_html( $card['description'] ); ?></p>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>

		<div class="sugar-calendar-checklist__promo-footer">
			<p class="sugar-calendar-checklist__promo-footer-text">
				<?php esc_html_e( 'Plus dozens of other powerful features to help your events succeed.', 'sugar-calendar-lite' ); ?>
			</p>
			<div class="sugar-calendar-checklist__promo-cta">
				<span class="sugar-calendar-checklist__discount">
					<img src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/setup-checklist/discount-badge.svg' ); ?>"
							width="16" height="16" alt="">
					<span class="sugar-calendar-checklist__discount-amount"><?php esc_html_e( '$50 OFF', 'sugar-calendar-lite' ); ?></span>
					<span><?php esc_html_e( 'for Sugar Calendar Lite users!', 'sugar-calendar-lite' ); ?></span>
				</span>
				<a class="sc-button sc-button--primary"
						href="
                        <?php
						echo esc_url(
							Helpers::get_upgrade_link(
								[
									'medium'  => 'setup-checklist',
									'content' => 'Upgrade to Pro',
								]
							)
						);
						?>
                        "
						target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Upgrade to Pro', 'sugar-calendar-lite' ); ?>
					<span class="sc-button__icon sc:icon-[fa6-solid--arrow-right]" aria-hidden="true"></span>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Growth Tools group contents.
	 *
	 * Does not reuse the Addons page's `.addon-item` markup or installer data
	 * attributes, because delegated install handlers are bound to them.
	 *
	 * @since 3.15.0
	 *
	 * @param array $tools Growth tool cards.
	 */
	public function render_growth_tools( $tools ) {

		?>
		<ul class="sugar-calendar-checklist__tools-grid">
			<?php foreach ( $tools as $tool ) : ?>
				<?php $installed = ( $tool['action_state'] ?? '' ) === 'installed'; ?>
				<li class="sugar-calendar-checklist__tool">
					<span class="sugar-calendar-checklist__tool-icon">
						<img src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/setup-checklist/' . $tool['icon'] . '.svg' ); ?>"
								width="40" height="40"
								alt="
                                <?php
								/* translators: %s - sister plugin name. */
								echo esc_attr( sprintf( __( '%s logo', 'sugar-calendar-lite' ), $tool['title'] ) );
								?>
                                ">
					</span>
					<div class="sugar-calendar-checklist__text">
						<h3 class="sugar-calendar-checklist__tool-title"><?php echo esc_html( $tool['title'] ); ?></h3>
						<p class="sugar-calendar-checklist__tool-description"><?php echo esc_html( $tool['description'] ); ?></p>
						<button type="button"
								class="sugar-calendar-checklist__tool-action<?php echo $installed ? ' sugar-calendar-checklist__tool-action--installed' : ''; ?>"
								data-checklist-task="growth_tool"
								data-checklist-action="<?php echo esc_attr( $tool['action_state'] ); ?>"
								data-key="<?php echo esc_attr( $tool['key'] ); ?>"
								<?php disabled( $installed, true ); ?>>
							<?php echo esc_html( $tool['action_label'] ); ?>
							<?php if ( $installed ) : ?>
								<span class="sc:icon-[fa6-regular--circle-check]" aria-hidden="true"></span>
							<?php endif; ?>
						</button>
						<p class="sugar-calendar-checklist__feedback" role="status" aria-live="polite" hidden></p>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}
}
