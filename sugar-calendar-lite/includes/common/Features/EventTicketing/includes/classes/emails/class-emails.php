<?php
/**
 * Events: Sugar_Calendar\AddOn\Ticketing\Emails class
 */

namespace Sugar_Calendar\AddOn\Ticketing;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Settings as Settings;

/**
 * Emails class
 *
 * @since 1.0.0
 */
class Emails {

	/**
	 * Holds the from address
	 *
	 * @since 1.0.0
	 */
	private $from_address;

	/**
	 * Holds the from name
	 *
	 * @since 1.0.0
	 */
	private $from_name;

	/**
	 * Holds the email content type
	 *
	 * @since 1.0.0
	 */
	private $content_type;

	/**
	 * Holds the email headers
	 *
	 * @since 1.0.0
	 */
	private $headers;

	/**
	 * Whether to send email in HTML
	 *
	 * @since 1.0.0
	 */
	private $html = true;

	/**
	 * The email template to use
	 *
	 * @since 1.0.0
	 */
	private $template;

	/**
	 * The header text for the email
	 *
	 * @since 1.0.0
	 */
	private $heading = '';

	/**
	 * Container for storing all tags
	 *
	 * @since 1.0.0
	 */
	private $tags;

	/**
	 * Object ID
	 *
	 * @since 1.0.0
	 */
	private $object_id;

	/**
	 * Object type
	 *
	 * @since 1.0.0
	 */
	private $object_type = 'order';

	/**
	 * Get things going
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		if ( 'none' === $this->get_template() ) {
			$this->html = false;
		}

		add_action( 'sc_email_send_before', [ $this, 'send_before' ] );
		add_action( 'sc_email_send_after', [ $this, 'send_after' ] );
	}

	/**
	 * Set a property
	 *
	 * @since 1.0.0
	 */
	public function __set( $key, $value ) {

		$this->$key = $value;
	}

	/**
	 * Get the email from name
	 *
	 * @since 1.0.0
	 * @return string The email from name
	 */
	public function get_from_name() {

		if ( empty( $this->from_name ) ) {
			$this->from_name = Settings\get_setting( 'receipt_from_name', get_bloginfo( 'name' ) );
		}

		/**
		 * Filters the From name for sending emails.
		 *
		 * @since 1.0.0
		 *
		 * @param string  $name Email From name.
		 * @param \Emails $this Email class instance.
		 */
		return apply_filters( 'sc_email_from_name', wp_specialchars_decode( $this->from_name ), $this );
	}

	/**
	 * Get the email from address
	 *
	 * @since 1.0.0
	 *
	 * @return string The email from address
	 */
	public function get_from_address() {

		if ( empty( $this->from_address ) ) {
			$this->from_address = Settings\get_setting( 'receipt_from_email', get_option( 'admin_email' ) );
		}

		/**
		 * Filters the From email for sending emails.
		 *
		 * @since 1.0.0
		 *
		 * @param string  $from_address Email address to send from.
		 * @param \Emails $this         Email class instance.
		 */
		return apply_filters( 'sc_email_from_address', $this->from_address, $this );
	}

	/**
	 * Get the email content type
	 *
	 * @since 1.0.0
	 *
	 * @return string The email content type
	 */
	public function get_content_type() {

		if ( empty( $this->content_type ) && ! empty( $this->html ) ) {
			$this->content_type = apply_filters( 'sc_email_default_content_type', 'text/html', $this );
		} elseif ( empty( $this->html ) ) {
			$this->content_type = 'text/plain';
		}

		return apply_filters( 'sc_email_content_type', $this->content_type, $this );
	}

	/**
	 * Get the email headers
	 *
	 * @since 1.0.0
	 *
	 * @return string The email headers
	 */
	public function get_headers() {

		if ( empty( $this->headers ) ) {
			$this->headers = "From: {$this->get_from_name()} <{$this->get_from_address()}>\r\n";
			$this->headers .= "Reply-To: {$this->get_from_address()}\r\n";
			$this->headers .= "Content-Type: {$this->get_content_type()}; charset=utf-8\r\n";
		}

		/**
		 * Filters the headers sent when sending emails.
		 *
		 * @since 1.0.0
		 *
		 * @param array   $headers Array of constructed headers.
		 * @param \Emails $this    Email class instance.
		 */
		return apply_filters( 'sc_email_headers', $this->headers, $this );
	}

	/**
	 * Retrieves email templates.
	 *
	 * @since 1.0.0
	 *
	 * @return array The email templates.
	 */
	public function get_templates() {

		$templates = [
			'default' => esc_html__( 'Default Template', 'sugar-calendar-lite' ),
			'none'    => esc_html__( 'No template, plain text only', 'sugar-calendar-lite' ),
		];

		/**
		 * Filters the list of email templates.
		 *
		 * @since 1.0.0
		 *
		 * @param array $templates Key/value pairs of templates where the key is the slug
		 *                         and the value is the translatable label.
		 */
		return apply_filters( 'sc_email_templates', $templates );
	}

	/**
	 * Get the enabled email template
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function get_template() {

		if ( empty( $this->template ) ) {
			$this->template = 'default';
		}

		/**
		 * Filters the template for the current email.
		 *
		 * @since 1.0.0
		 *
		 * @param string $template Current template slug.
		 */
		return apply_filters( 'sc_email_template', $this->template );
	}

	/**
	 * Get the header text for the email
	 *
	 * @since 1.0.0
	 *
	 * @return string The header text
	 */
	public function get_heading() {

		/**
		 * Filters the header text for the current email.
		 *
		 * @since 1.0.0
		 *
		 * @param string $heading Header text.
		 */
		return apply_filters( 'sc_email_heading', $this->heading );
	}

	/**
	 * Build the email
	 *
	 * @since 1.0.0
	 *
	 * @param string $message The email message
	 *
	 * @return string
	 */
	public function build_email( $message ) {

		if ( false === $this->html ) {
			/**
			 * Filters the message contents of the current email.
			 *
			 * @since 1.0.0
			 *
			 * @param string  $message Email message contents.
			 * @param \Emails $this    Email class instance.
			 */
			return apply_filters( 'sc_email_message', wp_strip_all_tags( $message ), $this );
		}

		ob_start();

		include SC_CORE_ET_PLUGIN_DIR . 'includes/templates/email.php';

		$body = ob_get_clean();

		$message = str_replace( '{email}', $message, $body );
		$message = str_replace( '{heading}', $this->heading, $message );

		/** This filter is documented in includes/emails/class-emails.php */
		return apply_filters( 'sc_email_message', $message, $this );
	}

	/**
	 * Send the email
	 *
	 * @since 1.0.0
	 *
	 * @param string       $to          The To address
	 * @param string       $subject     The subject line of the email
	 * @param string       $message     The body of the email
	 * @param string|array $attachments Attachments to the email
	 */
	public function send( $to, $subject, $message, $attachments = '' ) {

		if ( ! did_action( 'init' ) && ! did_action( 'admin_init' ) ) {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'You cannot send emails with sc_Emails until init/admin_init has been reached', 'sugar-calendar-lite' ), null );

			return false;
		}

		$this->setup_email_tags();

		/**
		 * Hooks before email is sent
		 *
		 * @since 1.0
		 */
		do_action( 'sc_email_send_before', $this );

		$message = $this->build_email( $message );
		$message = $this->parse_tags( $message );
		$message = $this->text_to_html( $message );

		/**
		 * Filters the attachments for the current email (if any).
		 *
		 * @since 1.0
		 *
		 * @param array   $attachments Attachments for the email (if any).
		 * @param \Emails $this        Email class instance.
		 */
		$attachments = apply_filters( 'sc_email_attachments', $attachments, $this );

		$sent = wp_mail( $to, $subject, $message, $this->get_headers(), $attachments );

		/**
		 * Hooks after the email is sent
		 *
		 * @since 1.0
		 */
		do_action( 'sc_email_send_after', $this );

		return $sent;
	}

	/**
	 * Add filters/actions before the email is sent
	 *
	 * @since 1.0.0
	 */
	public function send_before() {

		add_filter( 'wp_mail_from', [ $this, 'get_from_address' ] );
		add_filter( 'wp_mail_from_name', [ $this, 'get_from_name' ] );
		add_filter( 'wp_mail_content_type', [ $this, 'get_content_type' ] );
	}

	/**
	 * Remove filters/actions after the email is sent
	 *
	 * @since 1.0.0
	 */
	public function send_after() {

		remove_filter( 'wp_mail_from', [ $this, 'get_from_address' ] );
		remove_filter( 'wp_mail_from_name', [ $this, 'get_from_name' ] );
		remove_filter( 'wp_mail_content_type', [ $this, 'get_content_type' ] );

		// Reset heading to an empty string
		$this->heading = '';
	}

	/**
	 * Converts text formatted HTML. This is primarily for turning line breaks into <p> and <br/> tags.
	 *
	 * @since 1.0.0
	 */
	public function text_to_html( $message ) {

		if ( 'text/html' === $this->content_type || true === $this->html ) {
			$message = wpautop( make_clickable( $message ), false );
			$message = str_replace( '&#038;', '&amp;', $message );
		}

		return $message;
	}

	/**
	 * Search content for email tags and filter email tags through their hooks
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Content to search for email tags
	 *
	 * @return string $content Filtered content
	 */
	private function parse_tags( $content ) {

		// Make sure there's at least one tag
		if ( empty( $this->tags ) || ! is_array( $this->tags ) ) {
			return $content;
		}

		$new_content = preg_replace_callback( "/{([A-z0-9\-\_]+)}/s", [ $this, 'do_tag' ], $content );

		return $new_content;
	}

	/**
	 * Setup all registered email tags
	 *
	 * @since 1.0.0
	 */
	private function setup_email_tags() {

		$tags = $this->get_tags( 'all' );

		if ( empty( $tags ) ) {
			return;
		}

		foreach ( $tags as $tag ) {
			if ( isset( $tag['function'] ) && is_callable( $tag['function'] ) ) {
				$this->tags[ $tag['tag'] ] = $tag;
			}
		}
	}

	/**
	 * Retrieve all registered email tags
	 *
	 * @since 1.0.0
	 *
	 * @param $object_type The tag type to return; order, ticket, event, or attendee
	 *
	 * @return array
	 */
	public function get_tags( $object_type = 'order' ) {

		// Setup default tags array
		$email_tags = [
			[
				'tag'         => 'name',
				'description' => esc_html__( 'The display name of the customer', 'sugar-calendar-lite' ),
				'function'    => '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_name',
				'type'        => 'order',
			],
			[
				'tag'         => 'email',
				'description' => esc_html__( 'The email address of the customer', 'sugar-calendar-lite' ),
				'function'    => '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_email',
				'type'        => 'order',
			],
			[
				'tag'         => 'order_id',
				'description' => esc_html__( 'The ID number of the order', 'sugar-calendar-lite' ),
				'function'    => '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_order_id',
				'type'        => 'order',
			],
			[
				'tag'         => 'order_amount',
				'description' => esc_html__( 'The total purchase amount of the order', 'sugar-calendar-lite' ),
				'function'    => '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_order_amount',
				'type'        => 'order',
			],
			[
				'tag'         => 'order_date',
				'description' => esc_html__( 'The purchase date of the order', 'sugar-calendar-lite' ),
				'function'    => '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_order_date',
				'type'        => 'order',
			],
			[
				'tag'         => 'receipt_url',
				'description' => esc_html__( 'URL to view the receipt in the browser', 'sugar-calendar-lite' ),
				'function'    => '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_receipt_url',
				'type'        => 'order',
			],
			[
				'tag'         => 'tickets',
				'description' => esc_html__( 'Outputs a list of tickets in the order', 'sugar-calendar-lite' ),
				'function'    => '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_tickets',
				'type'        => 'order',
			],
			[
				'tag'         => 'event_id',
				'description' => esc_html__( 'The ID number of the event associated with the order', 'sugar-calendar-lite' ),
				'function'    => '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_event_id',
				'type'        => 'event',
			],
			[
				'tag'         => 'event_title',
				'description' => esc_html__( 'The title of the event associated with the order', 'sugar-calendar-lite' ),
				'function'    => '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_event_title',
				'type'        => 'event',
			],
			[
				'tag'         => 'event_url',
				'description' => esc_html__( 'The URL of the event details page', 'sugar-calendar-lite' ),
				'function'    => '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_event_url',
				'type'        => 'event',
			],
			[
				'tag'         => 'event_date',
				'description' => esc_html__( 'The date of the event associated with the order', 'sugar-calendar-lite' ),
				'function'    => '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_event_date',
				'type'        => 'event',
			],
			[
				'tag'         => 'event_start_time',
				'description' => esc_html__( 'The start time of the event associated with the order', 'sugar-calendar-lite' ),
				'function'    => '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_event_start_time',
				'type'        => 'event',
			],
			[
				'tag'         => 'event_end_time',
				'description' => esc_html__( 'The end time of the event associated with the order', 'sugar-calendar-lite' ),
				'function'    => '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_event_start_time',
				'type'        => 'event',
			],
			[
				'tag'         => 'ticket_id',
				'description' => esc_html__( 'The ticket ID number', 'sugar-calendar-lite' ),
				'function'    => '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_ticket_id',
				'type'        => 'ticket',
			],
			[
				'tag'         => 'ticket_code',
				'description' => esc_html__( 'The ticket code', 'sugar-calendar-lite' ),
				'function'    => '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_ticket_code',
				'type'        => 'ticket',
			],
			[
				'tag'         => 'ticket_url',
				'description' => esc_html__( 'The URL to the ticket details', 'sugar-calendar-lite' ),
				'function'    => '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_ticket_url',
				'type'        => 'ticket',
			],
			[
				'tag'         => 'attendee_name',
				'description' => esc_html__( 'The name of the attendee assigned to a ticket, if provided', 'sugar-calendar-lite' ),
				'function'    => '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_attendee_name',
				'type'        => 'attendee',
			],
			[
				'tag'         => 'attendee_email',
				'description' => esc_html__( 'The email of the attendee assigned to a ticket, if provided', 'sugar-calendar-lite' ),
				'function'    => '\Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\get_email_tag_attendee_email',
				'type'        => 'attendee',
			],
		];

		if ( 'all' !== $object_type ) {
			// Filter the default list by
			$email_tags = wp_list_filter( $email_tags, [ 'type' => $object_type ] );
		}

		/**
		 * Filters the supported email tags and their attributes.
		 *
		 * @since 1.0
		 *
		 * @param array   $email_tags  {
		 *                             Email tags and their attributes
		 *
		 * @type string   $tag         Email tag slug.
		 * @type string   $description Translatable description for what the email tag represents.
		 * @type callable $function    Callback function for rendering the email tag.
		 * @type string   $type        The object type this tag is for.
		 *                             }
		 *
		 * @param \Emails $this        Email class instance.
		 */
		return apply_filters( 'sc_email_tags', $email_tags, $object_type, $this );
	}

	/**
	 * Parse a specific tag.
	 *
	 * @since 1.0.0
	 *
	 * @param $m Message
	 */
	private function do_tag( $m ) {

		// Get tag
		$tag = $m[1];

		// Return tag if not set
		if ( ! $this->email_tag_exists( $tag ) ) {
			return $m[0];
		}

		return call_user_func( $this->tags[ $tag ]['function'], $this->object_id, $this->object_type, $tag );
	}

	/**
	 * Check if $tag is a registered email tag
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag Email tag that will be searched
	 *
	 * @return bool True if exists, false otherwise
	 */
	public function email_tag_exists( $tag ) {

		return array_key_exists( $tag, $this->tags );
	}
}
