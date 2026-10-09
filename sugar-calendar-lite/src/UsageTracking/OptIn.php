<?php

namespace Sugar_Calendar\UsageTracking;

use Sugar_Calendar\Integrations\OAuthRelay\SiteRegistrar;
use Sugar_Calendar\Options;
use Sugar_Calendar\SetupChecklist\State;
use WP_Error;

/**
 * Enable Lite usage tracking from an explicit consent source and record that source
 * on the Setup Checklist State.
 *
 * @since 3.15.0
 */
class OptIn {

	/**
	 * Error code for "tracking is on, but the checklist did not record the source".
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const PROVENANCE_ERROR = 'usage_tracking_provenance_not_recorded';

	/**
	 * Checklist State that records the consent source.
	 *
	 * @since 3.15.0
	 *
	 * @var State
	 */
	private $state;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param State|null $state Checklist State; a fresh instance when omitted.
	 */
	public function __construct( State $state = null ) {

		$this->state = $state ?? new State();
	}

	/**
	 * Enable tracking, register the site, record the consent source and publish it.
	 *
	 * A `PROVENANCE_ERROR` result means tracking is enabled but the checklist State
	 * write failed; callers decide whether that is fatal for them.
	 *
	 * @since 3.15.0
	 *
	 * @param string $source Wizard or checklist.
	 *
	 * @return true|WP_Error
	 */
	public function enable( $source ) {

		if ( ! in_array( $source, [ 'wizard', 'checklist' ], true ) ) {
			return new WP_Error( 'usage_tracking_invalid_source', __( 'This usage tracking source is not supported.', 'sugar-calendar-lite' ) );
		}

		if ( sugar_calendar()->is_pro() ) {
			return new WP_Error( 'usage_tracking_protected', __( 'Usage tracking cannot be changed from this action.', 'sugar-calendar-lite' ) );
		}

		Options::update( 'disable_integrations', false );

		$persisted = $this->is_persisted_enabled();

		if ( is_wp_error( $persisted ) ) {
			return $persisted;
		}

		if ( ! $persisted ) {
			return new WP_Error( 'usage_tracking_enable_failed', __( 'Usage tracking could not be enabled. Please try again.', 'sugar-calendar-lite' ) );
		}

		$this->maybe_register_site();

		$recorded = $this->state->record_opt_in( $source );

		/**
		 * Fires after tracking is enabled from an explicit Lite consent source.
		 *
		 * The checklist provenance write has already been attempted at this point.
		 *
		 * @since 3.15.0
		 *
		 * @param string $source Wizard or checklist.
		 */
		do_action( 'sugar_calendar_usage_tracking_opted_in', $source ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- named for the feature, not this class.

		if ( is_wp_error( $recorded ) ) {
			return new WP_Error(
				self::PROVENANCE_ERROR,
				__( 'Usage tracking was enabled, but Sugar Calendar could not save the checklist progress. Please reload and try again.', 'sugar-calendar-lite' ),
				[
					'source' => $source,
					'cause'  => $recorded->get_error_message(),
				]
			);
		}

		return true;
	}

	/**
	 * Read the stored option directly, because Options keeps its own request cache.
	 *
	 * @since 3.15.0
	 *
	 * @return bool|WP_Error
	 */
	private function is_persisted_enabled() {

		global $wpdb;

		$serialized = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The persisted value, not Options' request cache, is the success condition.
			$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", Options::OPTION_NAME )
		);

		if ( $wpdb->last_error !== '' ) {
			return new WP_Error( 'usage_tracking_read_failed', __( 'Usage tracking could not be verified. Please try again.', 'sugar-calendar-lite' ) );
		}

		$options = maybe_unserialize( $serialized );

		return is_array( $options ) &&
			array_key_exists( 'disable_integrations', $options ) &&
			! (bool) $options['disable_integrations'];
	}

	/**
	 * Best-effort site registration with the Product API.
	 *
	 * @since 3.15.0
	 */
	private function maybe_register_site() {

		if ( defined( 'SC_OAUTH_RELAY_TEST_MODE' ) && SC_OAUTH_RELAY_TEST_MODE ) {
			return;
		}

		( new SiteRegistrar() )->register();
	}
}
