<?php

namespace Sugar_Calendar\Block;

use Sugar_Calendar\Common\Editor;
use Sugar_Calendar\Helpers as BaseHelpers;

class Loader {

	/**
	 * Array containing the block classes.
	 *
	 * @since 3.1.0
	 *
	 * @var string[]
	 */
	private $blocks_classes = [
		Calendar\Loader::class,
		EventList\Loader::class,
	];

	/**
	 * Array containing the blocks.
	 *
	 * @since 3.1.0
	 *
	 * @var array
	 */
	private $blocks = [];

	/**
	 * Initialize the Blocks loader.
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

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );

		// Add translation support.
		add_filter( 'sugar_calendar_block_calendar_loader_localized_script_data', [ $this, 'add_translation_support' ] );
		add_filter( 'sugar_calendar_block_common_localized_script_data', [ $this, 'add_translation_support' ] );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @since 3.1.0
	 * @since 3.7.0 Added filter for the localized script data.
	 */
	public function enqueue_scripts() {

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script(
			'floating-ui-core',
			SC_PLUGIN_ASSETS_URL . 'lib/floating-ui/core-1.6.0.min.js',
			[],
			'1.6.0'
		);

		wp_register_script(
			'floating-ui-dom',
			SC_PLUGIN_ASSETS_URL . 'lib/floating-ui/dom-1.6.3.min.js',
			[],
			'1.6.3'
		);

		wp_register_script(
			'bootstrap-datepicker',
			SC_PLUGIN_ASSETS_URL . 'lib/bootstrap-datepicker/bootstrap-datepicker.min.js',
			[],
			'1.10.0'
		);

		wp_register_style(
			'bootstrap-datepicker',
			SC_PLUGIN_ASSETS_URL . 'lib/bootstrap-datepicker/bootstrap-datepicker.standalone.min.css',
			[],
			'1.10.0'
		);

		wp_register_script(
			'sc-frontend-blocks-common-js',
			SC_PLUGIN_ASSETS_URL . "js/frontend/blocks/common{$min}.js",
			[ 'jquery', 'floating-ui-core', 'floating-ui-dom', 'bootstrap-datepicker' ],
			BaseHelpers::get_asset_version()
		);

		wp_localize_script(
			'sc-frontend-blocks-common-js',
			'sc_frontend_blocks_common_obj',
			/**
			 * Filters the localized script data.
			 *
			 * @since 3.7.0
			 *
			 * @param array $data The localized script data.
			 */
			apply_filters(
				'sugar_calendar_block_common_localized_script_data',
				[
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'sc-frontend-block' ),
					'strings'  => [
						'this_week' => esc_html__( 'This Week', 'sugar-calendar-lite' ),
					],
					'settings' => [
						'sow' => absint( sc_get_week_start_day() ),
					],
				]
			)
		);
	}

	/**
	 * Enqueue the common editor assets.
	 *
	 * @since 3.3.0
	 */
	public function enqueue_editor_assets() {

		// Get dark mode setting.
		$display_mode = Editor\get_single_event_appearance_mode();

		/**
		 * Enqueue the common editor assets.
		 * This can be used by all the blocks since it's localized for the first one.
		 */
		wp_localize_script(
			'sugar-calendar-block-editor-script',
			'sugar_calendar_settings',
			[
				'appearance' => $display_mode,
			]
		);
	}

	/**
	 * Load blocks.
	 *
	 * @since 3.1.0
	 */
	public function get_blocks() {

		if ( ! empty( $this->blocks ) ) {
			return $this->blocks;
		}

		foreach ( $this->blocks_classes as $block_class ) {
			$block = new $block_class();

			$block->init();

			$this->blocks[ $block_class::KEY ] = $block;
		}

		return $this->blocks;
	}

	/**
	 * Add translation support based on WordPress locale.
	 *
	 * @since 3.7.0
	 *
	 * @param array $data The localized script data.
	 *
	 * @return array
	 */
	public function add_translation_support( $data ) {

		// Check if key 'settings' exists in the data array. Return early if it doesn't.
		if ( ! isset( $data['settings'] ) ) {
			return $data;
		}

		// Get WordPress locale.
		global $wp_locale;

		// Add current locale. Set in lowercase.
		$data['settings']['locale'] = strtolower( get_locale() );

		// Add days translation locale.
		$data['settings']['i18n']['days'] = array_values( $wp_locale->weekday );

		// Add daysShort translation locale.
		$data['settings']['i18n']['daysShort'] = array_values( $wp_locale->weekday_abbrev );

		// Add daysMin translation locale.
		$data['settings']['i18n']['daysMin'] = array_values( $wp_locale->weekday_initial );

		// Add months translation locale.
		$data['settings']['i18n']['months'] = array_values( $wp_locale->month );

		// Add monthsShort translation locale.
		$data['settings']['i18n']['monthsShort'] = array_values( $wp_locale->month_abbrev );

		return $data;
	}
}
