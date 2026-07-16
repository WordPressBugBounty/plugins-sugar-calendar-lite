<?php

namespace Sugar_Calendar\Integrations\Wpml;

use Sugar_Calendar\Event;
use WP_Post;
use WPML_Post_Status_Display;

/**
 * WPML Admin UI Integration.
 *
 * Handles language columns, translation flags, and admin styles
 * for the Sugar Calendar events list table.
 *
 * @since 3.12.0
 */
class WpmlAdmin {

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 */
	public function hooks() {

		// Admin: inject a WPML languages column into the custom Events list table.
		add_filter( 'sugar_calendar_manage_sc_event_posts_columns', [ $this, 'add_wpml_flags_column' ], 10 );
		add_filter( 'sugar_calendar_admin_events_tables_base_icl_translations_contents', [ $this, 'render_wpml_flags_cell' ], 10, 2 );

		// Admin: ensure WPML icon fonts/styles load on Sugar Calendar admin page.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_wpml_admin_styles_for_sc' ] );

		// Support for other plugins to add WPML support to Sugar Calendar admin pages.
		add_action( 'admin_init', [ $this, 'enable_wpml_admin_pages_support' ], 0 );

		// Adjust calendar color term ID to use original (default-language) term.
		add_filter( 'sugar_calendar_get_calendar_color_term_id', [ $this, 'filter_calendar_color_term_id' ] );
	}

	/**
	 * Enable WPML admin pages support.
	 *
	 * @since 3.12.0
	 */
	public function enable_wpml_admin_pages_support() {

		/**
		 * Support actions for other plugins to add WPML support.
		 *
		 * @since 3.12.0
		 *
		 * @param WpmlAdmin $this The WpmlAdmin instance.
		 *
		 * @return array
		 */
		do_action( 'sugar_calendar_integrations_wpml_admin_pages', $this );
	}

	/**
	 * Enqueue WPML admin styles (icon font, base styles) on the Sugar Calendar page
	 * so translation status icons render correctly.
	 *
	 * @since 3.12.0
	 *
	 * @param string $hook Current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_wpml_admin_styles_for_sc( $hook ) {

		$allowed_pages = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_integrations_wpml_admin_script_allowed_pages',
			[ 'sugar-calendar' ]
		);

		// Only run in admin and on our Events or Venues page.
		if ( ! is_admin() || empty( $_GET['page'] ) || ! in_array( sanitize_key( wp_unslash( $_GET['page'] ) ), $allowed_pages, true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Register styles if needed (mirrors WPML_Admin_Scripts_Setup::register_styles()).
		if ( ! wp_style_is( 'otgs-dialogs', 'registered' ) ) {
			wp_register_style( 'otgs-dialogs', ICL_PLUGIN_URL . '/res/css/otgs-dialogs.css', [ 'wp-jquery-ui-dialog' ], ICL_SITEPRESS_VERSION );
		}
		if ( ! wp_style_is( 'wpml-dialog', 'registered' ) ) {
			wp_register_style( 'wpml-dialog', ICL_PLUGIN_URL . '/res/css/dialog.css', [ 'otgs-dialogs' ], ICL_SITEPRESS_VERSION );
		}

		// Enqueue icon font and base styles used by WPML icons.
		if ( ! wp_style_is( 'sitepress-style', 'enqueued' ) ) {
			wp_enqueue_style( 'sitepress-style', ICL_PLUGIN_URL . '/res/css/style.css', [], ICL_SITEPRESS_VERSION );
		}
		if ( ! wp_style_is( 'wpml-dialog', 'enqueued' ) ) {
			wp_enqueue_style( 'wpml-dialog' );
		}
		if ( ! wp_style_is( 'otgs-icons', 'enqueued' ) ) {
			wp_enqueue_style( 'otgs-icons' );
		}
	}

	/**
	 * Add a WPML-compatible languages column to the custom list table.
	 *
	 * @since 3.12.0
	 *
	 * @param array $columns Existing columns.
	 *
	 * @return array
	 */
	public function add_wpml_flags_column( $columns ) {

		// Insert after the Title column for visibility, mirroring WPML behavior.
		$new = [];

		foreach ( $columns as $key => $label ) {

			$new[ $key ] = $label;

			if ( $key === 'title' && ! isset( $new['icl_translations'] ) ) {
				$new['icl_translations'] = $this->get_wpml_flags_column_header();
			}
		}

		return $new;
	}

	/**
	 * Build the WPML flags header HTML (like WPML_Custom_Columns::get_flags_column).
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	private function get_wpml_flags_column_header() {

		global $sitepress;

		if ( empty( $sitepress ) || ! method_exists( $sitepress, 'get_active_languages' ) ) {
			return esc_html__( 'Languages', 'sugar-calendar-lite' );
		}

		$active_languages = $sitepress->get_active_languages();

		if ( empty( $active_languages ) || count( $active_languages ) <= 1 ) {
			return '';
		}

		$current_language = $sitepress->get_current_language();

		unset( $active_languages[ $current_language ] );

		if ( empty( $active_languages ) ) {
			return '';
		}

		$flags_column = '<span class="screen-reader-text">' . esc_html__( 'Languages', 'sugar-calendar-lite' ) . '</span>';

		foreach ( $active_languages as $language_data ) {

			$code = isset( $language_data['code'] ) ? $language_data['code'] : '';

			if ( $code === '' ) {
				continue;
			}

			$url = method_exists( $sitepress, 'get_flag_url' ) ? $sitepress->get_flag_url( $code ) : '';

			if ( $url !== '' ) {
				$flags_column .= '<img src="' . esc_url( $url ) . '" width="18" height="12" alt="' . esc_attr( $language_data['display_name'] ) . '" title="' . esc_attr( $language_data['display_name'] ) . '" style="margin:2px" />';
			}
		}

		return $flags_column;
	}

	/**
	 * Render the WPML status icons for each language in the list table cell.
	 *
	 * @since 3.12.0
	 *
	 * @param string $default Default cell content.
	 * @param object $item    Current row item (event entry with ->object_id WordPress post ID).
	 *
	 * @return string HTML to echo for the cell content.
	 */
	public function render_wpml_flags_cell( $default, $item ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		$item_id = null;

		if ( $item instanceof WP_Post ) {
			$item_id = $item->ID;
		} elseif ( $item instanceof Event ) {
			$item_id = $item->object_id;
		}

		// Ensure environment.
		if ( empty( $item_id ) ) {
			return $default;
		}

		// Access SitePress to fetch active languages.
		global $sitepress;
		if ( empty( $sitepress ) || ! method_exists( $sitepress, 'get_active_languages' ) ) {
			return $default;
		}

		$active_languages = $sitepress->get_active_languages();

		if ( empty( $active_languages ) || count( $active_languages ) <= 1 ) {
			return '';
		}

		$current_language = $sitepress->get_current_language();

		unset( $active_languages[ $current_language ] );

		if ( empty( $active_languages ) ) {
			return '';
		}

		// Build icons using WPML's own renderer for status and actions.
		if ( ! class_exists( 'WPML_Post_Status_Display' ) ) {
			// Fallback: try to include WPML class if available.
			if ( defined( 'WPML_PLUGIN_PATH' ) ) {

				$display_class = WPML_PLUGIN_PATH . '/menu/wpml-post-status-display.class.php';

				if ( file_exists( $display_class ) ) {
					require_once $display_class;
				}
			}
		}

		if ( ! class_exists( 'WPML_Post_Status_Display' ) ) {
			return $default;
		}

		$post_status_display = new WPML_Post_Status_Display( $active_languages );
		$output              = '';

		foreach ( $active_languages as $lang_data ) {

			$lang_code = isset( $lang_data['code'] ) ? $lang_data['code'] : '';

			if ( $lang_code === '' ) {
				continue;
			}
			$output .= $post_status_display->get_status_html( (int) $item_id, $lang_code );
		}

		return $output;
	}

	/**
	 * Filter the calendar term ID so the admin calendar color uses the original term.
	 *
	 * @since 3.12.0
	 *
	 * @param int $calendar_id Calendar term ID.
	 *
	 * @return int Mapped term ID.
	 */
	public function filter_calendar_color_term_id( $calendar_id ) {

		global $sitepress;

		if ( empty( $calendar_id ) || empty( $sitepress ) || ! method_exists( $sitepress, 'get_default_language' ) ) {
			return intval( $calendar_id );
		}

		$default_lang  = $sitepress->get_default_language();
		$taxonomy_slug = sugar_calendar_get_calendar_taxonomy_id();

		$mapped_term_id = apply_filters(
			'wpml_object_id',
			intval( $calendar_id ),
			$taxonomy_slug,
			true,
			$default_lang
		);

		return ! empty( $mapped_term_id )
			? intval( $mapped_term_id )
			: intval( $calendar_id );
	}
}
