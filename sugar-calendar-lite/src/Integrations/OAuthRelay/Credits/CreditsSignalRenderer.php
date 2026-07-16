<?php

namespace Sugar_Calendar\Integrations\OAuthRelay\Credits;

use Sugar_Calendar\Helpers\Helpers;

/**
 * Renders the usage indicator pill + popover shown in the Settings →
 * Integrations page header.
 *
 * Extracted from SettingsIntegrationsTab for SRP — this widget is a
 * self-contained CreditsPresenter consumer with no tab-chrome concerns of
 * its own. The popover's `#sugar-calendar-credits-popover` id and the
 * toggle's `data-credits-toggle` attribute are read by
 * assets/js/admin-integration-usage.js — keep them exact.
 *
 * @since 3.12.0
 */
class CreditsSignalRenderer {

	/**
	 * Render the indicator. No-op when there is no credits data.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function render() {

		$presenter = CreditsPresenter::from_service( new CreditsService() );

		if ( ! $presenter->has_data() ) {
			return;
		}

		$maxed      = $presenter->is_maxed();
		$percent    = $presenter->percentage();
		$reset_text = $presenter->reset_text();
		?>
		<div class="sugar-calendar-credits-signal <?php echo $maxed ? 'is-maxed' : ''; ?>">
			<button type="button" class="sugar-calendar-credits-signal__toggle" data-credits-toggle
				aria-expanded="false" aria-controls="sugar-calendar-credits-popover">
				<?php echo $this->ring_svg( $percent ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="sugar-calendar-credits-signal__label">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					printf(
						/* translators: %s - percent of integration usage consumed, wrapped in emphasis markup. */
						esc_html__( '%s Integration Usage Used', 'sugar-calendar-lite' ),
						'<strong>' . esc_html( sprintf( '%d%%', (int) $percent ) ) . '</strong>'
					);
					?>
				</span>
			</button>

			<div class="sugar-calendar-credits-popover <?php echo $maxed ? 'is-maxed' : ''; ?>" id="sugar-calendar-credits-popover">
				<p class="sugar-calendar-credits-popover__count">
					<strong><?php echo esc_html( $presenter->used_display() ); ?></strong>
					/ <?php echo esc_html( $presenter->limit_display() ); ?>
					<?php
					if ( $presenter->is_monthly() ) {
						printf(
							/* translators: %s - usage period month and year. */
							' ' . esc_html__( 'used for %s', 'sugar-calendar-lite' ),
							esc_html( $presenter->usage_period_label() )
						);
					} else {
						echo ' ' . esc_html__( 'used lifetime', 'sugar-calendar-lite' );
					}
					?>
				</p>
				<div class="sugar-calendar-credits-popover__bar">
					<span style="width: <?php echo esc_attr( $percent ); ?>%;"></span>
				</div>
				<div class="sugar-calendar-credits-popover__footer">
					<?php if ( $reset_text !== '' ) : ?>
						<span class="sugar-calendar-credits-popover__reset"><?php echo esc_html( $reset_text ); ?></span>
					<?php endif; ?>
					<?php if ( $presenter->show_upgrade() ) : ?>
						<?php
						$upgrade_url = Helpers::get_upgrade_link(
							[
								'content' => 'usage-popover',
								'medium'  => 'integrations',
							]
						);
						?>
						<a class="sugar-calendar-credits-popover__upgrade"
							href="<?php echo esc_url( $upgrade_url ); ?>"
							target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade for more', 'sugar-calendar-lite' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Build the circular usage ring as inline SVG.
	 *
	 * @since 3.12.0
	 *
	 * @param int $percent Percent 0-100.
	 *
	 * @return string
	 */
	private function ring_svg( int $percent ): string {

		$radius        = 9;
		$circumference = 2 * M_PI * $radius;
		$offset        = $circumference * ( 1 - min( max( $percent, 0 ), 100 ) / 100 );

		return sprintf(
			'<svg class="sugar-calendar-credits-signal__ring" width="22" height="22" viewBox="0 0 22 22" aria-hidden="true">'
			. '<circle cx="11" cy="11" r="%1$s" fill="none" stroke-width="3" class="sugar-calendar-credits-signal__ring-track"/>'
			. '<circle cx="11" cy="11" r="%1$s" fill="none" stroke-width="3" class="sugar-calendar-credits-signal__ring-fill" '
			. 'stroke-dasharray="%2$s" stroke-dashoffset="%3$s" transform="rotate(-90 11 11)"/>'
			. '</svg>',
			esc_attr( (string) $radius ),
			esc_attr( (string) round( $circumference, 2 ) ),
			esc_attr( (string) round( $offset, 2 ) )
		);
	}
}
