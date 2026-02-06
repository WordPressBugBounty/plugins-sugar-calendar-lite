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
				'url'           => $assets_url . 'settings/recurring.png',
				'url_thumbnail' => $assets_url . 'settings/recurring-thumbnail.png',
				'title'         => esc_html__( 'Recurring Events', 'sugar-calendar-lite' ),
			],
			[
				'url'           => $assets_url . 'settings/rsvp-list.png',
				'url_thumbnail' => $assets_url . 'settings/rsvp-list-thumbnail.png',
				'title'         => esc_html__( 'RSVP List', 'sugar-calendar-lite' ),
			],
			[
				'url'           => $assets_url . 'settings/speaker.png',
				'url_thumbnail' => $assets_url . 'settings/speaker-thumbnail.png',
				'title'         => esc_html__( 'Speaker Page', 'sugar-calendar-lite' ),
			],
		];
		?>
		<div class="sugar-calendar__product-education sugar-calendar__product-education__general sugar-calendar-education-notice sugar-calendar-settings-education"
			data-notice="<?php echo esc_attr( static::NOTICE_SETTINGS_GENERAL_PAGE ); ?>">

			<button type="button"
				class="sugar-calendar-dismiss-notice"
				title="<?php esc_html_e( 'Dismiss this message.', 'sugar-calendar-lite' ); ?>"
				data-notice="<?php echo esc_attr( static::NOTICE_SETTINGS_GENERAL_PAGE ); ?>"></button>
			
			<div class="sugar-calendar-education-header">
				<h4><?php esc_html_e( 'Let Your Calendar Do the Heavy Lifting — Unlock Pro Features', 'sugar-calendar-lite' ); ?></h4>
				<p>
					<?php
					esc_html_e( 'Step up from basic scheduling to a full event system, all inside WordPress.', 'sugar-calendar-lite' );
					?>
				</p>
			</div>

			<div class="sugar-calendar-education-header sugar-calendar__product-education__features">
				<h4><?php esc_html_e( "Key Features You'll Unlock:", 'sugar-calendar-lite' ); ?></h4>
				<div class="sugar-calendar__product-education__features__list">
					<?php
					$features = [
						[
							'title' => __( 'Save Time With Automated Scheduling', 'sugar-calendar-lite' ),
							'desc'  => __( 'Recurring events with flexible patterns — daily, weekly, monthly, or fully custom', 'sugar-calendar-lite' ),
						],
						[
							'title' => __( 'Showcase the People and Places Behind Your Events', 'sugar-calendar-lite' ),
							'desc'  => __( 'Add speaker bios and venue details with maps, photos, and social links.', 'sugar-calendar-lite' ),
						],
						[
							'title' => __( 'Stay Organized With Attendee Tracking', 'sugar-calendar-lite' ),
							'desc'  => __( 'RSVP tools and attendee reports keep signups clear and capacity under control', 'sugar-calendar-lite' ),
						],
						[
							'title' => __( 'Make Events Easy to Remember', 'sugar-calendar-lite' ),
							'desc'  => __( 'One-click add-to-calendar support for Google, Outlook, Apple, and more', 'sugar-calendar-lite' ),
						],
						[
							'title' => __( 'And much more...', 'sugar-calendar-lite' ),
							'icon'  => false,
						],
					];

					foreach ( $features as $feature ) {
						?>
						<div class="sugar-calendar__product-education__features__list__item">
							<div class="sugar-calendar__product-education__features__list__item__icon">
								<?php
								if (
									! isset( $feature['icon'] ) || $feature['icon']
								) {
									?>
									<svg width="9" height="7" viewBox="0 0 9 7" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M8.63281 0.253906C8.88672 0.488281 8.88672 0.898438 8.63281 1.13281L3.63281 6.13281C3.39844 6.38672 2.98828 6.38672 2.75391 6.13281L0.253906 3.63281C0 3.39844 0 2.98828 0.253906 2.75391C0.488281 2.5 0.898438 2.5 1.13281 2.75391L3.20312 4.80469L7.75391 0.253906C7.98828 0 8.39844 0 8.63281 0.253906Z" fill="#008A20"/>
									</svg>
									<?php
								}
								?>
							</div>
							<div class="sugar-calendar__product-education__features__list__item__content">
								<p><strong><?php echo esc_html( $feature['title'] ); ?></strong></p>
								<?php
								if ( ! empty( $feature['desc'] ) ) {
									?>
									<p><?php echo esc_html( $feature['desc'] ); ?></p>
									<?php
								}
								?>
							</div>
						</div>
						<?php
					}
					?>
				</div>
			</div>
			<div class="sugar-calendar__product-education__button-section">
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
				<div class="sugar-calendar__product-education__button-section__discount">
					<div class="sugar-calendar__product-education__button-section__discount__icon">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M8 0C9.125 0 10.125 0.65625 10.625 1.625C11.6562 1.28125 12.8125 1.53125 13.6562 2.34375C14.4688 3.15625 14.6875 4.34375 14.375 5.375C15.3438 5.875 16 6.875 16 8C16 9.15625 15.3438 10.1562 14.375 10.6562C14.7188 11.6875 14.4688 12.8438 13.6562 13.6562C12.8125 14.4688 11.6562 14.7188 10.625 14.4062C10.125 15.375 9.125 16 8 16C6.84375 16 5.84375 15.375 5.34375 14.4062C4.3125 14.7188 3.15625 14.4688 2.3125 13.6562C1.5 12.8438 1.28125 11.6875 1.59375 10.6562C0.625 10.1562 0 9.15625 0 8C0 6.875 0.625 5.875 1.59375 5.375C1.25 4.34375 1.5 3.15625 2.3125 2.34375C3.15625 1.53125 4.3125 1.28125 5.34375 1.625C5.84375 0.65625 6.84375 0 8 0ZM6 7C6.53125 7 7 6.5625 7 6C7 5.46875 6.53125 5 6 5C5.4375 5 5 5.46875 5 6C5 6.5625 5.4375 7 6 7ZM10 9C9.4375 9 9 9.46875 9 10C9 10.5625 9.4375 11 10 11C10.5312 11 11 10.5625 11 10C11 9.46875 10.5312 9 10 9ZM10.5 6.53125C10.8125 6.25 10.8125 5.78125 10.5 5.46875C10.2188 5.1875 9.75 5.1875 9.46875 5.46875L5.46875 9.46875C5.15625 9.78125 5.15625 10.25 5.46875 10.5312C5.75 10.8438 6.21875 10.8438 6.5 10.5312L10.5 6.53125Z" fill="#008A20"/>
						</svg>
					</div>
					<div class="sugar-calendar__product-education__button-section__discount__text">
						<p>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %1$s - Discount off in percentage (eg. 50% OFF). */
									__( '%1$s for Sugar Calendar users, applied at checkout.', 'sugar-calendar-lite' ),
									'<strong>50% OFF</strong>'
								),
								[
									'strong' => [],
								]
							);
							?>
						</p>
					</div>
				</div>
			</div>
			<div class="sugar-calendar-education-preview">
				<?php
				foreach ( $screenshots as $screenshot ) {
					?>
					<figure>
						<a href="<?php echo esc_url( $screenshot['url'] ); ?>" data-lity data-lity-desc="<?php echo esc_attr( $screenshot['title'] ); ?>">
							<img src="<?php echo esc_url( $screenshot['url_thumbnail'] ); ?>" alt="">
						</a>
						<figcaption>
							<dl>
								<dt><?php echo esc_html( $screenshot['title'] ); ?></dt>
							</dl>
						</figcaption>
					</figure>
					<?php
				}
				?>
			</div>
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
