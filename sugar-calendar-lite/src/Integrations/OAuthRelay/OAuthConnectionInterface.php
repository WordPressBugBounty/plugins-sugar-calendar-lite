<?php

namespace Sugar_Calendar\Integrations\OAuthRelay;

// phpcs:disable Squiz.Commenting.FunctionComment.ParamCommentNotCapital

/**
 * OAuth Connection Interface.
 *
 * Contract for OAuth connections. Allows provider-specific behavior
 * such as different token expiration buffers.
 *
 * @since 3.12.0
 */
interface OAuthConnectionInterface {

	/**
	 * Check if token is expiring soon and needs refresh.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public function is_expiring_soon(): bool;

	/**
	 * Get the encrypted access token.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_access_token(): string;

	/**
	 * Get the encrypted refresh token.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	public function get_refresh_token(): string;

	/**
	 * Get the OAuth app ID for token refresh.
	 *
	 * @since 3.12.0
	 *
	 * @return int
	 */
	public function get_app_id(): int;

	/**
	 * Update tokens after refresh.
	 *
	 * @since 3.12.0
	 *
	 * @param array $token_data {
	 *     Token data from refresh response.
	 *
	 *     @type string $access_token  New encrypted access token.
	 *     @type string $refresh_token New encrypted refresh token.
	 *     @type int    $expires_in    Seconds until expiration.
	 * }
	 */
	public function update_tokens( array $token_data ): void;

	/**
	 * Mark the connection as having an authentication error.
	 *
	 * Called when token refresh fails, indicating the connection
	 * needs to be re-authorized by the user.
	 *
	 * @since 3.12.0
	 */
	public function mark_auth_error(): void;
}
