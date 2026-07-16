<?php

namespace Sugar_Calendar\Vendor\ProductApi\Http\Middleware;

use Sugar_Calendar\Vendor\ProductApi\Http\Request;
use Sugar_Calendar\Vendor\ProductApi\Http\Response;
use Sugar_Calendar\Vendor\ProductApi\Options;
use WP_Error;
/**
 * Rate Limit Middleware.
 *
 * Limits the number of requests per time period to prevent abuse.
 *
 * @since 1.0.0
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * Action name.
     *
     * @since 1.0.0
     *
     * @var string
     */
    private $action;
    /**
     * Options instance.
     *
     * @since 1.0.0
     *
     * @var Options
     */
    private $options;
    /**
     * Maximum number of requests allowed per time period.
     *
     * @since 1.0.0
     *
     * @var int
     */
    private $max_requests;
    /**
     * Time period in seconds.
     *
     * @since 1.0.0
     *
     * @var int
     */
    private $period;
    /**
     * Error message when rate limit is exceeded.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    private $error_message;
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param string  $action        Action name (e.g., 'auth_token', 'api_requests').
     * @param Options $options       Options instance.
     * @param int     $max_requests  Maximum number of requests allowed per time period.
     * @param int     $period        Time period in seconds.
     * @param string  $error_message Error message when rate limit is exceeded.
     */
    public function __construct($action, Options $options, $max_requests, $period, $error_message = null)
    {
        $this->action = $action;
        $this->options = $options;
        $this->max_requests = (int) $max_requests;
        $this->period = (int) $period;
        $this->error_message = $error_message;
    }
    /**
     * Handle the request.
     *
     * @since 1.0.0
     *
     * @param Request  $request The request object.
     * @param callable $next    The next middleware in the stack.
     *
     * @return mixed Response|WP_Error
     */
    public function handle(Request $request, callable $next)
    {
        // Check if rate limit is exceeded.
        if ($this->is_rate_limited()) {
            $message = $this->error_message ? $this->error_message : esc_html__('Rate limit exceeded. Please try again later.', 'wpforms-product-api-client');
            return new WP_Error('rate_limit_exceeded', $message);
        }
        $response = $next($request);
        // Increment request count, only if the request was actually made.
        if ($response instanceof Response) {
            $this->increment_request_count();
        }
        return $response;
    }
    /**
     * Check if rate limit is exceeded.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    private function is_rate_limited()
    {
        $request_count = $this->get_request_count();
        return $request_count >= $this->max_requests;
    }
    /**
     * Get current request count for the time period.
     *
     * @since 1.0.0
     *
     * @return int
     */
    private function get_request_count()
    {
        $transient_key = $this->get_rate_limit_transient_key();
        return (int) $this->options->get_transient($transient_key, 0);
    }
    /**
     * Increment request count for the time period.
     *
     * @since 1.0.0
     */
    private function increment_request_count()
    {
        $transient_key = $this->get_rate_limit_transient_key();
        $current_count = $this->get_request_count();
        $this->options->set_transient($transient_key, $current_count + 1, $this->period);
    }
    /**
     * Get the transient key for storing rate limit count.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private function get_rate_limit_transient_key()
    {
        return "rate_limit_{$this->action}";
    }
}
