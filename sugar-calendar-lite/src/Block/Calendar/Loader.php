<?php

namespace Sugar_Calendar\Block\Calendar;

use Elementor\Plugin;
use Sugar_Calendar\Block\Calendar\CalendarView\Block;
use Sugar_Calendar\Helpers;
use Sugar_Calendar\Options;

class Loader {

	/**
	 * The Block key.
	 *
	 * @since 3.1.0
	 *
	 * @var string
	 */
	const KEY = 'calendar';

	/**
	 * Initialize the Block.
	 *
	 * @since 3.0.0
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.0.0
	 */
	private function hooks() {

		add_action( 'init', [ $this, 'register_block' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_script' ] );

		add_action( 'wp_ajax_sugar_calendar_event_popover', [ $this, 'ajax_event_popover' ] );
		add_action( 'wp_ajax_nopriv_sugar_calendar_event_popover', [ $this, 'ajax_event_popover' ] );

		add_action( 'wp_ajax_sugar_calendar_block_update', [ $this, 'ajax_update' ] );
		add_action( 'wp_ajax_nopriv_sugar_calendar_block_update', [ $this, 'ajax_update' ] );

		// Create a Sugar Calendar block category.
		if ( version_compare( get_bloginfo( 'version' ), '5.8', '>=' ) ) {
			add_filter( 'block_categories_all', [ $this, 'register_block_category' ] );
		} else {
			add_filter( 'block_categories', [ $this, 'register_block_category' ] );
		}
	}

	/**
	 * Register the scripts for the block.
	 *
	 * @since 3.0.0
	 * @since 3.1.2 Add new deps for the `sugar-calendar-js` script.
	 * @since 3.2.0 Loaded the assets only when the block is present.
	 * @since 3.7.0 Added filter for the localized script data.
	 */
	public function enqueue_script() {

		if ( ! $this->should_load_assets() ) {
			return;
		}

		$min = '.min';

		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$min = '';
		}

		$sugar_calendar_js_deps = [
			'jquery',
			'floating-ui-core',
			'floating-ui-dom',
			'bootstrap-datepicker',
		];

		if ( Options::get( 'timezone_convert' ) ) {
			$sugar_calendar_js_deps[] = 'sc-time-zones';
		}

		wp_register_script(
			'sugar-calendar-js',
			SC_PLUGIN_ASSETS_URL . "js/sugar-calendar{$min}.js",
			$sugar_calendar_js_deps,
			Helpers::get_asset_version()
		);

		wp_localize_script(
			'sugar-calendar-js',
			'sugar_calendar_obj',
			/**
			 * Filters the localized script data.
			 *
			 * @since 3.7.0
			 *
			 * @param array $data The localized script data.
			 */
			apply_filters(
				'sugar_calendar_block_calendar_loader_localized_script_data',
				[
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'sugar-calendar-block' ),
					'strings'  => [
						'events_on'  => sprintf(
							// phpcs:ignore Squiz.Commenting.BlockComment.NoEmptyLineBefore
							/*
							* translators: %s: Month name and the date. E.g January 1.
							*/
							esc_html__( 'Events on %s', 'sugar-calendar-lite' ),
							'[Month Date]'
						),
						'this_month' => esc_html__( 'This Month', 'sugar-calendar-lite' ),
						'this_week'  => esc_html__( 'This Week', 'sugar-calendar-lite' ),
						'today'      => esc_html__( 'Today', 'sugar-calendar-lite' ),
					],
					'settings' => [
						'sow' => absint( sc_get_week_start_day() ),
					],
				]
			)
		);
	}

	/**
	 * Check if the assets should be loaded.
	 *
	 * @since 3.2.0
	 * @since 3.2.1 Added the filter to determine if the assets should be loaded.
	 * @since 3.4.0 Removed the additional checks.
	 *
	 * @return bool
	 */
	private function should_load_assets() {

		/**
		 * Filter to determine if the assets should be loaded.
		 *
		 * @since 3.2.1
		 *
		 * @param bool $should_load_assets Whether the assets should be loaded.
		 */
		return apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_block_calendar_should_load_assets',
			true
		);
	}

	/**
	 * Register the block.
	 *
	 * @since 3.0.0
	 */
	public function register_block() {

		register_block_type(
			__DIR__ . '/build',
			[
				'render_callback' => [ $this, 'render' ],
			]
		);
	}

	/**
	 * Render the block.
	 *
	 * @since 3.0.0
	 *
	 * @param array $block_attributes Block attributes.
	 *
	 * @return string
	 */
	public function render( $block_attributes ) {

		if ( Helpers::is_on_admin_editor() ) {
			// We always want to show the events in editor.
			$should_not_load_events = false;
		} else {
			$should_not_load_events = boolval( Options::get( 'timezone_convert' ) );
		}

		// Default attributes.
		$default_attr = [
			'clientId'               => '',
			'display'                => 'month',
			'accentColor'            => '#5685BD',
			/*
			 * If visitor timezone conversion is enabled, we don't load the events the first time
			 * since we still need to get the visitor's timezone from the browser.
			 */
			'should_not_load_events' => $should_not_load_events,
			'groupEventsByWeek'      => true,
		];

		$attr  = wp_parse_args( $block_attributes, $default_attr );
		$block = new CalendarView\Block( $attr );

		switch ( $attr['display'] ) {
			case 'week':
				$view = new CalendarView\Week\Week( $block );
				break;

			case 'day':
				$view = new CalendarView\Day\Day( $block );
				break;

			default:
				$view = new CalendarView\Month\Month( $block );
				break;
		}

		$block->set_view( $view );

		return $block->get_html();
	}

	/**
	 * Enqueue the editor assets.
	 *
	 * @since 3.0.0
	 */
	public function enqueue_editor_assets() {

		$asset_file = include __DIR__ . '/build/index.asset.php';

		wp_register_script(
			'sugar-calendar-block',
			plugins_url( 'build/index.js', __FILE__ ),
			$asset_file['dependencies'],
			$asset_file['version']
		);
	}

	/**
	 * AJAX handler when the event popover is requested.
	 *
	 * This function is used to get the event description and image for the event popover.
	 *
	 * @since 3.0.0
	 * @since 3.5.0 Added `filter_event_more_string` filter.
	 */
	public function ajax_event_popover() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		check_ajax_referer( 'sugar-calendar-block', 'nonce' );

		if ( empty( $_POST['event_object_id'] ) ) {
			wp_send_json_error(
				[
					'message' => esc_attr__( 'Invalid request.', 'sugar-calendar-lite' ),
				]
			);
		}

		$event_object_id = absint( $_POST['event_object_id'] );

		if ( empty( $event_object_id ) ) {
			wp_send_json_error(
				[
					'message' => esc_attr__( 'Invalid request.', 'sugar-calendar-lite' ),
				]
			);
		}

		$event_image = get_the_post_thumbnail_url(
			$event_object_id,
			/**
			 * Filter the size of the event image in the popover in Calendar block.
			 *
			 * @since 3.5.0
			 *
			 * @param string $size Image size. Accepts any registered image size name, or an array of width and height values in pixels (in that order).
			 */
			apply_filters( 'sugar_calendar_block_calendar_loader_popover_image_size', 'medium' )
		);

		add_filter( 'excerpt_length', [ $this, 'filter_event_description' ], PHP_INT_MAX );
		add_filter( 'excerpt_more', [ $this, 'filter_event_more_string' ], PHP_INT_MAX );

		$event_description = wp_trim_excerpt( '', $event_object_id );

		remove_filter( 'excerpt_length', [ $this, 'filter_event_description' ], PHP_INT_MAX );
		remove_filter( 'excerpt_more', [ $this, 'filter_event_more_string' ], PHP_INT_MAX );

		wp_send_json_success(
			[
				'description' => esc_html( $event_description ),
				'image'       => empty( $event_image ) ? false : esc_url( $event_image ),
			]
		);
	}

	/**
	 * Filter the number of words in the event description.
	 *
	 * @since 3.0.0
	 * @since 3.5.0
	 *
	 * @param int $number Number of words passed to the filter.
	 *
	 * @return int
	 */
	public function filter_event_description( $number ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		/**
		 * Filter the number of words in the event description.
		 *
		 * @since 3.5.0
		 *
		 * @param int $number Number of words in the event description.
		 */
		return apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_block_popover_event_description_length',
			30
		);
	}

	/**
	 * Filters the string after the event description excerpt in popover.
	 *
	 * @since 3.5.0
	 *
	 * @param string $excerpt_more The string after the event description excerpt in popover.
	 *
	 * @return string
	 */
	public function filter_event_more_string( $excerpt_more ) {

		/**
		 * Filters the string after the event description excerpt in popover.
		 *
		 * @since 3.5.0
		 *
		 * @param string $excerpt_more The string after the event description excerpt in popover.
		 */
		return apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_block_popover_event_more_string',
			$excerpt_more
		);
	}

	/**
	 * AJAX handler when the block is updated/controlled in the front end.
	 *
	 * This function is used to update the calendar block content.
	 *
	 * @since 3.0.0
	 */
	public function ajax_update() {

		check_ajax_referer( 'sugar-calendar-block', 'nonce' );

		if ( empty( $_POST['calendar_block'] ) ) {
			wp_send_json_error(
				[
					'message' => esc_attr__( 'Invalid request.', 'sugar-calendar-lite' ),
				]
			);
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$clean_data = Helpers::clean_block_data_from_ajax( wp_unslash( $_POST['calendar_block'] ) );

		$block = new Block( $clean_data );

		switch ( $clean_data['display'] ) {
			case 'day':
				$cal = new CalendarView\Day\Day( $block );
				break;

			case 'week':
				$cal = new CalendarView\Week\Week( $block );
				break;

			default:
				$cal = new CalendarView\Month\Month( $block );
		}

		// Get the heading.
		$heading        = $cal->get_heading();
		$heading_mobile = $cal->get_heading( true );

		ob_start();

		if ( $clean_data['updateDisplay'] ) {
			$cal->render_base();
		} else {
			$cal->render();
		}

		$body = ob_get_clean();

		wp_send_json_success(
			[
				'body'              => $body,
				'heading'           => $heading,
				'heading_mobile'    => $heading_mobile,
				'is_update_display' => $clean_data['updateDisplay'],
				'control_labels'    => [
					'prev' => esc_attr( $block->get_previous_pagination_display() ),
					'next' => esc_attr( $block->get_next_pagination_display() ),
				],
				'date'              => [
					'day'   => $block->get_day_num_without_zero(),
					'month' => $block->get_month_num_without_zero(),
					'year'  => $block->get_year(),
				],
			]
		);
	}

	/**
	 * Register the Sugar Calendar block category.
	 *
	 * @since 3.0.0
	 *
	 * @param array $categories Block categories.
	 *
	 * @return array
	 */
	public function register_block_category( $categories ) {

		return array_merge(
			[
				[
					'slug'  => 'sugar-calendar',
					'title' => esc_html__( 'Sugar Calendar', 'sugar-calendar-lite' ),
				],
			],
			$categories
		);
	}
}
