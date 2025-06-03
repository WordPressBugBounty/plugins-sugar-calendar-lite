<?php

namespace Sugar_Calendar\Block\EventList;

use Sugar_Calendar\Block\EventList\EventListView\Block;
use Sugar_Calendar\Block\EventList\EventListView\GridView;
use Sugar_Calendar\Block\EventList\EventListView\ListView;
use Sugar_Calendar\Block\EventList\EventListView\PlainView;
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
	const KEY = 'list';

	/**
	 * Initialize the Block.
	 *
	 * @since 3.1.0
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.1.0
	 */
	public function hooks() {

		add_action( 'init', [ $this, 'register_block' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_script' ] );

		add_action( 'wp_ajax_sugar_calendar_event_list_block_update', [ $this, 'ajax_update' ] );
		add_action( 'wp_ajax_nopriv_sugar_calendar_event_list_block_update', [ $this, 'ajax_update' ] );
	}

	/**
	 * Register the Block.
	 *
	 * @since 3.1.0
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
	 * Render the Block.
	 *
	 * @since 3.1.0
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
			'groupEventsByWeek'      => true,
			'eventsPerPage'          => 10,
			'maximumEventsToShow'    => 10,
			'display'                => 'list',
			'accentColor'            => '#5685BD',
			'linksColor'             => '#000000D9',
			'allowUserChangeDisplay' => true,
			'showBlockHeader'        => true,
			'showFeaturedImages'     => true,
			'showDescriptions'       => true,
			'showDateCards'          => true,
			'showSearch'             => true,
			'showFilters'            => true,
			'imagePosition'          => 'right',
			'appearance'             => 'light',
			'should_not_load_events' => $should_not_load_events,
		];

		$attr = wp_parse_args( $block_attributes, $default_attr );

		$block = new Block( $attr );

		switch ( $attr['display'] ) {
			case GridView::DISPLAY_MODE:
				$view = new GridView( $block );
				break;

			case PlainView::DISPLAY_MODE:
				$view = new PlainView( $block );
				break;

			default:
				$view = new ListView( $block );
		}

		$block->set_view( $view );

		$html = $block->get_html();

		return $html;
	}

	/**
	 * Register the scripts for the block.
	 *
	 * @since 3.1.0
	 * @since 3.2.0 Loaded the assets only when the block is present.
	 */
	public function enqueue_script() {

		if ( ! $this->should_load_assets() ) {
			return;
		}

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		$sc_block_deps = [
			'jquery',
			'sc-frontend-blocks-common-js',
		];

		if ( Options::get( 'timezone_convert' ) ) {
			$sc_block_deps[] = 'sc-time-zones';
		}

		wp_register_script(
			'sc-frontend-blocks-event-list-js',
			SC_PLUGIN_ASSETS_URL . "js/frontend/blocks/event-list{$min}.js",
			$sc_block_deps,
			Helpers::get_asset_version()
		);

		wp_localize_script(
			'sc-frontend-blocks-event-list-js',
			'SCEventListBlock',
			[
				'strings' => [
					'no_events_criteria_based' => esc_html__( 'There are no events scheduled that match your criteria.', 'sugar-calendar-lite' ),
				],
			]
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
			'sugar_calendar_block_list_should_load_assets',
			true
		);
	}

	/**
	 * AJAX handler when the block is updated/controlled in the front end.
	 *
	 * This function is used to update the block content.
	 *
	 * @since 3.1.0
	 */
	public function ajax_update() {

		check_ajax_referer( 'sc-frontend-block', 'nonce' );

		if ( empty( $_POST['block'] ) ) {
			wp_send_json_error(
				[
					'message' => esc_attr__( 'Invalid request.', 'sugar-calendar-lite' ),
				]
			);
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$clean_data = Helpers::clean_block_data_from_ajax( wp_unslash( $_POST['block'] ) );
		$block      = new Block( $clean_data );

		/**
		 * @var AbstractView $view
		 */
		switch ( $block->get_display_mode() ) {
			case GridView::DISPLAY_MODE:
				$view = new GridView( $block );
				break;

			case PlainView::DISPLAY_MODE:
				$view = new PlainView( $block );
				break;

			default:
				$view = new ListView( $block );
		}

		$block->set_view( $view );

		ob_start();

		$view->render_base();

		$body = ob_get_clean();

		$response = [
			'body'              => $body,
			'heading'           => $view->get_heading(),
			'heading_mobile'    => $view->get_heading( true ),
			'is_update_display' => $clean_data['updateDisplay'],
			'date'              => [
				'day'   => $block->get_day_num_without_zero(),
				'month' => $block->get_month_num_without_zero(),
				'year'  => $block->get_year(),
			],
		];

		// Add pagination response.
		if (
			! $block->should_group_events_by_week()
		) {
			// Add the current page to the response.
			$response['paged'] = $block->get_paged();

			// Respond whether there are upcoming events.
			$response['enable_next']     = $block->has_upcoming_events();
			$response['enable_previous'] = $block->has_previous_events();
		}

		wp_send_json_success( $response );
	}
}
