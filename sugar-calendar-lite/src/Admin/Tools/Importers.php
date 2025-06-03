<?php

namespace Sugar_Calendar\Admin\Tools;

use Sugar_Calendar\Admin\Tools\Importers\ImporterInterface;

/**
 * Importers class.
 *
 * @since 3.3.0
 */
class Importers {
	/**
	 * Migration notice dismiss nonce action.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const MIGRATION_NOTICE_DISMISS_NONCE_ACTION = 'sc-admin-dismiss-migration-notice';

	/**
	 * Dismissed migrations option key.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const DISMISSED_MIGRATIONS_OPTION_KEY = 'sc_admin_dismissed_migrations';

	/**
	 * Import nonce action.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const IMPORT_NONCE_ACTION = 'sc-admin-tools-importers';

	/**
	 * Migration nonce action.
	 *
	 * @since 3.3.0
	 *
	 * @var string
	 */
	const MIGRATION_NONCE_ACTION = 'sc-admin-tools-migration';

	/**
	 * Loaded importers.
	 *
	 * @since 3.3.0
	 *
	 * @var \Sugar_Calendar\Admin\Tools\Importers\Importer[]
	 */
	private $loaded_importers = [];

	/**
	 * Importers hooks.
	 *
	 * @since 3.3.0
	 */
	public function hooks() {

		add_action( 'admin_init', [ $this, 'load_importer_admin_hooks' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_importers_script' ] );
		add_action( 'wp_ajax_sc_admin_importer', [ $this, 'run_ajax_importer' ] );

		add_action( 'wp_ajax_sc_admin_dismiss_migration_notice', [ $this, 'ajax_dismiss_migration_notice' ] );
	}

	/**
	 * AJAX handler for dismissing the migration notice.
	 *
	 * @since 3.3.0
	 */
	public function ajax_dismiss_migration_notice() {

		check_admin_referer( self::MIGRATION_NOTICE_DISMISS_NONCE_ACTION, 'nonce' );

		$migration_slug = ! empty( $_POST['slug'] ) ? wp_unslash( $_POST['slug'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( empty( $migration_slug ) ) {
			wp_send_json_error(
				esc_html__( 'Invalid request.', 'sugar-calendar-lite' )
			);
		}

		// Get the dismissed migrations.
		$dismissed_migrations = json_decode( get_option( self::DISMISSED_MIGRATIONS_OPTION_KEY, false ) );

		if ( empty( $dismissed_migrations ) || ! is_array( $dismissed_migrations ) ) {
			$dismissed_migrations = [];
		}

		// Already dismissed.
		if ( in_array( $migration_slug, $dismissed_migrations, true ) ) {
			return;
		}

		$dismissed_migrations[] = $migration_slug;

		update_option( self::DISMISSED_MIGRATIONS_OPTION_KEY, wp_json_encode( $dismissed_migrations ) );
	}

	/**
	 * Load the admin hooks of the importers.
	 *
	 * @since 3.3.0
	 */
	public function load_importer_admin_hooks() {

		foreach ( $this->get_loaded_importers() as $importer ) {

			if ( method_exists( $importer, 'admin_hooks' ) ) {
				$importer->admin_hooks();
			}
		}
	}

	/**
	 * Enqueue admin importers script.
	 *
	 * @since 3.3.0
	 * @since 3.6.0 Add categories to the importer.
	 *
	 * @param string $hook Hook suffix for the current admin page.
	 */
	public function enqueue_admin_importers_script( $hook ) {

		// For now, we only need the JS for migrations.
		if ( $hook !== 'sugar-calendar_page_sc-tools' || ! $this->is_in_migration_page() ) {
			return;
		}

		wp_enqueue_script( 'sugar-calendar-admin-importers' );

		wp_localize_script(
			'sugar-calendar-admin-importers',
			'sc_admin_importers',
			[
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( self::MIGRATION_NONCE_ACTION ),
				'assets_url' => SC_PLUGIN_ASSETS_URL,
				'strings'    => [
					'complete'                 => esc_html__( 'Complete', 'sugar-calendar-lite' ),
					'in_progress'              => esc_html__( 'In progress', 'sugar-calendar-lite' ),
					'migration_in_progress'    => esc_html__( 'Migration in Progress...', 'sugar-calendar-lite' ),
					'migration_completed'      => esc_html__( 'Migration Complete!', 'sugar-calendar-lite' ),
					'migrated_events'          => esc_html__( 'Events migrated:', 'sugar-calendar-lite' ),
					'migrated_venues'          => esc_html__( 'Venues migrated:', 'sugar-calendar-lite' ),
					'migrated_speakers'        => esc_html__( 'Speakers migrated:', 'sugar-calendar-lite' ),
					'migrated_tickets'         => esc_html__( 'Tickets migrated:', 'sugar-calendar-lite' ),
					'migrated_orders'          => esc_html__( 'Orders migrated:', 'sugar-calendar-lite' ),
					'migrated_categories'      => esc_html__( 'Categories migrated:', 'sugar-calendar-lite' ),
					'migrated_tags'            => esc_html__( 'Tags migrated:', 'sugar-calendar-lite' ),
					'migrated_attendees'       => esc_html__( 'Attendees migrated:', 'sugar-calendar-lite' ),
					'migrated_failed'          => esc_html__( 'An error occurred during the migration ', 'sugar-calendar-lite' ),
					'heads_up'                 => esc_html__( 'Heads up!', 'sugar-calendar-lite' ),
					'yes'                      => esc_html__( 'Yes', 'sugar-calendar-lite' ),
					'cancel'                   => esc_html__( 'Cancel', 'sugar-calendar-lite' ),
					'recurring_events_warning' => esc_html__( 'Are you sure you want to import the recurring events as normal non-recurring events?', 'sugar-calendar-lite' ),
				],
			]
		);
	}

	/**
	 * Check if we are in a migration page.
	 *
	 * @since 3.3.0
	 *
	 * @return bool
	 */
	private function is_in_migration_page() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		return ! empty( $_GET['page'] ) && $_GET['page'] === 'sc-tools' &&
			! empty( $_GET['section'] ) && $_GET['section'] === 'migrate';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Run AJAX importer.
	 *
	 * For now, this is only used for migrations.
	 *
	 * @since 3.3.0
	 *
	 * @return void
	 */
	public function run_ajax_importer() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		if (
			! check_admin_referer( self::MIGRATION_NONCE_ACTION, 'nonce' ) ||
			empty( $_POST['importer_slug'] )
		) {
			wp_send_json_error(
				esc_html__( 'Invalid request.', 'sugar-calendar-lite' )
			);
		}

		$importer_slug = sanitize_key( $_POST['importer_slug'] );

		// Get importers.
		$importers = $this->get_loaded_importers();

		if (
			empty( $importers[ $importer_slug ] ) ||
			! ( $importers[ $importer_slug ] instanceof ImporterInterface )
		) {
			wp_send_json_error(
				esc_html__( 'Invalid importer.', 'sugar-calendar-lite' )
			);
		}

		// Default.
		$total_number_to_import = [
			'events'    => '',
			'tickets'   => '',
			'orders'    => '',
			'attendees' => '',
		];

		// Prepare the total number to import data.
		if ( ! empty( $_POST['total_number_to_import'] ) ) {
			$allowed_keys = array_keys( $total_number_to_import );

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			foreach ( $_POST['total_number_to_import'] as $key => $value ) {

				if ( ! in_array( $key, $allowed_keys, true ) ) {
					continue;
				}

				if ( is_numeric( $value ) ) {
					$total_number_to_import[ $key ] = absint( $value );
				}
			}
		}

		wp_send_json_success(
			[
				'importer'      => $importers[ $importer_slug ]->run( $total_number_to_import ),
				'importer_slug' => $importer_slug,
			]
		);
	}


	/**
	 * Get loaded importers/migrators.
	 *
	 * @since 3.3.0
	 * @since 3.6.0 Add ICS importer.
	 *
	 * @return Importer[]
	 */
	public function get_loaded_importers() {

		if ( ! empty( $this->loaded_importers ) ) {
			return $this->loaded_importers;
		}

		/**
		 * Filter the loaded importers.
		 *
		 * @since 3.6.0
		 *
		 * @param Importer[] $importers Loaded importers.
		 */
		$this->loaded_importers = apply_filters(
			'sugar_calendar_admin_tools_importers',
			[
				'sugar-calendar'      => new Importers\SugarCalendar(),
				'sugar-calendar-ics'  => new Importers\SugarCalendarIcs(),
				'the-events-calendar' => new Importers\TheEventCalendar(),
			]
		);

		return $this->loaded_importers;
	}
}
