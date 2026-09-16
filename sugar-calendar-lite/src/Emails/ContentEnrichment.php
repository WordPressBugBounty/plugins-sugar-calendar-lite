<?php

namespace Sugar_Calendar\Emails;

use Sugar_Calendar\AddOn\Ticketing\Emails;
use Sugar_Calendar\Features\RegistrationForm\Frontend\OrderEmailResumeLink;
use Throwable;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\add_online_meeting_email_filter;

/**
 * Own first-party content callbacks for one email render.
 *
 * @since 3.14.0
 */
class ContentEnrichment {

	/**
	 * Prepared sc_email_message callbacks.
	 *
	 * @since 3.14.0
	 *
	 * @var callable[]
	 */
	private $pending_callbacks = [];

	/**
	 * Frame-bound sc_email_message callbacks.
	 *
	 * @since 3.14.0
	 *
	 * @var callable[]
	 */
	private $active_callbacks = [];

	/**
	 * Sender owning the active render frame.
	 *
	 * @since 3.14.0
	 *
	 * @var Emails|null
	 */
	private $sender;

	/**
	 * Active render-frame token.
	 *
	 * @since 3.14.0
	 *
	 * @var object|null
	 */
	private $frame_token;

	/**
	 * Install ticket content enrichments.
	 *
	 * @since 3.14.0
	 *
	 * @param int $event_id SC event id.
	 *
	 * @return void
	 */
	public function install_ticket( int $event_id ): void {

		if ( $event_id <= 0 ) {
			return;
		}

		$this->store( add_online_meeting_email_filter( $event_id, true ) );
	}

	/**
	 * Install receipt content enrichments in delivery order.
	 *
	 * @since 3.14.0
	 *
	 * @param int    $order_id            Order id.
	 * @param int    $event_id            SC event id.
	 * @param string $resume_url_override Optional emitted registration URL.
	 *
	 * @return void
	 */
	public function install_receipt( int $order_id, int $event_id, string $resume_url_override = '' ): void {

		$this->install_ticket( $event_id );
		$this->store( OrderEmailResumeLink::install( $order_id, $resume_url_override, true ) );
	}

	/**
	 * Bind prepared callbacks to one exact Emails render frame.
	 *
	 * @since 3.14.0
	 *
	 * @param Emails $sender      Email renderer.
	 * @param object $frame_token Unique render-frame token.
	 *
	 * @throws Throwable When a callback cannot be registered.
	 *
	 * @return bool Whether this invocation acquired the render scope.
	 */
	public function activate( Emails $sender, object $frame_token ): bool { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks -- Registers only callbacks prepared and owned by this render scope.

		if ( $this->sender !== null ) {
			return false;
		}

		$this->sender      = $sender;
		$this->frame_token = $frame_token;

		try {
			$this->register_pending_callbacks( $sender, $frame_token );

			return true;
		} catch ( Throwable $exception ) {
			$this->remove();

			throw $exception;
		}
	}

	/**
	 * Register prepared callbacks for an acquired render scope.
	 *
	 * @since 3.14.0
	 *
	 * @param Emails $sender      Email renderer.
	 * @param object $frame_token Unique render-frame token.
	 *
	 * @return void
	 */
	private function register_pending_callbacks( Emails $sender, object $frame_token ): void { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks -- Registers only callbacks prepared and owned by this render scope.

		foreach ( $this->pending_callbacks as $prepared_callback ) {
			$callback = null;
			$callback = static function ( $message, $email = null ) use ( $prepared_callback, $sender, $frame_token, &$callback ) {

				if ( $email !== $sender || ! $sender->is_render_frame( $frame_token ) ) {
					return $message;
				}

				remove_filter( 'sc_email_message', $callback, 20 );

				return call_user_func( $prepared_callback, $message );
			};

			$this->active_callbacks[] = $callback;

			add_filter( 'sc_email_message', $callback, 20, 2 );
		}

		$this->pending_callbacks = [];
	}

	/**
	 * Remove every callback still owned by this render.
	 *
	 * @since 3.14.0
	 *
	 * @return void
	 */
	public function remove(): void { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks -- Removes only the exact render-scoped callbacks stored by this instance.

		foreach ( $this->active_callbacks as $callback ) {
			remove_filter( 'sc_email_message', $callback, 20 );
		}

		$this->pending_callbacks = [];
		$this->active_callbacks  = [];
		$this->sender            = null;
		$this->frame_token       = null;
	}

	/**
	 * Store an installed callback.
	 *
	 * @since 3.14.0
	 *
	 * @param callable|null $callback Installed callback.
	 *
	 * @return void
	 */
	private function store( $callback ): void {

		if ( $callback !== null ) {
			$this->pending_callbacks[] = $callback;
		}
	}
}
