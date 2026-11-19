<?php

namespace Sugar_Calendar\Vendor\ProductApi\Http\Middleware;

use Sugar_Calendar\Vendor\ProductApi\Http\Request;
use Sugar_Calendar\Vendor\ProductApi\Http\Response;
use Sugar_Calendar\Vendor\ProductApi\Options;
use WP_Error;
/**
 * Refusal Pause Middleware.
 *
 * After the API refuses a request with a 422, skips the same request for a period
 * instead of sending it again on the next run. A 422 is the answer to what the request
 * carries, so it does not change by itself, while every other failure (a 429, a 5xx,
 * a timeout) leaves the request to the next run as before.
 *
 * The pause is kept in the plugin option, not in a transient, so it holds on sites
 * whose transients do not survive between requests. It is bound to a fingerprint of
 * what the request depends on, so a change there (a new license key, a new site URL)
 * lets the request through at once.
 *
 * @since 1.3.1
 */
class RefusalPauseMiddleware implements MiddlewareInterface
{
    /**
     * Status code that starts a pause.
     *
     * @since 1.3.1
     */
    const REFUSAL_STATUS_CODE = 422;
    /**
     * Action name.
     *
     * @since 1.3.1
     *
     * @var string
     */
    private $action;
    /**
     * Options instance.
     *
     * @since 1.3.1
     *
     * @var Options
     */
    private $options;
    /**
     * Fingerprint of what the request depends on.
     *
     * @since 1.3.1
     *
     * @var string
     */
    private $fingerprint;
    /**
     * Pause duration in seconds.
     *
     * @since 1.3.1
     *
     * @var int
     */
    private $duration;
    /**
     * Error message while the request is paused.
     *
     * @since 1.3.1
     *
     * @var string|null
     */
    private $error_message;
    /**
     * Constructor.
     *
     * @since 1.3.1
     *
     * @param string  $action        Action name (e.g., 'events_register_site').
     * @param Options $options       Options instance.
     * @param string  $fingerprint   Fingerprint of what the request depends on.
     * @param int     $duration      Pause duration in seconds.
     * @param string  $error_message Error message while the request is paused (optional).
     */
    public function __construct($action, Options $options, $fingerprint, $duration, $error_message = null)
    {
        $this->action = (string) $action;
        $this->options = $options;
        $this->fingerprint = (string) $fingerprint;
        $this->duration = (int) $duration;
        $this->error_message = $error_message;
    }
    /**
     * Handle the request.
     *
     * @since 1.3.1
     *
     * @param Request  $request The request object.
     * @param callable $next    The next middleware in the stack.
     *
     * @return mixed Response|WP_Error
     */
    public function handle(Request $request, callable $next)
    {
        if ($this->is_paused()) {
            $message = $this->error_message ? $this->error_message : esc_html__('The request was refused recently. It will be sent again later.', 'wpforms-product-api-client');
            return new WP_Error('request_paused', $message);
        }
        $response = $next($request);
        if ($response instanceof Response && (int) $response->get_status_code() === self::REFUSAL_STATUS_CODE) {
            $this->pause();
        }
        return $response;
    }
    /**
     * Whether the request is paused.
     *
     * A pause that ends further away than one duration from now was written under a clock
     * that has since moved back, and is ignored rather than kept for longer than intended.
     *
     * @since 1.3.1
     *
     * @return bool
     */
    private function is_paused()
    {
        if ($this->duration <= 0) {
            return \false;
        }
        $pause = $this->options->get($this->get_option_key());
        if (!is_array($pause) || !isset($pause['until'], $pause['fingerprint']) || !is_string($pause['fingerprint'])) {
            return \false;
        }
        $until = (int) $pause['until'];
        $now = time();
        if ($until <= $now || $until > $now + $this->duration) {
            return \false;
        }
        return hash_equals($pause['fingerprint'], $this->fingerprint);
    }
    /**
     * Pause the request for one duration from now.
     *
     * @since 1.3.1
     */
    private function pause()
    {
        if ($this->duration <= 0) {
            return;
        }
        $this->options->update([$this->get_option_key() => ['until' => time() + $this->duration, 'fingerprint' => $this->fingerprint]]);
    }
    /**
     * Get the option key the pause is stored under.
     *
     * @since 1.3.1
     *
     * @return string
     */
    private function get_option_key()
    {
        return 'pauses.' . $this->action;
    }
}
