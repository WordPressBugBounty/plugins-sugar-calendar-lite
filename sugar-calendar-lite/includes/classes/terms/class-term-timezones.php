<?php

namespace Sugar_Calendar;

/**
 * Term Timezones Class
 *
 * @since 2.1.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Use the Term Meta UI class
use JJJ\WP\Term\Meta\UI;
use Sugar_Calendar\Helpers\UI as PluginUI;

/**
 * Main Term Timezones class
 *
 * @since 2.1.0
 */
final class Term_Timezones extends UI {

	/**
	 * @var string Plugin version
	 */
	public $version = '2.1.0';

	/**
	 * @var string Database version
	 */
	public $db_version = 202010260001;

	/**
	 * @var string Database version
	 */
	public $db_version_key = 'wpdb_sc_term_timezone_version';

	/**
	 * @var string Metadata key
	 */
	public $meta_key = 'timezone';

	/**
	 * Hook into queries, admin screens, and more!
	 *
	 * @since 2.1.0
	 */
	public function __construct( $file = '' ) {

		// Bail if time zones are disabled
		if ( 'off' === Options::get( 'timezone_type', 'off' ) ) {
			return;
		}

		// Filter taxonomies
		add_filter( 'wp_term_timezone_get_taxonomies', [ $this, 'filter_taxonomies' ] );

		// Call the parent and pass the file
		parent::__construct( $file );

		// Set the URL
		$this->url = SC_PLUGIN_URL . 'assets/admin/';
	}

	/** Taxonomy **************************************************************/

	/**
	 * Setup the labels.
	 *
	 * @since 2.1.0
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function setup_labels() {

		// Setup the labels
		$this->labels = [
			'singular'    => esc_html__( 'Time Zone', 'sugar-calendar-lite' ),
			'plural'      => esc_html__( 'Time Zones', 'sugar-calendar-lite' ),
			'description' => esc_html__( 'Assign calendars a time zone for events to inherit their settings from.', 'sugar-calendar-lite' ),
		];
	}

	/**
	 * Only add time zones to taxonomies that support them.
	 *
	 * @since 2.1.0
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function filter_taxonomies( $args = [] ) {

		$args['timezones'] = true;

		return $args;
	}

	/** Assets ****************************************************************/

	/**
	 * Enqueue quick-edit JS
	 *
	 * @since 2.1.0
	 */
	public function enqueue_scripts() {

		// Version
		$ver = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG
			? time()
			: $this->db_version;

		// Enqueue fancy time zone; includes quick-edit
		wp_enqueue_script( 'term-timezone', $this->url . 'js/term-timezone.js', [], $ver, true );
	}

	/**
	 * Sanitizing the hex time zone
	 *
	 * @since 2.1.0
	 *
	 * @param string $data
	 *
	 * @return  string
	 */
	public function sanitize_callback( $data = '' ) {

		return sugar_calendar_sanitize_timezone( $data );
	}

	/**
	 * Add help tabs for `timezone` column
	 *
	 * @since 2.1.0
	 */
	public function help_tabs() {}

	/**
	 * Align custom `timezone` column
	 *
	 * @since 2.1.0
	 */
	public function admin_head() {}

	/** Markup ****************************************************************/

	/**
	 * Output the form field
	 *
	 * @since 2.0.0
	 *
	 * @param  $term
	 */
	protected function form_field( $term = '' ) {

		// Get the meta value
		$value = isset( $term->term_id )
			? $this->get_meta( $term->term_id )
			: '';

		PluginUI::timezone_dropdown_control(
			[
				'id'           => 'term-' . $this->meta_key,
				'name'         => 'term-' . $this->meta_key,
				'current'      => $value,

				// What time zones to allow
				'allow_empty'  => true,
				'allow_utc'    => true,
				'allow_manual' => false,
			],
			true
		);
	}

	/**
	 * Output the form field
	 *
	 * @since 2.0.0
	 *
	 * @param  $term
	 */
	protected function quick_edit_form_field() {

		sugar_calendar_timezone_dropdown( [
			'id'           => 'term-' . $this->meta_key,
			'name'         => 'term-' . $this->meta_key,
			'class'        => 'ptitle',
			'current'      => '',

			// What time zones to allow
			'allow_empty'  => true,
			'allow_utc'    => true,
			'allow_manual' => false,
		] );
	}

	/**
	 * Return the formatted output for the column row
	 *
	 * @since 2.1.0
	 *
	 * @param string $meta
	 */
	protected function format_output( $meta = '' ) {

		// Replace underscores with spaces
		$tz = str_replace( '_', '&nbsp;', $meta );

		// Get offset
		$offset = sugar_calendar_human_diff_timezone( $meta );

		// Escape & return
		return '<span class="term-timezone" data-timezone="' . esc_attr( $meta ) . '" title="' . esc_attr( $offset ) . '">' . esc_html( $tz ) . '</span>';
	}
}
