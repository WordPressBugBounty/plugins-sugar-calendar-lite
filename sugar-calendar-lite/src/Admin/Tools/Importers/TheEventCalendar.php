<?php

namespace Sugar_Calendar\Admin\Tools\Importers;

use Sugar_Calendar\AddOn\Ticketing\Migrate\TicketingWriter;
use Sugar_Calendar\Admin\Tools\Migrate\MigrateRunner;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\TecIdMap;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\TecItemReader;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\TecMigrationNotice;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\TecReader;
use Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\TecSource;
use Sugar_Calendar\Helpers\Helpers;

/**
 * The Events Calendar Migrator.
 *
 * @since 3.3.0
 */
class TheEventCalendar extends Importer {

	use TheEventCalendarWriteApi;

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
	 * Option holding the highest mapping row IDs from before the current run.
	 *
	 * A re-run's post-migration steps only touch rows above these, so events and
	 * calendars from an earlier run keep the edits made to them since.
	 *
	 * @since 3.18.0
	 *
	 * @var string
	 */
	const SC_TEC_LAST_MIGRATED_IDS_OPTION_KEY = TecIdMap::LAST_IDS_OPTION;

	/**
	 * DB table used to keep track of the events migrated.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const MIGRATE_EVENTS_TABLE = TecIdMap::EVENTS_TABLE;

	/**
	 * `sc_event_id` of an event mapping row whose migration is under way.
	 *
	 * Left behind only when the request dies, so the retry takes the event up again.
	 * Earlier versions left NULL for a failed event; that, and 0, still mean done.
	 *
	 * @since 3.18.0
	 *
	 * @var int
	 */
	const MIGRATING_EVENT_ID = TecIdMap::MIGRATING_ID;

	/**
	 * DB table used to keep track of the categories migrated.
	 *
	 * @since 3.6.0
	 *
	 * @var string
	 */
	const MIGRATE_CATEGORIES_TABLE = TecIdMap::CATEGORIES_TABLE;

	/**
	 * DB table used to keep track of the tickets migrated.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const MIGRATE_TICKETS_TABLE = TecIdMap::TICKETS_TABLE;

	/**
	 * DB table used to keep track of the attendees migrated.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const MIGRATE_ATTENDEES_TABLE = TecIdMap::ATTENDEES_TABLE;

	/**
	 * DB table used to keep track of the orders migrated.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const MIGRATE_ORDERS_TABLE = TecIdMap::ORDERS_TABLE;

	/**
	 * DB table used to keep track of the venues migrated.
	 *
	 * @since 3.6.0
	 *
	 * @var string
	 */
	const MIGRATE_VENUES_TABLE = TecIdMap::VENUES_TABLE;

	/**
	 * DB table used to keep track of the tags migrated.
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	const MIGRATE_TAGS_TABLE = TecIdMap::TAGS_TABLE;

	/**
	 * The number of TEC events to import.
	 *
	 * @since 3.3.0
	 *
	 * @var int
	 */
	private $number_of_tec_events_to_import = null;

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

		( new TecMigrationNotice( $this ) )->hooks();
	}

	/**
	 * Check if there are TEC event post to migrate.
	 *
	 * @since 3.3.0
	 * @since 3.18.0 After a completed migration, check for TEC events it hasn't migrated.
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

		if ( empty( $tec_event_post ) ) {
			return false;
		}

		if ( self::is_migration_unfinished() ) {
			return true;
		}

		return self::has_unmigrated_tec_events();
	}

	/**
	 * Check if a completed migration left TEC events behind, such as ones added since.
	 *
	 * A site migrated before the mapping table was kept has no table, or only an empty one.
	 * Nothing there tells old events from new, so a re-run would duplicate them all, and the tab stays closed for it.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	private static function has_unmigrated_tec_events() {

		global $wpdb;

		$mapping = $wpdb->prefix . TecIdMap::EVENTS_TABLE;

		if ( ! TheEventCalendarTables::exists( TecIdMap::EVENTS_TABLE ) || ! $wpdb->get_var( "SELECT 1 FROM {$mapping} LIMIT 1" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return false;
		}

		$count = ( new TecReader() )->get_unmigrated_events( true, 0 );

		return ! empty( $count[0]->context_to_import_count );
	}

	/**
	 * Check if the migration has never finished: not started yet, or stopped partway.
	 *
	 * After a finished migration, TEC events added since reopen the Migrate tab but
	 * not the admin notice.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	public static function is_migration_unfinished() {

		return in_array( self::get_tec_migration_option(), [ false, 'in_progress' ], true );
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
		if ( ( new TecItemReader() )->has_community_events() ) {
			?>
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: Community Events feature page URL. */
						__(
							'Used Community Events in The Events Calendar? Submitted events come over with the rest, and pending ones stay pending. The submission form doesn\'t migrate, so you\'ll set up a new one in WPForms, Formidable Forms or Gravity Forms. Community Events comes with every paid Sugar Calendar Events plan. <a target="_blank" rel="noopener noreferrer" href="%s">See how Community Events works</a>',
							'sugar-calendar-lite'
						),
						esc_url(
							Helpers::get_utm_url(
								'https://sugarcalendar.com/events/features/community-events/',
								[
									'medium'  => 'tools-tec-migration',
									'content' => 'community-events',
								]
							)
						)
					),
					[
						'a' => [
							'href'   => [],
							'target' => [],
							'rel'    => [],
						],
					]
				);
				?>
			</p>
			<?php
		}

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
		$result = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for the import tool over TEC tables.
			"SELECT COUNT(*) FROM " . $wpdb->posts . " LEFT JOIN "
			. $wpdb->postmeta . " ON " . $wpdb->postmeta . ".post_id = " . $wpdb->posts . ".ID AND "
			. $wpdb->postmeta . ".meta_key = '_EventRecurrence' WHERE "
			. $wpdb->posts . ".post_type = 'tribe_events' AND " . $wpdb->postmeta . ".post_id IS NOT NULL"
		);

		return empty( $result ) ? 0 : absint( $result );
	}

	/**
	 * The TEC migration source every run and count goes through.
	 *
	 * @since 3.18.0
	 *
	 * @return TecSource
	 */
	private function get_source() {

		return new TecSource( $this, new TicketingWriter() );
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

		$source           = $this->get_source();
		$events_count     = $this->get_number_of_tec_events_to_import();
		$venues_count     = $source->count_pending( 'venues' );
		$orders_count     = $source->count_pending( 'orders' );
		$tickets_count    = $source->count_pending( 'attendees' );
		$attendees_count  = ( new TecReader() )->count_people_to_migrate();
		$categories_count = $source->count_pending( 'categories' );
		$tags_count       = $source->count_pending( 'tags' );

		$items_per_context = [];

		if ( $events_count > 0 ) {
			$items_per_context[] = sprintf(
				/* translators: %d - number of events to migrate. */
				_n( '%d event', '%d events', $events_count, 'sugar-calendar-lite' ),
				$events_count
			);
		}

		if ( $venues_count > 0 ) {
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
	 * @since 3.18.0 A re-run of a finished migration keeps its date until it finishes.
	 * @since 3.18.0 The migration runner walks the steps.
	 *
	 * @param int[] $total_number_to_import The total number to import per context.
	 * @param bool  $from_start             Whether to look from the first step instead of the saved one.
	 *
	 * @return array
	 */
	public function run( $total_number_to_import = [], $from_start = false ) {

		return ( new MigrateRunner() )->step( $this->get_source(), $total_number_to_import, $from_start )->to_array();
	}

	/**
	 * The number of people the attendee batches added so far.
	 *
	 * @since 3.18.0
	 *
	 * @return int|null
	 */
	public function get_imported_attendees_count() {

		return $this->imported_attendees_count;
	}

	/**
	 * Get the number of TEC events to import.
	 *
	 * @since 3.3.0
	 *
	 * @return int
	 */
	public function get_number_of_tec_events_to_import() {

		if ( is_null( $this->number_of_tec_events_to_import ) ) {
			$this->number_of_tec_events_to_import = $this->get_source()->count_pending( 'events' );
		}

		return absint( $this->number_of_tec_events_to_import );
	}

	/**
	 * Get the TEC migration option.
	 *
	 * @since 3.3.0
	 *
	 * @return mixed
	 */
	public static function get_tec_migration_option() {

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
