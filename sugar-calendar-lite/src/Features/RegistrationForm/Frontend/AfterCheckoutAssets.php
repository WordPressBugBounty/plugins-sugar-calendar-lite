<?php

namespace Sugar_Calendar\Features\RegistrationForm\Frontend;

use Sugar_Calendar\Helpers as Sugar_Calendar_Helpers;
use Sugar_Calendar\Helpers\WP;

/**
 * Registers the after-checkout modal controller for every host that uses it.
 *
 * One shared caller for the `sc_regform_after` global and the
 * `sc-registration-form-after` handle, so the hosts (TicketingReceipt,
 * RsvpAfterCheckout) can't drift apart. Also owns the request-scoped
 * one-root claim, since those same hosts are the ones that must agree on it.
 *
 * @since 3.13.0
 */
class AfterCheckoutAssets {

	/**
	 * The localized values a host may override.
	 *
	 * An allowlist, not the whole default set: the endpoint and its action are
	 * this class's to own, and a host that could replace them would be able to
	 * point the modal's submit somewhere else.
	 *
	 * `openSuccess`/`flashCookie` are the before-checkout hosts' entry: they print no
	 * form and ask the controller for the confirmation alone.
	 *
	 * @since 3.13.0
	 *
	 * @var string[]
	 */
	const HOST_OVERRIDABLE = [
		'title',
		'submitLabel',
		'successTitle',
		'successDescription',
		'successCta',
		'openSuccess',
		'flashCookie',
	];

	/**
	 * Whether an after-checkout form root has already been printed this request.
	 *
	 * @since 3.13.0
	 *
	 * @var bool
	 */
	private static $root_printed = false;

	/**
	 * Claim the right to print the one after-checkout form root of this request.
	 *
	 * The controller detaches `$('.sc-regform-after').first()` into its modal, so
	 * a second root would render inline as a visibly broken copy of the form.
	 * Two hosts can both try on one request (RsvpAfterCheckout, TokenResume), so
	 * this is test-and-set in one call to prevent a check-then-forget-to-claim bug.
	 *
	 * @since 3.13.0
	 *
	 * @return bool True when the caller may print; false when a sibling already did.
	 */
	public static function claim_root() {

		if ( self::$root_printed ) {
			return false;
		}

		self::$root_printed = true;

		return true;
	}

	/**
	 * Clear the claim at the start of a request.
	 *
	 * The static only outlives a single request in a long-lived process (a test
	 * runner, WP-CLI), so the hosts register this on `template_redirect` at
	 * priority 0, before either of their own gates runs.
	 *
	 * @since 3.13.0
	 */
	public static function reset_root_claim() {

		self::$root_printed = false;
	}

	/**
	 * Enqueue the after-checkout modal controller and localize its endpoint.
	 *
	 * `SubmitEndpoint::ACTION` is referenced rather than retyped so the two
	 * can't drift apart. `sc-et-bootstrap` is declared as a dependency here
	 * (not just assumed enqueued elsewhere) since it's otherwise only
	 * conditionally enqueued by Assets\enqueue().
	 *
	 * @since 3.13.0
	 *
	 * @param array $labels Host overrides for the localized strings, e.g. a CTA label
	 *                      that names what the host is completing. Unknown keys are
	 *                      ignored, so a host can never introduce a string after.js
	 *                      does not read.
	 */
	public static function enqueue( array $labels = [] ) {

		wp_enqueue_script(
			'sc-registration-form-after',
			SC_PLUGIN_ASSETS_URL . 'js/features/registration-form/frontend/after' . WP::asset_min() . '.js',
			[ 'jquery', 'sc-registration-form', 'sc-et-bootstrap' ],
			Sugar_Calendar_Helpers::get_asset_version(),
			true
		);

		$defaults = [
			'ajaxurl'            => admin_url( 'admin-ajax.php' ),
			'action'             => SubmitEndpoint::ACTION,
			// Plain __(), not esc_html__(): after.js sets this via textContent, which
			// escapes it itself; pre-escaping here would double-encode an apostrophe.
			// The heading names the whole step; the form's own heading, inside the
			// body, is the one that names the attendee details.
			'title'              => __( 'Complete Your Registration', 'sugar-calendar-lite' ),
			'submitLabel'        => __( 'Submit', 'sugar-calendar-lite' ),
			// The final stage, per the design's success frame: check, title, description,
			// one button. Hosts override the title to name what completed, and the
			// button to name where dismissing it lands.
			'successTitle'       => __( 'Your Registration was Successful!', 'sugar-calendar-lite' ),
			'successDescription' => __( 'We will soon send an email confirmation to all attendees', 'sugar-calendar-lite' ),
			'successCta'         => __( 'Close', 'sugar-calendar-lite' ),
			// Set by the before-checkout hosts only; see CompletionFlash.
			'openSuccess'        => false,
			'flashCookie'        => '',
			'genericError'       => __( 'Something went wrong. Please try again.', 'sugar-calendar-lite' ),
			// Shown for a failure that repeats on every attempt; the modal has no
			// close button, so this pair is its only exit. See after.js's showTerminalError().
			'closeLabel'         => __( 'Close', 'sugar-calendar-lite' ),
			'terminalError'      => __( 'This form can no longer be submitted. Please contact the organizer.', 'sugar-calendar-lite' ),
		];

		wp_localize_script(
			'sc-registration-form-after',
			'sc_regform_after',
			array_merge( $defaults, array_intersect_key( $labels, array_flip( self::HOST_OVERRIDABLE ) ) )
		);
	}
}
