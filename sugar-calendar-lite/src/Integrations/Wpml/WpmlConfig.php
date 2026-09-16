<?php

namespace Sugar_Calendar\Integrations\Wpml;

/**
 * Declare our post types and taxonomies to WPML.
 *
 * This used to ship as a `wpml-config.xml` in the plugin root. Polylang reads
 * WPML config *files* from the same locations, so that file also forced the
 * calendar taxonomy and the event post types to be language-filtered on
 * Polylang sites, and Polylang hides the setting whenever a plugin declares it
 * (see PLL_WPML_Config::translate_taxonomies). Feeding WPML's own parser
 * through `wpml_config_array` keeps the identical behaviour on WPML and is
 * invisible to Polylang, which has no filter equivalent. See issue #795.
 *
 * @since 3.14.0
 */
class WpmlConfig {

	/**
	 * WPML's `translate="1"` attribute value, as its XML parser yields it.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const TRANSLATE = '1';

	/**
	 * Register hooks.
	 *
	 * Must run at plugin boot: WPML parses its config on a small allowlist of
	 * admin screens (plugins.php, themes.php, its own settings pages), so a
	 * filter added later can miss the parse entirely.
	 *
	 * @since 3.14.0
	 */
	public function hooks() {

		add_filter( 'wpml_config_array', [ $this, 'declare_translatable_content' ] );
	}

	/**
	 * Add our post types and taxonomies to WPML's parsed config.
	 *
	 * @since 3.14.0
	 *
	 * @param array $config WPML's parsed config array.
	 *
	 * @return array
	 */
	public function declare_translatable_content( $config ) {

		if ( ! is_array( $config ) ) {
			return $config;
		}

		$config['wpml-config'] = isset( $config['wpml-config'] ) && is_array( $config['wpml-config'] )
			? $config['wpml-config']
			: [];

		$config['wpml-config'] = $this->add_entries(
			$config['wpml-config'],
			'custom-types',
			'custom-type',
			$this->get_post_types()
		);

		$config['wpml-config'] = $this->add_entries(
			$config['wpml-config'],
			'taxonomies',
			'taxonomy',
			$this->get_taxonomies()
		);

		return $config;
	}

	/**
	 * Get the post types to declare as translatable.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	private function get_post_types() {

		// Union the older seam so a Pro feature subscribing only to it (as
		// AdvancedRecurring, Venues and Speakers do) still reaches WPML's config.
		$post_types = array_merge(
			[
				'sc_event',
				'sc_recurring_event',
				'sugarcalendar_venue',
				'sc_speakers',
			],
			(array) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
				'sugar_calendar_integrations_wpml_custom_post_types',
				[ sugar_calendar_get_event_post_type_id() ]
			)
		);

		/**
		 * Filter the post types declared to WPML as translatable.
		 *
		 * @since 3.14.0
		 *
		 * @param array $post_types Post type names.
		 */
		return (array) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_integrations_wpml_config_post_types',
			array_values( array_unique( $post_types ) )
		);
	}

	/**
	 * Get the taxonomies to declare as translatable.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	private function get_taxonomies() {

		/**
		 * Filter the taxonomies declared to WPML as translatable.
		 *
		 * @since 3.14.0
		 *
		 * @param array $taxonomies Taxonomy names.
		 */
		return (array) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_integrations_wpml_config_taxonomies',
			[
				'sc_event_category',
				'sc_event_tags',
			]
		);
	}

	/**
	 * Append entries to one section of WPML's config, skipping any the site
	 * already declares so a theme or another plugin keeps precedence.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $wpml_config    The `wpml-config` array.
	 * @param string $section_plural Section key, e.g. `taxonomies`.
	 * @param string $section_single Entry key, e.g. `taxonomy`.
	 * @param array  $names          Post type or taxonomy names to declare.
	 *
	 * @return array
	 */
	private function add_entries( $wpml_config, $section_plural, $section_single, $names ) {

		if ( empty( $names ) ) {
			return $wpml_config;
		}

		$section = isset( $wpml_config[ $section_plural ] ) && is_array( $wpml_config[ $section_plural ] )
			? $wpml_config[ $section_plural ]
			: [];

		$entries = isset( $section[ $section_single ] ) && is_array( $section[ $section_single ] )
			? $section[ $section_single ]
			: [];

		$declared = array_filter( wp_list_pluck( $entries, 'value' ) );

		foreach ( $names as $name ) {

			$name = sanitize_key( $name );

			if ( $name === '' || in_array( $name, $declared, true ) ) {
				continue;
			}

			$entries[] = [
				'value' => $name,
				'attr'  => [ 'translate' => self::TRANSLATE ],
			];
		}

		$section[ $section_single ]     = $entries;
		$wpml_config[ $section_plural ] = $section;

		return $wpml_config;
	}
}
