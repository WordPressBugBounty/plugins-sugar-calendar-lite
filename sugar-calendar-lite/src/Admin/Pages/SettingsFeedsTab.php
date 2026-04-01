<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Pages\Settings;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Plugin;
use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\Helpers\WP;

/**
 * Calendar Feeds Settings tab.
 *
 * @since 3.0.0
 */
class SettingsFeedsTab extends Settings {

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
	 * Enqueue the assets for the Feeds tab.
	 *
	 * @since 3.11.0
	 */
	public function enqueue_assets() {

		parent::enqueue_assets();

		wp_enqueue_style(
			'sugar-calendar-admin-education',
			SC_PLUGIN_ASSETS_URL . 'css/admin-education' . WP::asset_min() . '.css',
			[ 'sugar-calendar-vendor-lity', 'sugar-calendar-vendor-jquery-confirm' ],
			BaseHelpers::get_asset_version()
		);

		wp_enqueue_script(
			'sugar-calendar-admin-education',
			SC_PLUGIN_ASSETS_URL . 'js/admin-education' . WP::asset_min() . '.js',
			[ 'jquery', 'sugar-calendar-vendor-lity', 'sugar-calendar-vendor-jquery-confirm' ],
			BaseHelpers::get_asset_version(),
			true
		);

		wp_localize_script(
			'sugar-calendar-admin-education',
			'sugar_calendar_admin_education',
			[
				'ajax_url'                           => Plugin::instance()->get_admin()->ajax_url(),
				'sce_admin_settings_feeds_education' => BaseHelpers::get_education_upgrade_modal_content(),
				'sce_admin_upgrade_thank_you_modal'  => BaseHelpers::get_education_upgrade_thank_you_modal_content(),
			]
		);
	}

	/**
	 * Filter the help URL in the Settings page -> Feeds tab.
	 *
	 * @since 3.8.0
	 *
	 * @param string $help_url The help URL.
	 *
	 * @return string
	 */
	public function help_url( $help_url ) {

		return Helpers::get_utm_url(
			'https://sugarcalendar.com/docs/events/using-calendar-feeds/',
			[
				'content' => 'Help',
				'medium'  => 'plugin-settings-feeds',
			]
		);
	}

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
		<div id="sugar-calendar-settings" class="wrap sugar-calendar-admin-wrap sugar-calendar-admin__settings__tab-wrap">
			<?php UI::tabs( $this->get_tabs(), static::get_tab_slug() ); ?>
			<div class="sugar-calendar-admin-content">
				<h1 class="screen-reader-text"><?php esc_html_e( 'Settings', 'sugar-calendar-lite' ); ?></h1>
				<?php
				UI::heading(
					[
						'class' => 'sugar-calendar--pro-only',
						'title' => esc_html__( 'Available Feeds', 'sugar-calendar-lite' ),
					]
				);

				printf(
					'<p class="desc">%1$s</p>',
					esc_html__( 'Select the feeds to show on Calendars, Event Archives, and Single Events. (WebCal and Direct are not used for Single Events).', 'sugar-calendar-lite' )
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
										'medium'  => 'feeds-settings',
										'content' => 'Upgrade to Sugar Calendar Pro Top',
									]
								)
							),
							'target' => '_blank',
						]
					);
				}

				$feeds = [
					'google-calendar'   => esc_html__( 'Google Calendar', 'sugar-calendar-lite' ),
					'microsoft-outlook' => esc_html__( 'Microsoft Outlook', 'sugar-calendar-lite' ),
					'apple-calendar'    => esc_html__( 'Apple Calendar', 'sugar-calendar-lite' ),
					'webcal'            => esc_html__( 'WebCal', 'sugar-calendar-lite' ),
					'download'          => esc_html__( 'Download', 'sugar-calendar-lite' ),
					'direct'            => esc_html__( 'Direct', 'sugar-calendar-lite' ),
				];

				ob_start();
				?>
				<ul id="sugar-calendar-settings-feeds-list" data-sortable>
					<?php foreach ( $feeds as $id => $label ) : ?>
						<li class="sugar-calendar-settings-field-checkbox-wrapper" data-feed-name="<?php echo esc_attr( $label ); ?>" data-feed-id="<?php echo esc_attr( $id . '-feed' ); ?>">
							<input id="sugar-calendar-setting-sc_cf_feeds_active-<?php echo esc_attr( $id ); ?>"
								type="checkbox" checked disabled/>
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

				UI::button(
					[
						'text'   => esc_html__( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ),
						'size'   => 'lg',
						'link'   => esc_url( Helpers::get_upgrade_link( [ 'medium' => 'settings-feeds', 'content' => 'Upgrade to Sugar Calendar Pro' ] ) ),
						'target' => '_blank',
					]
				);
				?>
			</div>
		</div>
		<?php
	}
}
