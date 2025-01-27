<?php

namespace Sugar_Calendar\Admin\Tools\Importers;

use Sugar_Calendar\Admin\Tools\Importers;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Options;
use Sugar_Calendar\Plugin;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\add_order;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\add_ticket;

/**
 * The Events Calendar Migrator.
 *
 * @since 3.3.0
 */
class TheEventCalendar extends Importer {

	/**
	 * The TEC to SC migration option key.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const SC_TEC_MIGRATION_OPTION_KEY = 'sugar_calendar_tec_migration';

	/**
	 * DB table used to keep track of the events migrated.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const MIGRATE_EVENTS_TABLE = 'sc_migrate_tec_events';

	/**
	 * DB table used to keep track of the tickets migrated.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const MIGRATE_TICKETS_TABLE = 'sc_migrate_tec_tickets';

	/**
	 * DB table used to keep track of the attendees migrated.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const MIGRATE_ATTENDEES_TABLE = 'sc_migrate_tec_attendees';

	/**
	 * DB table used to keep track of the orders migrated.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const MIGRATE_ORDERS_TABLE = 'sc_migrate_tec_orders';

	/**
	 * The number of TEC events to import.
	 *
	 * @since 3.3.0
	 *
	 * @var int
	 */
	private $number_of_tec_events_to_import = null;

	/**
	 * TEC custom fields.
	 *
	 * @since 3.3.0
	 *
	 * @var array
	 */
	private $tec_custom_fields = null;

	/**
	 * TEC migration option.
	 *
	 * @since 3.3.0
	 *
	 * @var mixed
	 */
	private static $tec_migration_option = null;

	/**
	 * {@inheritDoc}.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	public function get_title() {

		return __( 'Migrate From The Events Calendar', 'sugar-calendar-lite' );
	}

	/**
	 * {@inheritDoc}.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	public function get_slug() {

		return 'the-events-calendar';
	}

	/**
	 * Run admin hooks.
	 *
	 * @since 3.3.0
	 */
	public function admin_hooks() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		$this->auto_detect_for_migration();
	}

	/**
	 * Detect if TEC migration is possible.
	 *
	 * @since 3.3.0
	 */
	private function auto_detect_for_migration() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		// Only display the TEC migration notice on SC admin pages.
		if ( ! Plugin::instance()->get_admin()->is_sc_admin_page() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if (
			Plugin::instance()->get_admin()->is_page( 'tools_migrate' ) &&
			! empty( $_GET['importer'] ) &&
			$_GET['importer'] === 'the-events-calendar'
		) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! self::is_migration_possible() ) {
			return;
		}

		// Check if the migration notice was dismissed before.
		$dismissed_migrations = json_decode( get_option( Importers::DISMISSED_MIGRATIONS_OPTION_KEY, false ) );

		if ( ! empty( $dismissed_migrations ) && is_array( $dismissed_migrations ) && in_array( $this->get_slug(), $dismissed_migrations, true ) ) {
			return;
		}

		add_action( 'admin_notices', [ $this, 'show_tec_migration_notice' ] );
	}

	/**
	 * Check if there are TEC event post to migrate.
	 *
	 * @since 3.3.0
	 *
	 * @return bool
	 */
	public static function is_migration_possible() {

		// Check if there's any TEC event post.
		$tec_event_post = get_posts(
			[
				'post_type'   => 'tribe_events',
				'numberposts' => 1,
			]
		);

		return ! empty( $tec_event_post ) &&
			( self::get_tec_migration_option() === false || self::get_tec_migration_option() === 'in_progress' );
	}

	/**
	 * Show the TEC migration notice.
	 *
	 * @since 3.3.0
	 */
	public function show_tec_migration_notice() {
		?>
		<div class="notice sugar-calendar-notice notice-warning notice is-dismissible">
			<p>
			<?php
			if ( $this->get_tec_migration_option() === 'in_progress' ) {
				echo wp_kses(
					sprintf(
						/* translators: %s: Sugar Calendar to TEC migration admin page. */
						__(
							'Sugar Calendar to The Events Calendar migration was not completed. Please complete the migration <a href="%s">here</a>.',
							'sugar-calendar-lite'
						),
						esc_url( $this->get_migration_page_url() )
					),
					[
						'a' => [
							'href' => [],
						],
					]
				);
			} else {
				echo wp_kses(
					sprintf(
						/* translators: %s: Sugar Calendar to TEC migration admin page. */
						__(
							'Sugar Calendar has detected The Events Calendar events on this site. Migrate them to Sugar Calendar with our <a href="%s">1-click migration tool</a>.',
							'sugar-calendar-lite'
						),
						esc_url( $this->get_migration_page_url() )
					),
					[
						'a' => [
							'href' => [],
						],
					]
				);
			}
			?>
			</p>
			<button id="sc-admin-tools-migrate-notice-dismiss" data-nonce="<?php echo esc_attr( wp_create_nonce( Importers::MIGRATION_NOTICE_DISMISS_NONCE_ACTION ) ); ?>"
				data-migration-slug="<?php echo esc_attr( $this->get_slug() ); ?>" type="button" class="notice-dismiss">
				<span class="screen-reader-text">
					<?php esc_html_e( 'Dismiss this notice.', 'sugar-calendar-lite' ); ?>
				</span>
			</button>
		</div>
		<?php
	}

	/**
	 * Get the TEC to SC migration admin page url.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	private function get_migration_page_url() {

		return add_query_arg(
			[
				'section'  => 'migrate',
				'page'     => 'sc-tools',
				'importer' => 'the-events-calendar',
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * The Migration admin page display.
	 *
	 * @since 3.3.0
	 *
	 * @return void
	 */
	public function display() {

		if ( empty( $this->get_number_of_tec_events_to_import() ) && self::get_tec_migration_option() === false ) {
			printf(
				'<p>%s</p>',
				esc_html__( 'You have no The Events Calendar events to import.', 'sugar-calendar-lite' )
			);

			return;
		}

		$should_warn_about_recurring_events = ! sugar_calendar()->is_pro() && $this->detected_recurring_events_to_migrate();
		?>

		<p>
			<?php
			if ( $this->get_tec_migration_option() === 'in_progress' ) {
				esc_html_e( 'The previous migration was not completed. Click the button below to continue the migration.', 'sugar-calendar-lite' );
				$btn_text = __( 'Continue Migration', 'sugar-calendar-lite' );
			} else {

				echo esc_html(
					sprintf(
						/* translators: %s: A sentence describing the number of items per context to be imported. */
						__(
							'There are %s defined in The Events Calendar. You can import them to Sugar Calendar with just one click!',
							'sugar-calendar-lite'
						),
						$this->get_number_of_items_per_context_string()
					)
				);
				$btn_text = __( 'Migrate Events', 'sugar-calendar-lite' );
			}
			?>
		</p>
		<?php
		if ( $should_warn_about_recurring_events ) {
			?>
			<div id="sc-admin-importer-tec-recur-info-warning" class="sc-admin-tools-import-notice sc-admin-tools-import-notice__warning">
				<p>
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s: Sugar Calendar Pro pricing page URL. */
							__(
								'The Events Calendar migration contains recurring events. Please <a target="_blank" href="%1$s">upgrade to Sugar Calendar Pro</a>, to successfully import recurring events. If you want to proceed with this migration on Sugar Calendar Lite, then the recurring events will be converted to normal non-recurring events. Are you sure you want to continue?',
								'sugar-calendar-lite'
							),
							esc_url(
								Helpers::get_utm_url(
									'https://sugarcalendar.com/lite-upgrade/',
									[
										'medium'  => 'tools-tec-migration',
										'content' => 'recurring-events-upgrade',
									]
								)
							)
						),
						[
							'a' => [
								'href'   => [],
								'target' => [],
							],
						]
					);
					?>
				</p>
			</div>
			<?php
		}
		?>
		<div class="sc-admin-tools-divider"></div>
		<p id="sc-admin-importer-tec-status" style="display: none;"></p>
		<div id="sc-admin-importer-tec-logs"></div>
		<p>
			<?php
			$data_warning = $should_warn_about_recurring_events ? '1' : '0';
			?>
			<button
				id="sc-admin-tools-import-btn"
				class="sugar-calendar-btn sugar-calendar-btn-primary sugar-calendar-btn-md"
				data-importer="<?php echo esc_attr( $this->get_slug() ); ?>"
				data-warning="<?php echo esc_attr( $data_warning ); ?>"
			>
				<?php echo esc_html( $btn_text ); ?>
			</button>
		</p>
		<?php
	}

	/**
	 * Return the number of TEC recurring events to migrate.
	 *
	 * @since 3.3.0
	 *
	 * @return int
	 */
	private function detected_recurring_events_to_migrate() {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			"SELECT COUNT(*) FROM " . $wpdb->posts . " LEFT JOIN "
			. $wpdb->postmeta . " ON " . $wpdb->postmeta . ".post_id = " . $wpdb->posts . ".ID AND "
			. $wpdb->postmeta . ".meta_key = '_EventRecurrence' WHERE "
			. $wpdb->posts . ".post_type = 'tribe_events' AND " . $wpdb->postmeta . ".post_id IS NOT NULL"
		);

		return empty( $result ) ? 0 : absint( $result );
	}

	/**
	 * Get the string sentence that describes the number of items per context to be imported.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	private function get_number_of_items_per_context_string() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$events_count    = $this->get_number_of_tec_events_to_import();
		$orders_count    = $this->get_total_number_to_import_by_context( 'orders', [] );
		$tickets_count   = $this->get_total_number_to_import_by_context( 'attendees', [] );
		$attendees_count = $this->get_number_of_tec_attendees_to_import();

		$context_count_string = '';

		if ( ! empty( $events_count ) ) {
			$context_count_string .= sprintf(
				/* translators: %s: Number of TEC events to import. */
				_n( '%s event', '%s events', $events_count, 'sugar-calendar-lite' ),
				$events_count
			);
		}

		if ( ! empty( $orders_count ) ) {
			if ( ! empty( $context_count_string ) ) {
				$context_count_string .= ', ';

				if ( empty( $tickets_count ) && empty( $attendees_count ) ) {
					$context_count_string .= 'and ';
				}
			}

			$context_count_string .= sprintf(
				/* translators: %s: Number of TEC orders to import. */
				_n( '%s order', '%s orders', $orders_count, 'sugar-calendar-lite' ),
				$orders_count
			);
		}

		if ( ! empty( $tickets_count ) ) {
			if ( ! empty( $context_count_string ) ) {
				$context_count_string .= ', ';

				if ( empty( $attendees_count ) ) {
					$context_count_string .= 'and ';
				}
			}

			$context_count_string .= sprintf(
				/* translators: %s: Number of TEC tickets to import. */
				_n( '%s ticket', '%s tickets', $tickets_count, 'sugar-calendar-lite' ),
				$tickets_count
			);
		}

		if ( ! empty( $attendees_count ) ) {
			if ( ! empty( $context_count_string ) ) {
				$context_count_string .= ', and ';
			}

			$context_count_string .= sprintf(
				/* translators: %s: Number of TEC attendees to import. */
				_n( '%s attendee', '%s attendees', $attendees_count, 'sugar-calendar-lite' ),
				$attendees_count
			);
		}

		return $context_count_string;
	}

	/**
	 * Migrate.
	 *
	 * @since 3.3.0
	 *
	 * @param int[] $total_number_to_import The total number to import per context.
	 *
	 * @return array|false
	 */
	public function run( $total_number_to_import = [] ) {

		update_option( self::SC_TEC_MIGRATION_OPTION_KEY, 'in_progress', false );

		// Get the migration progress.
		$migration_progress     = $this->get_migration_progress();
		$total_number_to_import = $this->get_total_number_to_import_by_context( $migration_progress['migration_process'], $total_number_to_import );

		// phpcs:disable WPForms.Formatting.EmptyLineBeforeReturn.AddEmptyLineBeforeReturnStatement

		switch ( $migration_progress['migration_process'] ) {
			case 'events':
				return [
					'total_number_to_import' => $total_number_to_import,
					'process'                => 'events',
					'progress'               => $this->start_tec_events_migration( $migration_progress['context'] ),
					'status'                 => self::AJAX_RETURN_STATUS_IN_PROGRESS,
				];

			case 'tickets':
				return [
					'total_number_to_import' => $total_number_to_import,
					/*
					 * We return 'hidden' because in the context of SC. The tickets are not independent
					 * on their own. We perform this migration to add the ticket data to the proper
					 * SC event.
					 */
					'process'                => 'hidden',
					'progress'               => $this->start_tec_tickets_migration( $migration_progress['context'] ),
					'status'                 => self::AJAX_RETURN_STATUS_IN_PROGRESS,
				];

			case 'orders':
				return [
					'total_number_to_import' => $total_number_to_import,
					'process'                => 'orders',
					'progress'               => $this->start_tec_orders_migration( $migration_progress['context'] ),
					'status'                 => self::AJAX_RETURN_STATUS_IN_PROGRESS,
				];

			case 'attendees':
				return [
					'attendees_total_count'  => $this->get_number_of_tec_attendees_to_import(),
					'total_number_to_import' => $total_number_to_import,
					/*
					 * We return 'tickets' because in the context of SC. The TEC attendees are the tickets.
					 */
					'process'                => 'tickets',
					'progress'               => $this->start_tec_attendees_migration( $migration_progress['context'] ),
					'attendees_count'        => $this->imported_attendees_count,
					'status'                 => self::AJAX_RETURN_STATUS_IN_PROGRESS,
				];

			case self::AJAX_RETURN_STATUS_COMPLETE:
				$this->drop_migration_tables();

				// Let's delete the error transient as well.
				delete_transient( $this->get_errors_transient_key() );

				update_option( self::SC_TEC_MIGRATION_OPTION_KEY, gmdate( 'Y-m-d' ) );

				return [
					'status'     => self::AJAX_RETURN_STATUS_COMPLETE,
					'error_html' => wp_kses_post( $this->get_error_html_display() ),
				];
		}
		// phpcs:enable WPForms.Formatting.EmptyLineBeforeReturn.AddEmptyLineBeforeReturnStatement

		return false;
	}

	/**
	 * Get the number of items to import.
	 *
	 * @since 3.3.0
	 *
	 * @param string $context  The context of the migration.
	 * @param array  $haystack The array containing the total number to import per context.
	 *
	 * @return int
	 */
	private function get_total_number_to_import_by_context( $context, $haystack ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		if ( isset( $haystack[ $context ] ) && is_numeric( $haystack[ $context ] ) ) {
			return absint( $haystack[ $context ] );
		}

		switch ( $context ) {
			case 'events':
				$result = $this->get_tec_events_to_import( true );
				break;

			case 'tickets':
				$result = $this->get_tec_tickets_to_import( true );
				break;

			case 'orders':
				$result = $this->get_tec_orders_to_import( true );
				break;

			case 'attendees':
				$result = $this->get_tec_attendees_to_import( true );
				break;
		}

		if ( ! empty( $result ) && ! empty( $result[0]->context_to_import_count ) ) {
			return absint( $result[0]->context_to_import_count );
		}

		return 0;
	}

	/**
	 * Get the migration progress.
	 *
	 * This method returns an array containing the following keys:
	 * - migration_process: The current migration process.
	 * - context: The data of the current context.
	 *
	 * @since 3.3.0
	 *
	 * @return array
	 */
	private function get_migration_progress() {

		// Get the TEC events that are not yet migrated.
		$tec_events_to_import = $this->get_tec_events_to_import();

		if ( ! empty( $tec_events_to_import ) ) {
			return [
				'migration_process' => 'events',
				'context'           => $tec_events_to_import,
			];
		}

		/*
		 * If in here, then all the TEC events are already migrated.
		 * Next is importing all TEC tickets.
		 */
		$tickets_to_import = $this->get_tec_tickets_to_import();

		if ( ! empty( $tickets_to_import ) ) {
			return [
				'migration_process' => 'tickets',
				'context'           => $tickets_to_import,
			];
		}

		/*
		 * Next is importing all TEC orders.
		 */
		$orders_to_import = $this->get_tec_orders_to_import();

		if ( ! empty( $orders_to_import ) ) {
			return [
				'migration_process' => 'orders',
				'context'           => $orders_to_import,
			];
		}

		/*
		 * Next is importing all TEC attendees.
		 *
		 * In context of Sugar Calendar, these are tickets.
		 */
		$attendees_to_import = $this->get_tec_attendees_to_import();

		if ( ! empty( $attendees_to_import ) ) {
			return [
				'migration_process' => 'attendees',
				'context'           => $attendees_to_import,
			];
		}

		return [
			'migration_process' => self::AJAX_RETURN_STATUS_COMPLETE,
		];
	}

	/**
	 * Start the migration of TEC events.
	 *
	 * @since 3.3.0
	 *
	 * @param array $tec_events Array containing the TEC events to migrate.
	 *
	 * @return int The number of successful migrations.
	 */
	private function start_tec_events_migration( $tec_events ) {

		$successful_migrations = 0;

		// Get TEC event data.
		foreach ( $tec_events as $result ) {

			$tec_event = $this->get_tec_event( $result->post_id );

			if (
				empty( $tec_event ) ||
				empty( $tec_event->ID ) ||
				$tec_event->post_type !== 'tribe_events'
			) {
				continue;
			}

			if ( $this->migrate_tec_event( $result->event_id, $tec_event ) ) {
				++$successful_migrations;
			}
		}

		return $successful_migrations;
	}

	/**
	 * Get the TEC event data.
	 *
	 * @since 3.3.0
	 *
	 * @param int $tec_event_post_id The TEC event post ID.
	 *
	 * @return false|\WP_Post
	 */
	private function get_tec_event( $tec_event_post_id ) {

		// Get the post object of TEC event.
		$tec_post = get_post( $tec_event_post_id );

		if ( empty( $tec_post ) ) {
			return false;
		}

		// Get Post Meta.
		$tec_post_meta = get_post_meta( $tec_event_post_id );

		$tec_post->start_date = $this->get_data_from_meta( '_EventStartDate', $tec_post_meta );
		$tec_post->end_date   = $this->get_data_from_meta( '_EventEndDate', $tec_post_meta );
		$tec_post->start_tz   = $this->get_data_from_meta( '_EventTimezone', $tec_post_meta );
		$tec_post->end_tz     = $tec_post->start_tz; // TEC does not support end timezone.

		// Add the optional data.
		$all_day = $this->get_data_from_meta( '_EventAllDay', $tec_post_meta );

		if ( ! empty( $all_day ) && absint( $all_day ) === 1 ) {
			$all_day = true;
		}

		$tec_post->all_day = (bool) $all_day;

		$event_url = $this->get_data_from_meta( '_EventURL', $tec_post_meta );

		if ( ! empty( $event_url ) ) {
			$tec_post->event_url = $event_url;
		}

		$recurrence = $this->get_data_from_meta( '_EventRecurrence', $tec_post_meta );

		if ( ! empty( $recurrence ) && is_serialized( $recurrence ) ) {
			// We expect an array for the recurrence.
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
			$tec_post->recurrence = unserialize( $recurrence, [ 'allowed_classes' => false ] );
		}

		$tec_post->tec_meta = $tec_post_meta;

		return $tec_post;
	}

	/**
	 * Start the migration of TEC tickets.
	 *
	 * @since 3.3.0
	 *
	 * @param array $tec_tickets Array containing the TEC tickets to migrate.
	 *
	 * @return int The number of successful migrations.
	 */
	private function start_tec_tickets_migration( $tec_tickets ) {

		$successful_migrations = 0;

		foreach ( $tec_tickets as $ticket_to_import ) {

			if ( $this->migrate_ticket( $ticket_to_import->ID ) ) {
				++$successful_migrations;
			}
		}

		return $successful_migrations;
	}

	/**
	 * Start the migration of TEC orders.
	 *
	 * @since 3.3.0
	 *
	 * @param array $tec_orders_to_import Array containing the TEC orders to migrate.
	 *
	 * @return int The number of successful migrations.
	 */
	private function start_tec_orders_migration( $tec_orders_to_import ) {

		$successful_migrations = 0;

		foreach ( $tec_orders_to_import as $tec_order_to_import ) {

			if ( $this->migrate_order( $tec_order_to_import->ID ) ) {
				++$successful_migrations;
			}
		}

		return $successful_migrations;
	}

	/**
	 * Start the migration of TEC attendees.
	 *
	 * @since 3.3.0
	 *
	 * @param array $tec_attendees_to_import Array containing the TEC attendees to migrate.
	 *
	 * @return int The number of successful migrations.
	 */
	private function start_tec_attendees_migration( $tec_attendees_to_import ) {

		$successful_migrations = 0;

		foreach ( $tec_attendees_to_import as $tec_attendee ) {

			if ( $this->migrate_attendee( $tec_attendee->ID ) ) {
				++$successful_migrations;
			}
		}

		return $successful_migrations;
	}

	/**
	 * Migrate the TEC event to Sugar Calendar.
	 *
	 * @since 3.3.0
	 *
	 * @param int      $tec_event_id The Event Calendar event ID.
	 * @param \WP_Post $tec_event    The Event Calendar event data.
	 *
	 * @return bool Returns `true` if the event is successfully migrated. Otherwise, `false`.
	 */
	private function migrate_tec_event( $tec_event_id, $tec_event ) {

		$data = [
			'post_id'           => $tec_event->ID,
			'title'             => $tec_event->post_title,
			'content'           => $tec_event->post_content,
			'status'            => $tec_event->post_status,
			'post_thumbnail_id' => get_post_thumbnail_id( $tec_event->ID ),
			'all_day'           => $tec_event->all_day,
			'start_date'        => $tec_event->start_date,
			'end_date'          => $tec_event->end_date,
			'start_tz'          => $tec_event->timezone,
			'end_tz'            => $tec_event->timezone,
		];

		$location = $this->get_tec_venue( $tec_event->ID );

		if ( ! empty( $location ) ) {
			$data['location'] = $location;
		}

		if ( ! empty( $tec_event->event_url ) ) {
			$data['url']        = $tec_event->event_url;
			$data['url_target'] = 1;
		}

		if ( ! empty( $tec_event->recurrence ) ) {

			$recurrence_data = $this->prepare_recurrence_data( $tec_event->recurrence );

			if ( is_array( $recurrence_data ) ) {
				$data = array_merge( $data, $recurrence_data );
			}
		}

		// Set the event to the default calendar.
		$data['calendars'] = [ absint( sugar_calendar_get_default_calendar() ) ];

		$create_sc_event = $this->create_sc_event( $data );

		if ( ! empty( $create_sc_event ) ) {

			$this->attempt_to_import_custom_fields( $create_sc_event, $tec_event );

			$this->save_migrated_event(
				$create_sc_event['sc_event_id'],
				$create_sc_event['sc_event_post_id'],
				$tec_event_id,
				$tec_event->ID
			);

			return true;
		}

		return false;
	}

	/**
	 * Convert the TEC recurrence data to SC-compatible recurrence data.
	 *
	 * @since 3.3.0
	 *
	 * @param array $recurrence_data The TEC event recurrence data.
	 *
	 * @return array|false
	 */
	private function prepare_recurrence_data( $recurrence_data ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.NestingLevel.MaxExceeded

		if ( empty( $recurrence_data['rules'] ) ) {
			return false;
		}

		$rules = reset( $recurrence_data['rules'] );

		if ( empty( $rules ) ) {
			return false;
		}

		// For now, we are only supporting 'custom' rules.
		if ( empty( $rules['custom'] ) ) {
			return false;
		}

		$type = ! empty( $rules['custom']['type'] ) ? strtolower( $rules['custom']['type'] ) : false;

		if ( ! in_array( $type, [ 'daily', 'weekly', 'monthly', 'yearly' ], true ) ) {
			return false;
		}

		$recurrence_byday      = false;
		$recurrence_bymonthday = false;
		$recurrence_bypos      = false;
		$recurrence_bymonth    = false;

		// Handle the recurrence depending on the type.
		switch ( $type ) {
			case 'weekly':
				if ( ! empty( $rules['custom']['week']['day'] ) ) {
					$recurrence_byday = $this->convert_weekday_num_to_abbrev( $rules['custom']['week']['day'] );
				}
				break;

			case 'monthly':
				if ( ! empty( $rules['custom']['month']['number'] ) ) {

					if ( ! empty( $rules['custom']['month']['day'] ) ) {
						$recurrence_byday = $this->convert_weekday_num_to_abbrev( [ $rules['custom']['month']['day'] ] );
						$recurrence_bypos = $this->convert_ordinal_string_to_num( $rules['custom']['month']['number'] );
					} else {
						$recurrence_bymonthday = absint( $rules['custom']['month']['number'] );
					}
				}
				break;

			case 'yearly':
				if ( ! empty( $rules['custom']['year']['number'] ) ) {

					if ( ! empty( $rules['custom']['year']['day'] ) ) {
						$recurrence_byday = $this->convert_weekday_num_to_abbrev( [ $rules['custom']['year']['day'] ] );
						$recurrence_bypos = $this->convert_ordinal_string_to_num( $rules['custom']['year']['number'] );
					}
				}

				if ( ! empty( $rules['custom']['year']['month'] ) ) {
					$recurrence_bymonth = $rules['custom']['year']['month'];
				}
				break;

			default: // We shouldn't be in here.
				return false;
		}

		$return_val = [
			'recurrence'          => $type,
			'recurrence_count'    => ! empty( $rules['end-count'] ) ? $rules['end-count'] : 0,
			'recurrence_interval' => ! empty( $rules['custom']['interval'] ) ? $rules['custom']['interval'] : 0,
		];

		if ( ! empty( $rules['end'] ) ) {
			$return_val['recurrence_end'] = $rules['end'];
		}

		if ( ! empty( $recurrence_byday ) ) {
			$return_val['recurrence_byday'] = $recurrence_byday;
		}

		if ( ! empty( $recurrence_bymonthday ) ) {
			$return_val['recurrence_bymonthday'] = [ $recurrence_bymonthday ];
		}

		if ( ! empty( $recurrence_bypos ) ) {
			$return_val['recurrence_bypos'] = $recurrence_bypos;
		}

		if ( ! empty( $recurrence_bymonth ) ) {
			$return_val['recurrence_bymonth'] = $recurrence_bymonth;
		}

		return $return_val;
	}

	/**
	 * Convert an array of weekday numbers to a string of weekday abbreviations
	 * separated by commas.
	 *
	 * @since 3.3.0
	 *
	 * @param array $days Array containing the weekday in number.
	 *
	 * @return array|false
	 */
	private function convert_weekday_num_to_abbrev( $days ) {

		$weekday_map = [
			1 => 'MO',
			2 => 'TU',
			3 => 'WE',
			4 => 'TH',
			5 => 'FR',
			6 => 'SA',
			7 => 'SU',
		];

		$weekday_abbr = [];

		foreach ( $days as $day ) {

			$day = absint( $day );

			if ( ! empty( $weekday_map[ $day ] ) ) {
				$weekday_abbr[] = $weekday_map[ $day ];
			}
		}

		return empty( $weekday_abbr ) ? false : $weekday_abbr;
	}

	/**
	 * Get the number representation of an ordinal string.
	 *
	 * @since 3.3.0
	 *
	 * @param string $ordinal_string The ordinal string.
	 *
	 * @return int|false Returns the ordinal number. Otherwise returns `false`.
	 */
	private function convert_ordinal_string_to_num( $ordinal_string ) {

		$ordinal_string = strtolower( $ordinal_string );

		$accepted_ordinal_strings = [
			-1 => 'last',
			1  => 'first',
			2  => 'second',
			3  => 'third',
			4  => 'fourth',
			5  => 'fifth',
			6  => 'sixth',
			7  => 'seventh',
		];

		if ( ! in_array( $ordinal_string, $accepted_ordinal_strings, true ) ) {
			return false;
		}

		return array_search( $ordinal_string, $accepted_ordinal_strings, true );
	}

	/**
	 * Get the TEC custom fields to import.
	 *
	 * @since 3.3.0
	 *
	 * @return array
	 */
	private function get_tec_custom_fields() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		// First, try to fetch from run time cache.
		if ( ! is_null( $this->tec_custom_fields ) ) {
			return $this->tec_custom_fields;
		}

		// Then, try to fetch from transient.
		$tec_custom_fields = get_transient( 'sc_migration_tec_custom_fields' );

		if ( $tec_custom_fields !== false ) {
			$tec_custom_fields = json_decode( $tec_custom_fields, true );

			if ( ! empty( $tec_custom_fields ) ) {
				$this->tec_custom_fields = $tec_custom_fields;
			} else {
				$this->tec_custom_fields = [];
			}

			return $this->tec_custom_fields;
		}

		if ( Options::get( 'custom_fields' ) ) {

			$tec_options = get_option( 'tribe_events_calendar_options' );

			if ( ! empty( $tec_options['custom-fields'] ) ) {

				$this->tec_custom_fields = wp_list_pluck( $tec_options['custom-fields'], 'name' );

				set_transient(
					'sc_migration_tec_custom_fields',
					wp_json_encode( $this->tec_custom_fields ),
					12 * HOUR_IN_SECONDS
				);

				return $this->tec_custom_fields;
			}
		}

		// If we end up here, then we don't have any custom fields to import.
		$this->tec_custom_fields = [];

		return $this->tec_custom_fields;
	}

	/**
	 * Attempt to import TEC custom fields to Sugar Calendar.
	 *
	 * @since 3.3.0
	 *
	 * @param array    $created_sc_event Array containing the created SC event info.
	 * @param \WP_Post $tec_event        The TEC event data containing TEC post meta data.
	 *
	 * @return void
	 */
	private function attempt_to_import_custom_fields( $created_sc_event, $tec_event ) {

		$custom_fields_to_import = $this->get_tec_custom_fields();

		if ( empty( $custom_fields_to_import ) ) {
			return;
		}

		if ( empty( $tec_event->tec_meta ) ) {
			return;
		}

		// Loop through each of the custom fields.
		foreach ( $custom_fields_to_import as $custom_field ) {

			$meta = $this->get_data_from_meta( $custom_field, $tec_event->tec_meta );

			if ( $meta === false ) {
				continue;
			}

			update_post_meta(
				$created_sc_event['sc_event_post_id'],
				sanitize_key( $custom_field ),
				esc_sql( $meta )
			);
		}
	}

	/**
	 * Migrate the TEC ticket to Sugar Calendar.
	 *
	 * The "Ticket" in context is "Ticket" associated in the event and
	 * NOT the ticket purchased.
	 *
	 * @since 3.3.0
	 *
	 * @param int $tec_ticket_id The TEC ticket ID.
	 *
	 * @return bool Returns `false` if the ticket is not migrated.
	 *              This method returns `true` if all of the needed data to migrate the ticket to SC
	 *              is present and we attempted to update the SC event ticket meta BUT it not necessarily
	 *              mean that the ticket is successfully migrated.
	 */
	private function migrate_ticket( $tec_ticket_id ) {

		$ticket = $this->get_tec_post_ticket( $tec_ticket_id );

		if ( empty( $ticket ) ) {
			$this->save_migrated_ticket( $tec_ticket_id );

			return false;
		}

		// Get the TEC event the ticket belongs to.
		$tec_event_post_id = get_post_meta( $tec_ticket_id, '_tec_tickets_commerce_event', true );

		if ( empty( $tec_event_post_id ) ) {
			$this->save_migrated_ticket( $tec_ticket_id );

			return false;
		}

		// Let's get the SC event ID of the migrated TEC event.
		$migrated_event_info = $this->get_migrated_sc_info( 'tec_event_post_id', $tec_event_post_id );

		if ( empty( $migrated_event_info ) ) {
			$this->save_migrated_ticket( $tec_ticket_id );

			return false;
		}

		// Check if the migrated SC event already has a ticket.
		$existing_sc_ticket = get_event_meta( $migrated_event_info->sc_event_id, 'ticket_price', true );

		if ( ! empty( $existing_sc_ticket ) ) {
			$this->save_migrated_ticket( $tec_ticket_id, $migrated_event_info->sc_event_id );

			return false;
		}

		$this->update_sc_event_ticket_meta(
			$migrated_event_info->sc_event_id,
			$ticket['price'],
			$ticket['capacity']
		);

		$this->save_migrated_ticket( $tec_ticket_id, $migrated_event_info->sc_event_id, true );

		// If we end up here, we assume that the ticket is successfully migrated.
		return true;
	}

	/**
	 * Get the TEC post ticket information to migrate.
	 *
	 * @since 3.3.0
	 *
	 * @param int $tec_ticket_post_id The TEC ticket post ID.
	 *
	 * @return array|false
	 */
	private function get_tec_post_ticket( $tec_ticket_post_id ) {

		$price    = get_post_meta( $tec_ticket_post_id, '_price', true );
		$capacity = get_post_meta( $tec_ticket_post_id, '_tribe_ticket_capacity', true );

		if ( $price === false ) {
			return false;
		}

		return [
			'capacity' => $capacity,
			'price'    => $price,
		];
	}

	/**
	 * Migrate the TEC attendee to Sugar Calendar.
	 *
	 * @since 3.3.0
	 *
	 * @param int $tec_attendee_id The TEC attendee ID.
	 *
	 * @return bool Returns `true` if the attendee is successfully migrated. Otherwise, `false`.
	 */
	private function migrate_attendee( $tec_attendee_id ) {

		$tec_attendee = $this->get_tec_attendee( $tec_attendee_id );

		if ( empty( $tec_attendee ) ) {
			return false;
		}

		$tec_order_id = ! empty( $tec_attendee['order_id'] ) ? absint( $tec_attendee['order_id'] ) : 0;

		// Get the migrated SC order ID of the TEC order.
		$migrated_sc_order_info = $this->get_migrated_sc_order_info_by_tec_order_id( $tec_order_id );

		if ( empty( $migrated_sc_order_info ) ) {
			return false;
		}

		$migrated_sc_order_info = $migrated_sc_order_info[0];

		$sc_attendee_id = $this->get_or_create_sc_attendee(
			$tec_attendee['holder_email'],
			$tec_attendee['holder_name'],
			'' // TEC doesn't have last name.
		);

		if ( empty( $sc_attendee_id ) ) {
			return false;
		}

		$add_ticket_args = [
			'attendee_id' => $sc_attendee_id,
			'event_id'    => $migrated_sc_order_info->sc_event_id,
			'order_id'    => $migrated_sc_order_info->sc_order_id,
		];

		if ( ! empty( $migrated_sc_order_info->sc_event_start ) ) {
			$add_ticket_args['event_date'] = $migrated_sc_order_info->sc_event_start;
		}

		$sc_ticket = add_ticket( $add_ticket_args );

		if ( empty( $sc_ticket ) ) {
			$this->log_errors(
				'tickets',
				[
					'id'           => $tec_attendee_id,
					'context_name' => $tec_attendee['holder_name'],
				]
			);

			return false;
		}

		$this->save_migrated_attendee( $tec_attendee_id );

		return true;
	}

	/**
	 * Get the TEC attendee data to migrate.
	 *
	 * @since 3.3.0
	 *
	 * @param int $tec_attendee_post_id The TEC attendee post ID.
	 *
	 * @return array
	 */
	private function get_tec_attendee( $tec_attendee_post_id ) {

		$holder_email = get_post_meta( $tec_attendee_post_id, '_tec_tickets_commerce_email', true );

		if ( empty( $holder_email ) ) {
			$holder_email = '';
		}

		$holder_name = get_post_meta( $tec_attendee_post_id, '_tec_tickets_commerce_full_name', true );

		if ( empty( $holder_name ) ) {
			$holder_name = '';
		}

		$post_parent = get_post_parent( $tec_attendee_post_id );
		$order_id    = 0;

		if ( ! empty( $post_parent ) ) {
			$order_id = $post_parent->ID;
		}

		return [
			'holder_email' => $holder_email,
			'holder_name'  => $holder_name,
			'order_id'     => $order_id,
		];
	}

	/**
	 * Get the migrated order info.
	 *
	 * @since 3.3.0
	 *
	 * @param int $tec_order_id The TEC order ID.
	 *
	 * @return array
	 */
	private function get_migrated_sc_order_info_by_tec_order_id( $tec_order_id ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ' . $wpdb->prefix . esc_sql( self::MIGRATE_ORDERS_TABLE ) . '.*, '
				. $wpdb->prefix . 'sc_orders.event_id AS sc_event_id, '
				. $wpdb->prefix . 'sc_events.start AS sc_event_start FROM '
				. $wpdb->prefix . esc_sql( self::MIGRATE_ORDERS_TABLE )
				. ' LEFT JOIN ' . $wpdb->prefix . 'sc_orders ON '
				. $wpdb->prefix . 'sc_orders.id = ' . $wpdb->prefix . esc_sql( self::MIGRATE_ORDERS_TABLE ) . '.sc_order_id LEFT JOIN '
				. $wpdb->prefix . 'sc_events ON ' . $wpdb->prefix . 'sc_events.id = ' . $wpdb->prefix . 'sc_orders.event_id WHERE tec_order_id = %d',
				$tec_order_id
			)
		);
	}

	/**
	 * Migrate the TEC order to Sugar Calendar.
	 *
	 * @since 3.3.0
	 *
	 * @param int $tec_order_id The TEC order ID.
	 *
	 * @return bool Returns `true` if the order is successfully migrated. Otherwise, `false`.
	 */
	private function migrate_order( $tec_order_id ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$tec_order = $this->get_tec_order( $tec_order_id );

		if ( empty( $tec_order ) ) {
			$this->log_errors(
				'orders',
				[
					'id'           => $tec_order_id,
					'context_name' => '',
				]
			);

			return false;
		}

		$order_status = $tec_order['status_slug'];

		if ( $order_status === 'completed' ) {
			$order_status = 'paid';
		}

		$subtotal    = $tec_order['total'];
		$sc_event_id = 0;
		$event_date  = '0000-00-00 00:00:00';

		if ( ! empty( $tec_order['events_in_order'] ) ) {
			$tec_event = $this->get_tec_event( $tec_order['events_in_order'] );

			if ( ! empty( $tec_event ) ) {
				$event_date = $tec_event->start_date;

				// We also need to get the SC event ID of the migrated TEC event.
				$sc_info = $this->get_migrated_sc_info( 'tec_event_post_id', $tec_order['events_in_order'] );

				if ( ! empty( $sc_info ) ) {
					$sc_event_id = $sc_info->sc_event_id;
				}
			}
		}

		$order_data = [
			'transaction_id' => $tec_order['gateway_order_id'],
			'currency'       => $tec_order['currency'],
			'status'         => $order_status,
			'discount_id'    => '',
			'subtotal'       => $subtotal,
			'tax'            => '',
			'discount'       => '',
			'total'          => $tec_order['total'],
			'event_id'       => empty( $sc_event_id ) ? 0 : $sc_event_id,
			'event_date'     => $event_date,
			'email'          => $tec_order['purchaser_email'],
			'first_name'     => $tec_order['purchaser_first_name'],
			'last_name'      => $tec_order['purchaser_last_name'],
			'date_paid'      => $tec_order['purchase_time'],
		];

		$sc_order_id = add_order( $order_data );

		if ( ! empty( $sc_order_id ) ) {
			$this->save_migrated_order( $tec_order_id, $sc_order_id );

			return true;
		}

		$this->log_errors(
			'orders',
			[
				'id'           => $tec_order_id,
				'context_name' => $tec_order['purchaser_first_name'] . ' ' . $tec_order['purchaser_last_name'],
			]
		);

		return false;
	}

	/**
	 * Get the TEC order data to migrate.
	 *
	 * @since 3.3.0
	 *
	 * @param int $tec_order_post_id The TEC order post ID.
	 *
	 * @return array|false Returns `false` if the TEC order data can't be retrieved.
	 *                     Otherwise, returns TEC order data.
	 */
	private function get_tec_order( $tec_order_post_id ) {

		$tec_order_metadata = get_post_meta( $tec_order_post_id );

		if ( empty( $tec_order_metadata ) ) {
			return false;
		}

		// TEC order status is derived from its post status.
		$status = str_replace( 'tec-tc-', '', get_post_status( $tec_order_post_id ) );

		return [
			'currency'             => $this->get_data_from_meta( '_tec_tc_order_currency', $tec_order_metadata ),
			'events_in_order'      => $this->get_data_from_meta( '_tec_tc_order_events_in_order', $tec_order_metadata ),
			'gateway_order_id'     => $this->get_data_from_meta( '_tec_tc_order_gateway_order_id', $tec_order_metadata ),
			'purchaser_email'      => $this->get_data_from_meta( '_tec_tc_order_purchaser_email', $tec_order_metadata ),
			'purchaser_first_name' => $this->get_data_from_meta( '_tec_tc_order_purchaser_first_name', $tec_order_metadata ),
			'purchaser_last_name'  => $this->get_data_from_meta( '_tec_tc_order_purchaser_last_name', $tec_order_metadata ),
			'purchase_time'        => get_post_time( 'Y-m-d H:i:s', false, $tec_order_post_id ),
			'status_slug'          => $status,
			'total'                => $this->get_data_from_meta( '_tec_tc_order_total_value', $tec_order_metadata ),
		];
	}

	/**
	 * Get the TEC events and its corresponding post ID that are not yet migrated.
	 *
	 * @since 3.3.0
	 *
	 * @param bool $count_only Whether to return the count only.
	 *
	 * @return array
	 */
	private function get_tec_events_to_import( $count_only = false ) {

		// First let's check if the `sc_migrate_tec_events` table exists.
		if ( empty( $this->check_if_db_table_exists( self::MIGRATE_EVENTS_TABLE ) ) ) {
			// Create the table.
			$this->create_tec_migrate_tec_events_table();
		}

		global $wpdb;

		if ( $count_only ) {
			$select_query = 'SELECT COUNT(' . $wpdb->prefix . 'tec_events.event_id) AS context_to_import_count';
			$limit_query  = '';
		} else {
			$select_query = 'SELECT ' . $wpdb->prefix . 'tec_events.event_id, ' . $wpdb->prefix . 'tec_events.post_id';
			$limit_query  = $wpdb->prepare(
				'LIMIT %d',
				/**
				 * Filter the number of TEC events to import per iteration.
				 *
				 * @since 3.3.0
				 *
				 * @param int $limit The number of TEC events to import per iteration.
				 */
				apply_filters( 'sc_import_tec_events_limit', 10 ) // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			esc_sql( $select_query ) . ' FROM ' . $wpdb->prefix . 'tec_events'
			. ' LEFT JOIN ' . $wpdb->prefix . esc_sql( self::MIGRATE_EVENTS_TABLE ) . ' ON ' . $wpdb->prefix . esc_sql( self::MIGRATE_EVENTS_TABLE ) . '.tec_event_id = ' . $wpdb->prefix . 'tec_events.event_id'
			. ' LEFT JOIN ' . $wpdb->posts . ' ON ' . $wpdb->posts . '.ID = ' . $wpdb->prefix . 'tec_events.post_id' .
			' WHERE ' . $wpdb->prefix . esc_sql( self::MIGRATE_EVENTS_TABLE ) . '.id IS NULL AND '
			. $wpdb->posts . '.ID IS NOT NULL AND ' . $wpdb->posts . '.post_type = "tribe_events" ' . esc_sql( $limit_query )
		);
	}

	/**
	 * Get the number of TEC events to import.
	 *
	 * @since 3.3.0
	 *
	 * @return int
	 */
	private function get_number_of_tec_events_to_import() {

		if ( ! is_null( $this->number_of_tec_events_to_import ) ) {
			return absint( $this->number_of_tec_events_to_import );
		}

		$result = $this->get_tec_events_to_import( true );

		if ( ! empty( $result ) && ! empty( $result[0]->context_to_import_count ) ) {
			$this->number_of_tec_events_to_import = absint( $result[0]->context_to_import_count );
		} else {
			$this->number_of_tec_events_to_import = 0;
		}

		return $this->number_of_tec_events_to_import;
	}

	/**
	 * Get the number of TEC attendees to import as SC attendees.
	 *
	 * @since 3.3.0
	 *
	 * @return int
	 */
	private function get_number_of_tec_attendees_to_import() {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			"SELECT COUNT(*) FROM " .
			"(SELECT DISTINCT(" . $wpdb->postmeta . ".meta_value) AS tec_email FROM " . $wpdb->posts
			. " LEFT JOIN " . $wpdb->postmeta . " ON " . $wpdb->postmeta . ".post_id = " . $wpdb->posts . ".ID AND "
			. $wpdb->postmeta . ".meta_key = '_tec_tickets_commerce_email' WHERE " . $wpdb->posts . ".post_type = 'tec_tc_attendee') tec_attendees"
			. " LEFT JOIN " . $wpdb->prefix . "sc_attendees ON " . $wpdb->prefix . "sc_attendees.email = tec_attendees.tec_email"
			. " WHERE " . $wpdb->prefix . "sc_attendees.id IS NULL"
		);

		if ( empty( $result ) ) {
			return 0;
		}

		return absint( $result );
	}

	/**
	 * Get the TEC tickets that are not yet migrated.
	 *
	 * @since 3.3.0
	 *
	 * @param bool $count_only Whether to return the count only.
	 *
	 * @return array
	 */
	private function get_tec_tickets_to_import( $count_only = false ) {

		if ( empty( $this->check_if_db_table_exists( self::MIGRATE_TICKETS_TABLE ) ) ) {
			$this->create_tec_migrate_tec_tickets_table();
		}

		global $wpdb;

		if ( $count_only ) {
			$select_query = 'SELECT COUNT(' . $wpdb->posts . '.ID) AS context_to_import_count';
			$limit_query  = '';
		} else {
			$select_query = 'SELECT ' . $wpdb->posts . '.ID';
			$limit_query  = $wpdb->prepare(
				'LIMIT %d',
				/**
				 * Filter the number of TEC tickets to import per iteration.
				 *
				 * @since 3.3.0
				 *
				 * @param int $limit The number of TEC tickets to import per iteration.
				 */
				apply_filters( 'sc_import_tec_tickets_limit', 10 ) // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			);
		}

		/*
		 * `tec_tc_ticket` is the post ID of the TEC tickets.
		 */

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			esc_sql( $select_query ) . ' FROM ' . $wpdb->posts
			. ' LEFT JOIN ' . $wpdb->prefix . esc_sql( self::MIGRATE_TICKETS_TABLE ) . ' ON '
			. $wpdb->prefix . esc_sql( self::MIGRATE_TICKETS_TABLE ) . '.tec_ticket_id = ' . $wpdb->posts . '.ID WHERE '
			. ' ' . $wpdb->posts . '.post_type = "tec_tc_ticket" AND '
			. $wpdb->prefix . esc_sql( self::MIGRATE_TICKETS_TABLE ) . '.id IS NULL ' . esc_sql( $limit_query )
		);
	}

	/**
	 * Get the TEC orders that are not yet migrated.
	 *
	 * @since 3.3.0
	 *
	 * @param bool $count_only Whether to return the count only.
	 *
	 * @return array
	 */
	private function get_tec_orders_to_import( $count_only = false ) {

		if ( empty( $this->check_if_db_table_exists( self::MIGRATE_ORDERS_TABLE ) ) ) {
			$this->create_tec_migrate_tec_orders_table();
		}

		global $wpdb;

		if ( $count_only ) {
			$select_query = 'SELECT COUNT(' . $wpdb->posts . '.ID) AS context_to_import_count';
			$limit_query  = '';
		} else {
			$select_query = 'SELECT ' . $wpdb->posts . '.ID';
			$limit_query  = $wpdb->prepare(
				'LIMIT %d',
				/**
				 * Filter the number of TEC orders to import per iteration.
				 *
				 * @since 3.3.0
				 *
				 * @param int $limit The number of TEC orders to import per iteration.
				 */
				apply_filters( 'sc_import_tec_orders_limit', 10 ) // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			esc_sql( $select_query ) . ' FROM ' . $wpdb->posts .
			' LEFT JOIN ' . $wpdb->prefix . esc_sql( self::MIGRATE_ORDERS_TABLE ) .
			' ON ' . $wpdb->prefix . esc_sql( self::MIGRATE_ORDERS_TABLE ) . '.tec_order_id = '
			. $wpdb->posts . '.ID WHERE ' . $wpdb->posts . '.post_type = "tec_tc_order" AND '
			. $wpdb->prefix . esc_sql( self::MIGRATE_ORDERS_TABLE ) . '.id IS NULL ' . esc_sql( $limit_query )
		);
	}

	/**
	 * Get the TEC attendees that are not yet migrated.
	 *
	 * @since 3.3.0
	 *
	 * @param bool $count_only Whether to return the count only.
	 *
	 * @return array
	 */
	private function get_tec_attendees_to_import( $count_only = false ) {

		if ( empty( $this->check_if_db_table_exists( self::MIGRATE_ATTENDEES_TABLE ) ) ) {
			$this->create_tec_migrate_tec_attendees_table();
		}

		global $wpdb;

		if ( $count_only ) {
			$select_query = 'SELECT COUNT(' . $wpdb->posts . '.ID) AS context_to_import_count';
			$limit_query  = '';
		} else {
			$select_query = 'SELECT ' . $wpdb->posts . '.ID';
			$limit_query  = $wpdb->prepare(
				'LIMIT %d',
				/**
				 * Filter the number of TEC attendees to import per iteration.
				 *
				 * @since 3.3.0
				 *
				 * @param int $limit The number of TEC attendees to import per iteration.
				 */
				apply_filters( 'sc_import_tec_attendees_limit', 10 ) // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			esc_sql( $select_query ) . ' FROM ' . $wpdb->posts
			. ' LEFT JOIN ' . $wpdb->prefix . esc_sql( self::MIGRATE_ATTENDEES_TABLE )
			. ' ON ' . $wpdb->posts . '.ID = ' . $wpdb->prefix . esc_sql( self::MIGRATE_ATTENDEES_TABLE ) . '.tec_attendees_id WHERE '
			. $wpdb->posts . '.post_type = "tec_tc_attendee" AND ' . $wpdb->posts . '.post_status = "publish" AND '
			. $wpdb->prefix . esc_sql( self::MIGRATE_ATTENDEES_TABLE ) . '.id IS NULL ORDER BY '
			. $wpdb->posts . '.ID ASC ' . esc_sql( $limit_query )
		);
	}

	/**
	 * Get the migrated SC info.
	 *
	 * @since 3.3.0
	 *
	 * @param string $by    The column to search by.
	 * @param int    $value The value to search for.
	 *
	 * @return mixed
	 */
	private function get_migrated_sc_info( $by, $value ) {

		global $wpdb;

		if ( ! in_array( $by, [ 'tec_event_id', 'tec_event_post_id', 'sc_event_id' ], true ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $wpdb->prefix . esc_sql( self::MIGRATE_EVENTS_TABLE ) . ' WHERE ' . esc_sql( $by ) . ' = %d',
				$value
			)
		);
	}

	/**
	 * Save the migrated event info.
	 *
	 * @since 3.3.0
	 *
	 * @param int $sc_event_id       The Sugar Calendar event ID.
	 * @param int $sc_event_post_id  The Sugar Calendar event post ID.
	 * @param int $tec_event_id      The Event Calendar event ID.
	 * @param int $tec_event_post_id The Event Calendar event post ID.
	 *
	 * @return int|false
	 */
	private function save_migrated_event( $sc_event_id, $sc_event_post_id, $tec_event_id, $tec_event_post_id ) {

		global $wpdb;

		return $wpdb->insert(
			$wpdb->prefix . self::MIGRATE_EVENTS_TABLE,
			[
				'tec_event_id'      => $tec_event_id,
				'tec_event_post_id' => $tec_event_post_id,
				'sc_event_id'       => $sc_event_id,
				'sc_event_post_id'  => $sc_event_post_id,
			],
			[
				'%d',
				'%d',
				'%d',
				'%d',
			]
		);
	}

	/**
	 * Save the migrated ticket info.
	 *
	 * @since 3.3.0
	 *
	 * @param int|null $tec_ticket_id The TEC ticket ID.
	 * @param int      $sc_event_id   The Sugar Calendar event ID. Default: `0`.
	 * @param bool     $is_migrated   Whether the ticket is migrated or not. Default: `false`.
	 *
	 * @return int|false
	 */
	private function save_migrated_ticket( $tec_ticket_id, $sc_event_id = 0, $is_migrated = false ) {
		/*
		 * SC can only have 1 ticket per event, for the other TEC tickets we don't have to migrate them.
		 */
		if ( empty( $sc_event_id ) ) {
			$sc_event_id = 0;
		}

		global $wpdb;

		return $wpdb->insert(
			$wpdb->prefix . self::MIGRATE_TICKETS_TABLE,
			[
				'tec_ticket_id' => $tec_ticket_id,
				'sc_event_id'   => $sc_event_id,
				'is_migrated'   => $is_migrated,
			],
			[
				'%d',
				'%d',
				'%d',
			]
		);
	}

	/**
	 * Save the migrated order info.
	 *
	 * @since 3.3.0
	 *
	 * @param int $tec_order_id The Event Calendar order ID.
	 * @param int $sc_order_id  The Sugar Calendar order ID.
	 *
	 * @return int|false
	 */
	private function save_migrated_order( $tec_order_id, $sc_order_id ) {

		global $wpdb;

		return $wpdb->insert(
			$wpdb->prefix . self::MIGRATE_ORDERS_TABLE,
			[
				'tec_order_id' => $tec_order_id,
				'sc_order_id'  => $sc_order_id,
			],
			[
				'%d',
				'%d',
			]
		);
	}

	/**
	 * Save the migrated attendee info.
	 *
	 * @since 3.3.0
	 *
	 * @param int $tec_attendee_id The Event Calendar attendee ID.
	 *
	 * @return int|false
	 */
	private function save_migrated_attendee( $tec_attendee_id ) {

		global $wpdb;

		return $wpdb->insert(
			$wpdb->prefix . self::MIGRATE_ATTENDEES_TABLE,
			[
				'tec_attendees_id' => $tec_attendee_id,
			],
			[
				'%d',
			]
		);
	}

	/**
	 * Create the `sc_migrate_tec_events` table.
	 *
	 * @since 3.3.0
	 *
	 * @return void
	 */
	private function create_tec_migrate_tec_events_table() {

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = 'CREATE TABLE ' . $wpdb->prefix . self::MIGRATE_EVENTS_TABLE
			. ' (`id` int AUTO_INCREMENT,`tec_event_id` int,`tec_event_post_id` int,`sc_event_id` int,`sc_event_post_id` int, PRIMARY KEY (id));';

		dbDelta( $sql );
	}

	/**
	 * Create the `sc_migrate_tec_events` table.
	 *
	 * @since 3.3.0
	 *
	 * @return void
	 */
	private function create_tec_migrate_tec_tickets_table() {

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = 'CREATE TABLE ' . $wpdb->prefix . self::MIGRATE_TICKETS_TABLE
			. ' (`id` int AUTO_INCREMENT,`tec_ticket_id` int,`sc_event_id` int, `is_migrated` int, PRIMARY KEY (id));';

		dbDelta( $sql );
	}

	/**
	 * Create the `sc_migrate_tec_orders` table.
	 *
	 * @since 3.3.0
	 *
	 * @return void
	 */
	private function create_tec_migrate_tec_orders_table() {

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = 'CREATE TABLE ' . $wpdb->prefix . self::MIGRATE_ORDERS_TABLE
			. ' (`id` int AUTO_INCREMENT,`tec_order_id` int,`sc_order_id` int, PRIMARY KEY (id));';

		dbDelta( $sql );
	}

	/**
	 * Create the `sc_migrate_tec_attendees` table.
	 *
	 * @since 3.3.0
	 *
	 * @return void
	 */
	private function create_tec_migrate_tec_attendees_table() {

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = 'CREATE TABLE ' . $wpdb->prefix . self::MIGRATE_ATTENDEES_TABLE
			. ' (`id` int AUTO_INCREMENT,`tec_attendees_id` int,PRIMARY KEY (id));';

		dbDelta( $sql );
	}

	/**
	 * Get the venue address.
	 *
	 * @since 3.3.0
	 *
	 * @param int $tec_event_post_id The Event Calendar event ID.
	 *
	 * @return false|string Returns `false` if the event doesn't have a venue,
	 *                      otherwise returns the venue address.
	 */
	private function get_tec_venue( $tec_event_post_id ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$tec_venue_id = get_post_meta( $tec_event_post_id, '_EventVenueID', true );

		if ( empty( $tec_venue_id ) ) {
			return false;
		}

		$tec_venue_post = get_post( $tec_venue_id );

		if (
			empty( $tec_venue_post ) ||
			$tec_venue_post->post_type !== 'tribe_venue'
		) {
			return false;
		}

		// Get the full venue address.
		$venue_address = $tec_venue_post->post_title;

		$meta_data = get_post_meta( $tec_venue_id );

		if ( empty( $meta_data ) ) {
			return $venue_address;
		}

		$address = $this->get_data_from_meta( '_VenueAddress', $meta_data );

		if ( ! empty( $address ) ) {
			$venue_address .= ', ' . $address;
		}

		$city = $this->get_data_from_meta( '_VenueCity', $meta_data );

		if ( ! empty( $city ) ) {
			$venue_address .= ', ' . $city;
		}

		$province = $this->get_data_from_meta( '_VenueProvince', $meta_data );

		if ( ! empty( $province ) ) {
			$venue_address .= ', ' . $province;
		}

		$state = $this->get_data_from_meta( '_VenueState', $meta_data );

		if ( ! empty( $state ) ) {
			$venue_address .= ', ' . $state;
		}

		$zip = $this->get_data_from_meta( '_VenueZip', $meta_data );

		if ( ! empty( $zip ) ) {
			$venue_address .= ', ' . $zip;
		}

		$country = $this->get_data_from_meta( '_VenueCountry', $meta_data );

		if ( ! empty( $country ) ) {
			$venue_address .= ', ' . $country;
		}

		return $venue_address;
	}

	/**
	 * Drop the migration tables.
	 *
	 * @since 3.3.0
	 *
	 * @return bool|int
	 */
	private function drop_migration_tables() {

		global $wpdb;

		return $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
			'DROP TABLE IF EXISTS ' . // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->prefix . esc_sql( self::MIGRATE_EVENTS_TABLE ) . ', ' .
			$wpdb->prefix . esc_sql( self::MIGRATE_TICKETS_TABLE ) . ', ' .
			$wpdb->prefix . esc_sql( self::MIGRATE_ORDERS_TABLE ) . ', ' .
			$wpdb->prefix . esc_sql( self::MIGRATE_ATTENDEES_TABLE ) . ';'
		);
	}

	/**
	 * Get the TEC migration option.
	 *
	 * @since 3.3.0
	 *
	 * @return mixed
	 */
	private static function get_tec_migration_option() {

		if ( is_null( self::$tec_migration_option ) ) {
			self::$tec_migration_option = get_option( self::SC_TEC_MIGRATION_OPTION_KEY );
		}

		return self::$tec_migration_option;
	}

	/**
	 * {@inheritDoc}.
	 *
	 * @since 3.3.0
	 */
	public function is_ajax() {

		return true;
	}
}
