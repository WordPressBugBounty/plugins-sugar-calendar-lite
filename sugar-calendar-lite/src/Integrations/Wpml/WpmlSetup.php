<?php

namespace Sugar_Calendar\Integrations\Wpml;

/**
 * WPML Integration.
 *
 * @since 3.12.0
 */
class WpmlSetup {

	/**
	 * Enable WPML translation for the Event post type the first time we detect WPML.
	 *
	 * @since 3.12.0
	 */
	public function hooks() {

		add_action( 'admin_init', [ $this, 'maybe_enable_post_type_translation' ] );
	}

	/**
	 * Enable WPML translation for the Event post type the first time we detect WPML.
	 *
	 * @since 3.12.0
	 */
	public function maybe_enable_post_type_translation() {

		if ( ! is_admin() ) {
			return;
		}

		/**
		 * Filter the post types to enable translation for.
		 *
		 * @since 3.12.0
		 *
		 * @param array $post_types The post types to enable translation for.
		 */
		$post_types = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_integrations_wpml_custom_post_types',
			[ sugar_calendar_get_event_post_type_id() ]
		);

		// If post types are not set or not an array, return.
		if (
			empty( $post_types )
			||
			! is_array( $post_types )
		) {
			return;
		}

		// Idempotent sync: only touch CPTs that need updating.
		$this->sync_post_types_translation_settings( $post_types );
	}

	/**
	 * Mark Sugar Calendar Event post type as translatable in WPML settings.
	 *
	 * @since 3.12.0
	 *
	 * @param array $post_types The post types to enable translation for.
	 */
	private function sync_post_types_translation_settings( $post_types ) {

		global $sitepress;

		if ( empty( $sitepress ) || ! is_object( $sitepress ) || ! method_exists( $sitepress, 'get_setting' ) ) {
			return;
		}

		// Fallback if WPML constants are not defined for any reason.
		$translate_flag = defined( 'WPML_CONTENT_TYPE_TRANSLATE' ) ? WPML_CONTENT_TYPE_TRANSLATE : 1;

		$sync_settings = $sitepress->get_setting( 'custom_posts_sync_option', [] );

		$changes = [];

		foreach ( $post_types as $post_type ) {

			$post_type = sanitize_key( $post_type );

			if ( $post_type === '' ) {
				continue;
			}

			if ( ! isset( $sync_settings[ $post_type ] ) || (int) $sync_settings[ $post_type ] !== (int) $translate_flag ) {
				$sync_settings[ $post_type ] = $translate_flag;
				$changes[ $post_type ]       = $translate_flag;
			}
		}

		if ( empty( $changes ) ) {
			return;
		}

		// Save and let WPML refresh internal caches.
		$sitepress->set_setting( 'custom_posts_sync_option', $sync_settings, true );

		// Let WPML refresh any internal caches/listeners (no-op if WPML is not active).
		/**
		 * Verify post translations for the provided post type.
		 *
		 * @since 3.12.0
		 *
		 * @param array $post_type_flag Post type and translation flag.
		 */
		do_action( 'wpml_verify_post_translations', [ $post_type => $translate_flag ] ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		/**
		 * Notify that CPT sync settings were saved.
		 *
		 * @since 3.12.0
		 */
		do_action( 'wpml_save_cpt_sync_settings' ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}
}
