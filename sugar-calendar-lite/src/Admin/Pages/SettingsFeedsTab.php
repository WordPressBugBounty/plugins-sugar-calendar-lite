<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Pages\Settings;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;

/**
 * Calendar Feeds Settings tab.
 *
 * @since 3.0.0
 */
class SettingsFeedsTab extends Settings {

	/**
	 * Page tab slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_tab_slug() {

		return 'feeds';
	}

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Feeds', 'sugar-calendar-lite' );
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
						'title'       => esc_html__( 'Available Feeds', 'sugar-calendar-lite' ),
						'description' => esc_html__( 'Select the feeds to show on Calendars, Event Archives, and Single Events. (WebCal and Direct are not used for Single Events).', 'sugar-calendar-lite' ),
						'class'       => 'sugar-calendar--pro-only',
					]
				);

				$feeds = [
					'google'    => esc_html__( 'Google Calendar', 'sugar-calendar-lite' ),
					'microsoft' => esc_html__( 'Microsoft Outlook', 'sugar-calendar-lite' ),
					'apple'     => esc_html__( 'Apple Calendar', 'sugar-calendar-lite' ),
					'webcal'    => esc_html__( 'WebCal', 'sugar-calendar-lite' ),
					'download'  => esc_html__( 'Download', 'sugar-calendar-lite' ),
					'direct'    => esc_html__( 'Direct', 'sugar-calendar-lite' ),
				];

				ob_start();
				?>

                <ul data-sortable>

					<?php foreach ( $feeds as $id => $label ) : ?>

                        <li class="sugar-calendar-settings-field-checkbox-wrapper">
                            <input id="sugar-calendar-setting-sc_cf_feeds_active-<?php echo esc_attr( $id ); ?>"
                                   type="checkbox"
                                   checked
                                   disabled/>
                            <label for="sugar-calendar-setting-sc_cf_feeds_active-<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
                            <i data-handle></i>
                        </li>

					<?php endforeach; ?>

                </ul>

				<?php
				UI::field_wrapper(
					[
						'type'  => 'calendar-feeds',
						'class' => 'sugar-calendar--pro-only',
					],
					ob_get_clean()
				);
				?>

                <p class="submit">
					<?php
					UI::button(
						[
							'text'   => esc_html__( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ),
							'size'   => 'lg',
							'link'   => esc_url( Helpers::get_upgrade_link( [ 'medium' => 'settings-feeds', 'content' => 'Upgrade to Sugar Calendar Pro' ] ) ),
							'target' => '_blank',
						]
					);
					?>
                </p>
            </div>
        </div>

		<?php
	}
}
