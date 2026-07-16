<?php

namespace Sugar_Calendar\Vendor\ProductApi\Events;

/**
 * Product API Event.
 *
 * @since 1.0.0
 */
class Event
{
    /**
     * Event name.
     *
     * @since 1.0.0
     *
     * @var string
     */
    private $name;
    /**
     * Event properties.
     *
     * @since 1.0.0
     *
     * @var array
     */
    private $properties;
    /**
     * Event time.
     *
     * @since 1.0.0
     *
     * @var string
     */
    private $time;
    /**
     * Event context (user or system).
     *
     * @since 1.0.0
     *
     * @var string
     */
    private $context = 'user';
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param string $name       Event name.
     * @param array  $properties Event properties.
     */
    public function __construct($name, $properties = [])
    {
        $this->name = $name;
        $this->properties = $properties;
        $this->time = gmdate('Y-m-d\TH:i:s\Z');
    }
    /**
     * Set event context.
     *
     * @since 1.0.0
     *
     * @param string $context Event context.
     */
    public function context($context)
    {
        $this->context = $context;
        return $this;
    }
    /**
     * Get event name.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_name()
    {
        return $this->name;
    }
    /**
     * Get event properties.
     *
     * @since 1.0.0
     *
     * @return array
     */
    public function get_properties()
    {
        return $this->properties;
    }
    /**
     * Get event time.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_time()
    {
        return $this->time;
    }
    /**
     * Get event context.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_context()
    {
        return $this->context;
    }
    /**
     * Get event key for deduplication.
     *
     * Key is based on event name, properties, and context (excludes time).
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_key()
    {
        return md5(wp_json_encode([$this->name, $this->properties, $this->context]));
    }
}
