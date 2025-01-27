<?php

namespace Sugar_Calendar;

/**
 * Term Colors Class
 *
 * @since 2.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Use the Term Meta UI class
use JJJ\WP\Term\Meta\UI;

/**
 * Main Term Colors class
 *
 * @since 2.0.0
 */
final class Term_Colors extends UI {

	/**
	 * @var string Plugin version
	 */
	public $version = '4.0.0';

	/**
	 * @var string Database version
	 */
	public $db_version = 202004020001;

	/**
	 * @var string Database version
	 */
	public $db_version_key = 'wpdb_sc_term_color_version';

	/**
	 * @var string Metadata key
	 */
	public $meta_key = 'color';

	/**
	 * Hook into queries, admin screens, and more!
	 *
	 * @since 2.0.0
	 */
	public function __construct( $file = '' ) {

		// Filter taxonomies
		add_filter( 'wp_term_color_get_taxonomies', [ $this, 'filter_taxonomies' ] );

		// Call the parent and pass the file
		parent::__construct( $file );

		// Set the URL
		$this->url = SC_PLUGIN_URL . 'assets/admin/';
	}

	/** Taxonomy **************************************************************/

	/**
	 * Setup the labels.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function setup_labels() {

		// Setup the labels
		$this->labels = [
			'singular'    => esc_html__( 'Color', 'sugar-calendar-lite' ),
			'plural'      => esc_html__( 'Colors', 'sugar-calendar-lite' ),
			'description' => esc_html__( 'Assign calendars a custom color to visually separate them from each-other.', 'sugar-calendar-lite' ),
		];
	}

	/**
	 * Only add colors to taxonomies that support them.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function filter_taxonomies( $args = [] ) {

		$args['colors'] = true;

		return $args;
	}

	/** Assets ****************************************************************/

	/**
	 * Enqueue quick-edit JS
	 *
	 * @since 2.0.0
	 */
	public function enqueue_scripts() {

		// Enqueue the color picker
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style( 'wp-color-picker' );

		// Enqueue fancy coloring; includes quick-edit
		wp_enqueue_script( 'term-color', $this->url . 'js/term-color.js', [ 'wp-color-picker' ], $this->db_version, true );
	}

	/**
	 * Sanitizing the hex color
	 *
	 * @since 2.0.0
	 *
	 * @param string $data
	 *
	 * @return  string
	 */
	public function sanitize_callback( $data = '' ) {

		return sugar_calendar_sanitize_hex_color( $data );
	}

	/**
	 * Add help tabs for `color` column
	 *
	 * @since 2.0.0
	 */
	public function help_tabs() {}

	/**
	 * Align custom `color` column
	 *
	 * @since 2.0.0
	 */
	public function admin_head() {}

	/**
	 * Return the formatted output for the column row
	 *
	 * @since 2.0.0
	 *
	 * @param string $meta
	 */
	protected function format_output( $meta = '' ) {

		return '<i class="term-color" data-color="' . esc_attr( $meta ) . '" style="background-color: ' . esc_attr( $meta ) . '"></i>';
	}
}
