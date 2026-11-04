<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;

/**
 * Markup of a Lite upsell preview for a Pro feature: heading, upgrade buttons, screenshots and feature list.
 *
 * @since 3.17.0
 */
class EducationContent {

	/**
	 * Display the preview.
	 *
	 * @since 3.17.0
	 *
	 * @param array $education Data with the keys title, heading_id, header_class, button_class, description, medium,
	 *                         screenshots, features_title and features.
	 *
	 * @return void
	 */
	public static function display( $education ) {

		self::display_header( $education );
		self::display_screenshots( $education['screenshots'] );
		self::display_features( $education );
		self::display_upgrade_button( $education['medium'], 'Upgrade to Sugar Calendar Pro Bottom', '' );
	}

	/**
	 * Display the heading, description and top upgrade button.
	 *
	 * @since 3.17.0
	 *
	 * @param array $education Preview data.
	 *
	 * @return void
	 */
	private static function display_header( $education ) {

		?>
		<div class="<?php echo esc_attr( $education['header_class'] ); ?>">

			<h1 class="screen-reader-text"><?php echo esc_html( $education['title'] ); ?></h1>

			<?php
			UI::heading(
				[
					'id'    => $education['heading_id'],
					'title' => $education['title'],
				]
			);
			?>

			<p class="description"><?php echo esc_html( $education['description'] ); ?></p>

			<?php self::display_upgrade_button( $education['medium'], 'Upgrade to Sugar Calendar Pro Top', $education['button_class'] ); ?>
		</div>
		<?php
	}

	/**
	 * Display the screenshot thumbnails.
	 *
	 * @since 3.17.0
	 *
	 * @param array $screenshots Screenshots with url, url_thumbnail and title.
	 *
	 * @return void
	 */
	private static function display_screenshots( $screenshots ) {

		?>
		<div class="sugar-calendar-education-preview">

			<?php foreach ( $screenshots as $screenshot ) : ?>

				<?php if ( empty( $screenshot['url'] ) ) : ?>
					<figure>
						<div style="aspect-ratio: 16 / 10; background: #dcdcde; border-radius: 4px;"></div>
					</figure>
					<?php continue; ?>
				<?php endif; ?>

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
		<?php
	}

	/**
	 * Display the feature list.
	 *
	 * @since 3.17.0
	 *
	 * @param array $education Preview data.
	 *
	 * @return void
	 */
	private static function display_features( $education ) {

		?>
		<div class="sugar-calendar-settings-education">
			<h4><?php echo esc_html( $education['features_title'] ); ?></h4>

			<ul>
				<?php foreach ( $education['features'] as $feature ) : ?>
					<li><?php echo esc_html( $feature ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Display an upgrade button.
	 *
	 * @since 3.17.0
	 *
	 * @param string $medium       UTM medium.
	 * @param string $content      UTM content.
	 * @param string $button_class Extra button class.
	 *
	 * @return void
	 */
	private static function display_upgrade_button( $medium, $content, $button_class ) {

		$args = [
			'text'   => esc_html__( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ),
			'size'   => 'lg',
			'link'   => esc_url(
				Helpers::get_upgrade_link(
					[
						'medium'  => $medium,
						'content' => $content,
					]
				)
			),
			'target' => '_blank',
		];

		if ( $button_class !== '' ) {
			$args['class'] = $button_class;
		}

		UI::button( $args );
	}
}
