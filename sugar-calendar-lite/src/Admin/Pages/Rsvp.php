<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\PageAbstract;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers as BaseHelpers;

/**
 * RSVP page.
 *
 * @since 3.7.0
 */
class Rsvp extends PageAbstract {

	/**
	 * Register page hooks.
	 *
	 * @since 3.7.0
	 */
	public function hooks() {

		// Load assets.
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );

		// Admin subheader.
		add_action( 'in_admin_header', [ $this, 'display_admin_subheader' ] );

		// Remove "Screen Options".
		add_filter( 'screen_options_show_screen', '__return_false' );

		add_filter( 'sugar_calendar_helpers_ui_help_url', [ $this, 'help_url' ] );
	}

	/**
	 * Filter the help URL in the RSVP education page.
	 *
	 * @since 3.8.0
	 *
	 * @param string $help_url The help URL.
	 *
	 * @return string
	 */
	public function help_url( $help_url ) {

		return Helpers::get_utm_url(
			'https://sugarcalendar.com/docs/events/rsvp-addon/',
			[
				'content' => 'Help',
				'medium'  => 'rsvp-education',
			]
		);
	}

	/**
	 * Display license upgrade if user is in basic license.
	 *
	 * @since 3.7.0
	 */
	public function maybe_display_notices() {

		if ( sugar_calendar()->get_license_type() !== 'basic' ) {
			return;
		}
		?>
		<div class="sugar-calendar__admin-notice">
			<p>
				<?php
				printf(
					wp_kses( /* translators: %1$s - Sugar Calendar Account License URL. */
						__( 'RSVP feature is available as an add-on. Please <a target="_blank" href="%1$s">upgrade your plan</a> to Plus, Pro or Elite, in order to get access to the RSVP add-on and others.', 'sugar-calendar-lite' ),
						[
							'a' => [
								'href'   => [],
								'target' => [],
							],
						]
					),
					esc_url(
						Helpers::get_utm_url(
							'https://sugarcalendar.com/account/licenses/',
							[
								'medium'  => 'rsvp',
								'content' => 'upgrade your plan',
							]
						)
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style(
			'sugar-calendar-admin-education-rsvp',
			SC_PLUGIN_ASSETS_URL . 'css/admin-education-rsvp' . WP::asset_min() . '.css',
			[ 'sugar-calendar-admin-settings', 'sugar-calendar-admin-education' ],
			BaseHelpers::get_asset_version()
		);

		// Enqueue addon install script for Pro users with non-basic licenses.
		if ( sugar_calendar()->is_pro() && sugar_calendar()->get_license_type() !== 'basic' ) {
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
	}

	/**
	 * Page slug.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		$slug = sugar_calendar()->is_pro() ? 'sc-rsvp' : 'sugar-calendar-rsvp';

		return esc_attr( $slug );
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
	 * Display admin subheader.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function display_admin_subheader() {}

	/**
	 * Display page.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function display() {

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

		// List of features to display.
		$education_features = [
			__( 'Effortlessly Collect RSVPs', 'sugar-calendar-lite' ),
			__( 'Control Your Capacity', 'sugar-calendar-lite' ),
			__( 'Manage Plus-Ones with Ease', 'sugar-calendar-lite' ),
			__( 'Export Your Data', 'sugar-calendar-lite' ),
			__( 'Keep Attendees Informed', 'sugar-calendar-lite' ),
			__( 'Block Out Spam', 'sugar-calendar-lite' ),
		];
		?>
			<div id="sugar-calendar-rsvp-education" class="wrap sugar-calendar-admin-wrap sugar-calendar-admin-page-education">
				<div class="sugar-calendar-admin-page-education__content sugar-calendar-admin-content sugar-calendar-admin-content-rsvp">

					<div class="sugar-calendar-admin-page-education__content__header sugar-calendar-rsvp-education-header">

						<h1 class="screen-reader-text"><?php echo self::get_label(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h1>

						<?php
							UI::heading(
								[
									'id'    => 'sugar-calendar-rsvp-heading',
									'title' => self::get_label(),
								]
							);

							$this->maybe_display_notices();
						?>

						<p class="description">
							<?php esc_html_e( 'Easily manage event attendance with our RSVP addon. Allow guests to confirm attendance, provide contact information, and receive email confirmations. Ideal for weddings, parties, conferences, and more.', 'sugar-calendar-lite' ); ?>
						</p>

						<?php
						if ( ! sugar_calendar()->is_pro() ) {
							UI::button(
								[
									'class'  => 'sugar-calendar-admin-page-education__content__header__buy-pro-btn',
									'text'   => esc_html__( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ),
									'size'   => 'lg',
									'link'   => esc_url(
										Helpers::get_upgrade_link(
											[
												'medium'  => 'rsvp',
												'content' => 'Upgrade to Sugar Calendar Pro Top',
											]
										)
									),
									'target' => '_blank',
								]
							);
						}
						?>
					</div>

					<div class="sugar-calendar-education-preview">

						<?php foreach ( $screenshots as $screenshot ) : ?>

							<figure>
								<a href="<?php echo esc_url( $screenshot['url'] ); ?>" data-lity data-lity-desc="<?php echo esc_attr( $screenshot['title'] ); ?>">
									<img src="<?php echo esc_url( $screenshot['url_thumbnail'] ); ?>" alt="<?php echo esc_attr( $screenshot['title'] ); ?>">
								</a>
								<figcaption>
									<?php echo esc_html( $screenshot['title'] ); ?>
								</figcaption>
							</figure>

						<?php endforeach; ?>
					</div>

					<div class="sugar-calendar-settings-education">
						<h4><?php esc_html_e( 'Unlock These Awesome RSVP Features!', 'sugar-calendar-lite' ); ?></h4>

						<ul>
							<?php foreach ( $education_features as $feature ) : ?>
								<li><?php echo esc_html( $feature ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>

					<?php
					if ( sugar_calendar()->is_pro() && sugar_calendar()->get_license_type() !== 'basic' ) {
						$addon = sugar_calendar()->get_addons()->get_addon( 'sc-rsvp' );
						$status = ! empty( $addon['status'] ) ? $addon['status'] : 'missing';

						if ( $status === 'missing' && ! empty( $addon['url'] ) ) {
							$btn_text   = __( 'Install RSVP add-on', 'sugar-calendar-lite' );
							$btn_action = 'install';
							$btn_plugin = $addon['url'];
						} elseif ( $status === 'installed' ) {
							// Installed but not active.
							$btn_text   = __( 'Activate RSVP add-on', 'sugar-calendar-lite' );
							$btn_action = 'activate';
							$btn_plugin = 'sc-rsvp/sc-rsvp.php';
						}

						if ( ! empty( $btn_action ) ) {
							UI::button(
								[
									'class'  => 'sugar-calendar-education-addon-install',
									'text'   => esc_html( $btn_text ),
									'size'   => 'lg',
									'submit' => false,
									'data'   => [
										'action' => $btn_action,
										'plugin' => $btn_plugin,
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
												'medium'  => 'rsvp',
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
											'medium'  => 'rsvp',
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
											'medium'  => 'rsvp',
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
