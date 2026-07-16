<?php

namespace Sugar_Calendar\Integrations\OAuthRelay\Credits;

use Sugar_Calendar\Helpers as Sugar_Calendar_Helpers;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;

/**
 * Renders the Integration Usage card shown on the License & Usage settings tab.
 *
 * Extracted from SettingsLicenseUsageTab for SRP — the license-key concerns
 * stay on the tab class; this is a self-contained CreditsPresenter consumer.
 * CSS classes here (e.g. `.sugar-calendar-usage-card__bar-segment`,
 * `__legend-series`, `__legend-value`) are asserted directly by
 * zoom-integration-license-usage-tab.spec.ts, and `data-usage-segment` /
 * `data-usage-tooltip` / `data-tooltip-*` are read by
 * assets/js/admin-integration-usage.js — keep them exact.
 *
 * @since 3.12.0
 */
class UsageCardRenderer {

	/**
	 * Render the Integration Usage section: heading + description + the card
	 * (only when credit data exists).
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

		$this->render_heading( $presenter );

		$is_maxed   = $presenter->is_maxed();
		$card_class = 'sugar-calendar-usage-card' . ( $is_maxed ? ' sugar-calendar-usage-card--maxed' : '' );
		$segments   = $presenter->segments();
		?>
		<div class="sugar-calendar-usage-cards">
			<div class="<?php echo esc_attr( $card_class ); ?>">
				<div class="sugar-calendar-usage-card__body">
					<div class="sugar-calendar-usage-card__header">
						<div class="sugar-calendar-usage-card__icon">
							<?php
							// Static plugin SVG (tools/wrench icon); not user data.
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo $this->get_usage_icon_svg();
							?>
						</div>
						<div class="sugar-calendar-usage-card__info">
							<div class="sugar-calendar-usage-card__label-row">
								<span class="sugar-calendar-usage-card__label"><?php esc_html_e( 'Integration Usage', 'sugar-calendar-lite' ); ?></span>
								<?php if ( $is_maxed ) : ?>
									<span class="sugar-calendar-usage-card__badge"><?php esc_html_e( 'Out of Usage!', 'sugar-calendar-lite' ); ?></span>
								<?php endif; ?>
							</div>
							<div class="sugar-calendar-usage-card__stats">
								<span class="sugar-calendar-usage-card__count">
									<span class="sugar-calendar-usage-card__used"><?php echo esc_html( $presenter->used_display() ); ?></span>
									<span class="sugar-calendar-usage-card__total">/ <?php echo esc_html( $presenter->limit_display() ); ?></span>
								</span>
								<span class="sugar-calendar-usage-card__percentage">
									<?php
									printf(
										/* translators: %d - percentage of usage credits consumed. */
										esc_html__( '%d%% used', 'sugar-calendar-lite' ),
										(int) $presenter->percentage()
									);
									?>
								</span>
							</div>
						</div>
					</div>

					<?php $this->render_bar( $presenter, $segments ); ?>

					<?php $this->render_license_note(); ?>

					<?php if ( $is_maxed ) : ?>
						<p class="sugar-calendar-usage-card__maxed-text">
							<?php esc_html_e( "You've hit your usage limit for this month! Features that use credits will be temporarily disabled until your usage reset or your limit is increased.", 'sugar-calendar-lite' ); ?>
						</p>
						<?php if ( $presenter->show_upgrade() ) : ?>
							<a class="sugar-calendar-usage-card__upgrade-btn"
								href="<?php echo esc_url( Helpers::get_upgrade_link( [ 'content' => 'usage-credits-upgrade', 'medium' => 'license-usage' ] ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound ?>"
								target="_blank" rel="noopener noreferrer">+ <?php esc_html_e( 'Upgrade For More', 'sugar-calendar-lite' ); ?></a>
						<?php endif; ?>
					<?php endif; ?>
				</div>

				<?php $this->render_legend( $presenter, $segments ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Usage section heading and description.
	 *
	 * Monthly (Pro/licensed): "Usage for {Month Year}" + the resets-monthly copy.
	 * Lifetime (Lite/unlicensed): "Lifetime Usage" without the reset sentence.
	 *
	 * @since 3.12.0
	 *
	 * @param CreditsPresenter $presenter Credits presenter.
	 *
	 * @return void
	 */
	private function render_heading( CreditsPresenter $presenter ) {

		$docs_url = Helpers::get_utm_url(
			'https://sugarcalendar.com/docs/events/understanding-integration-credits-and-limits/',
			[
				'content' => 'usage-credits',
				'medium'  => 'license-usage',
			]
		);

		$learn_more = sprintf(
			' <a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( $docs_url ),
			esc_html__( 'Learn more', 'sugar-calendar-lite' )
		);

		$description = esc_html__( 'Usage credits allow you to access advanced features such as Integrations. These limits depend on your license plan and can be increased by upgrading.', 'sugar-calendar-lite' );

		if ( $presenter->is_monthly() ) {
			$description .= ' ' . sprintf(
				/* translators: %1$s, %2$s - opening and closing <strong> tags. */
				esc_html__( 'Remember, %1$susage resets every month on the 1st%2$s.', 'sugar-calendar-lite' ),
				'<strong>',
				'</strong>'
			);
		}

		$description .= $learn_more;

		UI::heading(
			[
				'title'       => esc_html( $presenter->heading() ),
				'description' => $description,
			]
		);
	}

	/**
	 * Render the segmented usage bar (one segment per integration) + tooltip.
	 *
	 * @since 3.12.0
	 *
	 * @param CreditsPresenter $presenter Credits presenter.
	 * @param array[]          $segments  Per-integration segments.
	 *
	 * @return void
	 */
	private function render_bar( CreditsPresenter $presenter, array $segments ) {
		?>
		<div class="sugar-calendar-usage-card__bar">
			<?php if ( ! empty( $segments ) ) : ?>
				<?php
				foreach ( $segments as $segment ) :
					/* translators: %s - number of credits used by this integration. */
					$credits_label = sprintf( __( 'Credits: %s', 'sugar-calendar-lite' ), number_format_i18n( $segment['count'] ) );
					?>
					<div class="sugar-calendar-usage-card__bar-segment"
						style="width: <?php echo esc_attr( $segment['percentage'] ); ?>%; background: <?php echo esc_attr( $segment['color'] ); ?>;"
						data-usage-segment
						data-name="<?php echo esc_attr( $segment['name'] ); ?>"
						data-credits="<?php echo esc_attr( $credits_label ); ?>"
						data-color="<?php echo esc_attr( $segment['color'] ); ?>"></div>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="sugar-calendar-usage-card__bar-fill" style="width: <?php echo esc_attr( $presenter->percentage() ); ?>%"></div>
			<?php endif; ?>
			<div class="sugar-calendar-usage-card__tooltip" style="display: none;" data-usage-tooltip>
				<div class="sugar-calendar-usage-card__tooltip-arrow"></div>
				<div class="sugar-calendar-usage-card__tooltip-header">
					<span class="sugar-calendar-usage-card__tooltip-dot" data-tooltip-dot></span>
					<span class="sugar-calendar-usage-card__tooltip-name" data-tooltip-name></span>
				</div>
				<span class="sugar-calendar-usage-card__tooltip-credits" data-tooltip-credits></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the legend (one wrapping row per integration: dot, name, count, percent).
	 *
	 * @since 3.12.0
	 *
	 * @param CreditsPresenter $presenter Credits presenter.
	 * @param array[]          $segments  Per-integration segments.
	 *
	 * @return void
	 */
	private function render_legend( CreditsPresenter $presenter, array $segments ) {

		if ( empty( $segments ) ) {
			return;
		}
		?>
		<div class="sugar-calendar-usage-card__legend-wrapper">
			<div class="sugar-calendar-usage-card__legend">
				<?php
				foreach ( $segments as $segment ) :
					?>
					<div class="sugar-calendar-usage-card__legend-series">
						<div class="sugar-calendar-usage-card__legend-label">
							<span class="sugar-calendar-usage-card__legend-dot" style="background: <?php echo esc_attr( $segment['color'] ); ?>;"></span>
							<span class="sugar-calendar-usage-card__legend-name"><?php echo esc_html( $segment['name'] ); ?></span>
						</div>
						<span class="sugar-calendar-usage-card__legend-value"><?php echo esc_html( number_format_i18n( $segment['count'] ) ); ?></span>
						<span class="sugar-calendar-usage-card__legend-percent">(<?php echo esc_html( $presenter->segment_percentage_display( $segment ) ); ?>%)</span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the "license isn't active" fallback note.
	 *
	 * Shows only on Pro builds where the license is NOT valid — in that state
	 * integrations fall back to Lite credit limits. Lite builds never show it.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	private function render_license_note() {

		if ( ! sugar_calendar()->is_pro() ) {
			return;
		}

		// On Pro builds, show the note only when the license is NOT valid — in
		// that state integrations fall back to Lite credit limits.
		if ( Sugar_Calendar_Helpers::is_license_valid() ) {
			return;
		}
		?>
		<p class="sugar-calendar-usage-card__license-note">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %1$s, %2$s - opening and closing anchor tags linking to the license key field. */
					__( 'Your license isn\'t active, so integrations are using Lite credit limits. %1$sActivate your license%2$s to unlock your Pro credits.', 'sugar-calendar-lite' ),
					'<a href="#sugar-calendar-setting-license-key">',
					'</a>'
				),
				[
					'a' => [ 'href' => [] ],
				]
			);
			?>
		</p>
		<?php
	}

	/**
	 * Static tools/wrench icon for the usage card.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	private function get_usage_icon_svg(): string {

		return '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5.66016 3.00781V4.375H5.6875C5.85156 2.13281 7.73828 0.355469 10.0352 0.355469C10.582 0.355469 11.1289 0.464844 11.5938 0.65625C11.8672 0.738281 11.9219 1.09375 11.7305 1.3125L9.29688 3.71875C9.21484 3.80078 9.16016 3.91016 9.16016 4.04688V5.16797C9.16016 5.41406 9.35156 5.60547 9.59766 5.60547H10.7461C10.8555 5.60547 10.9648 5.55078 11.0469 5.46875L13.4805 3.0625C13.6719 2.84375 14.0273 2.89844 14.1367 3.17188C14.3008 3.66406 14.4102 4.18359 14.4102 4.73047C14.4102 6.39844 13.4805 7.82031 12.1406 8.58594L14.3555 10.8008C14.875 11.3203 14.875 12.1406 14.3555 12.6602L12.7148 14.3008C12.1953 14.8203 11.375 14.8203 10.8555 14.3008L7.41016 10.8555C6.67188 10.1172 6.50781 8.99609 6.91797 8.09375L4.42969 5.60547H3.0625C2.78906 5.60547 2.51562 5.46875 2.35156 5.22266L0.164062 1.96875C0.0546875 1.80469 0.0820312 1.55859 0.21875 1.42188L1.47656 0.164062C1.61328 0.0273438 1.85938 0 2.02344 0.109375L5.27734 2.29688C5.52344 2.43359 5.66016 2.70703 5.66016 3.00781ZM5.44141 8.47656C5.25 9.48828 5.49609 10.5547 6.15234 11.4023L3.55469 14C2.78906 14.7656 1.53125 14.7656 0.765625 14C0 13.2344 0 11.9766 0.765625 11.2109L4.48438 7.51953L5.44141 8.47656Z" fill="#2271B1"></path></svg>';
	}
}
