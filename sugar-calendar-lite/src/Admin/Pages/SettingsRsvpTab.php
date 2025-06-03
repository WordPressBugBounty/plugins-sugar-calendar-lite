<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Pages\Settings;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;

/**
 * RSV Settings tab.
 *
 * @since 3.7.0
 */
class SettingsRsvpTab extends Settings {

	/**
	 * Page tab slug.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public static function get_tab_slug() {

		return 'rsvp';
	}

	/**
	 * Page label.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'RSVP', 'sugar-calendar-lite' );
	}

	/**
	 * Page menu priority.
	 *
	 * @since 3.7.0
	 *
	 * @return int
	 */
	public static function get_priority() {

		return 50;
	}

	/**
	 * Display page.
	 *
	 * @since 3.7.0
	 */
	public function display() {
		?>
		<div id="sugar-calendar-settings" class="wrap sugar-calendar-admin-wrap">
			<?php UI::tabs( $this->get_tabs(), static::get_tab_slug() ); ?>
			<div class="sugar-calendar-admin-content">
				<h1 class="screen-reader-text"><?php esc_html_e( 'Settings', 'sugar-calendar-lite' ); ?></h1>
				<?php

				$heading_classes = [ 'sugar-calendar--pro-only', 'sugar-calendar__admin__settings__rsvp__heading' ];
				$license_notice  = '';

				if ( sugar_calendar()->get_license_type() === 'basic' ) {
					$heading_classes[] = 'sugar-calendar__admin__settings__heading__notice-after';
					$license_notice = sprintf(
						__( 'RSVP feature is available as an add-on. Please <a target="_blank" href="%1$s">upgrade your plan</a> to Plus, Pro or Elite, in order to get access to the RSVP add-on and others.', 'sugar-calendar-lite' ),
						esc_url(
							Helpers::get_utm_url(
								'https://sugarcalendar.com/account/licenses/',
								[
									'medium'  => 'rsvp-settings',
									'content' => 'upgrade your plan',
								]
							)
						)
					);
				}

				UI::heading(
					[
						'class' => $heading_classes,
						'title' => esc_html__( 'RSVP', 'sugar-calendar-lite' ),
					]
				);

				if ( ! empty( $license_notice ) ) {
					printf(
						'<div class="sugar-calendar__admin-notice"><p>%1$s</p></div>',
						wp_kses(
							$license_notice,
							[
								'a' => [
									'href'   => [],
									'target' => [],
								],
							]
						)
					);
				}

				printf(
					'<p class="desc">%1$s</p>',
					esc_html__( 'Easily manage event attendance with our RSVP addon. Allow guests to confirm attendance, provide contact information, and receive email confirmations. Ideal for weddings, parties, conferences, and more.', 'sugar-calendar-lite' )
				);

				if ( ! sugar_calendar()->is_pro() ) {
					UI::button(
						[
							'class'  => 'sugar-calendar-settings__tab__header__buy-pro-btn',
							'text'   => esc_html__( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ),
							'size'   => 'lg',
							'link'   => esc_url(
								Helpers::get_upgrade_link(
									[
										'medium'  => 'rsvp-settings',
										'content' => 'Upgrade to Sugar Calendar Pro Top',
									]
								)
							),
							'target' => '_blank',
						]
					);
				}

				$assets_url  = SC_PLUGIN_ASSETS_URL . 'images/rsvp/';
				$screenshots = [
					[
						'url'           => $assets_url . 'adding-rsvp.png',
						'url_thumbnail' => $assets_url . 'adding-rsvp-thumb.png',
						'title'         => __( 'Adding RSVP', 'sugar-calendar-lite' ),
					],
					[
						'url'           => $assets_url . 'rsvp-frontend.png',
						'url_thumbnail' => $assets_url . 'rsvp-frontend-thumb.png',
						'title'         => __( 'RSVP Frontend', 'sugar-calendar-lite' ),
					],
				];
				?>
				<div class="sugar-calendar-education-preview">
					<?php foreach ( $screenshots as $screenshot ) : ?>
						<figure>
							<a href="<?php echo esc_url( $screenshot['url'] ); ?>" data-lity data-lity-desc="<?php echo esc_attr( $screenshot['title'] ); ?>">
								<img src="<?php echo esc_url( $screenshot['url_thumbnail'] ); ?>" alt="<?php echo esc_attr( $screenshot['title'] ); ?>">
							</a>
							<figcaption><?php echo esc_html( $screenshot['title'] ); ?></figcaption>
						</figure>
					<?php endforeach; ?>
				</div>

				<div class="sugar-calendar-education-features">
					<h4><?php esc_html_e( 'Unlock These Awesome RSVP Features!', 'sugar-calendar-lite' ); ?></h4>
					<ul>
						<?php
						$features = [
							__( 'Effortlessly Collect RSVPs', 'sugar-calendar-lite' ),
							__( 'Control Your Capacity', 'sugar-calendar-lite' ),
							__( 'Manage Plus-Ones with Ease', 'sugar-calendar-lite' ),
							__( 'Export Your Data', 'sugar-calendar-lite' ),
							__( 'Keep Attendees Informed', 'sugar-calendar-lite' ),
							__( 'Block Out Spam', 'sugar-calendar-lite' ),
						];

						foreach ( $features as $feature ) {
							printf(
								'<li>%s</li>',
								esc_html( $feature )
							);
						}
						?>
					</ul>
				</div>

				<?php
				if ( sugar_calendar()->is_pro() ) {
					$cta_text = __( 'Install RSVP add-on', 'sugar-calendar-lite' );
					$cta_link = Helpers::get_utm_url(
						'https://sugarcalendar.com/account/downloads/',
						[
							'medium'  => 'rsvp-settings',
							'content' => 'Install RSVP add-on',
						]
					);

					if ( sugar_calendar()->get_license_type() === 'basic' ) {
						$cta_text = __( 'Upgrade Now', 'sugar-calendar-lite' );
						$cta_link = Helpers::get_utm_url(
							'https://sugarcalendar.com/account/licenses/',
							[
								'medium'  => 'rsvp-settings',
								'content' => 'Upgrade Now',
							]
						);
					}
				} else {
					$cta_text = __( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' );
					$cta_link = Helpers::get_upgrade_link(
						[
							'medium'  => 'rsvp-settings',
							'content' => 'Upgrade to Sugar Calendar Pro Bottom',
						]
					);
				}

				UI::button(
					[
						'text'   => esc_html( $cta_text ),
						'size'   => 'lg',
						'link'   => esc_url( $cta_link ),
						'target' => '_blank',
					]
				);
				?>
			</div>
		</div>
		<?php
	}
}
