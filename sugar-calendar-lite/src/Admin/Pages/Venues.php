<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Pages\VenuesAbstract;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Helpers\Helpers;

/**
 * Venue page.
 *
 * @since 3.5.0
 */
class Venues extends VenuesAbstract {

	/**
	 * Page slug.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		$venue_slug = defined( 'SC_VENUE_POST_TYPE' )
			? SC_VENUE_POST_TYPE
			: 'venues';

		$slug = sugar_calendar()->is_pro() ? "edit.php?post_type=$venue_slug" : 'sugar-calendar-venue';

		return esc_attr( $slug );
	}

	/**
	 * Page label.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Venues', 'sugar-calendar-lite' );
	}

	/**
	 * Display admin subheader.
	 *
	 * @since 3.5.0
	 *
	 * @return void
	 */
	public function display_admin_subheader() {}

	/**
	 * Display page.
	 *
	 * @since 3.5.0
	 *
	 * @return void
	 */
	public function display() {

		$assets_url  = SC_PLUGIN_ASSETS_URL . 'images/';
		$screenshots = [
			[
				'url'           => $assets_url . 'features/venues/venues-add.png',
				'url_thumbnail' => $assets_url . 'features/venues/venues-add-thumbnail.png',
				'title'         => esc_html__( 'Venue Creation', 'sugar-calendar-lite' ),
			],
			[
				'url'           => $assets_url . 'features/venues/venues-single.png',
				'url_thumbnail' => $assets_url . 'features/venues/venues-single-thumbnail.png',
				'title'         => esc_html__( 'Venue Page', 'sugar-calendar-lite' ),
			],
		];

		// List of features to display.
		$education_features = [
			esc_html__( 'Venues/Locations Management', 'sugar-calendar-lite' ),
			esc_html__( "Google Map on Venue's Page", 'sugar-calendar-lite' ),
			esc_html__( 'Quick Venue Creation', 'sugar-calendar-lite' ),
			esc_html__( 'Filtering Events by Venue', 'sugar-calendar-lite' ),
			esc_html__( 'Detailed Venue Information', 'sugar-calendar-lite' ),
			esc_html__( 'Venue Events List', 'sugar-calendar-lite' ),
		];
		?>
			<div id="sugar-calendar-venues-education" class="wrap sugar-calendar-admin-wrap sugar-calendar-admin-page-education">
				<div class="sugar-calendar-admin-page-education__content sugar-calendar-admin-content sugar-calendar-admin-content-venue">

					<div class="sugar-calendar-admin-page-education__content__header sugar-calendar-venues-education-header">

						<h1 class="screen-reader-text"><?php echo self::get_label(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h1>

						<?php
							UI::heading(
								[
									'id'    => 'sugar-calendar-venues-heading',
									'title' => self::get_label(),
								]
							);
						?>

						<p class="description">
							<?php esc_html_e( 'Help your visitors with detailed information and a map of your venues where you are hosting your events. Sugar Calendar Venues will simplify your venues/locations management and your event attendees will have all the information they need.', 'sugar-calendar-lite' ); ?>
						</p>

						<?php
							UI::button(
								[
									'text'   => esc_html__( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ),
									'class'  => 'sugar-calendar-admin-page-education__content__header__buy-pro-btn',
									'size'   => 'lg',
									'link'   => esc_url(
										Helpers::get_upgrade_link(
											[
												'medium'  => 'venues',
												'content' => 'Upgrade to Sugar Calendar Pro Top',
											]
										)
									),
									'target' => '_blank',
								]
							);
						?>
					</div>

					<div class="sugar-calendar-education-preview">

						<?php foreach ( $screenshots as $screenshot ) : ?>

							<figure>
								<a href="<?php echo esc_url( $screenshot['url'] ); ?>" data-lity data-lity-desc="<?php echo esc_attr( $screenshot['title'] ); ?>">
									<img src="<?php echo esc_url( $screenshot['url_thumbnail'] ); ?>" alt="">
								</a>
								<figcaption>
									<?php echo esc_html( $screenshot['title'] ); ?>
								</figcaption>
							</figure>

						<?php endforeach; ?>
					</div>

					<div class="sugar-calendar-settings-education">
						<h4><?php esc_html_e( 'Unlock These Awesome Venue Features!', 'sugar-calendar-lite' ); ?></h4>

						<ul>
							<?php foreach ( $education_features as $feature ) : ?>
								<li><?php echo esc_html( $feature ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>

					<?php
						UI::button(
							[
								'text'   => esc_html__( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ),
								'size'   => 'lg',
								'link'   => esc_url(
									Helpers::get_upgrade_link(
										[
											'medium'  => 'venues',
											'content' => 'Upgrade to Sugar Calendar Pro Bottom',
										]
									)
								),
								'target' => '_blank',
							]
						);
					?>
				</div>
			</div>
		<?php
	}
}
