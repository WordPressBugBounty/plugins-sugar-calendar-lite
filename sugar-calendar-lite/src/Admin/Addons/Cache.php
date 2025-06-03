<?php

namespace Sugar_Calendar\Admin\Addons;

use Sugar_Calendar\Helpers\Helpers;

/**
 * Addons cache handler.
 *
 * @since 3.7.0
 */
class Cache {

	/**
	 * Option name.
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	private $option_name = 'sugar_calendar_addons_cache';

	/**
	 * Timestamp transient name.
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	private $timestamp_transient_name = 'sugar_calendar_addons_cache_timestamp';

	/**
	 * Request lock time.
	 *
	 * @since 3.7.0
	 *
	 * @var int
	 */
	private $request_lock_time = 15;

	/**
	 * Remote source URL.
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	const REMOTE_SOURCE = 'https://events.sugarcalendarapi.com/feeds/v1/addons/';

	/**
	 * Get data from cache or from API call.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	public function get() {

		if ( $this->is_expired() ) {
			$this->update();
		}

		$cache = get_option( $this->option_name );

		if ( empty( $cache ) ) {
			$this->update();

			$cache = get_option( $this->option_name );
		}

		return (array) json_decode( $cache, true );
	}

	/**
	 * Determine if the cache is expired.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	private function is_expired(): bool {

		return $this->get_timestamp() + $this->get_ttl() < time();
	}

	/**
	 * Get cache creation time.
	 *
	 * @since 3.7.0
	 *
	 * @return int
	 */
	private function get_timestamp() {

		return (int) get_transient( $this->timestamp_transient_name );
	}

	/**
	 * Get cache time-to-live.
	 *
	 * @since 3.7.0
	 *
	 * @return int
	 */
	public function get_ttl() {

		/**
		 * Filter addons cache time-to-live.
		 *
		 * @since 3.7.0
		 *
		 * @param int $ttl Time-to-live, in seconds.
		 */
		return (int) apply_filters( 'sugar_calendar_admin_addons_cache_ttl', WEEK_IN_SECONDS );
	}

	/**
	 * Update cache.
	 *
	 * @since 3.7.0
	 *
	 * @param bool $force Force update.
	 *
	 * @return bool
	 */
	public function update( bool $force = false ) {

		if (
			! $force &&
			time() < $this->get_timestamp() + $this->request_lock_time * MINUTE_IN_SECONDS
		) {
			return false;
		}

		set_transient( $this->timestamp_transient_name, time(), $this->get_ttl() );

		$addons = $this->prepare_addons( $this->fetch() );

		update_option( $this->option_name, wp_json_encode( $addons ) );

		return true;
	}

	/**
	 * Get remote source URL.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	private function get_remote_source(): string {

		return defined( 'SUGAR_CALENDAR_ADDONS_REMOTE_SOURCE' ) ? SUGAR_CALENDAR_ADDONS_REMOTE_SOURCE : self::REMOTE_SOURCE;
	}

	/**
	 * Get data from API.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	private function fetch(): array { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.CyclomaticComplexity.TooHigh

		$request = wp_remote_get(
			$this->get_remote_source(),
			[
				'timeout'    => 10,
				'user-agent' => Helpers::get_default_user_agent(),
			]
		);

		// Bail if the request failed.
		if ( is_wp_error( $request ) ) {
			return [];
		}

		$response_code = wp_remote_retrieve_response_code( $request );
		$response_body = wp_remote_retrieve_body( $request );

		// Bail if the response code is not 2xx or 3xx.
		if ( $response_code > 399 ) {
			return [];
		}

		$json = trim( $response_body );
		$data = json_decode( $json, true );

		// Bail if data is empty.
		if ( empty( $data ) ) {
			return [];
		}

		return $data;
	}

	/**
	 * Prepare addons data.
	 *
	 * @since 3.7.0
	 *
	 * @param array $addons Addons data.
	 *
	 * @return array
	 */
	private function prepare_addons( $addons ) {

		if ( empty( $addons ) || ! is_array( $addons ) ) {
			return [];
		}

		// Exclude core plugin.
		$addons = array_filter( $addons, fn( $addon ) => $addon['slug'] !== 'sugar-calendar' );

		$addons_cache = [];

		foreach ( $addons as $addon ) {
			// Addon icon.
			$addon['icon'] = str_replace( 'sc-', '', $addon['slug'] ) . '.png';

			// Use slug as a key for further usage.
			$addons_cache[ $addon['slug'] ] = $addon;
		}

		return $addons_cache;
	}
}

