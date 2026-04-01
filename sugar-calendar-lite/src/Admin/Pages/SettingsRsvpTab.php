<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Pages\Settings;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Helpers as BaseHelpers;

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
	 * Register page hooks.
	 *
	 * @since 3.11.0
	 */
	public function hooks() {

		parent::hooks();

		// Enqueue addon install script for Pro users with non-basic licenses.
		if ( sugar_calendar()->is_pro() && sugar_calendar()->get_license_type() !== 'basic' ) {
			add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_addon_install_assets' ] );
		}
	}

	/**
	 * Enqueue addon install assets.
	 *
	 * @since 3.11.0
	 *
	 * @return void
	 */
	public function enqueue_addon_install_assets() {

		wp_enqueue_script(
			'sugar-calendar-admin-education-addon-install',
			SC_PLUGIN_ASSETS_URL . 'js/admin-education-addon-install' . WP::asset_min() . '.js',
			[ 'jquery' ],
			BaseHelpers::get_asset_version(),
			true
		);

		wp_localize_script(
			'sugar-calendar-admin-education-addon-install',
			'sugar_calendar_admin_education_addon_install',
			[
				'ajax_url'      => sugar_calendar()->get_admin()->ajax_url(),
				'error_message' => esc_html__( 'Could not install the add-on. Please try again or install it from the Addons page.', 'sugar-calendar-lite' ),
			]
		);
	}

	/**
	 * Display page.
	 *
	 * @since 3.7.0
	 * @since 3.11.0 Allow RSVP add-on install and activate through the button.
	 */
	public function display() {
		?>
		<div id="sugar-calendar-settings" class="wrap sugar-calendar-admin-wrap sugar-calendar-admin__settings__tab-wrap">
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
				if ( sugar_calendar()->is_pro() && sugar_calendar()->get_license_type() !== 'basic' ) {
					$addon  = sugar_calendar()->get_addons()->get_addon( 'sc-rsvp' );
					$status = ! empty( $addon['status'] ) ? $addon['status'] : 'missing';

					if ( $status === 'missing' && ! empty( $addon['url'] ) ) {
						$cta_text   = __( 'Install RSVP add-on', 'sugar-calendar-lite' );
						$cta_action = 'install';
						$cta_plugin = $addon['url'];
					} elseif ( $status === 'installed' ) {
						// Installed but not active.
						$cta_text   = __( 'Activate RSVP add-on', 'sugar-calendar-lite' );
						$cta_action = 'activate';
						$cta_plugin = 'sc-rsvp/sc-rsvp.php';
					}

					if ( ! empty( $cta_action ) ) {
						UI::button(
							[
								'class'  => 'sugar-calendar-education-addon-install',
								'text'   => esc_html( $cta_text ),
								'size'   => 'lg',
								'submit' => false,
								'data'   => [
									'action' => $cta_action,
									'plugin' => $cta_plugin,
								],
							]
						);
					} else {
						// No valid license key — fall back to external link.
						UI::button(
							[
								'text'   => esc_html__( 'Install RSVP add-on', 'sugar-calendar-lite' ),
								'size'   => 'lg',
								'link'   => esc_url(
									Helpers::get_utm_url(
										'https://sugarcalendar.com/account/licenses/',
										[
											'medium'  => 'rsvp-settings',
											'content' => 'Install RSVP add-on',
										]
									)
								),
								'target' => '_blank',
							]
						);
					}
				} elseif ( sugar_calendar()->is_pro() && sugar_calendar()->get_license_type() === 'basic' ) {
					UI::button(
						[
							'text'   => esc_html__( 'Upgrade Now', 'sugar-calendar-lite' ),
							'size'   => 'lg',
							'link'   => esc_url(
								Helpers::get_utm_url(
									'https://sugarcalendar.com/account/licenses/',
									[
										'medium'  => 'rsvp-settings',
										'content' => 'Upgrade Now',
									]
								)
							),
							'target' => '_blank',
						]
					);
				} else {
					UI::button(
						[
							'text'   => esc_html__( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ),
							'size'   => 'lg',
							'link'   => esc_url(
								Helpers::get_upgrade_link(
									[
										'medium'  => 'rsvp-settings',
										'content' => 'Upgrade to Sugar Calendar Pro Bottom',
									]
								)
							),
							'target' => '_blank',
						]
					);
				}
				?>
			</div>
		</div>
		<?php
	}
}
