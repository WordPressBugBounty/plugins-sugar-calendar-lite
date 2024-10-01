<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\PageAbstract;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Plugin;

/**
 * Welcome page class.
 *
 * This page is shown when the plugin is activated.
 *
 * @since 3.0.0
 */
class Welcome extends PageAbstract {

	/**
	 * Page slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		return 'sugar-calendar-getting-started';
	}

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Welcome to Sugar Calendar', 'sugar-calendar' );
	}

	/**
	 * Whether the page appears in menus.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public static function has_menu_item() {

		return false;
	}

	/**
	 * Register all WP hooks.
	 *
	 * @since 3.0.0
	 */
	public function hooks() {

		// If user is in admin ajax or doing cron, return.
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		// If user cannot manage_options, return.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Display page.
	 *
	 * @since 3.0.0
	 */
	public function display() {

		$class = Plugin::instance()->is_pro() ? 'pro' : 'lite';

		$post_type = sugar_calendar_get_event_post_type_id();

		$new_event_url = "post-new.php?post_type={$post_type}";
		?>

        <div id="sugar-calendar-welcome" class="<?php echo sanitize_html_class( $class ); ?>">
            <div class="container">
                <header class="header">
                    <div class="header-content">
                        <h1><?php esc_html_e( 'Welcome to Sugar Calendar', 'sugar-calendar' ); ?></h1>
                        <p><?php esc_html_e( 'Most event calendar plugins are either way too simple, or extremely overly complex and bloated. Sugar Calendar is designed to be simple, light-weight, and provide just the major features you need for event management.', 'sugar-calendar' ); ?></p>
                        <div class="header-buttons">
                            <a href="<?php echo esc_url( admin_url( $new_event_url ) ); ?>" class="sugar-calendar-btn sugar-calendar-btn-primary sugar-calendar-btn-lg"><?php esc_html_e( 'Create Your First Event', 'sugar-calendar' ); ?></a>
                            <a href="<?php echo esc_url( Helpers::get_utm_url( 'https://sugarcalendar.com/docs/', [ 'medium' => 'plugin-welcome-page', 'content' => 'Read the Full Guide' ] ) ); ?>" target="_blank" class="sugar-calendar-btn sugar-calendar-btn-tertiary sugar-calendar-btn-lg"><?php esc_html_e( 'Read the Full Guide', 'sugar-calendar' ); ?></a>
                        </div>
                    </div>
                    <div class="header-image">
                        <img src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/welcome/illustration.png' ); ?>" alt="<?php esc_html_e( 'Calendar Illustration', 'sugar-calendar' ); ?>">
                    </div>
                </header>
                <section class="features">
                    <h2><?php esc_html_e( 'Simple And Powerful Features', 'sugar-calendar' ); ?></h2>
                    <p class="sugar-calendar-welcome__description"><?php esc_html_e( 'Sugar Calendar is easy-to-use, reliable, and exceptionally powerful. See for yourself.', 'sugar-calendar' ); ?></p>
                    <div class="feature-list">
                        <div class="feature-item">
                            <div>
                                <img src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/welcome/icon-event-management.png' ); ?>" alt="<?php esc_html_e( 'Event Management', 'sugar-calendar' ); ?>">
                            </div>
                            <div>
                                <h3><?php esc_html_e( 'Event Management', 'sugar-calendar' ); ?></h3>
                                <p><?php esc_html_e( 'Sugar Calendar is designed to be simple, light weight, and provide just the major features you need for event management.', 'sugar-calendar' ); ?></p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div>
                                <img src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/welcome/icon-recurring-events.png' ); ?>" alt="<?php esc_html_e( 'Recurring Events', 'sugar-calendar' ); ?>">
                            </div>
                            <div>
                                <h3><?php esc_html_e( 'Recurring Events', 'sugar-calendar' ); ?></h3>
                                <p><?php esc_html_e( 'Create events that recur automatically on a daily, weekly, monthly, and yearly basis. You can even set a date to end recurrence.', 'sugar-calendar' ); ?></p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div>
                                <img src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/welcome/icon-translation-ready.png' ); ?>" alt="<?php esc_html_e( 'Translation-Ready', 'sugar-calendar' ); ?>">
                            </div>
                            <div>
                                <h3><?php esc_html_e( 'Translation-Ready', 'sugar-calendar' ); ?></h3>
                                <p><?php esc_html_e( 'Sugar Calendar is fully localized and ready for your language. It has been translated in eight languages and is ready for more!', 'sugar-calendar' ); ?></p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div>
                                <img src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/welcome/icon-start-end-times.png' ); ?>" alt="<?php esc_html_e( 'Start And End Times', 'sugar-calendar' ); ?>">
                            </div>
                            <div>
                                <h3><?php esc_html_e( 'Start And End Times', 'sugar-calendar' ); ?></h3>
                                <p><?php esc_html_e( 'All events can be assigned a starting and end time. Both start and end dates are optional, support all-day or specific-duration events.', 'sugar-calendar' ); ?></p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div>
                                <img src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/welcome/icon-single-multi-day-events.png' ); ?>" alt="<?php esc_html_e( 'Single And Multi-Day Events', 'sugar-calendar' ); ?>">
                            </div>
                            <div>
                                <h3><?php esc_html_e( 'Single And Multi-Day Events', 'sugar-calendar' ); ?></h3>
                                <p><?php esc_html_e( 'Events can be set to occur on a specific day or over multiple days.', 'sugar-calendar' ); ?></p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div>
                                <img src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/welcome/icon-categories.png' ); ?>" alt="<?php esc_html_e( 'Event Categories', 'sugar-calendar' ); ?><">
                            </div>
                            <div>
                                <h3><?php esc_html_e( 'Event Categories', 'sugar-calendar' ); ?></h3>
                                <p><?php esc_html_e( 'Assign events to specific categories and then display calendars for just categories or even display a master calendar with all categories.', 'sugar-calendar' ); ?></p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div>
                                <img src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/welcome/icon-time-zones.png' ); ?>" alt="<?php esc_html_e( 'Event Time Zones', 'sugar-calendar' ); ?>">
                            </div>
                            <div>
                                <h3><?php esc_html_e( 'Event Time Zones', 'sugar-calendar' ); ?></h3>
                                <p><?php esc_html_e( 'Assign specific time zones to events and calendars and display event date and times in viewers\' local time zones.', 'sugar-calendar' ); ?></p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div>
                                <img src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/welcome/icon-event-ticketing.png' ); ?>" alt="<?php esc_html_e( 'Event Ticketing', 'sugar-calendar' ); ?>">
                            </div>
                            <div>
                                <h3><?php esc_html_e( 'Event Ticketing', 'sugar-calendar' ); ?></h3>
                                <p><?php esc_html_e( 'Easily sell tickets to events through Stripe or WooCommerce with the Event Ticketing addon.', 'sugar-calendar' ); ?></p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
		<?php
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style(
			'sugar-calendar-admin-welcome',
			SC_PLUGIN_ASSETS_URL . 'css/admin-welcome' . WP::asset_min() . '.css',
			[],
			SC_PLUGIN_VERSION
		);
	}
}
