<?php

namespace Sugar_Calendar;

/**
 * Class Settings handles setting storage and retrieval.
 *
 * @since 3.0.0
 */
class Options {

	/**
	 * Settings cache.
	 *
	 * @since 3.0.0
	 *
	 * @var null|array
	 */
	protected static $settings = null;

	/**
	 * The name of the option where all settings are stored.
	 *
	 * @since 3.0.0
	 *
	 * @var string
	 */
	const OPTION_NAME = 'sugar_calendar';

	/**
	 * Fill the cache for the current request
	 * with data from a `get_option` call.
	 * This prevents redundant `get_option` calls.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	protected static function populate() {

		if ( ! is_null( static::$settings ) ) {
			return;
		}

		static::$settings = get_option( static::OPTION_NAME, [] );
	}

	/**
	 * Sanitize an option's value before it's saved in DB.
	 *
	 * @since 3.0.0
	 *
	 * @param mixed  $value Option value.
	 * @param string $key   Option key.
	 *
	 * @return mixed|null
	 */
	protected static function sanitize_option( $value, $key ) {

		/**
		 * Filter an option's value before it's saved in DB.
		 *
		 * @since 3.0.0
		 *
		 * @param mixed  $value Option value.
		 * @param string $key   Option key.
		 */
		return apply_filters( 'sugar_calendar_options_sanitize_option', $value, $key );
	}

	/**
	 * Default settings.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public static function get_defaults() {

		$defaults = [
			'number_of_events'             => 30,
			'start_of_week'                => get_option( 'start_of_week' ),
			'date_format'                  => get_option( 'date_format' ),
			'time_format'                  => get_option( 'time_format' ),
			'day_color_style'              => 'none',
			'timezone_convert'             => false,
			'timezone_type'                => 'off',
			'timezone'                     => get_option( 'timezone_string' ),
			'editor_type'                  => 'classic',
			'custom_fields'                => true,
			'default_calendar'             => null,
			'hide_announcements'           => false,
			'allow_usage_tracking'         => false,
			'maps_google_api_key'          => '',
			'single_event_appearance_mode' => 'light',
		];

		/**
		 * Filters the default settings.
		 *
		 * @since 3.0.0
		 *
		 * @param array $defaults Default settings.
		 */
		$defaults = apply_filters( 'sugar_calendar_options_get_defaults', $defaults );

		return $defaults;
	}

	/**
	 * Add a new setting.
	 *
	 * @since 3.0.0
	 *
	 * @param string $key   The setting's key.
	 * @param mixed  $value The setting's value.
	 *
	 * @return bool
	 */
	public static function add( $key, $value ) {

		static::populate();

		$key = sanitize_key( $key );

		if ( isset( static::$settings[ $key ] ) ) {
			return false;
		}

		$value = static::sanitize_option( $value, $key );

		static::$settings[ $key ] = $value;

		return update_option( static::OPTION_NAME, static::$settings, false );
	}

	/**
	 * Get a setting.
	 *
	 * @since 3.0.0
	 *
	 * @param string $key     The setting's key.
	 * @param mixed  $default The setting's value, if it's not found.
	 *
	 * @return mixed
	 */
	public static function get( $key, $default = false ) {

		static::populate();

		$key = sanitize_key( $key );

		// If a setting value is stored, return it.
		if ( array_key_exists( $key, static::$settings ) ) {
			return static::$settings[ $key ];
		}

		// If function was called with a default, return it.
		if ( func_num_args() > 1 ) {
			return $default;
		}

		// If a function was called without a default,
		// and a default value is defined,
		// return it.
		$defaults = static::get_defaults();

		if ( array_key_exists( $key, $defaults ) ) {
			return $defaults[ $key ];
		}

		return $default;
	}

	/**
	 * Update a setting.
	 *
	 * @since 3.0.0
	 *
	 * @param string $key   The setting's key.
	 * @param mixed  $value The setting's value.
	 *
	 * @return bool
	 */
	public static function update( $key, $value ) {

		static::populate();

		$key   = sanitize_key( $key );
		$value = static::sanitize_option( $value, $key );

		static::$settings[ $key ] = $value;

		return update_option( static::OPTION_NAME, static::$settings, false );
	}

	/**
	 * Delete a setting.
	 *
	 * @since 3.0.0
	 *
	 * @param string $key The setting's key.
	 *
	 * @return bool
	 */
	public static function delete( $key ) {

		static::populate();

		$key = sanitize_key( $key );

		unset( static::$settings[ $key ] );

		return update_option( static::OPTION_NAME, static::$settings, false );
	}

	/**
	 * Get all settings.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	public static function all() {

		static::populate();

		return wp_parse_args( static::$settings, static::get_defaults() );
	}
}
