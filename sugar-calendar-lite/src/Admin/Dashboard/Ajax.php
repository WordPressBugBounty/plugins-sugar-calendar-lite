<?php

namespace Sugar_Calendar\Admin\Dashboard;

use Sugar_Calendar\Admin\Dashboard\Widgets\Activity;
use Sugar_Calendar\Admin\Dashboard\Widgets\GrowthTools;
use Sugar_Calendar\Admin\Dashboard\Widgets\Integrations;
use Sugar_Calendar\Integrations\OAuthRelay\Credits\CreditsService;
use Sugar_Calendar\PartnerPlugins\Catalog;
use Sugar_Calendar\PartnerPlugins\Installer;
use Sugar_Calendar\SetupChecklist\PartnerActions;
use Sugar_Calendar\SetupChecklist\Request;

/**
 * Dashboard AJAX endpoints: range refresh, credits refresh, Growth Tools install, card dismissal, widget settings.
 *
 * @since 3.18.0
 */
class Ajax {

	/**
	 * Access resolver.
	 *
	 * @since 3.18.0
	 *
	 * @var AccessResolver
	 */
	private $access_resolver;

	/**
	 * Dashboard numbers.
	 *
	 * @since 3.18.0
	 *
	 * @var Stats
	 */
	private $stats;

	/**
	 * The page's widgets.
	 *
	 * @since 3.18.0
	 *
	 * @var WidgetRegistry
	 */
	protected $registry;

	/**
	 * Constructor.
	 *
	 * @since 3.18.0
	 *
	 * @param AccessResolver $access_resolver Access resolver.
	 * @param Stats          $stats           Dashboard numbers.
	 * @param WidgetRegistry $registry        The page's widgets.
	 */
	public function __construct( AccessResolver $access_resolver, Stats $stats, WidgetRegistry $registry ) {

		$this->access_resolver = $access_resolver;
		$this->stats           = $stats;
		$this->registry        = $registry;
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.18.0
	 */
	public function hooks(): void {

		add_action( 'sugar_calendar_ajax_dashboard_get_stats', [ $this, 'get_stats' ] );
		add_action( 'sugar_calendar_ajax_dashboard_refresh_credits', [ $this, 'refresh_credits' ] );
		add_action( 'sugar_calendar_ajax_dashboard_growth_tool', [ $this, 'install_growth_tool' ] );
		add_action( 'sugar_calendar_ajax_dashboard_dismiss', [ $this, 'dismiss' ] );
		add_action( 'sugar_calendar_ajax_dashboard_save_widget_settings', [ $this, 'save_widget_settings' ] );
	}

	/**
	 * Re-render the range-dependent pieces for a new date range.
	 *
	 * @since 3.18.0
	 */
	public function get_stats(): void {

		$access = $this->validate_request();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Area::handle_ajax() checked the nonce.
		$raw   = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$range = DateRange::parse_range( $raw );

		if ( $range === null ) {
			$this->send_error( 'range', __( 'Invalid date range.', 'sugar-calendar-lite' ) );
		}

		$data = $this->stats->get_widget_data( $range );

		// From the registry, so on Pro the Pro widget's lists answer.
		$activity = $this->registry->get_widget( 'activity' );

		wp_send_json_success(
			[
				'series'          => $data['series'],
				'stat_cards_html' => ( new StatCards() )->render( $data ),
				'activity_html'   => $activity instanceof Activity ? $activity->render_body( 'data', $data, $access ) : '',
			]
		);
	}

	/**
	 * Refresh an out-of-date credits read and send back what it redraws: the usage indicator, and the
	 * integration cards when the out-of-credits answer changed, so their badges match it.
	 *
	 * Never forced: the relay is reached only when the stored read is out of date.
	 *
	 * @since 3.18.0
	 */
	public function refresh_credits(): void {

		$this->validate_request();

		// From the registry, so on Pro the Pro widget's cards answer.
		$widget = $this->registry->get_widget( 'integrations' );

		if ( ! $widget instanceof Integrations ) {
			$this->send_error( 'invalid', __( 'Invalid request.', 'sugar-calendar-lite' ) );

			return;
		}

		$service = new CreditsService();
		$before  = $service->get_cached_credits();
		$was_out = $service->is_out_of_credits();
		$after   = $service->get_credits();

		wp_send_json_success(
			[ 'refreshed' => $after !== null && $after !== $before ]
			+ $widget->get_credits_redraw( $after, $service->is_out_of_credits() !== $was_out )
		);
	}

	/**
	 * Dismiss an inline card for the current user until its condition ends and recurs.
	 *
	 * @since 3.18.0
	 */
	public function dismiss(): void {

		$access = $this->validate_request();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Area::handle_ajax() checked the nonce.
		$card = isset( $_POST['card'] ) ? sanitize_key( wp_unslash( $_POST['card'] ) ) : '';

		if ( ! in_array( $card, ActivityCards::CARD_IDS, true ) ) {
			$this->send_error( 'invalid', __( 'Invalid request.', 'sugar-calendar-lite' ) );
		}

		$episodes = new CardEpisodes();

		( new UserPreferences( $access->get_user_id() ) )->dismiss( $card, $episodes->get_marker( $card ) );

		$episodes->open( $card );

		/** This action is documented in src/Admin/Dashboard/Ajax.php */
		do_action( 'sugar_calendar_admin_dashboard_ajax_action', 'card_dismissed', $card );

		wp_send_json_success();
	}

	/**
	 * Remember a widget's settings for the current user, such as the Activity tab or Orders metric.
	 *
	 * @since 3.18.0
	 */
	public function save_widget_settings(): void {

		$access = $this->validate_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Area::handle_ajax() checked the nonce.
		$widget_id = isset( $_POST['widget'] ) ? sanitize_key( wp_unslash( $_POST['widget'] ) ) : '';
		$posted    = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? map_deep( wp_unslash( $_POST['settings'] ), 'sanitize_key' ) : [];
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$widget   = $this->registry->get_widget( $widget_id );
		$settings = $widget === null ? [] : $widget->sanitize_settings( $posted );

		// Every posted setting must be one the widget remembers, with a value it offers.
		if ( $widget === null || empty( $posted ) || count( $settings ) !== count( $posted ) ) {
			$this->send_error( 'invalid', __( 'Invalid request.', 'sugar-calendar-lite' ) );
		}

		( new UserPreferences( $access->get_user_id() ) )->save_widget_settings( $widget_id, $settings );

		wp_send_json_success();
	}

	/**
	 * Install or activate one of Growth Tools' sister plugins.
	 *
	 * The Setup Checklist's handler reads the key, checks it against the checklist's list and runs
	 * the installer; the package and plugin file come from `Catalog`, never the request. Unlike the
	 * checklist's own endpoint, this one still answers once the checklist is dismissed.
	 *
	 * @since 3.18.0
	 */
	public function install_growth_tool(): void {

		$this->validate_request();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Area::handle_ajax() checked the nonce.
		$key    = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
		$redraw = function () use ( $key ): array {
			$widget = $this->registry->get_widget( 'growth_tools' );
			$tile   = $widget instanceof GrowthTools ? $widget->get_tile_html( $key ) : '';

			return $tile === '' ? [] : [ 'tile_html' => $tile ];
		};
		$result = ( new PartnerActions( new Catalog(), new Installer(), new Request() ) )->install_growth_tool();

		if ( is_wp_error( $result ) ) {
			$this->send_error( 'growth_tool', $result->get_error_message(), $redraw() );
		}

		/**
		 * Fires after a successful Dashboard action.
		 *
		 * @since 3.18.0
		 *
		 * @param string $action The action: `growth_tool`, `addon` or `card_dismissed`.
		 * @param string $item   The tool key, add-on slug or card id.
		 */
		do_action( 'sugar_calendar_admin_dashboard_ajax_action', 'growth_tool', $key );

		$this->send_install_result( $result, $redraw );
	}

	/**
	 * Send an install result with the item redrawn from its state now.
	 *
	 * @since 3.18.0
	 *
	 * @param array    $result The `ActionResult` fields.
	 * @param callable $redraw Returns the redrawn item as response fields.
	 */
	protected function send_install_result( array $result, callable $redraw ): void {

		wp_send_json_success( array_merge( $result, $redraw() ) );
	}

	/**
	 * Reject users who cannot use the Dashboard.
	 *
	 * @since 3.18.0
	 *
	 * @return AccessContext
	 */
	protected function validate_request(): AccessContext {

		$access = $this->access_resolver->get_context();

		// A 403, like a stale nonce's from Area::handle_ajax(), so the page script reads both as "reload first".
		if ( ! $access->can_manage() ) {
			$this->send_error( 'cap', __( 'You do not have permission to do this.', 'sugar-calendar-lite' ), [], 403 );
		}

		return $access;
	}

	/**
	 * Send a JSON error: `code`, `message`, and any extra fields the page script reads.
	 *
	 * @since 3.18.0
	 *
	 * @param string   $code    Error code: `range`, `cap`, `growth_tool` or `invalid`; the Pro subclass adds `addon`.
	 * @param string   $message Message.
	 * @param array    $data    Extra fields, such as a redrawn `tile_html` or `card_html`.
	 * @param int|null $status  HTTP status; null sends 200.
	 */
	protected function send_error( string $code, string $message, array $data = [], ?int $status = null ): void {

		wp_send_json_error(
			array_merge(
				$data,
				[
					'code'    => $code,
					'message' => $message,
				]
			),
			$status
		);
	}
}
