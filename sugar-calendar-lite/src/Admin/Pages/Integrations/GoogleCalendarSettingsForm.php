<?php

namespace Sugar_Calendar\Admin\Pages\Integrations;

use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Integrations\Google\GoogleActionsClient;
use Sugar_Calendar\Integrations\Google\GoogleOAuthConnection;
use Sugar_Calendar\Integrations\IntegrationsDisabler;
use Sugar_Calendar\Options;
use Throwable;

/**
 * Data behind the Google Calendar settings rows: the calendar list, what the fields show, and the save.
 *
 * @since 3.16.0
 */
class GoogleCalendarSettingsForm {

	/**
	 * Sync directions the settings form accepts.
	 *
	 * @since 3.16.0
	 *
	 * @var string[]
	 */
	const SYNC_DIRECTIONS = [ 'both', 'sc_to_google', 'google_to_sc' ];

	/**
	 * Connection row, or null when not connected.
	 *
	 * @since 3.16.0
	 *
	 * @var array|null
	 */
	private $row;

	/**
	 * Calendar list for this request, or null before the first lookup.
	 *
	 * @since 3.16.0
	 *
	 * @var array|null
	 */
	private $calendars = null;

	/**
	 * Constructor.
	 *
	 * @since 3.16.0
	 *
	 * @param array|null $row Connection row, or null when not connected.
	 */
	public function __construct( $row ) {

		$this->row = $row;
	}

	/**
	 * Writable calendars of the connected account, cached for 15 minutes.
	 *
	 * Returns an empty list whenever the relay is unreachable or disabled, and
	 * never caches that, so the settings page always renders.
	 *
	 * @since 3.16.0
	 *
	 * @return array List of [ 'id' => string, 'name' => string ].
	 */
	public function get_calendars() {

		if ( is_array( $this->calendars ) ) {
			return $this->calendars;
		}

		$this->calendars = [];

		if ( IntegrationsDisabler::is_disabled() || $this->row === null || $this->row['status'] !== 'active' ) {
			return [];
		}

		$key    = GoogleCalendar::CALENDARS_TRANSIENT_PREFIX . (string) $this->row['account_id'];
		$cached = get_transient( $key );

		if ( is_array( $cached ) && $cached !== [] ) {
			$this->calendars = $cached;

			return $cached;
		}

		$this->calendars = $this->fetch_calendars( $key );

		return $this->calendars;
	}

	/**
	 * Ask the relay for the calendar list and cache a non-empty answer.
	 *
	 * @since 3.16.0
	 *
	 * @param string $key Transient key.
	 *
	 * @return array List of [ 'id' => string, 'name' => string ], or [] on any failure.
	 */
	private function fetch_calendars( string $key ): array {

		try {
			$result = ( new GoogleActionsClient() )->list_calendars( new GoogleOAuthConnection( $this->row ) );
		} catch ( Throwable $e ) {
			return [];
		}

		if ( is_wp_error( $result ) || ! is_array( $result ) || $result === [] ) {
			return [];
		}

		set_transient( $key, $result, 15 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * The calendar dropdown's options and selected value.
	 *
	 * @since 3.16.0
	 *
	 * @return array [ string[] $options keyed by value, string $selected ].
	 */
	public function calendar_choices(): array {

		$calendars  = $this->get_calendars();
		$saved_id   = (string) Options::get( 'google_calendar_id', '' );
		$saved_name = (string) Options::get( 'google_calendar_name', '' );

		$options = [
			''                             => __( 'Select Calendar', 'sugar-calendar-lite' ),
			GoogleCalendar::SYNC_OFF_VALUE => __( 'Don\'t Sync Events', 'sugar-calendar-lite' ),
		];
		$ids     = [];

		foreach ( $calendars as $calendar ) {
			$options[ $calendar['id'] ] = $calendar['name'];
			$ids[]                      = $calendar['id'];
		}

		$selected = in_array( $saved_id, $ids, true ) ? $saved_id : $this->get_no_calendar_value();

		// List unavailable: keep showing what was saved rather than an empty control.
		if ( $calendars === [] && $saved_id !== '' ) {
			$options[ $saved_id ] = $saved_name !== '' ? $saved_name : $saved_id;
			$selected             = $saved_id;
		}

		return [ $options, $this->preselect_only_calendar( $selected, $calendars ) ];
	}

	/**
	 * With exactly one calendar and nothing chosen, preselect it; saving is still the user's click.
	 *
	 * @since 3.16.0
	 *
	 * @param string $selected  Selected value so far.
	 * @param array  $calendars The calendar list.
	 *
	 * @return string
	 */
	private function preselect_only_calendar( string $selected, array $calendars ): string {

		if ( $selected === '' && count( $calendars ) === 1 ) {
			return $calendars[0]['id'];
		}

		return $selected;
	}

	/**
	 * Dropdown value when no calendar is saved: "Don't Sync Events" if chosen, else the empty prompt.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	private function get_no_calendar_value(): string {

		return Options::get( 'google_calendar_sync_enabled', true ) ? '' : GoogleCalendar::SYNC_OFF_VALUE;
	}

	/**
	 * The saved sync direction, 'both' when it is not a known one.
	 *
	 * @since 3.16.0
	 *
	 * @return string
	 */
	public function sync_direction(): string {

		// No default argument: Options::get( $key, $default ) skips get_defaults().
		$selected = (string) Options::get( 'google_sync_direction' );

		return in_array( $selected, self::SYNC_DIRECTIONS, true ) ? $selected : 'both';
	}

	/**
	 * Whether "Don't Sync Events" is saved, which locks the sync row.
	 *
	 * @since 3.16.0
	 *
	 * @return bool
	 */
	public function is_sync_locked(): bool {

		return ! Options::get( 'google_calendar_sync_enabled', true );
	}

	/**
	 * Persist the posted direction and calendar.
	 *
	 * @since 3.16.0
	 *
	 * @param array $post_data Posted `sugar-calendar` settings array.
	 *
	 * @return bool Whether the calendar was saved.
	 */
	public function save( $post_data ): bool {

		// Saved ahead of the calendar so an empty or unreachable calendar list cannot drop it.
		$this->save_direction( $post_data );

		$calendar_id = sanitize_text_field( $post_data['google_calendar_id'] ?? '' );

		// An empty control is shown in red by the render; no notice needed.
		if ( $calendar_id === '' ) {
			return false;
		}

		// Clearing the calendar is what stops the sync; already synced copies stay as they are.
		if ( $calendar_id === GoogleCalendar::SYNC_OFF_VALUE ) {
			return $this->store_calendar( '', '', false );
		}

		$match = $this->find_calendar( $calendar_id );

		return $match !== null && $this->store_calendar( $match['id'], $match['name'], true );
	}

	/**
	 * Persist the posted sync direction when it is a known one; a locked (disabled) field posts nothing.
	 *
	 * @since 3.16.0
	 *
	 * @param array $post_data Posted `sugar-calendar` settings array.
	 *
	 * @return void
	 */
	private function save_direction( $post_data ) {

		if ( ! isset( $post_data['google_sync_direction'] ) ) {
			return;
		}

		$direction = sanitize_key( $post_data['google_sync_direction'] );

		if ( in_array( $direction, self::SYNC_DIRECTIONS, true ) ) {
			Options::update( 'google_sync_direction', $direction );
		}
	}

	/**
	 * The listed calendar with this id, or null; an unreachable list adds an error notice.
	 *
	 * @since 3.16.0
	 *
	 * @param string $calendar_id Posted calendar id.
	 *
	 * @return array|null
	 */
	private function find_calendar( string $calendar_id ) {

		$calendars = $this->get_calendars();

		// A choice was made but the list is unreachable, so nothing can be verified or saved.
		if ( $calendars === [] ) {
			WP::add_admin_notice( esc_html__( 'Could not load your Google calendars. Please try again.', 'sugar-calendar-lite' ), WP::ADMIN_NOTICE_ERROR );

			return null;
		}

		foreach ( $calendars as $calendar ) {
			if ( $calendar['id'] === $calendar_id ) {
				return $calendar;
			}
		}

		return null;
	}

	/**
	 * Store the chosen calendar.
	 *
	 * @since 3.16.0
	 *
	 * @param string $calendar_id   Calendar id, '' for none.
	 * @param string $calendar_name Calendar name, '' for none.
	 * @param bool   $sync_enabled  False when "Don't Sync Events" was chosen.
	 *
	 * @return bool Always true.
	 */
	private function store_calendar( string $calendar_id, string $calendar_name, bool $sync_enabled ): bool {

		Options::update( 'google_calendar_id', $calendar_id );
		Options::update( 'google_calendar_name', $calendar_name );
		Options::update( 'google_calendar_sync_enabled', $sync_enabled );

		return true;
	}
}
