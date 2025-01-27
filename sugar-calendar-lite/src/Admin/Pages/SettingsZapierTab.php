<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Pages\Settings;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;

/**
 * Zapier Settings tab.
 *
 * @since 3.0.0
 */
class SettingsZapierTab extends Settings {

	/**
	 * Page tab slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_tab_slug() {

		return 'zapier';
	}

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Zapier', 'sugar-calendar-lite' );
	}

	/**
	 * Page menu priority.
	 *
	 * @since 3.0.0
	 *
	 * @return int
	 */
	public static function get_priority() {

		return 50;
	}

	/**
	 * Display page.
	 *
	 * @since 3.0.0
	 */
	public function display() {

		?>
        <div id="sugar-calendar-settings" class="wrap sugar-calendar-admin-wrap">

			<?php UI::tabs( $this->get_tabs(), static::get_tab_slug() ); ?>

            <div class="sugar-calendar-admin-content">

                <h1 class="screen-reader-text"><?php esc_html_e( 'Settings', 'sugar-calendar-lite' ); ?></h1>
				<?php

				UI::heading(
					[
						'title'       => esc_html__( 'Zapier', 'sugar-calendar-lite' ),
						'description' => esc_html__( 'Zapier lets you connect Sugar Calendar with thousands of the most popular apps, so you can automate your work and have more time for what matters most.', 'sugar-calendar-lite' ),
						'class'       => 'sugar-calendar--pro-only',
					]
				);

				$assets_url  = SC_PLUGIN_ASSETS_URL . 'images/zapier/';
				$screenshots = [
					[
						'url'           => $assets_url . 'zapier-settings.png',
						'url_thumbnail' => $assets_url . 'zapier-settings-thumbnail.png',
						'title'         => __( 'Zapier Settings', 'sugar-calendar-lite' ),
					],
					[
						'url'           => $assets_url . 'zapier-zaps.png',
						'url_thumbnail' => $assets_url . 'zapier-zaps-thumbnail.png',
						'title'         => __( 'Sugar Calendar Triggers for Zapier', 'sugar-calendar-lite' ),
					],
				];
				?>

                <div class="sugar-calendar-education-preview">

					<?php foreach ( $screenshots as $screenshot ) : ?>

                        <figure>
                            <a href="<?php echo esc_url( $screenshot['url'] ); ?>" data-lity data-lity-desc="<?php echo esc_attr( $screenshot['title'] ); ?>">
                                <img src="<?php echo esc_url( $screenshot['url_thumbnail'] ); ?>" alt="">
                            </a>
                            <figcaption><?php echo esc_html( $screenshot['title'] ); ?></figcaption>
                        </figure>

					<?php endforeach; ?>

                </div>

                <div class="sugar-calendar-education-features">
                    <h4><?php esc_html_e( 'Unlock These Awesome Zapier Features!', 'sugar-calendar-lite' ); ?></h4>
                    <ul>
                        <li><?php esc_html_e( 'Automate Repetitive Tasks', 'sugar-calendar-lite' ); ?></li>
                        <li><?php esc_html_e( 'Integrate with 3000+ Other Apps', 'sugar-calendar-lite' ); ?></li>
                        <li><?php esc_html_e( 'Set It and Forget It', 'sugar-calendar-lite' ); ?></li>
                        <li><?php esc_html_e( 'Connect to Your Favorite Services', 'sugar-calendar-lite' ); ?></li>
                        <li><?php esc_html_e( 'No Code Necessary', 'sugar-calendar-lite' ); ?></li>
                        <li><?php esc_html_e( 'Build Custom Solutions', 'sugar-calendar-lite' ); ?></li>
                    </ul>
                </div>

				<?php
				UI::button(
					[
						'text'   => esc_html__( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ),
						'size'   => 'lg',
						'link'   => esc_url( Helpers::get_upgrade_link( 'zapier' ) ),
						'target' => '_blank',
					]
				);
				?>
            </div>
        </div>

		<?php
	}
}
