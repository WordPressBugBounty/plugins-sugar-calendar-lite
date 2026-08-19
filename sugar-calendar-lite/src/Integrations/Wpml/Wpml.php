<?php

namespace Sugar_Calendar\Integrations\Wpml;

/**
 * WPML Integration.
 *
 * @since 3.12.0
 */
class Wpml {

	/**
	 * Setup instance.
	 *
	 * @since 3.12.0
	 *
	 * @var WpmlSetup
	 */
	public $setup;

	/**
	 * Admin instance.
	 *
	 * @since 3.12.0
	 *
	 * @var WpmlAdmin
	 */
	public $admin;

	/**
	 * Frontend instance.
	 *
	 * @since 3.12.0
	 *
	 * @var WpmlFrontend
	 */
	public $frontend;

	/**
	 * Save instance.
	 *
	 * @since 3.12.0
	 *
	 * @var WpmlSave
	 */
	public $save;

	/**
	 * Query instance.
	 *
	 * @since 3.12.0
	 *
	 * @var WpmlQuery
	 */
	public $query;

	/**
	 * Meta instance.
	 *
	 * @since 3.12.0
	 *
	 * @var WpmlMeta
	 */
	public $meta;

	/**
	 * Initialize the WPML integration.
	 *
	 * @since 3.12.0
	 */
	public function init() {

		if ( ! self::is_wpml_active() ) {
			return;
		}

		// WPML setup adjustments.
		$this->setup = new WpmlSetup();

		// Admin UI / List Table.
		$this->admin = new WpmlAdmin();

		// Frontend Display.
		$this->frontend = new WpmlFrontend();

		// Data Persistence / Admin Save.
		$this->save = new WpmlSave();

		// Data Retrieval / Query Mapping.
		$this->query = new WpmlQuery();

		// Meta.
		$this->meta = new WpmlMeta();

		// Run hooks.
		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 */
	private function hooks() {

		$this->setup->hooks();
		$this->admin->hooks();
		$this->frontend->hooks();
		$this->save->hooks();
		$this->query->hooks();
		$this->meta->hooks();
	}

	/**
	 * Detect whether WPML (SitePress) is active.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public static function is_wpml_active() {

		return defined( 'ICL_SITEPRESS_VERSION' ) || class_exists( 'SitePress' );
	}

	/**
	 * Get the original post ID for a post ID based on its default language counterpart via WPML.
	 * Returns the original ID when WPML is not present or mapping fails.
	 *
	 * @since 3.12.0
	 *
	 * @param int    $post_id   Post ID to map.
	 * @param string $post_type Target element type (e.g. 'sc_event').
	 *
	 * @return int Mapped post ID in default language (or original ID).
	 */
	public static function get_original_post_id( $post_id, $post_type ) {

		// If WPML is not active, return the original post ID.
		if ( ! self::is_wpml_active() ) {
			return absint( $post_id );
		}

		global $sitepress;

		$post_id = absint( $post_id );

		if ( empty( $sitepress ) || ! is_object( $sitepress ) || ! method_exists( $sitepress, 'get_default_language' ) ) {
			return $post_id;
		}

		$default_lang = $sitepress->get_default_language();

		/**
		 * Map a post ID from any language to the corresponding element ID in the
		 * default language. If no translation exists, return the original ID.
		 *
		 * @hook wpml_object_id
		 * @since 3.12.0
		 *
		 * @param int    $post_id     The post ID to map.
		 * @param string $post_type   Element type.
		 * @param bool   $return_orig Return original if translation missing.
		 * @param string $target_lang Target language code.
		 */
		$mapped = apply_filters( 'wpml_object_id', $post_id, $post_type, true, $default_lang ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML integration API hook.

		$mapped_id = absint( $mapped );

		return $mapped_id > 0 ? $mapped_id : $post_id;
	}

	/**
	 * Get the current translated post ID.
	 *
	 * @since 3.12.0
	 *
	 * @param int    $post_id   Post ID to map.
	 * @param string $post_type Target element type (e.g. 'sc_event').
	 *
	 * @return int Mapped post ID in current language (or original ID).
	 */
	public static function get_current_translated_post_id( $post_id, $post_type ) {

		// If WPML is not active, return the original post ID.
		if ( ! self::is_wpml_active() ) {
			return absint( $post_id );
		}

		global $sitepress;

		$post_id = absint( $post_id );

		if ( empty( $sitepress ) || ! is_object( $sitepress ) || ! method_exists( $sitepress, 'get_current_language' ) ) {
			return $post_id;
		}

		$current_lang = $sitepress->get_current_language();

		/**
		 * Map a post ID from any language to the corresponding element ID in the
		 * default language. If no translation exists, return the original ID.
		 *
		 * @hook wpml_object_id
		 * @since 3.12.0
		 *
		 * @param int    $post_id     The post ID to map.
		 * @param string $post_type   Element type.
		 * @param bool   $return_orig Return original if translation missing.
		 * @param string $target_lang Target language code.
		 */
		$mapped = apply_filters( 'wpml_object_id', $post_id, $post_type, true, $current_lang ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML integration API hook.

		$mapped_id = absint( $mapped );

		return $mapped_id > 0 ? $mapped_id : $post_id;
	}

	/**
	 * Check if the event is a duplicate.
	 *
	 * @since 3.12.0
	 *
	 * @param int    $post_id   ID of the event.
	 * @param string $post_type Post type of the event.
	 *
	 * @return bool
	 */
	public static function is_translated_post( $post_id, $post_type ) {

		// If WPML is not active, the post is not a translation.
		if ( ! self::is_wpml_active() ) {
			return false;
		}

		$original_post_id = self::get_original_post_id( $post_id, $post_type );

		return (int) $original_post_id !== (int) $post_id;
	}

	/**
	 * Get the original (default-language) post IDs that have a translation in a
	 * given language, for the provided post types.
	 *
	 * Sugar Calendar stores its custom-table rows against the original post IDs
	 * and queries those tables directly, bypassing WPML's WP_Query language
	 * filter. This returns the canonical IDs that are translated into the target
	 * language so those queries can be scoped to match the current language.
	 *
	 * @since 3.12.0
	 *
	 * @param string[] $post_types    Post type identifiers (e.g. 'sc_event').
	 * @param string   $language_code Target language code.
	 *
	 * @return int[] Original post IDs that have a translation in $language_code.
	 */
	public static function get_translated_original_ids( $post_types, $language_code ) {

		if ( ! self::is_wpml_active() || empty( $post_types ) || empty( $language_code ) ) {
			return [];
		}

		global $wpdb;

		// Check if WPML translations table exists.
		$table_name = $wpdb->prefix . 'icl_translations';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query against WPML tables.
			return [];
		}

		// WPML prefixes post-type element types with `post_`.
		$element_types = array_map(
			static function ( $post_type ) {

				return 'post_' . sanitize_key( $post_type );
			},
			(array) $post_types
		);

		// An empty type list would collapse the IN() below to `IN ()`, a SQL syntax
		// error. The post_types guard above already prevents this; the check is kept
		// here so the placeholder count and the bound args can never silently drift.
		if ( empty( $element_types ) ) {
			return [];
		}

		$type_placeholders = implode( ', ', array_fill( 0, count( $element_types ), '%s' ) );

		// Original rows (source_language_code IS NULL) whose translation group
		// includes a row in the target language. The only interpolated value is the
		// `%s` placeholder list built above, and the bound args match it exactly: one
		// %s for language_code plus one %s per element type ( count( $element_types ) ),
		// so placeholder count == arg count and the query stays fully prepared.
		$sql = $wpdb->prepare(
			"SELECT DISTINCT orig.element_id
			FROM {$wpdb->prefix}icl_translations AS orig
			INNER JOIN {$wpdb->prefix}icl_translations AS trans
				ON trans.trid = orig.trid
				AND trans.language_code = %s
			WHERE orig.source_language_code IS NULL
				AND orig.element_type IN ( {$type_placeholders} )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array_merge( [ $language_code ], $element_types )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col( $sql );

		return array_map( 'absint', (array) $ids );
	}

	/**
	 * Get the canonical event IDs to show in the admin tables for the current
	 * language, or null when no language scoping should be applied.
	 *
	 * Shared by the core Event_Query scoping and the Pro recurring admin queries
	 * so both code paths filter identically. The result is memoized for the
	 * request, since it is constant for a given page load.
	 *
	 * @since 3.12.0
	 *
	 * @return int[]|null Allowed original event IDs. Three distinct return states:
	 *                    - null  : no scoping applies (WPML inactive, SitePress
	 *                              unavailable, current language is the default,
	 *                              or the "All Languages" admin mode is active).
	 *                              Callers should leave their query untouched.
	 *                    - []    : scoping applies but no events are translated to
	 *                              the current language. Callers must scope to an
	 *                              empty result set (e.g. the `[ 0 ]` sentinel).
	 *                    - int[] : scoping applies; restrict to these original IDs.
	 */
	public static function get_admin_language_scoped_event_ids() {

		if ( ! self::is_wpml_active() ) {
			return null;
		}

		global $sitepress;

		if (
			empty( $sitepress )
			|| ! is_object( $sitepress )
			|| ! method_exists( $sitepress, 'get_current_language' )
			|| ! method_exists( $sitepress, 'get_default_language' )
		) {
			return null;
		}

		$current_lang = $sitepress->get_current_language();
		$default_lang = $sitepress->get_default_language();

		// Canonical rows live in the default language — show every event there.
		// 'all' is WPML's "All Languages" admin mode, which must likewise show
		// every event rather than scoping to a non-existent 'all' language code.
		if ( empty( $current_lang ) || $current_lang === 'all' || $current_lang === $default_lang ) {
			return null;
		}

		/**
		 * Filter the event post types scoped by language in the admin tables.
		 *
		 * Pro features (e.g. Advanced Recurring) append their event post types.
		 *
		 * @since 3.12.0
		 *
		 * @param array $post_types Event post type identifiers.
		 */
		$post_types = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_integrations_wpml_event_post_types',
			[ sugar_calendar_get_event_post_type_id() ]
		);

		// Memoize keyed by the resolved post-type list + language. The post-type
		// filter is load-order dependent (Pro may register late), so a single
		// request-wide cache could omit post types added after the first call.
		static $cache = [];

		$normalized_types = $post_types;
		sort( $normalized_types );

		$key = $current_lang . '|' . implode( ',', $normalized_types );

		if ( ! array_key_exists( $key, $cache ) ) {
			$cache[ $key ] = self::get_translated_original_ids( $post_types, $current_lang );
		}

		return $cache[ $key ];
	}
}
