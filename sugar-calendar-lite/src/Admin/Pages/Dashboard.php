<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Area;
use Sugar_Calendar\Admin\Dashboard\AccessResolver;
use Sugar_Calendar\Admin\Dashboard\Ajax;
use Sugar_Calendar\Admin\Dashboard\Assets;
use Sugar_Calendar\Admin\Dashboard\DateRange;
use Sugar_Calendar\Admin\Dashboard\StatCards;
use Sugar_Calendar\Admin\Dashboard\Stats;
use Sugar_Calendar\Admin\Dashboard\Templates;
use Sugar_Calendar\Admin\Dashboard\WidgetRegistry;
use Sugar_Calendar\Admin\Dashboard\Widgets\Activity;
use Sugar_Calendar\Admin\PageAbstract;
use Sugar_Calendar\Helpers\WP;

/**
 * Dashboard page.
 *
 * Pro subclasses it through `sugar_calendar_admin_area_pages`, so the seams are protected.
 *
 * @since 3.18.0
 */
class Dashboard extends PageAbstract {

	/**
	 * The widget registry's class; the Pro page names the one holding the Pro widgets.
	 *
	 * @since 3.18.0
	 */
	const REGISTRY_CLASS = WidgetRegistry::class;

	/**
	 * The widget registry, built once for the render, the chart data and the AJAX handler.
	 *
	 * @since 3.18.0
	 *
	 * @var WidgetRegistry|null
	 */
	private $registry;

	/**
	 * Access resolver shared by the render and the AJAX handler.
	 *
	 * @since 3.18.0
	 *
	 * @var AccessResolver|null
	 */
	private $access_resolver;

	/**
	 * Dashboard numbers, shared by the render and the AJAX handler.
	 *
	 * @since 3.18.0
	 *
	 * @var Stats|null
	 */
	private $stats;

	/**
	 * Page slug.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		return 'sugar-calendar-dashboard';
	}

	/**
	 * Page label.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Dashboard', 'sugar-calendar-lite' );
	}

	/**
	 * Submenu position.
	 *
	 * An insertion index, not a weight: `Area::admin_menu()` registers the page after
	 * the checklist, so 1 puts it directly after Events.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	public static function get_priority() {

		return 1;
	}

	/**
	 * Register page hooks.
	 *
	 * @since 3.18.0
	 */
	public function hooks() {

		add_action( 'in_admin_header', [ $this, 'display_admin_subheader' ] );
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );

		$this->get_ajax()->hooks();
	}

	/**
	 * The page's AJAX endpoints.
	 *
	 * @since 3.18.0
	 *
	 * @return Ajax
	 */
	protected function get_ajax(): Ajax {

		return new Ajax( $this->get_access_resolver(), $this->get_stats(), $this->get_registry() );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.18.0
	 */
	public function enqueue_assets() {

		( new Assets() )->enqueue( $this->get_script_dependencies(), $this->get_localized_data() );
	}

	/**
	 * Display the title bar.
	 *
	 * @since 3.18.0
	 */
	public function display_admin_subheader() {

		$view = [ 'label' => self::get_label() ] + DateRange::get_control_state( $this->get_initial_date_range() );

		echo Templates::get_html( 'subheader', $view ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes its own output.
	}

	/**
	 * Display the page.
	 *
	 * @since 3.18.0
	 */
	public function display() {

		$access   = $this->get_access_resolver()->get_context();
		$registry = $this->get_registry();
		$data     = $this->get_stats()->get_widget_data( $this->get_initial_date_range() );

		$registry->init( $access );

		$view = [
			'label'           => self::get_label(),
			'stat_cards'      => ( new StatCards() )->render( $data ),
			'main_widgets'    => $registry->render_column( 'main', $data, $access ),
			'sidebar_widgets' => $registry->render_column( 'sidebar', $data, $access ),
		];

		echo Templates::get_html( 'page', $view ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The templates escape their own output.

		/**
		 * Fires after the Dashboard page has rendered.
		 *
		 * @since 3.18.0
		 */
		do_action( 'sugar_calendar_admin_pages_dashboard_viewed' );
	}

	/**
	 * Page script dependencies.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	protected function get_script_dependencies(): array {

		return [ 'jquery', 'sugar-calendar-vendor-chart', 'sugar-calendar-flatpickr' ];
	}

	/**
	 * Data localized for the page script.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	protected function get_localized_data(): array {

		$activity = $this->get_registry()->get_widget( 'activity' );

		return [
			'ajax_url'        => WP::admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( Area::SLUG ),
			'page_id'         => 'dashboard',
			'today'           => wp_date( 'Y-m-d' ),
			'range'           => implode( DateRange::RANGE_SEPARATOR, $this->get_initial_date_range() ),
			'range_separator' => DateRange::RANGE_SEPARATOR,
			'display_format'  => DateRange::DISPLAY_FORMAT,
			'max_range_days'  => DateRange::MAX_RANGE_DAYS,
			'picker_locale'   => DateRange::get_picker_locale(),
			'series_meta'     => $activity instanceof Activity ? $activity->get_series_meta() : [],
			'modules'         => Assets::get_modules(),
			'i18n'            => Assets::get_i18n(),
		];
	}

	/**
	 * The range the first paint covers: a valid `?date=`, else the default preset.
	 *
	 * @since 3.18.0
	 *
	 * @return array `[ from, to ]`.
	 */
	protected function get_initial_date_range(): array {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page state; nothing changes as a result.
		$raw = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';

		return DateRange::parse_range( $raw ) ?? DateRange::get_preset_range( DateRange::DEFAULT_RANGE_DAYS );
	}

	/**
	 * Dashboard numbers, built once.
	 *
	 * @since 3.18.0
	 *
	 * @return Stats
	 */
	protected function get_stats(): Stats {

		if ( $this->stats === null ) {
			$this->stats = $this->create_stats();
		}

		return $this->stats;
	}

	/**
	 * A new Dashboard numbers instance; the Pro page counts more.
	 *
	 * @since 3.18.0
	 *
	 * @return Stats
	 */
	protected function create_stats(): Stats {

		return new Stats();
	}

	/**
	 * Access resolver, built once.
	 *
	 * @since 3.18.0
	 *
	 * @return AccessResolver
	 */
	protected function get_access_resolver(): AccessResolver {

		if ( $this->access_resolver === null ) {
			$this->access_resolver = new AccessResolver();
		}

		return $this->access_resolver;
	}

	/**
	 * The widget registry, from `REGISTRY_CLASS`, built once per request.
	 *
	 * @since 3.18.0
	 *
	 * @return WidgetRegistry
	 */
	protected function get_registry(): WidgetRegistry {

		if ( $this->registry === null ) {
			$registry_class = static::REGISTRY_CLASS;
			$this->registry = new $registry_class();
		}

		return $this->registry;
	}
}
