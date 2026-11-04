<?php

namespace Sugar_Calendar\Integrations\Polylang;

use Sugar_Calendar\Admin\Pages\CalendarEdit;
use Sugar_Calendar\Admin\Pages\CalendarNew;
use Sugar_Calendar\Event;
use WP_Post;
use WP_Term;

/**
 * Polylang on our admin list tables and calendar pages.
 *
 * @since 3.17.0
 */
class PolylangAdmin {

	/**
	 * Register hooks.
	 *
	 * @since 3.17.0
	 */
	public function hooks() {

		add_filter( 'sugar_calendar_manage_sc_event_posts_columns', [ $this, 'add_event_language_columns' ] );

		foreach ( $this->get_language_slugs() as $slug ) {
			add_filter( "sugar_calendar_admin_events_tables_base_language_{$slug}_contents", [ $this, 'render_language_column' ], 10, 2 );
		}

		// Move Polylang's language columns to the end of a list table.
		add_filter( 'manage_edit-' . sugar_calendar_get_calendar_taxonomy_id() . '_columns', [ $this, 'move_language_columns_last' ], 20 );

		// Calendars are added and edited on our own pages, not on WordPress's term screens.
		add_filter( 'pll_get_new_term_translation_link', [ $this, 'open_calendar_translation_in_calendar_page' ], 10, 4 );
		add_action( 'sugar_calendar_admin_pages_calendar_abstract_display_after_meta_boxes', [ $this, 'render_calendar_language_box' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_calendar_language_script' ] );

		$filters = PolylangApi::pll()->filters_term ?? null;

		// Polylang hooks the form fields our calendar options box prints; its box gets its own place instead.
		if ( $filters && $this->is_calendar_page() ) {
			remove_action( sugar_calendar_get_calendar_taxonomy_id() . '_add_form_fields', [ $filters, 'add_term_form' ] );
			remove_action( sugar_calendar_get_calendar_taxonomy_id() . '_edit_form_fields', [ $filters, 'edit_term_form' ] );
		}
	}

	/**
	 * Open the translation of a calendar on our New Calendar page.
	 *
	 * @since 3.17.0
	 *
	 * @param string $link     New translation link.
	 * @param object $language Language of the new translation.
	 * @param int    $term_id  Source term ID.
	 * @param string $taxonomy Taxonomy.
	 *
	 * @return string
	 */
	public function open_calendar_translation_in_calendar_page( $link, $language, $term_id, $taxonomy ) {

		if ( $taxonomy !== sugar_calendar_get_calendar_taxonomy_id() ) {
			return $link;
		}

		return add_query_arg(
			[
				'taxonomy' => $taxonomy,
				'from_tag' => $term_id,
				'new_lang' => $language->slug,
			],
			CalendarNew::get_url()
		);
	}

	/**
	 * Render Polylang's language fields below the calendar options, as WordPress's own term screens show them.
	 *
	 * @since 3.17.0
	 */
	public function render_calendar_language_box() {

		$filters = PolylangApi::pll()->filters_term ?? null;

		if ( ! $filters ) {
			return;
		}

		$this->prepare_calendar_language_box();

		echo '<h2>Polylang</h2>';

		$term = get_term( isset( $_GET['calendar_id'] ) ? absint( $_GET['calendar_id'] ) : 0, sugar_calendar_get_calendar_taxonomy_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $term instanceof WP_Term ) {
			$filters->add_term_form();

			return;
		}

		// The edit form is made of table rows.
		echo '<table class="form-table"><tbody>';

		$filters->edit_term_form( $term );

		echo '</tbody></table>';
	}

	/**
	 * Give Polylang's language box on our calendar pages what it reads on WordPress's term screens.
	 *
	 * @since 3.17.0
	 */
	private function prepare_calendar_language_box() {

		// Polylang reads the listed post type, and on new terms the taxonomy from the URL; our pages set neither.
		if ( empty( $GLOBALS['post_type'] ) ) {
			$GLOBALS['post_type'] = sugar_calendar_get_event_post_type_id(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		if ( empty( $_GET['taxonomy'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$_GET['taxonomy'] = sugar_calendar_get_calendar_taxonomy_id();
		}
	}

	/**
	 * Load Polylang's language box script on our calendar pages, e.g. to search a calendar to link as a translation.
	 *
	 * Polylang loads it only on WordPress's own term screens.
	 *
	 * @since 3.17.0
	 */
	public function enqueue_calendar_language_script() {

		if ( ! $this->is_calendar_page() || ! defined( 'POLYLANG_ROOT_FILE' ) ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script(
			'pll_term',
			plugins_url( "/js/build/term{$suffix}.js", constant( 'POLYLANG_ROOT_FILE' ) ),
			[ 'jquery', 'wp-ajax-response', 'jquery-ui-autocomplete' ],
			constant( 'POLYLANG_VERSION' ),
			true
		);

		// The script sends the listed post type, which WordPress only sets on its own screens.
		wp_add_inline_script( 'pll_term', "typenow = typenow || '" . esc_js( sugar_calendar_get_event_post_type_id() ) . "';", 'before' );
	}

	/**
	 * Whether this is our New or Edit Calendar page.
	 *
	 * @since 3.17.0
	 *
	 * @return bool
	 */
	private function is_calendar_page() {

		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return in_array( $page, [ CalendarNew::get_slug(), CalendarEdit::get_slug() ], true );
	}

	/**
	 * Move Polylang's language columns to the end of a list table.
	 *
	 * @since 3.17.0
	 *
	 * @param array $columns Column ID => label.
	 *
	 * @return array
	 */
	public function move_language_columns_last( $columns ) {

		$languages = [];

		foreach ( (array) $columns as $key => $label ) {
			if ( strpos( $key, 'language_' ) === 0 ) {
				$languages[ $key ] = $label;

				unset( $columns[ $key ] );
			}
		}

		return array_merge( $columns, $languages );
	}

	/**
	 * Add Polylang's language columns to the Events list.
	 *
	 * @since 3.17.0
	 *
	 * @param array $columns Column ID => label.
	 *
	 * @return array
	 */
	public function add_event_language_columns( $columns ) {

		return $this->add_language_columns( $columns, sugar_calendar_get_event_post_type_id() );
	}

	/**
	 * Render a language column cell of our list tables with Polylang's own markup.
	 *
	 * @since 3.17.0
	 *
	 * @param string        $content Default cell content.
	 * @param WP_Post|Event $item    Row post, or row event in the Events list.
	 *
	 * @return string
	 */
	public function render_language_column( $content, $item ) {

		$columns_ui = $this->get_columns_ui();
		$post_id    = $this->get_item_post_id( $item );

		// The column name only lives in the filter name, e.g. `…_language_en_contents`.
		if ( ! $columns_ui || ! $post_id || ! preg_match( '/_(language_[a-z0-9_-]+)_contents$/', current_filter(), $matches ) ) {
			return $content;
		}

		ob_start();

		$columns_ui->post_column( $matches[1], $post_id );

		return (string) ob_get_clean();
	}

	/**
	 * Add Polylang's language columns to one of our list tables.
	 *
	 * @since 3.17.0
	 *
	 * @param array  $columns   Column ID => label.
	 * @param string $post_type Post type listed by the table.
	 *
	 * @return array
	 */
	protected function add_language_columns( $columns, $post_type ) {

		$columns_ui = $this->get_columns_ui();

		if ( ! $columns_ui || ! pll_is_translated_post_type( $post_type ) ) {
			return $columns;
		}

		return $columns_ui->add_post_column( $columns );
	}

	/**
	 * Get the language slugs Polylang has set up.
	 *
	 * @since 3.17.0
	 *
	 * @return string[]
	 */
	protected function get_language_slugs() {

		return pll_languages_list();
	}

	/**
	 * Get the post ID behind a list table row.
	 *
	 * @since 3.17.0
	 *
	 * @param mixed $item Row post or event.
	 *
	 * @return int
	 */
	private function get_item_post_id( $item ) {

		if ( $item instanceof Event ) {
			return (int) $item->object_id;
		}

		return $item instanceof WP_Post ? $item->ID : 0;
	}

	/**
	 * Get Polylang's admin columns object, which only exists in the admin.
	 *
	 * @since 3.17.0
	 *
	 * @return object|null
	 */
	private function get_columns_ui() {

		return PolylangApi::pll()->filters_columns ?? null;
	}
}
