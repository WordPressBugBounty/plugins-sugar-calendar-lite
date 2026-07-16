<?php

namespace Sugar_Calendar\Integrations\OAuthRelay;

/**
 * Abstract OAuth Connection.
 *
 * Default implementation of OAuthConnectionInterface that wraps a plain
 * array row from wp_sc_oauth_connections. Bookings' equivalent wraps an
 * Eloquent-shaped AbstractModel; SCE has no ORM, so we operate on an
 * ARRAY_A row and persist via OAuthConnectionModel::update().
 *
 * Concrete subclasses (e.g. ZoomOAuthConnection) may override
 * $expiration_buffer if the provider's token lifetime differs from the
 * default 300 seconds.
 *
 * @since 3.12.0
 */
abstract class AbstractOAuthConnection implements OAuthConnectionInterface {

	/**
	 * Seconds before expiration to trigger refresh.
	 *
	 * Override in subclasses for provider-specific buffers.
	 *
	 * @since 3.12.0
	 *
	 * @var int
	 */
	protected $expiration_buffer = 300;

	/**
	 * The row from wp_sc_oauth_connections.
	 *
	 * @since 3.12.0
	 *
	 * @var array
	 */
	protected $row;

	/**
	 * Constructor.
	 *
	 * @since 3.12.0
	 *
	 * @param array $row Row from wp_sc_oauth_connections.
	 */
	public function __construct( array $row ) {

		$this->row = $row;
	}

	/**
	 * Get the underlying row.
	 *
	 * @since 3.12.0
	 *
	 * @return array
	 */
	public function get_row(): array {

		return $this->row;
	}

	/**
	 * Check if token is expiring soon and needs refresh.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public function is_expiring_soon(): bool {

		if ( empty( $this->row['expires_at'] ) ) {
			return true;
		}

		$expires_ts = strtotime( $this->row['expires_at'] . ' UTC' );

		if ( $expires_ts === false ) {
			return true;
		}

		return ( time() + $this->expiration_buffer ) >= $expires_ts;
	}

	/**
	 * Get the encrypted access token.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_access_token(): string {

		return (string) ( $this->row['access_token'] ?? '' );
	}

	/**
	 * Get the encrypted refresh token.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_refresh_token(): string {

		return (string) ( $this->row['refresh_token'] ?? '' );
	}

	/**
	 * Get the OAuth app ID for token refresh.
	 *
	 * @since 3.12.0
	 *
	 * @return int
	 */
	public function get_app_id(): int {

		return (int) ( $this->row['app_id'] ?? 0 );
	}

	/**
	 * Update tokens after refresh.
	 *
	 * Restores status to 'active' if the connection was previously in error,
	 * since a successful refresh means the connection is healthy again.
	 *
	 * @since 3.12.0
	 *
	 * @param array $token_data Token data from refresh response.
	 */
	public function update_tokens( array $token_data ): void {

		$update = [
			'access_token' => $token_data['access_token'],
			'expires_at'   => gmdate( 'Y-m-d H:i:s', time() + (int) $token_data['expires_in'] ),
			'refreshed_at' => current_time( 'mysql', true ),
		];

		// Refresh token is not always returned (e.g. Google only returns it on initial auth).
		if ( ! empty( $token_data['refresh_token'] ) ) {
			$update['refresh_token'] = $token_data['refresh_token'];
		}

		// Restore active status if previously in error.
		if ( ( $this->row['status'] ?? '' ) === 'auth_error' ) {
			$update['status'] = 'active';
		}

		OAuthConnectionModel::update( (int) $this->row['id'], $update );

		$this->row = array_merge( $this->row, $update );
	}

	/**
	 * Mark the connection as having an authentication error.
	 *
	 * @since 3.12.0
	 */
	public function mark_auth_error(): void {

		OAuthConnectionModel::update( (int) $this->row['id'], [ 'status' => 'auth_error' ] );

		$this->row['status'] = 'auth_error';
	}
}
