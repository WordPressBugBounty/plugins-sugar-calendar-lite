<?php

namespace Sugar_Calendar\Admin\Tools;

use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Helpers\Helpers;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\count_tickets;


/**
 * Sugar Calendar Dashboard Widget.
 *
 * @since 3.6.0
 */
class DashboardWidget {

	/**
	 * The action for saving widget meta.
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	const SAVE_WIDGET_META_ACTION = 'sc_admin_widget_meta_hide_recommended_plugin';

	/**
	 * The meta key for the hide education meta.
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	const SAVE_WIDGET_META_KEY = 'sugar_calendar_hide_dashboard_recommended_plugin';

	/**
	 * The nonce for saving widget meta.
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	const SAVE_WIDGET_META_NONCE = 'sc_admin_widget_meta_hide_recommended_plugin';

	/**
	 * Initialize the Dashboard Widget.
	 *
	 * @since 3.7.0
	 */
	public function init() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		add_action( 'admin_init', [ $this, 'admin_init' ] );
	}

	/**
	 * Admin init.
	 *
	 * @since 3.7.0
	 */
	public function admin_init() {

		$this->hooks();
	}

	/**
	 * Widget hooks.
	 *
	 * @since 3.7.0
	 */
	public function hooks() {

		add_action( 'wp_ajax_' . self::SAVE_WIDGET_META_ACTION, [ $this, 'save_user_preference_hide_recommended_plugin' ] );

		if ( ! $this->is_dashboard_page() ) {
			return;
		}

		add_action( 'wp_dashboard_setup', [ $this, 'widget_register' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// Filter for event data.
		add_filter( 'sugar_calendar_admin_tools_dashboard_widget_event_data', [ $this, 'filter_event_data' ], 10, 2 );

		// Filter for event list item details extra.
		add_filter( 'sugar_calendar_admin_tools_dashboard_widget_event_list_item_details_extra', [ $this, 'filter_event_list_item_details_extra' ], 10, 2 );
	}

	/**
	 * Enqueue scripts and styles for the widget.
	 *
	 * @since 3.7.0
	 */
	public function enqueue_scripts() {

		if ( ! $this->is_dashboard_page() ) {
			return;
		}

		wp_enqueue_style(
			'sugar-calendar-admin-dashboard-widget',
			SC_PLUGIN_ASSETS_URL . 'css/admin-dashboard' . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);
	}

	/**
	 * Register the widget.
	 *
	 * @since 3.7.0
	 */
	public function widget_register() {

		global $wp_meta_boxes;

		$widget_key = 'sugar_calendar_dashboard_widget';

		wp_add_dashboard_widget(
			$widget_key,
			esc_html__( 'Sugar Calendar', 'sugar-calendar-lite' ),
			[ $this, 'widget_content' ]
		);

		// Attempt to place the widget at the top.
		$normal_dashboard = $wp_meta_boxes['dashboard']['normal']['core'];
		$widget_instance  = [ $widget_key => $normal_dashboard[ $widget_key ] ];

		unset( $normal_dashboard[ $widget_key ] );

		$sorted_dashboard = array_merge( $widget_instance, $normal_dashboard );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;
	}

	/**
	 * Load widget content.
	 *
	 * @since 3.7.0
	 */
	public function widget_content() {

		?>
		<div class="sugar-calendar-dash-widget">

			<div class="sugar-calendar-dash-widget-content">

				<?php $this->render_widget_header(); ?>

				<?php $this->render_upcoming_event_list(); ?>
			</div>

			<?php $this->render_education(); ?>
		</div>
		<?php
	}

	/**
	 * Render the widget header.
	 *
	 * @since 3.7.0
	 */
	public function render_widget_header() {

		// Get the new event URL.
		$new_event_url = admin_url( 'post-new.php?post_type=' . sugar_calendar_get_event_post_type_id() );

		?>
		<div class="sugar-calendar-dash-widget-block-title sugar-calendar-dash-widget-block">
			<h3>
				<?php esc_html_e( 'Upcoming Events', 'sugar-calendar-lite' ); ?>
			</h3>

			<a href="<?php echo esc_url( $new_event_url ); ?>" class="button button-small button-secondary">
				<span><?php esc_html_e( 'Create New Event', 'sugar-calendar-lite' ); ?></span>
			</a>
		</div>
		<?php
	}

	/**
	 * Render the event list.
	 *
	 * @since 3.7.0
	 */
	public function render_upcoming_event_list() {

		$widget_events = $this->get_dashboard_widget_events();

		// If no events, render the empty state.
		if ( empty( $widget_events ) ) {

			$this->render_upcoming_event_list_empty();

			return;
		}

		?>
		<div class="sugar-calendar-dash-widget-block-event-list sugar-calendar-dash-widget-block">
			<?php
				foreach ( $widget_events as $widget_event ) {

					$this->render_upcoming_event_list_item( $widget_event );
				}
			?>
		</div>
		<?php
	}

	/**
	 * Render the upcoming event list.
	 *
	 * @since 3.7.0
	 */
	public function render_upcoming_event_list_empty() {

		?>
		<div class="sugar-calendar-dash-widget-block-event-list-empty sugar-calendar-dash-widget-block">
			<div class="sugar-calendar-dash-widget-icon-container">
				<img src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/icons/empty-upcoming-events.svg' ); ?>" alt="<?php esc_attr_e( 'Calendar Icon', 'sugar-calendar-lite' ); ?>" width="80" height="80">
			</div>
			<h3>
				<?php esc_html_e( 'No Upcoming Events!', 'sugar-calendar-lite' ); ?>
			</h3>
		</div>
		<?php
	}

	/**
	 * Render the upcoming event list item.
	 *
	 * @since 3.7.0
	 *
	 * @param array $widget_event The widget event.
	 */
	public function render_upcoming_event_list_item( $widget_event ) {

		// Create time display HTML based on single day or multiday event.
		if ( isset( $widget_event['multiday'] ) && $widget_event['multiday'] ) {
			$time_html = sprintf(
				/* translators: 1: start date, 2: start time, 3: end date, 4: end time. */
				'<span class="sugar-calendar-dash-widget-event-time">%1$s at %2$s - %3$s at %4$s</span>',
				esc_html( $widget_event['start_date'] ),
				esc_html( $widget_event['start_time'] ),
				esc_html( $widget_event['end_date'] ),
				esc_html( $widget_event['end_time'] )
			);
		} elseif ( isset( $widget_event['is_all_day'] ) && $widget_event['is_all_day'] ) {
			$time_html = sprintf(
				/* translators: 1: event date, 2: start time, 3: end time. */
				'<span class="sugar-calendar-dash-widget-event-time">%1$s %2$s</span>',
				esc_html( $widget_event['start_date'] ),
				esc_html__( 'All Day', 'sugar-calendar-lite' )
			);
		} else {
			$time_html = sprintf(
				/* translators: 1: event date, 2: start time, 3: end time. */
				'<span class="sugar-calendar-dash-widget-event-time">%1$s at %2$s - %3$s</span>',
				esc_html( $widget_event['start_date'] ),
				esc_html( $widget_event['start_time'] ),
				esc_html( $widget_event['end_time'] )
			);
		}

		?>
		<div class="sugar-calendar-dash-widget-block-event-list-item">

			<div class="sugar-calendar-dash-widget-block-event-list-item-col">
				<div class="sugar-calendar-dash-widget-event-image-container">
					<img src="<?php echo esc_url( $widget_event['image'] ); ?>" alt="<?php echo esc_attr( $widget_event['title'] ); ?>">
				</div>
			</div>

			<div class="sugar-calendar-dash-widget-block-event-list-item-col">

				<div class="sugar-calendar-dash-widget-event-title">
					<a href="<?php echo esc_url( $widget_event['edit_url'] ); ?>">
						<?php echo esc_html( $widget_event['title'] ); ?>
					</a>
				</div>
				<div class="sugar-calendar-dash-widget-event-details">
					<?php
					/**
					 * Filters the event list item template for additional content.
					 *
					 * @since 3.7.0
					 *
					 * @param string $template     The template for additional content.
					 * @param array  $widget_event The widget event data.
					 */
					$template = apply_filters(
						'sugar_calendar_admin_tools_dashboard_widget_event_list_item_details',
						$time_html,
						$widget_event
					);

					echo wp_kses_post( $template );
					?>
				</div>
			</div>

			<div class="sugar-calendar-dash-widget-block-event-list-item-col">

				<div class="sugar-calendar-dash-widget-event-details-extra">
					<?php
					/**
					 * Filters the event list item template for additional details.
					 *
					 * @since 3.7.0
					 *
					 * @param string $template     The template for additional details.
					 * @param array  $widget_event The widget event data.
					 */
					$template = apply_filters(
						'sugar_calendar_admin_tools_dashboard_widget_event_list_item_details_extra',
						'',
						$widget_event
					);

					echo wp_kses_post( $template );
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get the dashboard widget events.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	public function get_dashboard_widget_events() {

		// Get the upcoming events.
		$upcoming_events = BaseHelpers::get_upcoming_events_list_with_recurring(
			[
				'number' => 4,
			],
			[]
		);

		if ( empty( $upcoming_events ) ) {
			return [];
		}

		$widget_events = [];

		foreach ( $upcoming_events as $event ) {

			$widget_events[] = $this->get_widget_event( $event );
		}

		// Return the upcoming events.
		return $widget_events;
	}

	/**
	 * Get the widget event.
	 *
	 * @since 3.7.0
	 *
	 * @param Event $event The event.
	 *
	 * @return array
	 */
	public function get_widget_event( $event ) {

		// Default widget date and time formats.
		$format_default = [
			'date' => 'M j',
			'time' => 'g:i a',
		];

		/**
		 * Date and time formats for the dashboard widget.
		 *
		 * @since 3.7.0
		 *
		 * @param array $format The date and time formats.
		 * @param Event $event  The event object.
		 */
		$format_user = apply_filters(
			'sugar_calendar_admin_tools_dashboard_widget_date_time_format', // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			$format_default,
			$event
		);

		// Parse args to avoid undefined index errors.
		$format = wp_parse_args( $format_user, $format_default );

		// Use the featured image or default if not set.
		$event_image = get_the_post_thumbnail_url(
			$event->object_id,
			/**
			 * Filter the size of the event image in the Event List block (grid view).
			 *
			 * @since 3.7.0
			 *
			 * @param string $size Image size. Accepts any registered image size name, or an array of width and height values in pixels (in that order).
			 */
			apply_filters( 'sugar_calendar_admin_tools_dashboard_widget_image_size', 'medium' ) // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		);

		if ( ! $event_image ) {
			$event_image = SC_PLUGIN_ASSETS_URL . 'admin/images/event-feat-img-default.svg';
		}

		$event_data = [
			'title'      => $event->title,
			'image'      => $event_image,
			'edit_url'   => get_edit_post_link( $event->object_id ),
			'start_date' => sugar_calendar_format_date_i18n( $format['date'], $event->start ),
			'start_time' => sugar_calendar_format_date_i18n( $format['time'], $event->start ),
			'end_date'   => sugar_calendar_format_date_i18n( $format['date'], $event->end ),
			'end_time'   => sugar_calendar_format_date_i18n( $format['time'], $event->end ),
			'multiday'   => $event->is_multi(),
			'is_all_day' => $event->is_all_day(),
		];

		/**
		 * Filters the event data.
		 *
		 * @since 3.7.0
		 *
		 * @param array $event_data The event data.
		 * @param Event $event      The event object.
		 */
		$event_data = apply_filters(
			'sugar_calendar_admin_tools_dashboard_widget_event_data',
			$event_data,
			$event
		);

		return $event_data;
	}

	/**
	 * Filter the event data to add ticketing information.
	 *
	 * @since 3.7.0
	 *
	 * @param array $event_data The event data.
	 * @param Event $event      The event object.
	 *
	 * @return array
	 */
	public function filter_event_data( $event_data, $event ) {

		// Check if tickets are enabled.
		$event_data['is_tickets_enabled'] = $this->is_event_tickets_enabled( $event->id );

		if ( ! $event_data['is_tickets_enabled'] ) {
			return $event_data;
		}

		// Get ticket total.
		$event_data['ticket_total'] = intval( get_event_meta( $event->id, 'ticket_quantity', true ) );

		// Get tickets purchased.
		$event_data['tickets_purchased'] = max( 0, count_tickets( [ 'event_id' => $event->id ] ) );

		// Get ticket list url with event filter.
		$event_data['ticket_url'] = add_query_arg(
			[
				'page'     => 'sc-event-ticketing',
				'event_id' => $event->id,
			],
			get_admin_url( null, 'admin.php' )
		);

		return $event_data;
	}

	/**
	 * Check if an event has tickets enabled.
	 *
	 * @since 3.7.0
	 *
	 * @param int $event_id Event ID.
	 *
	 * @return bool
	 */
	public function is_event_tickets_enabled( $event_id ) {

		$tickets_enabled  = boolval( intval( get_event_meta( $event_id, 'tickets', true ) ) );
		$tickets_quantity = intval( get_event_meta( $event_id, 'ticket_quantity', true ) );

		return $tickets_enabled && $tickets_quantity > 0;
	}

	/**
	 * Filter the event list item details extra to add ticketing information.
	 *
	 * @since 3.7.0
	 *
	 * @param string $details The details.
	 * @param array  $event   The event.
	 *
	 * @return string
	 */
	public function filter_event_list_item_details_extra( $details, $event ) {

		// If tickets are not enabled, return the details.
		if (
			empty( $event['is_tickets_enabled'] )
			||
			! $event['is_tickets_enabled']
			||
			! isset( $event['ticket_total'] )
			||
			! isset( $event['tickets_purchased'] )
			||
			! isset( $event['ticket_url'] )
		) {
			return $details;
		}

		$details = wp_sprintf(
			'<span class="sugar-calendar-dash-widget-event-detail-remaining">%1$s%2$s</span>
			<a class="sugar-calendar-dash-widget-event-extra-icon %3$s" href="%4$s"></a>
			<span class="sugar-calendar-dash-widget-event-extra-tooltip">%5$s</span>',
			esc_html( $event['tickets_purchased'] ),
			$event['ticket_total'] > 0 ? esc_html( ' / ' . $event['ticket_total'] ) : '',
			esc_attr( 'sugar-calendar-dash-widget-event-extra-icon-ticketing' ),
			esc_url( $event['ticket_url'] ),
			esc_html__( 'Event Tickets', 'sugar-calendar-lite' )
		);

		return $details;
	}

	/**
	 * Render the education block.
	 *
	 * @since 3.7.0
	 */
	public function render_education() {

		if ( $this->widget_meta_hide_education( 'get' ) ) {
			return;
		}

		// If not Pro, render default education.
		if ( ! sugar_calendar()->is_pro() ) {

			$upgrade_link = esc_url(
				Helpers::get_upgrade_link(
					[
						'medium'  => 'dashboard-widget',
						'content' => 'Get Pro',
					]
				)
			);

			?>
			<div class="sugar-calendar-dash-widget-education-block sugar-calendar-dash-widget-block">

				<span class="sugar-calendar-dash-widget-recommended-plugin">
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: %1$s is the URL for the upgrade link, %2$s is the link text. */
							__( 'Upgrade to <strong>Sugar Calendar Pro</strong> and get access to powerful features like Venues, Speakers, Recurring Events, and much more! <a href="%1$s">%2$s</a>', 'sugar-calendar-lite' ),
							esc_url( $upgrade_link ),
							__( 'Get Pro', 'sugar-calendar-lite' )
						)
					);
					?>
				</span>

				<button
					type="button"
					class="sugar-calendar-dash-widget-dismiss-icon sugar-calendar-widget-ajax-action"
					title="<?php esc_html_e( 'Dismiss', 'sugar-calendar-lite' ); ?>"
					data-meta-action="<?php echo esc_attr( self::SAVE_WIDGET_META_ACTION ); ?>"
					data-meta-nonce="<?php echo esc_attr( wp_create_nonce( self::SAVE_WIDGET_META_NONCE ) ); ?>"
					data-meta-name="<?php echo esc_attr( self::SAVE_WIDGET_META_KEY ); ?>"
					data-meta-value="1"
					data-callback="closeWidgetBlock"
				>
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
			<?php

			return;
		}

		$plugin = $this->get_recommended_plugin();

		if (
			empty( $plugin )
		) {
			return;
		}

		$install_url = wp_nonce_url(
			self_admin_url( 'update.php?action=install-plugin&plugin=' . rawurlencode( $plugin['slug'] ) ),
			'install-plugin_' . $plugin['slug']
		);

		?>
		<div class="sugar-calendar-dash-widget-recommended-plugin-block sugar-calendar-dash-widget-block">

			<span class="sugar-calendar-dash-widget-recommended-plugin">
				<span class="recommended"><?php esc_html_e( 'Recommended Plugin:', 'sugar-calendar-lite' ); ?></span>
				<strong><?php echo esc_html( $plugin['name'] ); ?></strong>
				<span class="sep">-</span>
				<span class="action-links">
					<?php if ( $this->can_install_plugin() ) { ?>
						<a href="<?php echo esc_url( $install_url ); ?>"><?php esc_html_e( 'Install', 'sugar-calendar-lite' ); ?></a>
						<span class="sep sep-vertical">&vert;</span>
					<?php } ?>
					<a href="<?php echo esc_url( $plugin['more'] ); ?>?utm_source=wpformsplugin&utm_medium=link&utm_campaign=wpformsdashboardwidget"><?php esc_html_e( 'Learn More', 'sugar-calendar-lite' ); ?></a>
				</span>
			</span>

			<button
				type="button"
				class="sugar-calendar-dash-widget-dismiss-icon sugar-calendar-widget-ajax-action"
				title="<?php esc_html_e( 'Dismiss', 'sugar-calendar-lite' ); ?>"
				data-meta-action="<?php echo esc_attr( self::SAVE_WIDGET_META_ACTION ); ?>"
				data-meta-nonce="<?php echo esc_attr( wp_create_nonce( self::SAVE_WIDGET_META_NONCE ) ); ?>"
				data-meta-name="<?php echo esc_attr( self::SAVE_WIDGET_META_KEY ); ?>"
				data-meta-value="1"
				data-callback="closeWidgetBlock"
			>
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<?php
	}

	/**
	 * Get/set widget meta.
	 *
	 * @since 3.7.0
	 *
	 * @param string $action Possible value: 'get' or 'set'.
	 * @param int    $value  Value to set.
	 *
	 * @return mixed
	 */
	protected function widget_meta_hide_education( $action, $value = 0 ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$allowed_actions = [ 'get', 'set' ];

		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return false;
		}

		$meta_key = self::SAVE_WIDGET_META_KEY;
		$user_id  = get_current_user_id();

		if ( $action === 'get' ) {
			return get_user_meta( $user_id, $meta_key, true );
		}

		if ( $action === 'set' && ! empty( $value ) ) {
			return update_user_meta( $user_id, $meta_key, true );
		}

		return false;
	}

	/**
	 * Check if the current user can install plugins.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	protected function can_install_plugin() {

		if ( ! current_user_can( 'install_plugins' ) ) {
			return false;
		}

		// Check if file modifications are allowed.
		if ( ! wp_is_file_mod_allowed( 'sugar_calendar_can_install' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the recommended plugin.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	public function get_recommended_plugin() {

		$plugins = [
			'wp-mail-smtp/wp_mail_smtp.php'               => [
				'name' => __( 'WP Mail SMTP', 'sugar-calendar-lite' ),
				'slug' => 'wp-mail-smtp',
				'more' => 'https://wpmailsmtp.com/',
				'pro'  => [
					'file' => 'wp-mail-smtp-pro/wp_mail_smtp.php',
				],
			],
			'wpforms-lite/wpforms.php'                    => [
				'name' => __( 'WP Forms', 'sugar-calendar-lite' ),
				'slug' => 'wpforms-lite',
				'more' => 'https://wpforms.com/',
				'pro'  => [
					'file' => 'wpforms/wpforms.php',
				],
			],
			'google-analytics-for-wordpress/googleanalytics.php' => [
				'name' => __( 'MonsterInsights', 'sugar-calendar-lite' ),
				'slug' => 'google-analytics-for-wordpress',
				'more' => 'https://www.monsterinsights.com/',
				'pro'  => [
					'file' => 'google-analytics-premium/googleanalytics-premium.php',
				],
			],
			'all-in-one-seo-pack/all_in_one_seo_pack.php' => [
				'name' => __( 'AIOSEO', 'sugar-calendar-lite' ),
				'slug' => 'all-in-one-seo-pack',
				'more' => 'https://aioseo.com/',
				'pro'  => [
					'file' => 'all-in-one-seo-pack-pro/all_in_one_seo_pack.php',
				],
			],
			'coming-soon/coming-soon.php'                 => [
				'name' => __( 'SeedProd', 'sugar-calendar-lite' ),
				'slug' => 'coming-soon',
				'more' => 'https://www.seedprod.com/',
				'pro'  => [
					'file' => 'seedprod-coming-soon-pro-5/seedprod-coming-soon-pro-5.php',
				],
			],
		];

		$installed = get_plugins();

		foreach ( $plugins as $id => $plugin ) {

			if ( isset( $installed[ $id ] ) ) {
				unset( $plugins[ $id ] );
			}

			if ( isset( $plugin['pro']['file'], $installed[ $plugin['pro']['file'] ] ) ) {
				unset( $plugins[ $id ] );
			}
		}

		// Return the first plugin in the array.
		return $plugins ? reset( $plugins ) : [];
	}

	/**
	 * Check if the current page is a dashboard page.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	protected function is_dashboard_page() {

		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return $pagenow === 'index.php' && empty( $_GET['page'] );
	}

	/**
	 * Save widget meta.
	 *
	 * @since 3.7.0
	 */
	public function save_user_preference_hide_recommended_plugin() {

		check_ajax_referer( self::SAVE_WIDGET_META_NONCE, 'nonce' );

		$meta_name  = isset( $_POST['meta']['name'] ) ? sanitize_text_field( wp_unslash( $_POST['meta']['name'] ) ) : '';
		$meta_value = isset( $_POST['meta']['value'] ) ? sanitize_text_field( wp_unslash( $_POST['meta']['value'] ) ) : '';

		if ( empty( $meta_name ) || empty( $meta_value ) ) {
			wp_send_json_error( 'Invalid meta name or value' );
		}

		// Set the user preference.
		$this->widget_meta_hide_education( 'set', $meta_value );

		exit;
	}
}
