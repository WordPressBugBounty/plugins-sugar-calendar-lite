<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec;

use Sugar_Calendar\Admin\Tools\Importers;
use Sugar_Calendar\Admin\Tools\Importers\TheEventCalendar;
use Sugar_Calendar\Plugin;

/**
 * The admin notice that offers the TEC migration, or asks to finish it.
 *
 * @since 3.18.0
 */
class TecMigrationNotice {

	/**
	 * The TEC importer.
	 *
	 * @since 3.18.0
	 *
	 * @var TheEventCalendar
	 */
	private $tec;

	/**
	 * Constructor.
	 *
	 * @since 3.18.0
	 *
	 * @param TheEventCalendar $tec The TEC importer.
	 */
	public function __construct( TheEventCalendar $tec ) {

		$this->tec = $tec;
	}

	/**
	 * Show the notice on Sugar Calendar admin pages while a migration is possible and not dismissed.
	 *
	 * @since 3.18.0
	 */
	public function hooks() {

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

		if ( ! TheEventCalendar::is_migration_unfinished() || ! TheEventCalendar::is_migration_possible() ) {
			return;
		}

		// Check if the migration notice was dismissed before.
		$dismissed_migrations = json_decode( get_option( Importers::DISMISSED_MIGRATIONS_OPTION_KEY, false ) );

		if ( ! empty( $dismissed_migrations ) && is_array( $dismissed_migrations ) && in_array( $this->tec->get_slug(), $dismissed_migrations, true ) ) {
			return;
		}

		add_action( 'admin_notices', [ $this, 'show' ] );
	}

	/**
	 * Show the TEC migration notice.
	 *
	 * @since 3.18.0
	 */
	public function show() {
		?>
		<div class="notice sugar-calendar-notice notice-warning notice is-dismissible">
			<p>
			<?php
			echo wp_kses(
				$this->get_message(),
				[
					'a' => [
						'href' => [],
					],
				]
			);
			?>
			</p>
			<button id="sc-admin-tools-migrate-notice-dismiss" data-nonce="<?php echo esc_attr( wp_create_nonce( Importers::MIGRATION_NOTICE_DISMISS_NONCE_ACTION ) ); ?>"
				data-migration-slug="<?php echo esc_attr( $this->tec->get_slug() ); ?>" type="button" class="notice-dismiss">
				<span class="screen-reader-text">
					<?php esc_html_e( 'Dismiss this notice.', 'sugar-calendar-lite' ); ?>
				</span>
			</button>
		</div>
		<?php
	}

	/**
	 * The notice text: finish the migration, or start it.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	private function get_message() {

		$url = esc_url( $this->get_page_url() );

		if ( TheEventCalendar::get_tec_migration_option() === 'in_progress' ) {
			return sprintf(
				/* translators: %s: Sugar Calendar to TEC migration admin page. */
				__(
					'The Events Calendar to Sugar Calendar migration was not completed. Please complete the migration <a href="%s">here</a>.',
					'sugar-calendar-lite'
				),
				$url
			);
		}

		return sprintf(
			/* translators: %s: Sugar Calendar to TEC migration admin page. */
			__(
				'Sugar Calendar has detected The Events Calendar events on this site. Migrate them to Sugar Calendar with our <a href="%s">1-click migration tool</a>.',
				'sugar-calendar-lite'
			),
			$url
		);
	}

	/**
	 * The Migrate tab's URL for TEC.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	private function get_page_url() {

		return add_query_arg(
			[
				'section'  => 'migrate',
				'page'     => 'sc-tools',
				'importer' => 'the-events-calendar',
			],
			admin_url( 'admin.php' )
		);
	}
}
