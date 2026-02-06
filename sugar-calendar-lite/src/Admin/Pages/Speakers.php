<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Pages\SpeakersAbstract;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\Helpers;

/**
 * Speakers page.
 *
 * @since 3.7.0
 */
class Speakers extends SpeakersAbstract {

	/**
	 * Hooks.
	 *
	 * @since 3.8.0
	 */
	public function hooks() {

		parent::hooks();

		add_filter( 'sugar_calendar_helpers_ui_help_url', [ $this, 'help_url' ] );
	}

	/**
	 * Filter the help URL in the Speakers education page.
	 *
	 * @since 3.8.0
	 *
	 * @param string $help_url The help URL.
	 *
	 * @return string
	 */
	public function help_url( $help_url ) {

		return Helpers::get_utm_url(
			'https://sugarcalendar.com/docs/events/creating-and-managing-event-speakers/',
			[
				'content' => 'Help',
				'medium'  => 'speakers-education',
			]
		);
	}

	/**
	 * Page slug.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		$slug = 'sugar-calendar-speaker';

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

		return esc_html__( 'Speakers', 'sugar-calendar-lite' );
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

		$assets_url  = SC_PLUGIN_ASSETS_URL . 'images/';
		$screenshots = [
			[
				'url'           => $assets_url . 'features/speakers/speakers-add.jpg',
				'url_thumbnail' => $assets_url . 'features/speakers/speakers-add-thumb.jpg',
				'title'         => esc_html__( 'Adding a New Speaker', 'sugar-calendar-lite' ),
			],
			[
				'url'           => $assets_url . 'features/speakers/speakers-page.jpg',
				'url_thumbnail' => $assets_url . 'features/speakers/speakers-page-thumb.jpg',
				'title'         => esc_html__( 'Speaker Feature Page', 'sugar-calendar-lite' ),
			],
		];

		// List of features to display.
		$education_features = [
			esc_html__( 'Speaker Profiles Management', 'sugar-calendar-lite' ),
			esc_html__( 'Filtering Events by Speakers', 'sugar-calendar-lite' ),
			esc_html__( 'Multiple Speakers per Event', 'sugar-calendar-lite' ),
			esc_html__( 'Detailed Speaker Profile Page', 'sugar-calendar-lite' ),
			esc_html__( 'Upcoming Speaker Events List', 'sugar-calendar-lite' ),
			esc_html__( 'Frontend Submission Support', 'sugar-calendar-lite' ),
		];
		?>
			<div id="sugar-calendar-speakers-education" class="wrap sugar-calendar-admin-wrap">
				<div class="sugar-calendar-admin-content sugar-calendar-admin-content-speaker">

					<div class="sugar-calendar-speakers-education-header">

						<h1 class="screen-reader-text"><?php echo self::get_label(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h1>

						<?php
							UI::heading(
								[
									'id'    => 'sugar-calendar-speakers-heading',
									'title' => self::get_label(),
								]
							);
						?>

						<p class="description">
							<?php esc_html_e( "Bring your events to life by showcasing the voices behind them. The Speakers feature lets you connect one or more speakers to an event, making it easier to highlight who's involved and helps visitors filter events by their favorite performers.", 'sugar-calendar-lite' ); ?>
						</p>

						<?php
							UI::button(
								[
									'text'   => esc_html__( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ),
									'class'  => 'sugar-calendar-speakers-education-header__buy-pro-btn',
									'size'   => 'lg',
									'link'   => esc_url(
										Helpers::get_upgrade_link(
											[
												'medium'  => 'speakers',
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
						<h4><?php esc_html_e( 'Unlock These Awesome Speaker Features!', 'sugar-calendar-lite' ); ?></h4>

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
											'medium'  => 'speakers',
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
