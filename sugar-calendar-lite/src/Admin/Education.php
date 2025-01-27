<?php

namespace Sugar_Calendar\Admin;

use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Plugin;

/**
 * Sugar Calendar enhancements to admin pages to educate Lite users on what is available in WP Mail SMTP Pro.
 *
 * @since 3.0.0
 */
class Education {

	/**
	 * The dismissed notices user meta key.
	 *
	 * @since 3.0.0
	 */
	const DISMISSED_NOTICES_KEY = 'sugar_calendar_education_notices_dismissed';

	/**
	 * The upgrade notice in the top bar.
	 *
	 * @since 3.0.0
	 */
	const NOTICE_BAR = 'notice_bar';

	/**
	 * The upgrade notice in settings general tab.
	 *
	 * @since 3.0.0
	 */
	const NOTICE_SETTINGS_GENERAL_PAGE = 'settings_general_page';

	/**
	 * The upgrade notice in events page.
	 *
	 * @since 3.0.0
	 */
	const NOTICE_EVENTS_PAGE = 'events_page';

	/**
	 * The upgrade notice in calendars page.
	 *
	 * @since 3.0.0
	 */
	const NOTICE_CALENDARS_PAGE = 'calendars_page';

	/**
	 * Hooks.
	 *
	 * @since 3.0.0
	 */
	public function hooks() {

		// Notice bar.
		add_action( 'in_admin_header', [ $this, 'notice_bar_display' ], 0 );

		// Settings general tab.
		add_action( 'sugar_calendar_admin_page_after', [ $this, 'admin_page_after' ] );

		// Events page.
		add_action( 'sugar_calendar_admin_page_before', [ $this, 'admin_page_before' ] );

		// Dismiss ajax handler.
		add_action( 'sugar_calendar_ajax_education_notice_dismiss', [ $this, 'ajax_notice_dismiss' ] );

		// Enqueue assets.
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Return a list of default notices.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	private function get_default_notices() {

		return [
			static::NOTICE_BAR,
			static::NOTICE_SETTINGS_GENERAL_PAGE,
			static::NOTICE_EVENTS_PAGE,
			static::NOTICE_CALENDARS_PAGE,
		];
	}

	/**
	 * Return a list of dismissed notices.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	private function get_dismissed_notices() {

		$notices = get_user_meta( get_current_user_id(), static::DISMISSED_NOTICES_KEY, true );
		$notices = $notices ? $notices : [];

		return $notices;
	}

	/**
	 * Update the list of dismissed notices.
	 *
	 * @since 3.0.0
	 *
	 * @param array $notices List of notices.
	 *
	 * @return string[]
	 */
	private function update_notices( $notices ) {

		$notices = array_map( 'sanitize_key', $notices );
		$notices = array_unique( $notices );

		return update_user_meta( get_current_user_id(), static::DISMISSED_NOTICES_KEY, $notices );
	}

	/**
	 * Check whether a notice has been dismissed.
	 *
	 * @since 3.0.0
	 *
	 * @param string $notice_id Notice ID.
	 *
	 * @return bool
	 */
	private function is_dismissed( $notice_id ) {

		return in_array( $notice_id, $this->get_dismissed_notices() );
	}

	/**
	 * Notice bar display message.
	 *
	 * @since 3.0.0
	 */
	public function notice_bar_display() {

		// Bail if on Pro license.
		if ( Plugin::instance()->is_pro() ) {
			return;
		}

		// Bail if we're not on a plugin admin page.
		if ( ! Plugin::instance()->get_admin()->is_page() ) {
			return;
		}

		if ( $this->is_dismissed( static::NOTICE_BAR ) ) {
			return;
		}

		printf(
			'<div id="sugar-calendar-notice-bar" class="sugar-calendar-education-notice" data-notice="%3$s">
				<div class="sugar-calendar-notice-bar-container">
				<span class="sugar-calendar-notice-bar-message">%1$s</span>
				<button type="button" class="sugar-calendar-dismiss-notice" title="%2$s" data-notice="%3$s"></button>
				</div>
			</div>',
			wp_kses(
				sprintf( /* translators: %s - SugarCalendar.com Upgrade page URL. */
					__( '<strong>You’re using Sugar Calendar Lite</strong>. To unlock more features consider <a href="%s" target="_blank" rel="noopener noreferrer">upgrading to Pro</a> for 50%% off.', 'sugar-calendar-lite' ),
					Helpers::get_upgrade_link( [ 'medium' => 'lite-top-admin-bar', 'content' => 'upgrading to Pro' ] )
				),
				[
					'strong' => [],
					'a'      => [
						'href'   => [],
						'rel'    => [],
						'target' => [],
					],
				]
			),
			esc_attr__( 'Dismiss this message.', 'sugar-calendar-lite' ),
			esc_attr( static::NOTICE_BAR )
		);
	}

	/**
	 * Output notices after a page's content.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function admin_page_after() {

		// Bail if on Pro license.
		if ( Plugin::instance()->is_pro() ) {
			return;
		}

		// Bail if on wrong page.
		if ( ! Plugin::instance()->get_admin()->is_page( 'settings_general' ) ) {
			return;
		}

		// Bail if already dismissed.
		if ( $this->is_dismissed( static::NOTICE_SETTINGS_GENERAL_PAGE ) ) {
			return;
		}

		$assets_url  = SC_PLUGIN_ASSETS_URL . 'images/';
		$screenshots = [
			[
				'url'           => $assets_url . 'settings/event-recurrence.png',
				'url_thumbnail' => $assets_url . 'settings/event-recurrence-thumbnail.png',
				'title'         => esc_html__( 'Recurring Events', 'sugar-calendar-lite' ),
				'features'      => [
					esc_html__( 'Create recurring events', 'sugar-calendar-lite' ),
					esc_html__( 'Daily, weekly, monthly, and yearly frequency', 'sugar-calendar-lite' ),
					esc_html__( 'Dynamic intervals', 'sugar-calendar-lite' ),
					esc_html__( 'Ending on a date or number of occurrences', 'sugar-calendar-lite' ),
				],
			],
			[
				'url'           => $assets_url . 'payments/payments-popup.png',
				'url_thumbnail' => $assets_url . 'payments/payments-popup-thumbnail.png',
				'title'         => esc_html__( 'Event Ticketing', 'sugar-calendar-lite' ),
				'features'      => [
					esc_html__( 'Sell tickets for your events', 'sugar-calendar-lite' ),
					esc_html__( 'One page checkout for quick purchases', 'sugar-calendar-lite' ),
					esc_html__( 'Stripe integration', 'sugar-calendar-lite' ),
					esc_html__( 'WooCommerce integration', 'sugar-calendar-lite' ),
					esc_html__( 'Simple order management', 'sugar-calendar-lite' ),
				],
			],
			[
				'url'           => $assets_url . 'zapier/zapier-zaps.png',
				'url_thumbnail' => $assets_url . 'zapier/zapier-zaps-thumbnail.png',
				'title'         => esc_html__( 'And more...', 'sugar-calendar-lite' ),
				'features'      => [
					esc_html__( 'Frontend event submissions', 'sugar-calendar-lite' ),
					esc_html__( 'Zapier integration', 'sugar-calendar-lite' ),
					esc_html__( 'Calendar feeds (Google, Outlook, Apple, ...)', 'sugar-calendar-lite' ),
					esc_html__( 'Event duplication', 'sugar-calendar-lite' ),
					esc_html__( 'Premium support', 'sugar-calendar-lite' ),
				],
			],
		];
		?>

        <div class="sugar-calendar-education-notice sugar-calendar-settings-education"
             data-notice="<?php echo esc_attr( static::NOTICE_SETTINGS_GENERAL_PAGE ); ?>">
            <button type="button"
                    class="sugar-calendar-dismiss-notice"
                    title="<?php esc_html_e( 'Dismiss this message.', 'sugar-calendar-lite' ); ?>"
                    data-notice="<?php echo esc_attr( static::NOTICE_SETTINGS_GENERAL_PAGE ); ?>"></button>
            <div class="sugar-calendar-education-header">
                <h4><?php esc_html_e( 'Take Your Events to the Next Level', 'sugar-calendar-lite' ); ?></h4>
                <p>
					<?php
					echo wp_kses(
						sprintf( /* translators: %s - SugarCalendar.com Upgrade page URL. */
							__( 'Elevate your event management with Sugar Calendar Pro. <a href="%s" target="_blank" rel="noopener noreferrer">Upgrade today</a> and start leveraging advanced features to streamline and enhance your event management.', 'sugar-calendar-lite' ),
							Helpers::get_upgrade_link( [ 'medium' => 'settings-general', 'content' => 'Upgrade today' ] )
						),
						[
							'a' => [
								'href'   => [],
								'rel'    => [],
								'target' => [],
							],
						]
					);
					?>
                </p>
            </div>
            <div class="sugar-calendar-education-preview">

				<?php foreach ( $screenshots as $screenshot ) : ?>

                    <figure>
                        <a href="<?php echo esc_url( $screenshot['url'] ); ?>" data-lity data-lity-desc="<?php echo esc_attr( $screenshot['title'] ); ?>">
                            <img src="<?php echo esc_url( $screenshot['url_thumbnail'] ); ?>" alt="">
                        </a>
                        <figcaption>
                            <dl>
                                <dt><?php echo esc_html( $screenshot['title'] ); ?></dt>

								<?php foreach ( $screenshot['features'] as $feature ) : ?>

                                    <dd><?php echo esc_html( $feature ); ?></dd>
								<?php endforeach; ?>

                            </dl>
                        </figcaption>
                    </figure>

				<?php endforeach; ?>

            </div>

			<?php
			UI::button(
				[
					'text'   => esc_html__( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ),
					'size'   => 'lg',
					'link'   => esc_url( Helpers::get_upgrade_link( [ 'medium' => 'settings-general', 'content' => 'Upgrade to Sugar Calendar Pro' ] ) ),
					'target' => '_blank',
				]
			);
			?>
        </div>
		<?php
	}

	/**
	 * Output notices before a page's content.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function admin_page_before() {

		// Bail if on wrong page.
		if ( Plugin::instance()->get_admin()->is_page( 'events' ) ) {
			$this->events_page_education();
		} elseif ( Plugin::instance()->get_admin()->is_page( 'calendars' ) ) {
			$this->calendars_page_education();
		}
	}

	/**
	 * Output education for the events page.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function events_page_education() {

		// Bail if already dismissed.
		if ( $this->is_dismissed( static::NOTICE_EVENTS_PAGE ) ) {
			return;
		}

		?>
        <div class="sugar-calendar-education-notice sugar-calendar-events-education"
             data-notice="<?php echo esc_attr( static::NOTICE_EVENTS_PAGE ); ?>">
            <button type="button"
                    class="sugar-calendar-dismiss-notice"
                    title="<?php esc_html_e( 'Dismiss this message.', 'sugar-calendar-lite' ); ?>"
                    data-notice="<?php echo esc_attr( static::NOTICE_EVENTS_PAGE ); ?>"></button>

            <div class="sugar-calendar-education-content">
                <div class="sugar-calendar-education-content__text">
                    <h4><?php esc_html_e( 'Easily Add New Events to Your Calendar', 'sugar-calendar-lite' ); ?></h4>
                    <p><?php esc_html_e( 'Simply click the “Add New Event” button up top or on the desired date on the calendar to create a new event. Make your event recurring, add ticket sales, and more!', 'sugar-calendar-lite' ); ?></p>
                    <p class="help">
						<?php
						echo wp_kses(
							sprintf( /* translators: %s - SugarCalendar.com documentation page URL. */
								__( 'Need more help? <a href="%s" target="_blank" rel="noopener noreferrer">Read our Documentation</a>', 'sugar-calendar-lite' ),
								esc_url( Helpers::get_utm_url( 'https://sugarcalendar.com/docs/', [ 'medium' => 'events-education-banner', 'content' => 'Read our Documentation' ] ) )
							),
							[
								'a' => [
									'href'   => [],
									'rel'    => [],
									'target' => [],
								],
							]
						);
						?>
                    </p>
                </div>

                <div class="sugar-calendar-education-content__image">
                    <img src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/events/education.svg' ); ?>" alt="">
                </div>
            </div>
        </div>
		<?php
	}

	/**
	 * Output education for the calendars page.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function calendars_page_education() {

		// Bail if already dismissed.
		if ( $this->is_dismissed( static::NOTICE_CALENDARS_PAGE ) ) {
			return;
		}

		?>
        <div class="wrap sugar-calendar-education-notice" data-notice="<?php echo esc_attr( static::NOTICE_CALENDARS_PAGE ); ?>">
            <div class=" sugar-calendar-calendars-education">
                <button type="button"
                        class="sugar-calendar-dismiss-notice"
                        title="<?php esc_html_e( 'Dismiss this message.', 'sugar-calendar-lite' ); ?>"
                        data-notice="<?php echo esc_attr( static::NOTICE_CALENDARS_PAGE ); ?>"></button>

                <div class="sugar-calendar-education-content">
                    <div class="sugar-calendar-education-content__text">
                        <h4><?php esc_html_e( 'Event Management Made Easy', 'sugar-calendar-lite' ); ?></h4>
                        <p><?php esc_html_e( 'If you have multiple event types, you may want more than one calendar so you can easily categorize your events. Otherwise, you should be fine using the default calendar which we’ve set up for you.', 'sugar-calendar-lite' ); ?></p>
                        <p class="help">
							<?php
							echo wp_kses(
								sprintf( /* translators: %s - SugarCalendar.com documentation page URL. */
									__( 'Need more help? <a href="%s" target="_blank" rel="noopener noreferrer">Read our Documentation</a>', 'sugar-calendar-lite' ),
									esc_url( Helpers::get_utm_url( 'https://sugarcalendar.com/docs/', [ 'medium' => 'calendars-education-banner', 'content' => 'Read our Documentation' ] ) )
								),
								[
									'a' => [
										'href'   => [],
										'rel'    => [],
										'target' => [],
									],
								]
							);
							?>
                        </p>
                    </div>

                    <div class="sugar-calendar-education-content__image">
                        <img src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/calendars/education.svg' ); ?>" alt="">
                    </div>
                </div>
            </div>
        </div>
		<?php
	}

	/**
	 * Ajax handler for dismissing notices.
	 *
	 * @since 3.0.0
	 */
	public function ajax_notice_dismiss() {

		// Check for permissions.
		if ( ! current_user_can( Plugin::instance()->get_capability_manage_options() ) ) {
			wp_send_json_error();
		}

		// Bail if notice ID is missing.
		if ( ! isset( $_POST['notice_id'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_send_json_error();
		}

		$notice_id = sanitize_key( $_POST['notice_id'] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Bail if notice ID is unknown.
		if ( ! in_array( $notice_id, $this->get_default_notices() ) ) {
			wp_send_json_error();
		}

		$notices = $this->get_dismissed_notices();

		$notices[] = $notice_id;

		$this->update_notices( $notices );

		wp_send_json_success();
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style( 'sugar-calendar-admin-education' );
		wp_enqueue_script( 'sugar-calendar-admin-education' );

		wp_localize_script(
			'sugar-calendar-admin-education',
			'sugar_calendar_admin_education',
			[
				'ajax_url' => Plugin::instance()->get_admin()->ajax_url(),
			]
		);
	}
}
