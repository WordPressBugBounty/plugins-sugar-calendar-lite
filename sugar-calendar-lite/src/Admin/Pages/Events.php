<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Area;
use Sugar_Calendar\Admin\Events\Tables\Basic;
use Sugar_Calendar\Admin\Events\Tables\Day;
use Sugar_Calendar\Admin\Events\Tables\Month;
use Sugar_Calendar\Admin\Events\Tables\Week;
use Sugar_Calendar\Admin\PageAbstract;
use Sugar_Calendar\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Plugin;
use function Sugar_Calendar\Admin\Screen\Options\get_defaults;

/**
 * Events page.
 *
 * @since 3.0.0
 */
class Events extends PageAbstract {

	/**
	 * Page slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		return 'sugar-calendar';
	}

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Events', 'sugar-calendar-lite' );
	}

	/**
	 * Page menu priority.
	 *
	 * @since 3.0.0
	 *
	 * @return int
	 */
	public static function get_priority() {

		return 0;
	}

	/**
	 * Page capability.
	 *
	 * @since 3.0.1
	 *
	 * @return string
	 */
	public static function get_capability() {

		/**
		 * Filters the capability required to view the Events page.
		 *
		 * @since 3.0.1
		 *
		 * @param string $capability Capability required to view the calendars page.
		 */
		return apply_filters( 'sugar_calendar_admin_pages_events_get_capability', 'edit_events' );
	}

	/**
	 * Register page hooks.
	 *
	 * @since 3.0.0
	 */
	public function hooks() {

		$page_hook = get_plugin_page_hook( self::get_slug(), Area::SLUG );

		add_action( "load-{$page_hook}", [ $this, 'get_list_table' ] );
		add_filter( 'screen_options_show_screen', '__return_false' );
		add_action( 'sugar_calendar_ajax_update_hidden_columns', [ $this, 'ajax_update_hidden_columns' ] );
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_head', [ $this, 'output_grid_layout' ] );
		add_action( 'admin_footer', [ $this, 'output_tooltips' ] );
	}

	/**
	 * Display page.
	 *
	 * @since 3.0.0
	 */
	public function display() {

		// Add new event link url.
		$add_new_url = add_query_arg( [ 'post_type' => 'sc_event' ], WP::admin_url( 'post-new.php' ) );

		$table = $this->get_list_table();

		// Query for calendar content.
		$table->prepare_items();
		?>

        <div class="sugar-calendar-admin-subheader">
            <h4><?php esc_html_e( 'Events', 'sugar-calendar-lite' ); ?></h4>

			<?php
			UI::button(
				[
					'text'  => esc_html__( 'Add New Event', 'sugar-calendar-lite' ),
					'size'  => 'sm',
					'class' => 'sugar-calendar-btn-new-item',
					'link'  => $add_new_url,
				]
			);
			?>

            <div class="sugar-calendar-admin-subheader-tools">

				<?php
				$table->event_filters();
				$table->options_menu();
				?>

            </div>
        </div>

        <div id="sugar-calendar-events" class="wrap sugar-calendar-admin-wrap">

            <div class="sugar-calendar-admin-content">

                <h1 class="screen-reader-text"><?php esc_html_e( 'Events', 'sugar-calendar-lite' ); ?></h1>

				<?php
				/**
				 * Runs before the page content is displayed.
				 *
				 * @since 3.0.0
				 */
				do_action( 'sugar_calendar_admin_page_before' ); //phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
				?>

				<?php $table->views(); ?>

                <form id="posts-filter" method="get">

                    <input type="hidden" name="page" value="sugar-calendar"/>

					<?php $table->display(); ?>

                </form>

				<?php
				/**
				 * Runs after the page content is displayed.
				 *
				 * @since 3.0.0
				 */
				do_action( 'sugar_calendar_admin_page_after' ); //phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
				?>
            </div>
        </div>

		<?php
	}

	/**
	 * Get the current event table.
	 *
	 * @since 3.0.0
	 *
	 * @return mixed|Basic|Day|Month|Week
	 */
	public function get_list_table() {

		static $table;

		if ( $table === null ) {

			switch ( sugar_calendar_get_admin_view_mode() ) {
				case 'day':
					$table = new Day();
					break;

				case 'week':
					$table = new Week();
					break;

				case 'month':
					$table = new Month();
					break;

				case 'list':
				default:
					$table = new Basic();
					break;
			}
		}

		return $table;
	}

	/**
	 * Handle POST requests.
	 *
	 * @since 3.0.0
	 *
	 * @param array $post_data Post data.
	 */
	public function handle_post( $post_data = [] ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded,Generic.Metrics.NestingLevel.MaxExceeded

		$user = wp_get_current_user();

		if ( ! $user ) {
			return;
		}

		$settings = [
			'events_max_num',
			'start_of_week',
		];

		$defaults = get_defaults();

		foreach ( $settings as $key ) {

			if ( isset( $post_data[ $key ] ) ) {
				$value = $post_data[ $key ];

				switch ( $key ) {
					case 'events_max_num':
						$value = max( 0, intval( $value ) );
						break;

					case 'start_of_week':
						$value = in_array( intval( $value ), range( 0, 6 ) ) ? intval( $value ) : 0;
						break;
				}
			} else {
				$value = $defaults[ $key ];
			}

			sugar_calendar_set_user_preference( $key, $value );
		}

		if ( isset( $post_data['columns'] ) && ! is_array( $post_data['columns'] ) ) {
			return;
		}

		$table          = $this->get_list_table();
		$columns        = array_keys( $table->get_columns() );
		$hidden_columns = array_diff( $columns, $post_data['columns'] ?? [] );

		$this->update_hidden_columns( $user, $hidden_columns );
	}

	/**
	 * Update hidden columns AJAX handler.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function ajax_update_hidden_columns() {

		$user = wp_get_current_user();

		if ( ! $user ) {
			wp_send_json_error();
		}

		if ( isset( $_POST['columns'] ) && ! is_array( $_POST['columns'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_send_json_error();
		}

		$this->update_hidden_columns( $user, $_POST['columns'] ?? [] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Update hidden columns.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_User $user    Current user.
	 * @param array   $columns Array of columns.
	 *
	 * @return void
	 */
	private function update_hidden_columns( $user, $columns = [] ) {

		$table           = $this->get_list_table();
		$allowed_columns = array_keys( $table->get_columns() );
		$hidden_columns  = array_intersect( $columns, $allowed_columns );
		$screen_id       = 'toplevel_page_sugar-calendar';

		update_user_meta( $user->ID, "manage{$screen_id}columnshidden", $hidden_columns );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_register_style(
			'sugar-calendar-vendor-tippy',
			SC_PLUGIN_ASSETS_URL . 'lib/tippy/tippy.min.css',
			[],
			'2.11.8'
		);

		wp_register_script(
			'sugar-calendar-vendor-popper',
			SC_PLUGIN_ASSETS_URL . 'lib/tippy/popper.min.js',
			[],
			'2.11.8'
		);

		wp_register_script(
			'sugar-calendar-vendor-tippy',
			SC_PLUGIN_ASSETS_URL . 'lib/tippy/tippy-bundle.umd.min.js',
			[ 'sugar-calendar-vendor-popper' ],
			'2.11.8'
		);

		wp_enqueue_style(
			'sugar-calendar-admin-events',
			SC_PLUGIN_ASSETS_URL . 'css/admin-events' . WP::asset_min() . '.css',
			[ 'sugar-calendar-vendor-tippy' ],
			Helpers::get_asset_version()
		);

		wp_enqueue_script(
			'sugar-calendar-admin-events',
			SC_PLUGIN_ASSETS_URL . 'js/admin-events' . WP::asset_min() . '.js',
			[ 'jquery', 'sugar-calendar-vendor-tippy', 'sugar-calendar-vendor-choices', 'underscore' ],
			Helpers::get_asset_version(),
			true
		);

		/**
		 * Filters the localize script for the events page.
		 *
		 * @since 3.7.0
		 *
		 * @param array $localize_script Localize script.
		 */
		$admin_events_localize_script = apply_filters(
			'sugar_calendar_admin_pages_events_localize_script',
			[
				'ajax_url' => Plugin::instance()->get_admin()->ajax_url(),
			]
		);

		wp_localize_script(
			'sugar-calendar-admin-events',
			'sugar_calendar_admin_events',
			$admin_events_localize_script
		);
	}

	/**
	 * Output the current event table grid layout rules.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function output_grid_layout() {

		$table = $this->get_list_table();

		$table->output_grid_layout();
	}

	/**
	 * Output event tooltips.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function output_tooltips() {

		$table = $this->get_list_table();

		foreach ( $table->pointers as $event_id => $tooltip_content ) :
			?>

            <template id="sugar-calendar-tooltip-<?php echo esc_attr( $event_id ); ?>">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $tooltip_content;
				?>
            </template>

		<?php
		endforeach;
	}
}
