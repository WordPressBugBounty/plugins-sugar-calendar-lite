<?php

namespace Sugar_Calendar\SetupChecklist;

use Sugar_Calendar\Admin\Tools\Importers\TheEventCalendar;
use Sugar_Calendar\Admin\Tools\Smtp;
use Sugar_Calendar\Integrations\IntegrationsDisabler;
use Sugar_Calendar\Integrations\OAuthRelay\OAuthConnectionModel;
use Sugar_Calendar\Options;
use Sugar_Calendar\PartnerPlugins\Catalog;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\stripe_is_connected;

/**
 * Resolve authoritative Setup Checklist completion and applicability.
 *
 * @since 3.15.0
 */
class CompletionDetector {

	/**
	 * Options the `connect-stripe` predicate reads; the Loader invalidates on their writes.
	 *
	 * @since 3.15.0
	 *
	 * @var string[]
	 */
	public const STRIPE_OPTIONS = [
		'sc_stripe_connect_account_id',
		'sc_stripe_test_publishable',
		'sc_stripe_test_secret',
		'sc_stripe_live_publishable',
		'sc_stripe_live_secret',
	];

	/**
	 * Per-site checklist state.
	 *
	 * @since 3.15.0
	 *
	 * @var State
	 */
	private $state;

	/**
	 * Partner plugin catalogue.
	 *
	 * @since 3.15.0
	 *
	 * @var Catalog
	 */
	private $catalog;

	/**
	 * SMTP configuration detector.
	 *
	 * @since 3.15.0
	 *
	 * @var Smtp
	 */
	private $smtp;

	/**
	 * Lazily filtered completion callbacks.
	 *
	 * @since 3.15.0
	 *
	 * @var array|null
	 */
	private $checks;

	/**
	 * Lazily filtered applicability callbacks.
	 *
	 * @since 3.15.0
	 *
	 * @var array|null
	 */
	private $conditions;

	/**
	 * Request-local completion results.
	 *
	 * @since 3.15.0
	 *
	 * @var bool[]
	 */
	private $completed = [];

	/**
	 * Request-local applicability results.
	 *
	 * @since 3.15.0
	 *
	 * @var bool[]
	 */
	private $applicable = [];

	/**
	 * State supplied by an atomic mutation or fresh reset.
	 *
	 * @since 3.15.0
	 *
	 * @var array
	 */
	private $current_state = [];

	/**
	 * Whether current_state has been initialized.
	 *
	 * @since 3.15.0
	 *
	 * @var bool
	 */
	private $has_current_state = false;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param State   $state   Per-site checklist state.
	 * @param Catalog $catalog Partner plugin catalogue.
	 * @param Smtp    $smtp    SMTP configuration detector.
	 */
	public function __construct( State $state, Catalog $catalog, Smtp $smtp ) {

		$this->state   = $state;
		$this->catalog = $catalog;
		$this->smtp    = $smtp;
	}

	/**
	 * Whether a known checklist item is complete.
	 *
	 * Callbacks receive the current normalized State, so Pro callbacks see the exact
	 * State of an atomic mutation retry.
	 *
	 * @since 3.15.0
	 *
	 * @param string $item_id Canonical catalogue item ID.
	 *
	 * @return bool
	 */
	public function is_complete( $item_id ) {

		if ( array_key_exists( $item_id, $this->completed ) ) {
			return $this->completed[ $item_id ];
		}

		$checks = $this->get_checks();

		if ( ! array_key_exists( $item_id, $checks ) || ! is_callable( $checks[ $item_id ] ) ) {
			$this->completed[ $item_id ] = false;

			return $this->completed[ $item_id ];
		}

		$this->completed[ $item_id ] = (bool) call_user_func( $checks[ $item_id ], $this->get_current_state() );

		return $this->completed[ $item_id ];
	}

	/**
	 * Whether a known checklist item applies to this site.
	 *
	 * @since 3.15.0
	 *
	 * @param string $item_id Canonical catalogue item ID.
	 *
	 * @return bool
	 */
	public function is_applicable( $item_id ) {

		if ( array_key_exists( $item_id, $this->applicable ) ) {
			return $this->applicable[ $item_id ];
		}

		$conditions = $this->get_conditions();

		if ( array_key_exists( $item_id, $conditions ) ) {
			$this->applicable[ $item_id ] = is_callable( $conditions[ $item_id ] )
				? (bool) call_user_func( $conditions[ $item_id ], $this->get_current_state() )
				: false;

			return $this->applicable[ $item_id ];
		}

		$this->applicable[ $item_id ] = true;

		return $this->applicable[ $item_id ];
	}

	/**
	 * Clear memoized results without reading the database; the next predicate re-reads State.
	 *
	 * @since 3.15.0
	 */
	public function reset() {

		$this->checks            = null;
		$this->conditions        = null;
		$this->completed         = [];
		$this->applicable        = [];
		$this->current_state     = [];
		$this->has_current_state = false;
	}

	/**
	 * Adopt an exact normalized State without discarding memoized results.
	 *
	 * @since 3.15.0
	 *
	 * @param array $normalized_state Exact normalized State.
	 */
	public function set_current_state( array $normalized_state ) {

		$this->current_state     = $normalized_state;
		$this->has_current_state = true;
	}

	/**
	 * Completion callbacks keyed by canonical item ID.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private function get_checks() {

		if ( $this->checks !== null ) {
			return $this->checks;
		}

		$checks = [
			Config::ITEM_CREATE_EVENT               => [ $this, 'has_saved_event' ],
			Config::ITEM_IMPORT_THE_EVENTS_CALENDAR => [ $this, 'has_finished_the_events_calendar_import' ],
			Config::ITEM_DATE_TIME_SETTINGS         => [ $this, 'has_saved_date_time_settings' ],
			Config::ITEM_TIMEZONE                   => [ $this, 'has_saved_timezone' ],
			Config::ITEM_EMAIL_DELIVERY             => [ $this->smtp, 'is_configured' ],
			Config::ITEM_CONNECT_ZOOM               => [ $this, 'has_active_zoom_connection' ],
			Config::ITEM_PRIVACY_COMPLIANCE         => [ $this, 'is_wpconsent_active' ],
			Config::ITEM_AI_ASSISTANT               => [ $this, 'is_wpvibe_active' ],
			Config::ITEM_USAGE_TRACKING             => [ $this, 'has_enabled_usage_tracking' ],
			Config::ITEM_CONNECT_STRIPE             => [ $this, 'has_stripe_connection' ],
		];

		// Added before the filter, so a subscriber can still replace any of them.
		foreach ( Config::get_history_item_ids() as $item_id ) {
			$checks[ $item_id ] = static function ( $state ) use ( $item_id ) {
				return isset( $state['history'][ $item_id ] );
			};
		}

		/**
		 * Filters Setup Checklist completion callbacks.
		 *
		 * Each callable receives the current normalized State array.
		 *
		 * @since 3.15.0
		 *
		 * @param array $checks Callbacks keyed by canonical item ID.
		 */
		$this->checks = (array) apply_filters( 'sugar_calendar_setup_checklist_completion_checks', $checks ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- named for the feature, not this class.

		return $this->checks;
	}

	/**
	 * Applicability callbacks keyed by canonical item ID.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private function get_conditions() {

		if ( $this->conditions !== null ) {
			return $this->conditions;
		}

		$conditions = [
			Config::ITEM_IMPORT_THE_EVENTS_CALENDAR => [ $this, 'has_the_events_calendar_import' ],
			Config::ITEM_USAGE_TRACKING             => [ $this, 'can_opt_into_usage_tracking' ],
		];

		/**
		 * Filters Setup Checklist applicability callbacks.
		 *
		 * Each callable receives the current normalized State array.
		 *
		 * @since 3.15.0
		 *
		 * @param array $conditions Callbacks keyed by canonical item ID.
		 */
		$this->conditions = (array) apply_filters( 'sugar_calendar_setup_checklist_conditions', $conditions ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- named for the feature, not this class.

		return $this->conditions;
	}

	/**
	 * Get the current normalized State without writing storage.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function get_current_state() {

		if ( ! $this->has_current_state ) {
			$this->current_state     = $this->state->get();
			$this->has_current_state = true;
		}

		return $this->current_state;
	}

	/**
	 * Whether at least one saved event exists.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private function has_saved_event() {

		$events = sugar_calendar_get_events(
			[
				'object_type'    => 'post',
				'object_subtype' => [ 'sc_event', 'sc_recurring_event' ],
				'status__in'     => Config::SAVED_POST_STATUSES,
				'number'         => 1,
			]
		);

		return ! empty( $events );
	}

	/**
	 * Whether the three date and time values were explicitly saved and are valid.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private function has_saved_date_time_settings() {

		$options = $this->get_stored_options();

		if (
			! array_key_exists( 'date_format', $options ) ||
			! array_key_exists( 'time_format', $options ) ||
			! array_key_exists( 'start_of_week', $options ) ||
			! is_string( $options['date_format'] ) ||
			$options['date_format'] === '' ||
			! is_string( $options['time_format'] ) ||
			$options['time_format'] === ''
		) {
			return false;
		}

		$day = $options['start_of_week'];

		return ( is_int( $day ) && $day >= 0 && $day <= 6 ) || ( is_string( $day ) && preg_match( '/^[0-6]$/D', $day ) === 1 );
	}

	/**
	 * Whether a valid timezone choice was explicitly saved.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private function has_saved_timezone() {

		$options = $this->get_stored_options();

		return array_key_exists( 'timezone', $options ) &&
			is_string( $options['timezone'] ) &&
			in_array( $options['timezone'], sugar_calendar_get_valid_timezones(), true );
	}

	/**
	 * Whether Zoom has an active site-wide connection.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private function has_active_zoom_connection() {

		if ( IntegrationsDisabler::is_disabled() ) {
			return false;
		}

		$connection = OAuthConnectionModel::find_by_provider( 'zoom' );

		return is_array( $connection ) && ( $connection['status'] ?? '' ) === 'active';
	}

	/**
	 * Whether WPConsent is active.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private function is_wpconsent_active() {

		return $this->catalog->get_state( 'wpconsent' ) === 'active';
	}

	/**
	 * Whether WPVibe is active.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private function is_wpvibe_active() {

		return $this->catalog->get_state( 'wpvibe' ) === 'active';
	}

	/**
	 * Whether explicit opt-in evidence exists and tracking remains enabled.
	 *
	 * @since 3.15.0
	 *
	 * @param array $state Current normalized State.
	 *
	 * @return bool
	 */
	private function has_enabled_usage_tracking( $state ) {

		return ( (int) ( $state['opt_in']['wizard'] ?? 0 ) > 0 || (int) ( $state['opt_in']['checklist'] ?? 0 ) > 0 ) &&
			sugar_calendar()->get_usage_tracking()->is_enabled();
	}

	/**
	 * Whether Lite should offer the explicit tracking opt-in.
	 *
	 * @since 3.15.0
	 *
	 * @param array $state Current normalized State.
	 *
	 * @return bool
	 */
	private function can_opt_into_usage_tracking( $state ) {

		return (int) ( $state['opt_in']['wizard'] ?? 0 ) === 0;
	}

	/**
	 * Whether the built-in ticketing Stripe connection is configured.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private function has_stripe_connection() {

		if ( ! function_exists( 'Sugar_Calendar\AddOn\Ticketing\Common\Functions\stripe_is_connected' ) ) {
			return false;
		}

		/** This filter is documented in StripeConnect::get_connect_url(). */
		$test_mode = (bool) apply_filters( 'sugar_calendar_setup_checklist_stripe_test_mode', false ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- named for the feature, not this class.

		if ( ! $test_mode ) {
			return stripe_is_connected();
		}

		return (
			get_option( 'sc_stripe_connect_account_id' ) &&
			get_option( 'sc_stripe_test_publishable' ) &&
			get_option( 'sc_stripe_test_secret' )
		);
	}

	/**
	 * Whether The Events Calendar has events to import or an import already finished.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private function has_the_events_calendar_import() {

		return TheEventCalendar::is_migration_possible() || $this->has_finished_the_events_calendar_import();
	}

	/**
	 * Whether a The Events Calendar import ran to the end.
	 *
	 * The importer saves the date even when some items failed; a finished import counts as done.
	 *
	 * @since 3.15.0
	 *
	 * @return bool
	 */
	private function has_finished_the_events_calendar_import() {

		$value = get_option( TheEventCalendar::SC_TEC_MIGRATION_OPTION_KEY, '' );

		return ! empty( $value ) && $value !== 'in_progress';
	}

	/**
	 * Read only the raw stored Sugar Calendar options.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private function get_stored_options() {

		$options = get_option( Options::OPTION_NAME, [] );

		return is_array( $options ) ? $options : [];
	}
}
