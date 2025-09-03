<?php

namespace Sugar_Calendar\ConflictFixes\GiveWP;

/**
 * GiveWP conflict fixes.
 *
 * @since 3.8.2
 */
class GiveWP {

	/**
	 * Initialize the GiveWP conflict fixes.
	 *
	 * @since 3.8.2
	 */
	public function init() {

		// Loads only if GiveWP is active.
		if ( ! class_exists( 'Give' ) ) {
			return;
		}

		$this->hooks();
	}

	/**
	 * Register hooks to mitigate conflicts.
	 *
	 * @since 3.8.2
	 */
	private function hooks() {

		// Avoid GiveWP error in recurring events edit page.
		add_filter( 'give_disable_hook-admin_init:Give\\FormBuilder\\Routes\\EditFormRoute@__invoke', [ $this, 'disable_givewp_admin_init' ] );
	}

	/**
	 * Disable GiveWP admin_init hooks in recurring events edit page.
	 *
	 * @since 3.8.2
	 *
	 * @param bool $disabled Whether the hook is disabled.
	 *
	 * @return bool
	 */
	public function disable_givewp_admin_init( $disabled ) {

		// If it's already disabled, good.
		if ( $disabled ) {
			return true;
		}

		// If we're not in the post edit page, bail.
		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'edit' ) {
			return $disabled;
		}

		// If we're editing a Sugar Calendar virtual occurrence, disable GiveWP admin_init hooks.
		if ( isset( $_GET['sc-occurrence'] ) ) {
			return true;
		}

		// Otherwise, let it go.
		return $disabled;
	}
}
