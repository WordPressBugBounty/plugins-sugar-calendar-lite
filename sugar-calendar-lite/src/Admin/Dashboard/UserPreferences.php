<?php

namespace Sugar_Calendar\Admin\Dashboard;

/**
 * What a user's Dashboard remembers: the inline cards they dismissed and each widget's settings.
 *
 * @since 3.18.0
 */
class UserPreferences {

	/**
	 * Per-site user option for dismissed inline cards, card id => the `CardEpisodes` marker it was dismissed under.
	 *
	 * Per site because the markers are: a network-wide value would hide a card on every site still at marker 0.
	 *
	 * @since 3.18.0
	 */
	const DISMISSED_META_KEY = 'sugar_calendar_dashboard_dismissed';

	/**
	 * Per-site user option for each widget's remembered settings, widget id => settings.
	 *
	 * Remembered UI state only, such as the Activity tab: layout choices belong in screen options, so a
	 * Reset there leaves these alone.
	 *
	 * @since 3.18.0
	 */
	const SETTINGS_META_KEY = 'sugar_calendar_dashboard_settings';

	/**
	 * User ID.
	 *
	 * @since 3.18.0
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Constructor.
	 *
	 * @since 3.18.0
	 *
	 * @param int $user_id User ID.
	 */
	public function __construct( int $user_id ) {

		$this->user_id = $user_id;
	}

	/**
	 * Dismissed inline cards, card id => marker.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	public function get_dismissals(): array {

		return $this->get_array( self::DISMISSED_META_KEY );
	}

	/**
	 * Every widget's remembered settings, widget id => settings.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	public function get_widget_settings(): array {

		return $this->get_array( self::SETTINGS_META_KEY );
	}

	/**
	 * Remember that the user dismissed a card, under the marker of the card's current episode.
	 *
	 * @since 3.18.0
	 *
	 * @param string $card_id Card ID.
	 * @param int    $marker  The card's `CardEpisodes` marker.
	 */
	public function dismiss( string $card_id, int $marker ): void {

		$dismissals             = $this->get_dismissals();
		$dismissals[ $card_id ] = $marker;

		update_user_option( $this->user_id, self::DISMISSED_META_KEY, $dismissals );
	}

	/**
	 * Remember settings for a widget, over the ones it already has.
	 *
	 * @since 3.18.0
	 *
	 * @param string $widget_id Widget identifier.
	 * @param array  $settings  Settings, already checked against the widget's.
	 */
	public function save_widget_settings( string $widget_id, array $settings ): void {

		$all = $this->get_widget_settings();

		$all[ $widget_id ] = array_merge( (array) ( $all[ $widget_id ] ?? [] ), $settings );

		update_user_option( $this->user_id, self::SETTINGS_META_KEY, $all );
	}

	/**
	 * A user option array, empty when unset or not an array.
	 *
	 * @since 3.18.0
	 *
	 * @param string $key Option name.
	 *
	 * @return array
	 */
	private function get_array( string $key ): array {

		$value = get_user_option( $key, $this->user_id );

		return is_array( $value ) ? $value : [];
	}
}
