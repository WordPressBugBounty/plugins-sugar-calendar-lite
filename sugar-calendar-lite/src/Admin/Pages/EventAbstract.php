<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\PageAbstract;
use Sugar_Calendar\Admin\Pages\VenuesAbstract;
use Sugar_Calendar\Admin\Pages\SpeakersAbstract;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Helpers;
use Sugar_Calendar\Helpers\Helpers as ExtHelpers;
use WP_Post;

/**
 * Abstract Event page.
 *
 * @since 3.0.0
 */
abstract class EventAbstract extends PageAbstract {

	/**
	 * Whether to display the hand holding.
	 *
	 * @since 3.7.0
	 *
	 * @var bool
	 */
	private $should_display_hand_holding = null;

	/**
	 * Page slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	abstract public static function get_slug();

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	abstract public static function get_label();

	/**
	 * Page menu priority.
	 *
	 * @since 3.0.0
	 *
	 * @return int
	 */
	public static function get_priority() {

		return 1;
	}

	/**
	 * Which menu item to highlight
	 * if the page doesn't appear in dashboard menu.
	 *
	 * @since 3.0.0
	 *
	 * @return null|string;
	 */
	public static function highlight_menu_item() {

		return static::get_slug();
	}

	/**
	 * Register page hooks.
	 *
	 * @since 3.0.0
	 * @since 3.5.1 Add loading of compatibility hooks.
	 */
	public function hooks() {

		add_filter( 'screen_options_show_screen', '__return_false' );
		add_action( 'in_admin_header', [ $this, 'display_admin_subheader' ] );
		add_filter( 'enter_title_here', [ $this, 'get_title_field_placeholder' ], 10, 2 );
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );

		// Load compatibility hooks.
		Helpers::load_compatibility_hooks();

		add_action( 'admin_footer', [ $this, 'render_hand_holding_html' ] );
	}

	/**
	 * Render the hand holding HTML elements.
	 *
	 * @since 3.7.0
	 */
	public function render_hand_holding_html() {

		if ( ! $this->should_display_hand_holding() ) {
			return;
		}
		?>
		<div id="sc-hand-holding__overlay"></div>
		<div id="sc-hand-holding__tooltip" role="tooltip">
			<div id="sc-hand-holding__tooltip__body">
				<div id="sc-hand-holding__tooltip__close"></div>
				<strong></strong>
				<div id="sc-hand-holding__tooltip__content"></div>
				<div id="sc-hand-holding__tooltip__footer" role="tooltip">
					<a id="sc-hand-holding__tooltip__footer__next" href="#"><?php esc_html_e( 'Next', 'sugar-calendar-lite' ); ?></a>
					<div id="sc-hand-holding__tooltip__footer__progress">
						<span id="sc-hand-holding__tooltip__footer__progress__current"></span>/<span id="sc-hand-holding__tooltip__footer__progress__total"></span>
					</div>
				</div>
				<div id="sc-hand-holding__tooltip__arrow"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Whether or not to display the hand holding guide.
	 *
	 * @since 3.7.0
	 *
	 * @return string|false
	 */
	private function should_display_hand_holding() {

		if ( ! is_null( $this->should_display_hand_holding ) ) {
			return $this->should_display_hand_holding;
		}

		$hand_holding_status = get_option( 'sc_hand_holding_status', false );

		if ( empty( $hand_holding_status ) ) {
			global $wpdb;

			$events_count = absint( $wpdb->get_var( "SELECT COUNT(id) FROM " . $wpdb->prefix . "sc_events" ) );

			// We will not show if there are existing events.
			$this->should_display_hand_holding = empty( $events_count );
		} elseif ( empty( $hand_holding_status['end_time'] ) ) {
			$this->should_display_hand_holding = true;
		} elseif ( ! empty( $hand_holding_status['status'] ) && $hand_holding_status['status'] === 'publish' ) {
			$this->should_display_hand_holding = true;
		} else {
			$this->should_display_hand_holding = false;
		}

		return $this->should_display_hand_holding;
	}

	/**
	 * Display the subheader.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function display_admin_subheader() {

		global $pagenow;

		if ( ! in_array( $pagenow, [ 'post-new.php', 'post.php' ], true ) ) {
			return;
		}
		?>
        <div class="sugar-calendar-admin-subheader">
            <h4><?php echo esc_html( static::get_label() ); ?></h4>
        </div>
		<?php
	}

	/**
	 * Set the placeholder text for the title field for this post type.
	 *
	 * @since 2.0.0
	 *
	 * @param string  $title The placeholder text.
	 * @param WP_Post $post  The current post.
	 *
	 * @return string The updated placeholder text.
	 */
	public function get_title_field_placeholder( $title, WP_Post $post ) {

		// Override if primary post type.
		if ( sugar_calendar_get_event_post_type_id() === $post->post_type ) {
			$title = esc_html__( 'Name this event', 'sugar-calendar-lite' );
		}

		// Return possibly modified title.
		return $title;
	}

	/**
	 * Localized data to be used in admin-event.js.
	 *
	 * @since 3.3.0
	 *
	 * @return array
	 */
	public function get_localized_scripts() {

		return [
			'notice_title_required' => esc_html__( 'Event name is required', 'sugar-calendar-lite' ),
		];
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_register_script(
			'floating-ui-core',
			SC_PLUGIN_ASSETS_URL . 'lib/floating-ui/core-1.6.0.min.js',
			[],
			'1.6.0'
		);

		wp_register_script(
			'floating-ui-dom',
			SC_PLUGIN_ASSETS_URL . 'lib/floating-ui/dom-1.6.3.min.js',
			[],
			'1.6.3'
		);

		wp_enqueue_style(
			'sugar-calendar-admin-event',
			SC_PLUGIN_ASSETS_URL . 'css/admin-event' . WP::asset_min() . '.css',
			[],
			Helpers::get_asset_version()
		);

		// Enqueue venue styles.
		VenuesAbstract::enqueue_assets();

		// Enqueue speakers styles.
		SpeakersAbstract::enqueue_assets();

		wp_enqueue_script(
			'sugar-calendar-admin-event',
			SC_PLUGIN_ASSETS_URL . 'js/admin-event' . WP::asset_min() . '.js',
			[ 'jquery' ],
			Helpers::get_asset_version(),
			true
		);

		wp_localize_script(
			'sugar-calendar-admin-event',
			'sugar_calendar_admin_event_vars',
			$this->get_localized_scripts()
		);

		if ( $this->should_display_hand_holding() ) {
			wp_enqueue_style(
				'sugar-calendar-hand-holding',
				SC_PLUGIN_ASSETS_URL . '/css/admin/hand-holding' . WP::asset_min() . '.css',
				[
					'sugar-calendar-vendor-jquery-confirm',
				],
				Helpers::get_asset_version()
			);
	
			wp_enqueue_script(
				'sugar-calendar-hand-holding',
				SC_PLUGIN_ASSETS_URL . 'admin/js/hand-holding' . WP::asset_min() . '.js',
				[ 'jquery', 'floating-ui-core', 'floating-ui-dom', 'sugar-calendar-vendor-jquery-confirm' ],
				Helpers::get_asset_version(),
				true
			);

			$dummy_event_content = sprintf(
				'<p>%1$s</p><p>%2$s</p><p>%3$s</p>',
				__( "Join us for a night of soulful melodies and heartfelt performances at our Acoustic Open Mic Night! Whether you're a seasoned musician or just starting out, this is the perfect opportunity to showcase your talent in a warm and welcoming environment. Bring your guitar, ukulele, or just your voice, and share your favorite songs or original compositions with an appreciative audience.", 'sugar-calendar-lite' ),
				__( 'Enjoy a cozy atmosphere, delicious snacks, and the chance to connect with fellow music lovers.', 'sugar-calendar-lite' ),
				__( "Don't miss out on this vibrant celebration of creativity and community!", 'sugar-calendar-lite' )
			);

			$ticket_step_content = sprintf(
				'<p>%1$s <a target="_blank" href="%2$s">%3$s</a></p>',
				__( 'To sell tickets, enable ticketing here, but ensure your Stripe account is connected first.', 'sugar-calendar-lite' ),
				esc_url(
					ExtHelpers::get_utm_url(
						'https://sugarcalendar.com/docs/setting-up-event-ticketing-with-sugar-calendar-lite/',
						[
							'medium'  => 'hand-holding-ticket-step',
							'content' => 'Learn More',
						]
					)
				),
				__( 'Learn More', 'sugar-calendar-lite' )
			);

			$publish_step_content = sprintf(
				'<p>%1$s</p><p>%2$s</p>',
				__( 'Once all your event details are added, you can publish your event!', 'sugar-calendar-lite' ),
				__( 'Your event can be edited anytime from the events page.', 'sugar-calendar-lite' )
			);

			$hand_holding_status = get_option( 'sc_hand_holding_status', false );

			if (
				empty( $hand_holding_status ) || empty( $hand_holding_status['status'] )
			) {
				$hand_holding_status = 'start';
			} else {
				$hand_holding_status = $hand_holding_status['status'];
			}

			wp_localize_script(
				'sugar-calendar-hand-holding',
				'sugar_calendar_hand_holding',
				/**
				 * Filters the Hand Holding JS data.
				 *
				 * @since 3.7.0
				 *
				 * @param array $data The hand holding data.
				 */
				apply_filters(
					'sugar_calendar_admin_pages_event_abstract_hand_holding',
					[
						'status'  => $hand_holding_status,
						'urls'    => [
							'ajax_url'       => admin_url( 'admin-ajax.php' ),
							'admin_calendar' => esc_url( admin_url( 'admin.php?page=sugar-calendar' ) ),
							'sc_ext_docs'    => esc_url(
									ExtHelpers::get_utm_url(
									'https://sugarcalendar.com/docs/displaying-the-calendar/',
									[
										'medium'  => 'hand-holding-docs',
										'content' => 'Check Documentation',
									]
								)
							),
						],
						'nonce'   => wp_create_nonce( 'sc_hand_holding_status' ),
						'strings' => [
							'cancel_modal' => [
								'content_title' => wp_kses_post(
									__( 'Are you sure you want to cancel our event creation guide?', 'sugar-calendar-lite' )
								),
								'no'            => [
									'label' => wp_kses_post(
										__( 'No', 'sugar-calendar-lite' )
									),
								],
								'yes'            => [
									'label' => wp_kses_post(
										__( 'Yes', 'sugar-calendar-lite' )
									),
								],
							],
							'start_modal'  => [
								'content'       => wp_kses_post(
									__( 'Take a few minutes to walkthrough the whole guided process', 'sugar-calendar-lite' )
								),
								'content_title' => wp_kses_post(
									__( "Let's create your first event!", 'sugar-calendar-lite' )
								),
								'image_url'     => esc_url(
									SC_PLUGIN_URL . 'assets/images/Gia.png'
								),
								'title'         => wp_kses_post(
									__( "Let's get started!", 'sugar-calendar-lite' )
								),
							],
							'end_modal'    => [
								'button_finish' => [
									'label' => wp_kses_post( __( 'Finish Setup', 'sugar-calendar-lite' ) ),
								],
								'button_docs'   => [
									'label' => wp_kses_post( __( 'Check Documentation', 'sugar-calendar-lite' ) ),
								],
								'content'       => wp_kses_post(
									__( 'After publishing, display events on your website using our native blocks for Elementor and WP Block Editor, or use shortcodes found in our documentation.', 'sugar-calendar-lite' )
								),
								'content_title' => wp_kses_post(
									__( 'Congratulations! You are ready to roll!', 'sugar-calendar-lite' )
								),
								'image_url'     => esc_url(
									SC_PLUGIN_URL . 'assets/images/hand-holding-finish.png'
								),
							],
							'done'         => wp_kses_post(
								__( 'Done & Publish', 'sugar-calendar-lite' )
							),
						],
						'steps'   => [
							[
								'container'  => '#titlewrap',
								'dummy'      => [
									'screenReader' => '#title-prompt-text',
									'field'        => '#title',
									'value'        => wp_kses_post(
										__( 'Acoustic Open Mic', 'sugar-calendar-lite' )
									),
								],
								'highlights' => [],
								'key'        => 'title',
								'tooltip'    => [
									'content'   => sprintf(
										'<p>%1$s</p>',
										wp_kses_post(
											__( "Every event must have a name for it to be published. For example, we'll add a name to get you started.", 'sugar-calendar-lite' )
										)
									),
									'placement' => 'right',
									'title'     => wp_kses_post(
										__( 'Event name', 'sugar-calendar-lite' )
									),
								],
							],
							[
								'container'  => '#sugar_calendar_details',
								'dummy'      => [
									'value' => wp_kses_post( $dummy_event_content ),
								],
								'highlights' => [],
								'key'        => 'details',
								'tooltip'    => [
									'content'   => sprintf(
										'<p>%1$s</p>',
										wp_kses_post(
											__( 'Add your event description here, like a brief summary, agenda, requirements, and plans.', 'sugar-calendar-lite' )
										)
									),
									'placement' => 'right-start',
									'title'     => wp_kses_post(
										__( 'Event description', 'sugar-calendar-lite' )
									),
								],
							],
							[
								'container'  => '#sugar-calendar-metabox__section__duration',
								'dummy'      => [
									'value' => '+3 days',
								],
								'highlights' => [
									'#sugar-calendar-metabox__navigation__button-duration',
								],
								'key'        => 'duration',
								'tooltip'    => [
									'content'   => sprintf(
										'<p>%1$s</p>',
										wp_kses_post(
											__( 'Define the start and end times, or whether this is an all day event, to move to the next step.', 'sugar-calendar-lite' )
										)
									),
									'placement' => 'right-start',
									'title'     => wp_kses_post(
										__( 'Set event duration', 'sugar-calendar-lite' )
									),
								],
								'type'       => 'panel',
							],
							[
								'container'  => '#sugar-calendar-metabox__section__adv-recurrence',
								'highlights' => [
									'#sugar-calendar-metabox__navigation__button-adv-recurrence',
								],
								'key'        => 'recurrence-lite',
								'tooltip'    => [
									'content'   => sprintf(
										'<p>%1$s</p>',
										wp_kses_post(
											__( 'For repeating events, use the recurrence feature to easily set up & manage them.', 'sugar-calendar-lite' )
										)
									),
									'placement' => 'right-start',
									'title'     => wp_kses_post(
										__( 'Event recurrence', 'sugar-calendar-lite' )
									),
								],
								'type'       => 'panel',
							],
							[
								'container'  => '#sugar-calendar-metabox__section__location',
								'highlights' => [
									'#sugar-calendar-metabox__navigation__button-location',
								],
								'key'        => 'location-lite',
								'tooltip'    => [
									'content'   => sprintf(
										'<p>%1$s</p>',
										wp_kses_post(
											__( 'You can add a simple address for your event. If you are managing multiple event locations, upgrade to Pro for our awesome Venues feature.', 'sugar-calendar-lite' )
										)
									),
									'placement' => 'right-start',
									'title'     => wp_kses_post(
										__( 'Event Location', 'sugar-calendar-lite' )
									),
								],
								'type'       => 'panel',
							],
							[
								'container'  => '#sugar-calendar-metabox__section__tickets',
								'highlights' => [
									'#sugar-calendar-metabox__navigation__button-tickets',
								],
								'key'        => 'tickets',
								'tooltip'    => [
									'content'   => wp_kses_post( $ticket_step_content ),
									'placement' => 'right-start',
									'title'     => wp_kses_post(
										__( 'Event Tickets', 'sugar-calendar-lite' )
									),
								],
								'type'       => 'panel',
							],
							[
								'container'  => '#sc_event_categorydiv',
								'highlights' => [],
								'key'        => 'calendars',
								'tooltip'    => [
									'content'   => sprintf(
										'<p>%1$s</p>',
										wp_kses_post(
											__( "Set up which calendar you'd like this event to be connected to", 'sugar-calendar-lite' )
										)
									),
									'placement' => 'left-start',
									'title'     => wp_kses_post(
										__( 'Set Calendars', 'sugar-calendar-lite' )
									),
								],
							],
							[
								'container'  => '#submitdiv',
								'complete'   => true,
								'highlights' => [],
								'key'        => 'publish',
								'tooltip'    => [
									'content'   => wp_kses_post( $publish_step_content ),
									'placement' => 'left-start',
									'title'     => wp_kses_post(
										__( 'Publish', 'sugar-calendar-lite' )
									),
								],
							],
						],
					]
				)
			);
		}
	}
}
