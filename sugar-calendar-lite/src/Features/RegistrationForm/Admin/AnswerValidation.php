<?php

namespace Sugar_Calendar\Features\RegistrationForm\Admin;

use Sugar_Calendar\Helpers as Sugar_Calendar_Helpers;
use Sugar_Calendar\Helpers\WP;

/**
 * The answers block's client-side guard.
 *
 * Enqueued from ResponsesPanel::render() rather than off a screen hook, so it
 * loads wherever the block renders (the RSVP editor, the order editor, a future
 * host) with no screen slug to keep in sync. A host that must not have its form
 * held for a blank answer enqueues first with $blocks_save = false.
 *
 * @since 3.13.0
 */
class AnswerValidation {

	/**
	 * Script handle.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const HANDLE = 'sc-registration-form-admin-answers';

	/**
	 * Enqueue the guard once per request.
	 *
	 * Runs during the host's own render, which is early enough for a footer
	 * script. wp_script_is() is the guard rather than a static flag so a second
	 * panel in the same request adds nothing and a second request still enqueues.
	 * That idempotency is also how a host sets $blocks_save: it enqueues before
	 * rendering its panels, and ResponsesPanel's own call then finds the script
	 * already there and adds nothing.
	 *
	 * @since 3.13.0
	 *
	 * @param bool $blocks_save Whether a blank required answer may hold the host's
	 *                          form. False where the form also carries controls the
	 *                          server applies regardless of the panels.
	 */
	public static function enqueue( $blocks_save = true ) {

		if ( wp_script_is( self::HANDLE, 'enqueued' ) ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			SC_PLUGIN_ASSETS_URL . 'js/features/registration-form/admin/answers' . WP::asset_min() . '.js',
			[ 'jquery' ],
			Sugar_Calendar_Helpers::get_asset_version(),
			true
		);

		wp_localize_script(
			self::HANDLE,
			'sc_regform_admin_answers',
			[
				// Plain __(), not esc_html__(): answers.js prints this with .text(),
				// which escapes it itself, and pre-escaping would show the entity.
				'requiredError' => ResponsesPanel::required_error_message(),
				'blocksSave'    => (bool) $blocks_save,
			]
		);
	}
}
