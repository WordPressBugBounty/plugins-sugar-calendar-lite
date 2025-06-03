<?php

namespace Sugar_Calendar\Admin\Tools\Importers;

use Sugar_Calendar\Admin\Tools\Importers;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Options;
use Sugar_Calendar\Plugin;
use Sugar_Calendar\Features\Tags\Common\Helpers as TagsHelpers;
use WP_Term;
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
	 * The TEC to SC per-process migration option key.
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	const SC_TEC_PROCESS_PROGRESS_OPTION_KEY = 'sugar_calendar_tec_migration_process';

	/**
	 * DB table used to keep track of the events migrated.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const MIGRATE_EVENTS_TABLE = 'sc_migrate_tec_events';

	/**
	 * DB table used to keep track of the categories migrated.
	 *
	 * @since 3.6.0
	 *
	 * @var string
	 */
	const MIGRATE_CATEGORIES_TABLE = 'sc_migrate_tec_categories';

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
	 * DB table used to keep track of the venues migrated.
	 *
	 * @since 3.6.0
	 *
	 * @var string
	 */
	const MIGRATE_VENUES_TABLE = 'sc_migrate_tec_venues';

	/**
	 * DB table used to keep track of the tags migrated.
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	const MIGRATE_TAGS_TABLE = 'sc_migrate_tec_tags';

	/**
	 * The number of TEC events to import.
	 *
	 * @since 3.3.0
	 *
	 * @var int
	 */
	private $number_of_tec_events_to_import = null;

	/**
	 * The number of TEC categories to import.
	 *
	 * @since 3.6.0
	 *
	 * @var int
	 */
	private $number_of_tec_categories_to_import = null;

	/**
	 * The number of TEC venues to import.
	 *
	 * @since 3.6.0
	 *
	 * @var int
	 */
	private $number_of_tec_venues_to_import = null;

	/**
	 * The number of TEC tags to import.
	 *
	 * @since 3.7.0
	 *
	 * @var int
	 */
	private $number_of_tec_tags_to_import = null;

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
							'The Events Calendar to Sugar Calendar migration was not completed. Please complete the migration <a href="%s">here</a>.',
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
				$btn_text = __( 'Migrate', 'sugar-calendar-lite' );
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
	 * @since 3.6.0 Add tec category to sc calendar import.
	 * @since 3.7.0 Add tec tag to sc import.
	 *
	 * @return string
	 */
	private function get_number_of_items_per_context_string() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$events_count     = $this->get_number_of_tec_events_to_import();
		$venues_count     = $this->get_number_of_tec_venues_to_import();
		$orders_count     = $this->get_total_number_to_import_by_context( 'orders', [] );
		$tickets_count    = $this->get_total_number_to_import_by_context( 'attendees', [] );
		$attendees_count  = $this->get_number_of_tec_attendees_to_import();
		$categories_count = $this->get_total_number_to_import_by_context( 'categories', [] );
		$tags_count       = $this->get_total_number_to_import_by_context( 'tags', [] );

		$items_per_context = [];

		if ( $events_count > 0 ) {
			$items_per_context[] = sprintf(
				/* translators: %d - number of events to migrate. */
				_n( '%d event', '%d events', $events_count, 'sugar-calendar-lite' ),
				$events_count
			);
		}

		if ( sugar_calendar()->is_pro() && $venues_count > 0 ) {
			$items_per_context[] = sprintf(
				/* translators: %d - number of venues to migrate. */
				_n( ', %d venue', ', %d venues', $venues_count, 'sugar-calendar-lite' ),
				$venues_count
			);
		}

		if ( ! empty( $categories_count ) ) {
			if ( ! empty( $items_per_context ) ) {
				$items_per_context[] = ', and ';
			}

			$items_per_context[] = sprintf(
				/* translators: %s: Number of TEC categories to import. */
				_n( '%s category', '%s categories', $categories_count, 'sugar-calendar-lite' ),
				$categories_count
			);
		}

		if ( ! empty( $tags_count ) ) {
			if ( ! empty( $items_per_context ) ) {
				$items_per_context[] = ', ';

				if ( empty( $orders_count ) && empty( $tickets_count ) && empty( $attendees_count ) ) {
					$items_per_context[] = 'and ';
				}
			}

			$items_per_context[] = sprintf(
				/* translators: %s: Number of TEC tags to import. */
				_n( '%s tag', '%s tags', $tags_count, 'sugar-calendar-lite' ),
				$tags_count
			);
		}

		if ( ! empty( $orders_count ) ) {
			if ( ! empty( $items_per_context ) ) {
				$items_per_context[] = ', ';

				if ( empty( $tickets_count ) && empty( $attendees_count ) ) {
					$items_per_context[] = 'and ';
				}
			}

			$items_per_context[] = sprintf(
				/* translators: %s: Number of TEC orders to import. */
				_n( '%s order', '%s orders', $orders_count, 'sugar-calendar-lite' ),
				$orders_count
			);
		}

		if ( ! empty( $tickets_count ) ) {
			if ( ! empty( $items_per_context ) ) {
				$items_per_context[] = ', ';

				if ( empty( $attendees_count ) ) {
					$items_per_context[] = 'and ';
				}
			}

			$items_per_context[] = sprintf(
				/* translators: %s: Number of TEC tickets to import. */
				_n( '%s ticket', '%s tickets', $tickets_count, 'sugar-calendar-lite' ),
				$tickets_count
			);
		}

		if ( ! empty( $attendees_count ) ) {
			if ( ! empty( $items_per_context ) ) {
				$items_per_context[] = ', and ';
			}

			$items_per_context[] = sprintf(
				/* translators: %s: Number of TEC attendees to import. */
				_n( '%s attendee', '%s attendees', $attendees_count, 'sugar-calendar-lite' ),
				$attendees_count
			);
		}

		return implode( '', $items_per_context );
	}

	/**
	 * Migrate.
	 *
	 * @since 3.3.0
	 * @since 3.6.0 Add tec category to sc calendar import and post migration process.
	 * @since 3.7.0 Add tec tag migration.
	 *
	 * @param int[] $total_number_to_import The total number to import per context.
	 *
	 * @return array|false
	 */
	public function run( $total_number_to_import = [] ) {

		update_option( self::SC_TEC_MIGRATION_OPTION_KEY, 'in_progress', false );

		// Get the migration progress.
		$migration_progress     = $this->get_migration_progress();
		$migration_process      = $migration_progress['migration_process'];
		$total_number_to_import = $this->get_total_number_to_import_by_context( $migration_process, $total_number_to_import );

		// Update per-process progress.
		$process_progresses                       = get_option( self::SC_TEC_PROCESS_PROGRESS_OPTION_KEY, [] );
		$process_batch_size                       = $this->get_import_batch_size( $migration_process );
		$process_progress                         = ( $process_progresses[ $migration_process ] ?? 0 ) + $process_batch_size;
		$process_progresses[ $migration_process ] = $process_progress;

		update_option( self::SC_TEC_PROCESS_PROGRESS_OPTION_KEY, $process_progresses );

		// Get the per-process status.
		$process_status = self::AJAX_RETURN_STATUS_IN_PROGRESS;

		if ( $process_progress >= $total_number_to_import ) {
			$process_status = self::AJAX_RETURN_STATUS_COMPLETE;
		}

		// phpcs:disable WPForms.Formatting.EmptyLineBeforeReturn.AddEmptyLineBeforeReturnStatement

		switch ( $migration_process ) {
			case 'events':
				return [
					'total_number_to_import' => $total_number_to_import,
					'process'                => 'events',
					'progress'               => $this->start_tec_events_migration( $migration_progress['context'] ),
					'status'                 => self::AJAX_RETURN_STATUS_IN_PROGRESS,
					'process_status'         => $process_status,
				];

			case 'categories':
				return [
					'total_number_to_import' => $total_number_to_import,
					'process'                => 'categories',
					'progress'               => $this->start_tec_categories_migration( $migration_progress['context'] ),
					'status'                 => self::AJAX_RETURN_STATUS_IN_PROGRESS,
					'process_status'         => $process_status,
				];

			case 'tags':
				return [
					'total_number_to_import' => $total_number_to_import,
					'process'                => 'tags',
					'progress'               => $this->start_tec_tags_migration( $migration_progress['context'] ),
					'status'                 => self::AJAX_RETURN_STATUS_IN_PROGRESS,
					'process_status'         => $process_status,
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
					'process_status'         => $process_status,
				];

			case 'orders':
				return [
					'total_number_to_import' => $total_number_to_import,
					'process'                => 'orders',
					'progress'               => $this->start_tec_orders_migration( $migration_progress['context'] ),
					'status'                 => self::AJAX_RETURN_STATUS_IN_PROGRESS,
					'process_status'         => $process_status,
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
					'process_status'         => $process_status,
				];

			case 'venues':
				return [
					'total_number_to_import' => $total_number_to_import,
					'process'                => 'venues',
					'progress'               => $this->start_tec_venues_migration( $migration_progress['context'] ),
					'status'                 => self::AJAX_RETURN_STATUS_IN_PROGRESS,
					'process_status'         => $process_status,
				];

			case self::AJAX_RETURN_STATUS_COMPLETE:

				$this->post_migration_process();
				$this->drop_migration_tables();

				update_option( self::SC_TEC_MIGRATION_OPTION_KEY, gmdate( 'Y-m-d' ) );

				/**
				 * Post migration process action.
				 *
				 * @since 3.6.0
				 *
				 * @param TheEventCalendar $this The importer object.
				 */
				do_action( 'sugar_calendar_admin_tools_importers_the_event_calendar_post_migration_process', $this );

				$result = [
					'status'     => self::AJAX_RETURN_STATUS_COMPLETE,
					'errors'     => $this->get_errors(),
					'error_html' => wp_kses_post( $this->get_error_html_display() ),
				];

				// Delete per-process progress option.
				delete_option( self::SC_TEC_PROCESS_PROGRESS_OPTION_KEY );

				// Let's delete the error transient as well.
				delete_transient( $this->get_errors_transient_key() );

				return $result;
		}

		// phpcs:enable WPForms.Formatting.EmptyLineBeforeReturn.AddEmptyLineBeforeReturnStatement

		return false;
	}

	/**
	 * Get the number of items to import.
	 *
	 * @since 3.3.0
	 * @since 3.6.0 Add venue context.
	 * @since 3.7.0 Add tags context.
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

			case 'venues':
				$result = $this->get_tec_venues_to_import( true );
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

			case 'categories':
				$result = $this->get_tec_categories_to_import( true );
				break;

			case 'tags':
				$result = $this->get_tec_tags_to_import( true );
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
	 * @since 3.6.0 Add tec category and venue to migrate, and switched venue to be imported first.
	 * @since 3.7.0 Add tags to migration process.
	 *
	 * @return array
	 */
	private function get_migration_progress() {

		// Get the TEC venues that are not yet migrated.
		$tec_venues_to_import = $this->get_tec_venues_to_import();

		if ( sugar_calendar()->is_pro() && ! empty( $tec_venues_to_import ) ) {
			return [
				'migration_process' => 'venues',
				'context'           => $tec_venues_to_import,
			];
		}

		// Get the TEC tags that are not yet migrated.
		$tec_tags_to_import = $this->get_tec_tags_to_import();

		if ( ! empty( $tec_tags_to_import ) ) {
			return [
				'migration_process' => 'tags',
				'context'           => $tec_tags_to_import,
			];
		}

		// Get the TEC events that are not yet migrated.
		$tec_events_to_import = $this->get_tec_events_to_import();

		if ( ! empty( $tec_events_to_import ) ) {
			return [
				'migration_process' => 'events',
				'context'           => $tec_events_to_import,
			];
		}

		// Get the TEC categories that are not yet migrated.
		$tec_categories_to_import = $this->get_tec_categories_to_import();

		if ( ! empty( $tec_categories_to_import ) ) {
			return [
				'migration_process' => 'categories',
				'context'           => $tec_categories_to_import,
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

			$this->insert_migrating_event( $result->event_id );

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
	 * Start the migration of TEC categories.
	 *
	 * @since 3.6.0
	 *
	 * @param array $tec_categories Array containing the TEC categories to migrate.
	 *
	 * @return int The number of successful migrations.
	 */
	private function start_tec_categories_migration( $tec_categories ) {

		$successful_migrations = 0;

		foreach ( $tec_categories as $tec_category ) {

			$this->insert_migrating_category( intval( $tec_category->term_id ) );

			if ( $this->migrate_category( $tec_category ) ) {

				++$successful_migrations;
			}
		}

		return $successful_migrations;
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

			$this->insert_migrating_ticket( $ticket_to_import->ID );

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

			$this->insert_migrating_order( $tec_order_to_import->ID );

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

			$this->insert_migrating_attendee( $tec_attendee->ID );

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

		$location = $this->get_tec_location_venue( $tec_event->ID );

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

		// Add the venue ID to the event data.
		$data['venue_id'] = sugar_calendar()->is_pro() ? $this->get_tec_post_sc_venue_id( $tec_event->ID ) : 0;

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
	 * Get term data without requiring the taxonomy to be registered.
	 *
	 * This function retrieves term data directly from the database,
	 * bypassing the requirement for the taxonomy to be registered.
	 *
	 * @since 3.6.0
	 *
	 * @param int $term_id The term ID to retrieve.
	 *
	 * @return array|false Term data as an array or false on failure.
	 */
	private function get_term_data_from_db( $term_id ) {

		global $wpdb;

		// Get term data directly from the database.
		$term = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT t.*, tt.description, tt.parent
				FROM {$wpdb->terms} AS t
				INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
				WHERE t.term_id = %d",
				$term_id
			),
			ARRAY_A
		);

		if ( empty( $term ) ) {
			return false;
		}

		// Format it similar to get_term() ARRAY_A output.
		return [
			'term_id'     => (int) $term['term_id'],
			'name'        => $term['name'],
			'slug'        => $term['slug'],
			'term_group'  => (int) $term['term_group'],
			'description' => $term['description'],
			'parent'      => (int) $term['parent'],
		];
	}

	/**
	 * Migrate the TEC category to Sugar Calendar.
	 *
	 * @since 3.6.0
	 *
	 * @param object $tec_category The TEC category.
	 *                             - term_id: TEC category ID.
	 *                             - name: TEC category name.
	 *                             - slug: TEC category slug.
	 *                             - parent: TEC category parent ID.
	 *
	 * @return bool Returns `true` if the category is successfully migrated. Otherwise, `false`.
	 */
	private function migrate_category( $tec_category ) {

		// Default migrated state.
		$migrated = false;

		// Get the TEC category term data.
		$tec_category_id        = intval( $tec_category->term_id );
		$tec_category_parent_id = intval( $tec_category->parent );

		// Use our custom function instead of get_term to avoid dependency on the taxonomy being registered.
		$tec_category = $this->get_term_data_from_db( $tec_category_id );

		// Return if $tec_category is not an array or a wp error.
		if (
			! is_array( $tec_category )
			||
			is_wp_error( $tec_category )
		) {
			return $migrated;
		}

		// Get SC Calendar info.
		$sc_calendar_taxonomy = sugar_calendar_get_calendar_taxonomy_id();

		// By default, let wp handle the slug.
		$sc_calendar_args = [

			// Setup calendar name and description.
			'name'        => $tec_category['name'],
			'description' => $tec_category['description'],

			// If the term already exists, use the unique slug.
			'slug'        => term_exists( $tec_category['name'], $sc_calendar_taxonomy )
				? wp_unique_term_slug(
					$tec_category['slug'],
					(object) [ 'taxonomy' => $sc_calendar_taxonomy ]
				)
				: $tec_category['slug'],
		];

		// Insert term to SC calendar.
		$sc_calendar_id = wp_insert_term(
			$tec_category['name'],
			$sc_calendar_taxonomy,
			$sc_calendar_args
		);

		// If term is inserted, update migration table.
		if (
			is_array( $sc_calendar_id )
			&&
			! is_wp_error( $sc_calendar_id )
		) {

			$sc_calendar_id = intval( $sc_calendar_id['term_id'] );

			// Set random color for this calendar.
			update_term_meta( $sc_calendar_id, 'color', Helpers::generate_random_hex_color() );

			// Columns tec_category_id, tec_category_parent_id, sc_category_id.
			$saved_migrated_category = $this->save_migrated_category(
				$tec_category_id,
				$tec_category_parent_id,
				$sc_calendar_id
			);

			$migrated = $saved_migrated_category !== false;
		}

		return $migrated;
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
				$this->get_import_batch_size( 'events' )
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
	public function get_number_of_tec_events_to_import() {

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
	 * Get the TEC categories that are not yet migrated.
	 *
	 * @since 3.6.0
	 *
	 * @param bool $count_only Whether to return the count only.
	 *
	 * @return array
	 */
	private function get_tec_categories_to_import( $count_only = false ) {

		// First, check if the `sc_migrate_tec_categories` table exists.
		if ( empty( $this->check_if_db_table_exists( self::MIGRATE_CATEGORIES_TABLE ) ) ) {
			// Create the table.
			$this->create_tec_migrate_tec_categories_table();
		}

		global $wpdb;

		if ( $count_only ) {
			$select_query = 'SELECT COUNT(t.term_id) AS context_to_import_count';
			$limit_query  = '';
		} else {
			$select_query = 'SELECT t.term_id, t.name, t.slug, tt.parent';
			$limit_query  = $wpdb->prepare(
				'LIMIT %d',
				$this->get_import_batch_size( 'categories' )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			esc_sql( $select_query ) . ' FROM ' . $wpdb->terms . ' AS t'
			. ' INNER JOIN ' . $wpdb->term_taxonomy . ' AS tt ON t.term_id = tt.term_id'
			. ' LEFT JOIN ' . $wpdb->prefix . esc_sql( self::MIGRATE_CATEGORIES_TABLE ) . ' AS mtc'
			. ' ON mtc.tec_category_id = t.term_id'
			. ' WHERE tt.taxonomy = "tribe_events_cat"'
			. ' AND mtc.id IS NULL '
			. esc_sql( $limit_query )
		);
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
				$this->get_import_batch_size( 'tickets' )
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
				$this->get_import_batch_size( 'orders' )
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
				$this->get_import_batch_size( 'attendees' )
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
	 * Insert the event to be migrated.
	 *
	 * @since 3.7.0
	 *
	 * @param int $sc_event_id The Sugar Calendar event ID.
	 *
	 * @return int|false
	 */
	private function insert_migrating_event( $tec_event_id ) {

		global $wpdb;

		return $wpdb->insert(
			$wpdb->prefix . self::MIGRATE_EVENTS_TABLE,
			[
				'tec_event_id' => $tec_event_id,
			],
			[
				'%d',
			]
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

		return $wpdb->update(
			$wpdb->prefix . self::MIGRATE_EVENTS_TABLE,
			[
				'tec_event_post_id' => $tec_event_post_id,
				'sc_event_id'       => $sc_event_id,
				'sc_event_post_id'  => $sc_event_post_id,
			],
			[
				'tec_event_id' => $tec_event_id,
			],
			[
				'%d',
				'%d',
				'%d',
			],
			[
				'%d',
			]
		);
	}

	/**
	 * Insert the ticket to be migrated.
	 *
	 * @since 3.7.0
	 *
	 * @param int $tec_ticket_id TEC event ID.
	 *
	 * @return int|false
	 */
	private function insert_migrating_ticket( $tec_ticket_id ) {

		global $wpdb;

		return $wpdb->insert(
			$wpdb->prefix . self::MIGRATE_TICKETS_TABLE,
			[
				'tec_ticket_id' => $tec_ticket_id,
			],
			[
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

		return $wpdb->update(
			$wpdb->prefix . self::MIGRATE_TICKETS_TABLE,
			[
				'sc_event_id' => $sc_event_id,
				'is_migrated' => $is_migrated,
			],
			[
				'tec_ticket_id' => $tec_ticket_id,
			],
			[
				'%d',
			],
			[
				'%d',
				'%d',
			]
		);
	}

	/**
	 * Insert the category to be migrated.
	 *
	 * @since 3.7.0
	 *
	 * @param int $tec_category_id TEC category ID.
	 *
	 * @return int|false
	 */
	private function insert_migrating_category( $tec_category_id ) {

		global $wpdb;

		return $wpdb->insert(
			$wpdb->prefix . self::MIGRATE_CATEGORIES_TABLE,
			[
				'tec_category_id' => $tec_category_id,
			],
			[
				'%d',
			]
		);
	}

	/**
	 * Save the migrated category info.
	 *
	 * @since 3.6.0
	 *
	 * @param int $tec_category_id        The TEC category ID.
	 * @param int $tec_category_parent_id The TEC category parent ID.
	 * @param int $sc_category_id         The Sugar Calendar category ID.
	 *
	 * @return int|false
	 */
	private function save_migrated_category( $tec_category_id, $tec_category_parent_id, $sc_category_id ) {

		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . self::MIGRATE_CATEGORIES_TABLE,
			[
				'tec_category_parent_id' => $tec_category_parent_id,
				'sc_category_id'         => $sc_category_id,
			],
			[
				'tec_category_id' => $tec_category_id,
			],
			[
				'%d',
			],
			[
				'%d',
				'%d',
			]
		);
	}

	/**
	 * Insert the order to be migrated.
	 *
	 * @since 3.7.0
	 *
	 * @param int $tec_order_id TEC order ID.
	 *
	 * @return int|false
	 */
	private function insert_migrating_order( $tec_order_id ) {

		global $wpdb;

		return $wpdb->insert(
			$wpdb->prefix . self::MIGRATE_ORDERS_TABLE,
			[
				'tec_order_id' => $tec_order_id,
			],
			[
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

		return $wpdb->update(
			$wpdb->prefix . self::MIGRATE_ORDERS_TABLE,
			[
				'sc_order_id' => $sc_order_id,
			],
			[
				'tec_order_id' => $tec_order_id,
			],
			[
				'%d',
			],
			[
				'%d',
			]
		);
	}

	/**
	 * Insert the attendee to be migrated.
	 *
	 * @since 3.7.0
	 *
	 * @param int $tec_attendee_id TEC attendee ID.
	 *
	 * @return int|false
	 */
	private function insert_migrating_attendee( $tec_attendee_id ) {

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
	 * Create the `sc_migrate_tec_categories` table.
	 *
	 * @since 3.6.0
	 *
	 * @return void
	 */
	private function create_tec_migrate_tec_categories_table() {

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Create categories migration table.
		// Column: tec_category_id, tec_category_parent_id, sc_category_id.
		$sql = 'CREATE TABLE ' . $wpdb->prefix . self::MIGRATE_CATEGORIES_TABLE
			. ' (`id` int AUTO_INCREMENT,`tec_category_id` int,`tec_category_parent_id` int,`sc_category_id` int, PRIMARY KEY (id));';

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
	 * @since 3.6.0 Change function name to avoid confusion with `get_tec_venue`.
	 *
	 * @param int $tec_event_post_id The Event Calendar event ID.
	 *
	 * @return false|string Returns `false` if the event doesn't have a venue,
	 *                      otherwise returns the venue address.
	 */
	private function get_tec_location_venue( $tec_event_post_id ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

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
	 * @since 3.7.0 Add tags migration table.
	 *
	 * @return bool|int
	 */
	private function drop_migration_tables() {

		global $wpdb;

		return $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
			'DROP TABLE IF EXISTS ' . // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->prefix . esc_sql( self::MIGRATE_EVENTS_TABLE ) . ', ' .
			$wpdb->prefix . esc_sql( self::MIGRATE_VENUES_TABLE ) . ', ' .
			$wpdb->prefix . esc_sql( self::MIGRATE_TICKETS_TABLE ) . ', ' .
			$wpdb->prefix . esc_sql( self::MIGRATE_ORDERS_TABLE ) . ', ' .
			$wpdb->prefix . esc_sql( self::MIGRATE_ATTENDEES_TABLE ) . ', ' .
			$wpdb->prefix . esc_sql( self::MIGRATE_CATEGORIES_TABLE ) . ', ' .
			$wpdb->prefix . esc_sql( self::MIGRATE_TAGS_TABLE ) . ';'
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

	/**
	 * Post migration process.
	 *
	 * @since 3.6.0
	 * @since 3.7.0 Add tag association to events.
	 *
	 * @return void
	 */
	private function post_migration_process() {

		// Rebuild categories hierarchy in Sugar Calendar.
		$this->rebuild_categories_hierarchy();

		// Relate migrated categories to migrated events.
		$this->relate_migrated_categories_to_events();

		// Relate migrated tags to migrated events.
		$this->relate_migrated_tags_to_events();
	}

	/**
	 * Rebuild the categories hierarchy.
	 *
	 * @since 3.6.0
	 *
	 * @return void
	 */
	private function rebuild_categories_hierarchy() {

		// Get all migrated categories from the `sc_migrate_tec_categories` table.
		$migrated_categories = $this->get_migrated_categories();

		// Loop through the categories.
		foreach ( $migrated_categories as $migrated_category ) {

			// If tec_category_parent_id is not set, continue.
			if ( $migrated_category->tec_category_parent_id === 0 ) {
				continue;
			}

			// Get the parent category ID.
			$parent_category_id = $migrated_category->tec_category_parent_id;

			// Get the calendar category ID.
			$calendar_id = $migrated_category->sc_category_id;

			// Get sc_category_id (calendar) based on tec_category_id (category).
			$parent_calendar_id = $this->get_migrated_calendar_category_id_by_tec_category_id(
				$parent_category_id
			);

			$parent_term = get_term(
				$parent_calendar_id,
				sugar_calendar_get_calendar_taxonomy_id()
			);

			// Check if term exist.
			if (
				$parent_term instanceof WP_Term
			) {

				// Update term with the calendar ID.
				wp_update_term(
					$calendar_id,
					sugar_calendar_get_calendar_taxonomy_id(),
					[
						'parent' => $parent_term->term_id,
					]
				);
			}
		}
	}

	/**
	 * Get the migrated categories.
	 *
	 * @since 3.6.0
	 *
	 * @return array
	 */
	private function get_migrated_categories() {

		global $wpdb;

		$results = $wpdb->get_results(
			'SELECT * FROM ' . $wpdb->prefix . esc_sql( self::MIGRATE_CATEGORIES_TABLE ),
			OBJECT
		);

		foreach ( $results as $row ) {
			$row->id                     = (int) $row->id;
			$row->tec_category_id        = (int) $row->tec_category_id;
			$row->tec_category_parent_id = (int) $row->tec_category_parent_id;
			$row->sc_category_id         = (int) $row->sc_category_id;
		}

		return $results;
	}

	/**
	 * Get the migrated calendar category ID by TEC category ID.
	 *
	 * @since 3.6.0
	 *
	 * @param int $tec_category_id The TEC category ID.
	 *
	 * @return int
	 */
	private function get_migrated_calendar_category_id_by_tec_category_id( $tec_category_id ) {

		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare( 'SELECT sc_category_id FROM ' . $wpdb->prefix . esc_sql( self::MIGRATE_CATEGORIES_TABLE ) . ' WHERE tec_category_id = %d', $tec_category_id )
		);
	}

	/**
	 * Get the TEC venues that are not yet migrated.
	 *
	 * @since 3.6.0
	 *
	 * @param bool $count_only Whether to return the count only.
	 *
	 * @return array
	 */
	private function get_tec_venues_to_import( $count_only = false ) {

		// First, check if the `sc_migrate_tec_venues` table exists.
		if ( empty( $this->check_if_db_table_exists( self::MIGRATE_VENUES_TABLE ) ) ) {

			// Create the table.
			$this->create_tec_migrate_tec_venues_table();
		}

		global $wpdb;

		if ( $count_only ) {
			$select_query = 'SELECT COUNT(' . $wpdb->posts . '.ID) AS context_to_import_count';
			$limit_query  = '';
		} else {
			$select_query = 'SELECT ' . $wpdb->posts . '.ID';
			$limit_query  = $wpdb->prepare(
				'LIMIT %d',
				$this->get_import_batch_size( 'venues' )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			esc_sql( $select_query ) . ' FROM ' . $wpdb->posts .
			' LEFT JOIN ' . $wpdb->prefix . esc_sql( self::MIGRATE_VENUES_TABLE ) .
			' ON ' . $wpdb->prefix . esc_sql( self::MIGRATE_VENUES_TABLE ) . '.tec_venue_id = ' .
			$wpdb->posts . '.ID WHERE ' . $wpdb->posts . '.post_type = "tribe_venue" AND '
			. $wpdb->prefix . esc_sql( self::MIGRATE_VENUES_TABLE ) . '.id IS NULL ' . esc_sql( $limit_query )
		);
	}

	/**
	 * Start the migration of TEC venues.
	 *
	 * @since 3.6.0
	 *
	 * @param array $tec_venues Array containing the TEC venues to migrate.
	 *
	 * @return int The number of successful migrations.
	 */
	private function start_tec_venues_migration( $tec_venues ) {

		$successful_migrations = 0;

		foreach ( $tec_venues as $venue ) {

			$this->insert_migrating_venue( $venue->ID );

			if ( $this->migrate_venue( $venue->ID ) ) {
				++$successful_migrations;
			}
		}

		return $successful_migrations;
	}

	/**
	 * Migrate a TEC venue to Sugar Calendar.
	 *
	 * @since 3.6.0
	 *
	 * @param int $tec_venue_id The TEC venue ID.
	 *
	 * @return bool Returns `true` if the venue is successfully migrated. Otherwise, `false`.
	 */
	private function migrate_venue( $tec_venue_id ) {

		$tec_venue = get_post( $tec_venue_id );

		if ( empty( $tec_venue ) || $tec_venue->post_type !== 'tribe_venue' ) {
			return false;
		}

		$meta_data = get_post_meta( $tec_venue_id );

		/**
		 * Additional actions for venue migration.
		 *
		 * @since 3.6.0
		 *
		 * @param int    $sc_venue_id The Sugar Calendar venue ID.
		 * @param object $tec_venue   The TEC venue object.
		 * @param array  $meta_data   The venue meta data.
		 */
		$sc_venue_id = apply_filters(
			'sugar_calendar_admin_tools_importers_the_event_calendar_migrate_venue',
			0,
			$tec_venue,
			$meta_data
		);

		if ( is_wp_error( $sc_venue_id ) ) {
			return false;
		}

		// Save migration record.
		$this->save_migrated_venue( $tec_venue_id, $sc_venue_id );

		return true;
	}

	/**
	 * Insert the venue to be migrated.
	 *
	 * @since 3.7.0
	 *
	 * @param int $tec_venue_id TEC venue ID.
	 *
	 * @return int|false
	 */
	private function insert_migrating_venue( $tec_venue_id ) {

		global $wpdb;

		return $wpdb->insert(
			$wpdb->prefix . self::MIGRATE_VENUES_TABLE,
			[
				'tec_venue_id' => $tec_venue_id,
			],
			[
				'%d',
			]
		);
	}

	/**
	 * Save the migrated venue info.
	 *
	 * @since 3.6.0
	 *
	 * @param int $tec_venue_id The TEC venue ID.
	 * @param int $sc_venue_id  The Sugar Calendar venue ID.
	 *
	 * @return int|false
	 */
	private function save_migrated_venue( $tec_venue_id, $sc_venue_id ) {

		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . self::MIGRATE_VENUES_TABLE,
			[
				'sc_venue_id' => $sc_venue_id,
			],
			[
				'tec_venue_id' => $tec_venue_id,
			],
			[
				'%d',
			],
			[
				'%d',
			]
		);
	}

	/**
	 * Create the `sc_migrate_tec_venues` table.
	 *
	 * @since 3.6.0
	 *
	 * @return void
	 */
	private function create_tec_migrate_tec_venues_table() {

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = 'CREATE TABLE ' . $wpdb->prefix . self::MIGRATE_VENUES_TABLE
			. ' (`id` int AUTO_INCREMENT,`tec_venue_id` int,`sc_venue_id` int, PRIMARY KEY (id));';

		dbDelta( $sql );
	}

	/**
	 * Create the `sc_migrate_tec_tags` table.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	private function create_tec_migrate_tec_tags_table() {

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = 'CREATE TABLE ' . $wpdb->prefix . self::MIGRATE_TAGS_TABLE
			. ' (`id` int AUTO_INCREMENT,`tec_tag_id` int,`sc_tag_id` int, PRIMARY KEY (id));';

		dbDelta( $sql );
	}

	/**
	 * Get the number of TEC venues to import.
	 *
	 * @since 3.6.0
	 *
	 * @return int
	 */
	private function get_number_of_tec_venues_to_import() {

		if ( ! is_null( $this->number_of_tec_venues_to_import ) ) {
			return absint( $this->number_of_tec_venues_to_import );
		}

		$result = $this->get_tec_venues_to_import( true );

		if ( ! empty( $result ) && ! empty( $result[0]->context_to_import_count ) ) {
			$this->number_of_tec_venues_to_import = absint( $result[0]->context_to_import_count );
		} else {
			$this->number_of_tec_venues_to_import = 0;
		}

		return $this->number_of_tec_venues_to_import;
	}

	/**
	 * Get term IDs for a post without requiring the taxonomy to be registered.
	 *
	 * This function retrieves term IDs associated with a post directly from the database,
	 * bypassing the requirement for the taxonomy to be registered.
	 *
	 * @since 3.6.0
	 *
	 * @param int    $post_id  The post ID to get terms for.
	 * @param string $taxonomy The taxonomy name (used only to match term_taxonomy records).
	 *
	 * @return array Array of term IDs or empty array if none found.
	 */
	private function get_post_term_ids_from_db( $post_id, $taxonomy ) {

		global $wpdb;

		// Query to get term IDs for a post by joining term_relationships and term_taxonomy tables.
		$term_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT t.term_id
				FROM {$wpdb->term_relationships} AS tr
				INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id
				WHERE tr.object_id = %d
				AND tt.taxonomy = %s",
				$post_id,
				$taxonomy
			)
		);

		// Convert string IDs to integers.
		return array_map( 'intval', $term_ids );
	}

	/**
	 * Relate migrated categories to migrated events.
	 *
	 * @since 3.6.0
	 *
	 * @return void
	 */
	private function relate_migrated_categories_to_events() {

		global $wpdb;

		// Get all migrated events with their TEC IDs.
		$migrated_events = $wpdb->get_results(
			'SELECT
				tec_event_id, sc_event_id, tec_event_post_id, sc_event_post_id
			FROM
				' . esc_sql( $wpdb->prefix . self::MIGRATE_EVENTS_TABLE ) . '
			WHERE
				sc_event_id IS NOT NULL'
		);

		if ( empty( $migrated_events ) ) {
			return;
		}

		// Process each migrated event.
		foreach ( $migrated_events as $event ) {

			// Use our custom function instead of wp_get_object_terms to avoid dependency on the taxonomy being registered.
			$tec_terms = $this->get_post_term_ids_from_db(
				$event->tec_event_post_id,
				'tribe_events_cat'
			);

			if ( empty( $tec_terms ) || is_wp_error( $tec_terms ) ) {
				continue;
			}

			$sc_term_ids = [];

			// Map TEC category IDs to Sugar Calendar category IDs.
			foreach ( $tec_terms as $tec_term_id ) {

				// Get the migrated Sugar Calendar category ID by TEC category ID.
				$sc_term_id = $this->get_migrated_calendar_category_id_by_tec_category_id( $tec_term_id );

				if ( ! empty( $sc_term_id ) ) {
					$sc_term_ids[] = (int) $sc_term_id;
				}
			}

			if ( ! empty( $sc_term_ids ) ) {

				// Set the categories for the Sugar Calendar event using post ID.
				wp_set_object_terms(
					$event->sc_event_post_id,
					$sc_term_ids,
					sugar_calendar_get_calendar_taxonomy_id()
				);
			}
		}
	}

	/**
	 * Get the Sugar Calendar venue ID for a TEC event post.
	 *
	 * @since 3.6.0
	 *
	 * @param int $tec_event_post_id The Event Calendar event post ID.
	 *
	 * @return int|false Returns the Sugar Calendar venue ID if found. Otherwise, returns `false`.
	 */
	private function get_tec_post_sc_venue_id( $tec_event_post_id ) {

		global $wpdb;

		// Get the TEC venue ID from event meta.
		$tec_venue_id = get_post_meta( $tec_event_post_id, '_EventVenueID', true );

		if ( empty( $tec_venue_id ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		$sc_venue_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT
					sc_venue_id
				FROM
					' . esc_sql( $wpdb->prefix . self::MIGRATE_VENUES_TABLE ) . '
				WHERE
					tec_venue_id = %d',
				$tec_venue_id
			)
		);

		if ( empty( $sc_venue_id ) ) {
			return false;
		}

		return absint( $sc_venue_id );
	}

	/**
	 * Get the TEC tags that are not yet migrated.
	 *
	 * @since 3.7.0
	 *
	 * @param bool $count_only Whether to return the count only.
	 *
	 * @return array
	 */
	private function get_tec_tags_to_import( $count_only = false ) {

		// First, check if the `sc_migrate_tec_tags` table exists.
		if ( empty( $this->check_if_db_table_exists( self::MIGRATE_TAGS_TABLE ) ) ) {
			// Create the table.
			$this->create_tec_migrate_tec_tags_table();
		}

		global $wpdb;

		$group_by = '';

		if ( $count_only ) {

			$select_query = 'SELECT COUNT(t.term_id) AS context_to_import_count';
			$limit_query  = '';

		} else {

			$select_query = 'SELECT t.term_id, t.name, t.slug';
			$group_by     = ' GROUP BY t.term_id ';
			$limit_query  = $wpdb->prepare(
				'LIMIT %d',
				/**
				 * Filter the number of TEC tags to import per iteration.
				 *
				 * @since 3.7.0
				 *
				 * @param int $limit The number of TEC tags to import per iteration.
				 */
				apply_filters( 'sugar_calendar_admin_tools_importers_the_event_calendar_tags_limit', 100 )
			);
		}

		// Get tag terms that are used by TEC events and not yet migrated.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		$query = esc_sql( $select_query ) . ' FROM ' . $wpdb->terms . ' AS t'
			. ' INNER JOIN ' . $wpdb->term_taxonomy . ' AS tt ON t.term_id = tt.term_id'
			. ' LEFT JOIN ' . $wpdb->prefix . esc_sql( self::MIGRATE_TAGS_TABLE ) . ' AS mtt'
			. ' ON mtt.tec_tag_id = t.term_id'
			. ' WHERE tt.taxonomy = "post_tag"'
			. ' AND mtt.id IS NULL '
			. esc_sql( $group_by )
			. esc_sql( $limit_query );

		$results = $wpdb->get_results( $query );

		return $results;
	}

	/**
	 * Start the migration of TEC tags.
	 *
	 * @since 3.7.0
	 *
	 * @param array $tec_tags Array containing the TEC tags to migrate.
	 *
	 * @return int The number of successful migrations.
	 */
	private function start_tec_tags_migration( $tec_tags ) {

		$successful_migrations = 0;

		/**
		 * Debugging with Ray.
		 *
		 * @TODO: Remove before commit.
		 */
		ray(
			[
				'name' => __FUNCTION__,
				'tec_tags' => $tec_tags,
			]
		);

		foreach ( $tec_tags as $tec_tag ) {
			if ( $this->migrate_tag( $tec_tag ) ) {
				++$successful_migrations;
			}
		}

		return $successful_migrations;
	}

	/**
	 * Migrate the TEC tag to Sugar Calendar.
	 *
	 * @since 3.7.0
	 *
	 * @param object $tec_tag The TEC tag term object.
	 *                        - term_id: TEC tag ID.
	 *                        - name: TEC tag name.
	 *                        - slug: TEC tag slug.
	 *
	 * @return bool Returns `true` if the tag is successfully migrated. Otherwise, `false`.
	 */
	private function migrate_tag( $tec_tag ) {

		// Default migrated state.
		$migrated = false;

		// Get the TEC tag term ID.
		$tec_tag_id = intval( $tec_tag->term_id );

		// Get full tag term data.
		$tec_tag_data = $this->get_term_data_from_db( $tec_tag_id );

		// Return if $tec_tag_data is not an array or a wp error.
		if (
			! is_array( $tec_tag_data )
			||
			is_wp_error( $tec_tag_data )
		) {
			return $migrated;
		}

		// By default, let wp handle the slug.
		$sc_tag_args = [
			// Setup tag name and description.
			'name'        => $tec_tag_data['name'],
			'description' => $tec_tag_data['description'],

			// If the term already exists, use the unique slug.
			'slug'        => term_exists( $tec_tag_data['name'], 'post_tag' )
				? wp_unique_term_slug(
					$tec_tag_data['slug'],
					(object) [ 'taxonomy' => 'post_tag' ]
				)
				: $tec_tag_data['slug'],
		];

		// Insert term as a tag.
		$sc_tag_id = wp_insert_term(
			$tec_tag_data['name'],
			TagsHelpers::get_tags_taxonomy_id(),
			$sc_tag_args
		);

		// If term is inserted, update migration table.
		if (
			is_array( $sc_tag_id )
			&&
			! is_wp_error( $sc_tag_id )
		) {
			$sc_tag_id = intval( $sc_tag_id['term_id'] );

			// Save the migrated tag info.
			$saved_migrated_tag = $this->save_migrated_tag(
				$tec_tag_id,
				$sc_tag_id
			);

			$migrated = $saved_migrated_tag !== false;

			/**
			 * Additional actions for tag migration.
			 *
			 * @since 3.7.0
			 *
			 * @param int    $sc_tag_id    The Sugar Calendar tag ID.
			 * @param object $tec_tag      The TEC tag object.
			 * @param array  $tec_tag_data The tag data.
			 */
			do_action(
				'sugar_calendar_admin_tools_importers_the_event_calendar_migrate_tag',
				$sc_tag_id,
				$tec_tag,
				$tec_tag_data
			);
		}

		return $migrated;
	}

	/**
	 * Save the migrated tag info.
	 *
	 * @since 3.7.0
	 *
	 * @param int $tec_tag_id The TEC tag ID.
	 * @param int $sc_tag_id  The Sugar Calendar tag ID.
	 *
	 * @return int|false
	 */
	private function save_migrated_tag( $tec_tag_id, $sc_tag_id ) {

		global $wpdb;

		return $wpdb->insert(
			$wpdb->prefix . self::MIGRATE_TAGS_TABLE,
			[
				'tec_tag_id' => $tec_tag_id,
				'sc_tag_id'  => $sc_tag_id,
			],
			[
				'%d',
				'%d',
			]
		);
	}

	/**
	 * Get the migrated tag ID by TEC tag ID.
	 *
	 * @since 3.7.0
	 *
	 * @param int $tec_tag_id The TEC tag ID.
	 *
	 * @return int
	 */
	private function get_migrated_tag_id_by_tec_tag_id( $tec_tag_id ) {

		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare( 'SELECT sc_tag_id FROM ' . $wpdb->prefix . esc_sql( self::MIGRATE_TAGS_TABLE ) . ' WHERE tec_tag_id = %d', $tec_tag_id )
		);
	}

	/**
	 * Relate migrated tags to migrated events.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	private function relate_migrated_tags_to_events() {

		global $wpdb;

		// Get all migrated events with their TEC IDs.
		$migrated_events = $wpdb->get_results(
			'SELECT
				tec_event_id, sc_event_id, tec_event_post_id, sc_event_post_id
			FROM
				' . esc_sql( $wpdb->prefix . self::MIGRATE_EVENTS_TABLE ) . '
			WHERE
				sc_event_id IS NOT NULL'
		);

		if ( empty( $migrated_events ) ) {
			return;
		}

		// Process each migrated event.
		foreach ( $migrated_events as $event ) {

			$tec_tags = $this->get_post_term_ids_from_db(
				$event->tec_event_post_id,
				'post_tag'
			);

			if ( empty( $tec_tags ) || is_wp_error( $tec_tags ) ) {
				continue;
			}

			$sc_tag_ids = [];

			// Map TEC tag IDs to Sugar Calendar tag IDs.
			foreach ( $tec_tags as $tec_tag_id ) {

				// Get the migrated Sugar Calendar tag ID by TEC tag ID.
				$sc_tag_id = $this->get_migrated_tag_id_by_tec_tag_id( $tec_tag_id );

				if ( ! empty( $sc_tag_id ) ) {
					$sc_tag_ids[] = (int) $sc_tag_id;
				}
			}

			if ( ! empty( $sc_tag_ids ) ) {

				// Set the tags for the Sugar Calendar event using post ID.
				wp_set_object_terms(
					$event->sc_event_post_id,
					$sc_tag_ids,
					TagsHelpers::get_tags_taxonomy_id()
				);
			}
		}
	}

	private function get_import_batch_size( $context ) {

		switch ( $context ) {
			case 'events':
				/**
				 * Filter the number of TEC events to import per iteration.
				 *
				 * @since 3.3.0
				 *
				 * @param int $limit The number of TEC events to import per iteration.
				 */
				return apply_filters( 'sc_import_tec_events_limit', 10 ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

			case 'categories':
				/**
				 * Filter the number of TEC categories to import per iteration.
				 *
				 * @since 3.6.0
				 *
				 * @param int $limit The number of TEC categories to import per iteration.
				 */
				return apply_filters( 'sc_import_tec_categories_limit', 100 ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

			case 'tickets':
				/**
				 * Filter the number of TEC tickets to import per iteration.
				 *
				 * @since 3.3.0
				 *
				 * @param int $limit The number of TEC tickets to import per iteration.
				 */
				return apply_filters( 'sc_import_tec_tickets_limit', 10 ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

			case 'orders':
				/**
				 * Filter the number of TEC orders to import per iteration.
				 *
				 * @since 3.3.0
				 *
				 * @param int $limit The number of TEC orders to import per iteration.
				 */
				return apply_filters( 'sc_import_tec_orders_limit', 10 ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

			case 'attendees':
				/**
				 * Filter the number of TEC attendees to import per iteration.
				 *
				 * @since 3.3.0
				 *
				 * @param int $limit The number of TEC attendees to import per iteration.
				 */
				return apply_filters( 'sc_import_tec_attendees_limit', 10 ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

			case 'venues':
				/**
				 * Filter the number of TEC venues to import per iteration.
				 *
				 * @since 3.6.0
				 *
				 * @param int $limit The number of TEC venues to import per iteration.
				 */
				return apply_filters( 'sc_import_tec_venues_limit', 50 ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

			default:
				return 0;
		}
	}
}
