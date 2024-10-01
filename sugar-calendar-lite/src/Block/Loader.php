<?php

namespace Sugar_Calendar\Block;

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
	}

	/**
	 * Enqueue scripts.
	 *
	 * @since 3.1.0
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
			SC_PLUGIN_VERSION
		);

		wp_localize_script(
			'sc-frontend-blocks-common-js',
			'sc_frontend_blocks_common_obj',
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'sc-frontend-block' ),
				'strings'  => [
					'this_week' => esc_html__( 'This Week', 'sugar-calendar' ),
				],
				'settings' => [
					'sow' => absint( sc_get_week_start_day() ),
				],
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
}
