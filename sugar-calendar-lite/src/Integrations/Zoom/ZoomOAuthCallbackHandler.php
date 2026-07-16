<?php

namespace Sugar_Calendar\Integrations\Zoom;

use Sugar_Calendar\Integrations\OAuthRelay\AbstractOAuthCallbackHandler;

/**
 * Zoom OAuth callback handler.
 *
 * @since 3.12.0
 */
class ZoomOAuthCallbackHandler extends AbstractOAuthCallbackHandler {

	/**
	 * Provider slug.
	 *
	 * @since 3.12.0
	 *
	 * @return string
	 */
	protected function get_provider(): string {

		return 'zoom';
	}
}
