<?php

namespace Sugar_Calendar\Vendor\ProductApi\Events;

use Sugar_Calendar\Vendor\ProductApi\Context;
use Sugar_Calendar\Vendor\ProductApi\Http\Client;
use Sugar_Calendar\Vendor\ProductApi\Http\Middleware\RateLimitMiddleware;
use Sugar_Calendar\Vendor\ProductApi\Options;
/**
 * Product Events Event Tracker class.
 *
 * @since 1.0.0
 */
class EventTracker
{
    /**
     * Context instance.
     *
     * @since 1.0.0
     *
     * @var Context
     */
    private $context;
    /**
     * Client instance.
     *
     * @since 1.0.0
     *
     * @var Client
     */
    private $client;
    /**
     * Options instance.
     *
     * @since 1.0.0
     *
     * @var Options
     */
    private $options;
    /**
     * Request-scoped queue of events to dispatch on shutdown.
     *
     * Static to allow queuing from anywhere during the request lifecycle.
     *
     * @since 1.0.0
     *
     * @var Event[]
     */
    private static $queued_events = [];
    /**
     * Whether shutdown hook has been registered.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    private static $shutdown_registered = \false;
    /**
     * Constructor.
     *
     * @param Context $context Context instance.
     * @param Options $options Options instance.
     * @param Client  $client  Client instance.
     *
     * @since 1.0.0
     */
    public function __construct(Context $context, Options $options, Client $client)
    {
        $this->context = $context;
        $this->options = $options;
        $this->client = $client;
    }
    /**
     * Queue an event for deferred dispatch on shutdown.
     *
     * Events are stored in a request-scoped queue and dispatched
     * in bulk when PHP shuts down. Duplicate events (same name,
     * properties, and context) are automatically deduplicated
     * using the event key.
     *
     * @since 1.0.0
     *
     * @param Event $event Event to queue.
     *
     * @return void
     */
    public function track(Event $event)
    {
        self::$queued_events[$event->get_key()] = $event;
        $this->maybe_register_shutdown_hook();
    }
    /**
     * Register shutdown hook if not already registered.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function maybe_register_shutdown_hook()
    {
        if (self::$shutdown_registered) {
            return;
        }
        self::$shutdown_registered = \true;
        add_action('shutdown', function () {
            $this->flush();
        });
    }
    /**
     * Flush all queued events.
     *
     * Dispatches all queued events in a single bulk request
     * and clears the queue.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function flush()
    {
        if (empty(self::$queued_events)) {
            return;
        }
        $events = array_values(self::$queued_events);
        // Clear queue before dispatch to prevent duplicates.
        self::$queued_events = [];
        $events_data = array_map([$this, 'prepare_event_data'], $events);
        $this->client->post('/product-events/v1/collect')->middleware(new RateLimitMiddleware('events_collect', $this->options, 60, HOUR_IN_SECONDS), 0)->json($this->prepare_payload($events_data))->timeout(1)->blocking(\false)->send();
    }
    /**
     * Prepare event data for transmission.
     *
     * @since 1.0.0
     *
     * @param Event $event Event instance.
     *
     * @return array Event data.
     */
    private function prepare_event_data(Event $event)
    {
        $event_data = ['event_name' => $event->get_name(), 'event_context' => $event->get_context(), 'properties' => $event->get_properties(), 'event_time' => $event->get_time(), 'system_info' => $this->get_system_info()];
        if ($event->get_context() === 'user') {
            $event_data['user_info'] = $this->get_user_info();
        }
        return $event_data;
    }
    /**
     * Get system information.
     *
     * @since 1.0.0
     *
     * @return array System information.
     */
    private function get_system_info()
    {
        return ['wp_version' => get_bloginfo('version'), 'plugin_version' => $this->context->get_plugin_version()];
    }
    /**
     * Get user information.
     *
     * @since 1.0.0
     *
     * @return array User information.
     */
    private function get_user_info()
    {
        $user = wp_get_current_user();
        if (empty($user->ID) || empty($user->user_email)) {
            $user = get_user_by('email', get_option('admin_email'));
        }
        $email = !empty($user) && !empty($user->user_email) ? $user->user_email : '';
        $first_name = !empty($user) && !empty($user->first_name) ? $user->first_name : '';
        $last_name = !empty($user) && !empty($user->last_name) ? $user->last_name : '';
        return ['id' => $user->ID, 'email' => $email, 'first_name' => $first_name, 'last_name' => $last_name, 'locale' => get_user_locale()];
    }
    /**
     * Prepare payload for transmission.
     *
     * @since 1.0.0
     *
     * @param array $events Array of event data.
     *
     * @return array Prepared payload.
     */
    private function prepare_payload(array $events)
    {
        return [
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
            'data' => base64_encode(wp_json_encode(['events' => $events])),
        ];
    }
}
