<?php
/**
 * Events: Sugar_Calendar\AddOn\Ticketing\Emails class
 */

namespace Sugar_Calendar\AddOn\Ticketing;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Settings as Settings;
use Sugar_Calendar\Emails\AdminOrderTagResolver;
use Sugar_Calendar\Emails\CalendarAttachmentPool;
use Sugar_Calendar\Emails\LegacyRoute;
use Sugar_Calendar\Emails\ModernRoute;
use Sugar_Calendar\Emails\RenderedEmail;
use Sugar_Calendar\Emails\RenderRequest;
use Sugar_Calendar\Emails\Styling;
use Sugar_Calendar\Emails\TicketingSmartTags;

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
	 * Effective filtered content type from the latest successful render.
	 *
	 * @since 3.14.0
	 *
	 * @var string|null
	 */
	private $rendered_content_type;

	/**
	 * Holds the email headers
	 *
	 * @since 1.0.0
	 */
	private $headers;

	/**
	 * Whether headers were explicitly assigned through the public setter.
	 *
	 * @since 3.14.0
	 *
	 * @var bool
	 */
	private $custom_headers = false;

	/**
	 * Whether to send email in HTML
	 *
	 * @since 1.0.0
	 */
	private $html = true;

	/**
	 * Whether a failed Modern render must use the legacy plain-text pipeline.
	 *
	 * @since 3.14.0
	 *
	 * @var bool
	 */
	private $legacy_plain_text = false;

	/**
	 * First-party content prepared for one render.
	 *
	 * @since 3.14.0
	 *
	 * @var \Sugar_Calendar\Emails\ContentEnrichment|null
	 */
	private $content_enrichment;

	/**
	 * One-shot administrator order source for the next send.
	 *
	 * @since 3.14.0
	 *
	 * @var bool|array|null
	 */
	private $admin_order_record;

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
	 * Tag registry for the current render frame, or the last top-level render.
	 *
	 * @since 1.0.0
	 * @since 3.14.0 Holds a TicketingSmartTags registry instead of a keyed array.
	 *
	 * @var TicketingSmartTags|null
	 */
	private $tags;

	/**
	 * Admin tag resolver for callbacks handed out by get_tags() outside a render.
	 *
	 * Kept per instance so those callbacks share one bounded order-record cache
	 * and a fresh sender inherits nothing. Render frames build their own.
	 *
	 * @since 3.14.0
	 *
	 * @var AdminOrderTagResolver|null
	 */
	private $admin_tags;

	/**
	 * The render request currently executing on this sender, or null.
	 *
	 * @since 3.14.0
	 *
	 * @var RenderRequest|null
	 */
	private $active_request;

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
	 * Registry identity for the notification being sent.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	private $email_id = '';

	/**
	 * Get things going.
	 *
	 * @since 1.0.0
	 * @since 3.14.0 Accept a preselected template without resolving stored styling.
	 *
	 * @param string|null $template Optional preselected template slug.
	 */
	public function __construct( $template = null ) {

		if ( is_string( $template ) && $template !== '' ) {
			$this->template = $template;
		}

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

		if ( $key === 'headers' ) {
			$this->custom_headers = ! empty( $value );
		}

		$this->$key = $value;
	}

	/**
	 * Whether a token identifies this sender's current render frame.
	 *
	 * @since 3.14.0
	 *
	 * @internal
	 *
	 * @param object $frame_token Render-frame token.
	 *
	 * @return bool
	 */
	public function is_render_frame( object $frame_token ): bool {

		return $this->active_request === $frame_token;
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

		// A field the admin saved BLANK must fall back too. get_setting()
		// resolves its default on `! isset()` alone, so a cleared field returns
		// '' and the default above never applies.
		if ( empty( $this->from_name ) ) {
			$this->from_name = get_bloginfo( 'name' );
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

		// Same blank-field gap as get_from_name(), but this one stops mail
		// outright: get_headers() builds `From: {name} <{address}>`, so an empty
		// address yields `From:  <>`, wp_mail() fails validation, and EVERY
		// ticketing email silently stops — no receipt, no ticket, no admin
		// notification, and no error anywhere for the site owner to see.
		if ( empty( $this->from_address ) ) {
			$this->from_address = get_option( 'admin_email' );
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
			$content_type   = $this->rendered_content_type !== null ? $this->rendered_content_type : $this->get_content_type();
			$this->headers  = "From: {$this->get_from_name()} <{$this->get_from_address()}>\r\n";
			$this->headers .= "Reply-To: {$this->get_from_address()}\r\n";
			$this->headers .= "Content-Type: {$content_type}; charset=utf-8\r\n";
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
			'modern'  => esc_html__( 'Modern', 'sugar-calendar-lite' ),
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

			// `Styling` resolves the stored Email Styling choice to a slug this
			// class understands: `modern`, or `default` for Plain Text and for
			// anything unrecognised. The `sc_email_template` filter below still
			// has the final say.
			$this->template = Styling::email_template();
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

		return LegacyRoute::wrap_document( $message, (string) $this->heading, $this->html, $this );
	}

	/**
	 * Render an email without running the delivery lifecycle.
	 *
	 * Smart Tag overrides are fully resolved strings inserted verbatim. An
	 * override is used only when the normal tag registry registered the same
	 * slug, and its production callback is not invoked.
	 * Accepted config keys are `modern_context`, `modern_styling`,
	 * `calendar_action`, `calendar_url`, `smart_tag_overrides`,
	 * `content_enrichment`, `admin_order_record`, and `admin_order_preview`.
	 *
	 * @since 3.14.0
	 *
	 * @param string       $subject The subject line of the email.
	 * @param string|array $message The body of the email, or the first-party
	 *                              internal copy resolution.
	 * @param array        $config  Optional render configuration.
	 *
	 *                              `modern_context` accepts an array or null to force fallback.
	 *                              `modern_styling` accepts non-empty effective styling values.
	 *                              `calendar_action` accepts an array or null and
	 *                              suppresses automatic resolution when present.
	 *                              `calendar_url` accepts an explicit string when present.
	 *                              `smart_tag_overrides` maps registered tag slugs to resolved strings.
	 *                              `content_enrichment` accepts a render-scoped ContentEnrichment instance.
	 *                              `admin_order_record` accepts true for the saved current order or an administrator fictional context.
	 *                              `admin_order_preview` uses a preview receipt only when the filtered body uses the native receipt tag.
	 *
	 * @return array {
	 *     Rendered email values.
	 *
	 *     @type string $subject      Final single-line subject.
	 *     @type string $message      Final message body.
	 *     @type string $content_type Effective filtered content type.
	 *     @type array|null $calendar_action Calendar action for the sender.
	 * }
	 */
	public function render( $subject, $message, array $config = [] ) {

		return $this->render_email( $subject, $message, $config )->to_array();
	}

	/**
	 * Run one render frame: build the request and registry, resolve the subject,
	 * try the Modern route, fall back to Legacy.
	 *
	 * @since 3.14.0
	 *
	 * @param string       $subject The subject line of the email.
	 * @param string|array $message The body, or the first-party copy resolution.
	 * @param array        $config  Render configuration.
	 *
	 * @return RenderedEmail
	 */
	private function render_email( $subject, $message, array $config ): RenderedEmail { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh -- The render frame's branching mirrors the released transform order.

		$request  = RenderRequest::from_sender(
			$this,
			[
				'email_id'          => $this->email_id,
				'object_id'         => $this->object_id,
				'heading'           => $this->heading,
				'html'              => $this->html,
				'legacy_plain_text' => $this->legacy_plain_text,
			],
			$subject,
			$message,
			$config
		);
		$previous = $this->active_request;
		$admin    = $this->email_id === AdminOrderTagResolver::EMAIL_ID
			? new AdminOrderTagResolver( $this->object_id, $this->object_type )
			: null;
		$tags     = $this->build_registry( $request->smart_tag_overrides(), $admin );
		$owns     = false;

		$this->active_request = $request;

		// email_tag_exists() after a top-level render reads that render's tags, as it always has.
		if ( $previous === null ) {
			$this->tags = $tags;
		}

		try {
			if ( $request->content_enrichment() !== null ) {
				$owns = $request->content_enrichment()->activate( $this, $request );
			}

			$tags->setup();

			if ( $admin !== null ) {
				$admin->attach( $tags )->initialize( $request->admin_order_record(), $request->admin_order_preview() );
			}

			$subject = $this->render_subject( $request, $tags, $admin );

			return ( new ModernRoute() )->render( $request, $tags, $subject )
				?? ( new LegacyRoute() )->render( $request, $tags, $admin, $subject );
		} finally {
			if ( $owns ) {
				$request->content_enrichment()->remove();
			}

			$this->active_request = $previous;
		}
	}

	/**
	 * The subject pipeline shared by both routes.
	 *
	 * Subjects are always single-line, plain-text mail headers. Block-only
	 * tags stay untouched, entities are decoded, and newlines are collapsed.
	 *
	 * @since 3.14.0
	 *
	 * @param RenderRequest              $request The render's inputs.
	 * @param TicketingSmartTags         $tags    The render's tag registry.
	 * @param AdminOrderTagResolver|null $admin   The admin resolver, when active.
	 *
	 * @return string
	 */
	private function render_subject( RenderRequest $request, TicketingSmartTags $tags, ?AdminOrderTagResolver $admin ): string {

		$subject = wp_unslash( $request->subject() );

		if ( $admin !== null && $admin->is_active() ) {
			$subject = $admin->prepare_subject( $subject );
		}

		$subject = $tags->parse( $subject, [ 'tickets', 'admin_tickets_info', 'receipt_url' ] );
		$subject = html_entity_decode( $subject, ENT_QUOTES, 'UTF-8' );

		return trim( preg_replace( '/[\r\n]+/', ' ', $subject ) );
	}

	// The legacy class owns these per-send wp_mail hooks instead of a hooks() method.
	// phpcs:disable WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks
	/**
	 * Send the email.
	 *
	 * @since 1.0.0
	 *
	 * @param string       $to          The To address.
	 * @param string       $subject     The subject line of the email.
	 * @param string|array $message     The body of the email. An array is the
	 *                                  first-party internal copy resolution from
	 *                                  `Settings\get_email_copy_resolution()`,
	 *                                  which lets the fallback follow the final
	 *                                  delivery route. Third-party callers pass a
	 *                                  string and keep the released behavior.
	 * @param string|array $attachments Attachments to the email.
	 */
	public function send( $to, $subject, $message, $attachments = '' ) {

		if ( ! did_action( 'init' ) && ! did_action( 'admin_init' ) ) {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'You cannot send emails with sc_Emails until init/admin_init has been reached', 'sugar-calendar-lite' ), null );

			return false;
		}

		$original_html                  = $this->html;
		$original_content_type          = $this->content_type;
		$original_rendered_content_type = $this->rendered_content_type;
		$original_headers               = $this->headers;
		$original_custom_headers        = $this->custom_headers;
		$original_template              = $this->template;
		$content_enrichment             = $this->content_enrichment;
		$admin_order_record             = $this->admin_order_record;
		$this->content_enrichment       = null;
		$this->admin_order_record       = null;

		try {
			/**
			 * Hooks before email is sent.
			 *
			 * @since 1.0
			 *
			 * @param Emails $emails Email sender instance.
			 */
			do_action( 'sc_email_send_before', $this ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

			$rendered = $this->render_email(
				$subject,
				$message,
				[
					'content_enrichment' => $content_enrichment,
					'admin_order_record' => $admin_order_record,
				]
			);

			// Route-final content type for the wp_mail window: get_headers() reads
			// rendered_content_type, and the wp_mail_content_type callback recomputes
			// from html with the cache cleared.
			$this->html                  = $rendered->html();
			$this->content_type          = null;
			$this->rendered_content_type = $rendered->content_type();

			if ( ! $this->custom_headers ) {
				$this->headers = null;
			}

			if ( ! empty( $rendered->calendar_action() ) ) {
				$calendar_attachment = CalendarAttachmentPool::acquire( $rendered->calendar_action() );

				if ( $calendar_attachment !== '' ) {
					if ( is_array( $attachments ) && ! in_array( $calendar_attachment, $attachments, true ) ) {
						$attachments[] = $calendar_attachment;
					} elseif ( is_string( $attachments ) ) {
						$existing = explode( "\n", str_replace( "\r\n", "\n", $attachments ) );

						if ( ! in_array( $calendar_attachment, $existing, true ) ) {
							$separator    = $attachments === '' || substr( $attachments, -1 ) === "\n" ? '' : "\n";
							$attachments .= $separator . $calendar_attachment;
						}
					}
				}
			}

			/**
			 * Filters the attachments for the current email (if any).
			 *
			 * @since 1.0
			 *
			 * @param array  $attachments Attachments for the email (if any).
			 * @param Emails $emails      Email class instance.
			 */
			$attachments = apply_filters( 'sc_email_attachments', $attachments, $this ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

			$sent = wp_mail( $to, $rendered->subject(), $rendered->message(), $this->get_headers(), $attachments );

			/**
			 * Hooks after the email is sent.
			 *
			 * @since 1.0
			 *
			 * @param Emails $emails Email sender instance.
			 */
			do_action( 'sc_email_send_after', $this ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

			return $sent;
		} finally {
			remove_filter( 'wp_mail_from', [ $this, 'get_from_address' ] );
			remove_filter( 'wp_mail_from_name', [ $this, 'get_from_name' ] );
			remove_filter( 'wp_mail_content_type', [ $this, 'get_content_type' ] );

			$this->html                  = $original_html;
			$this->content_type          = $original_content_type;
			$this->rendered_content_type = $original_rendered_content_type;
			$this->headers               = $original_headers;
			$this->custom_headers        = $original_custom_headers;
			$this->template              = $original_template;
			$this->content_enrichment    = $content_enrichment;
			$this->admin_order_record    = null;
			$this->heading               = '';
		}
	}
	// phpcs:enable WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

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

		return LegacyRoute::text_to_html( $message, 'text/html' === $this->content_type || true === $this->html );
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

		return $this->registry()->get_tags( $object_type );
	}

	/**
	 * Build a registry for this sender's current inputs, outside a render frame.
	 *
	 * @since 3.14.0
	 *
	 * @return TicketingSmartTags
	 */
	private function registry(): TicketingSmartTags {

		return $this->build_registry( [], $this->admin_resolver() );
	}

	/**
	 * Build a tag registry for this sender.
	 *
	 * @since 3.14.0
	 *
	 * @param array                      $overrides Slug => resolved string for one render, or empty.
	 * @param AdminOrderTagResolver|null $admin     Resolver whose admin tag definitions join the defaults, or null.
	 *
	 * @return TicketingSmartTags
	 */
	private function build_registry( array $overrides, ?AdminOrderTagResolver $admin ): TicketingSmartTags {

		return new TicketingSmartTags(
			$this,
			$this->object_id,
			$this->object_type,
			$overrides,
			$admin !== null ? $admin->tag_definitions() : []
		);
	}

	/**
	 * The per-instance admin resolver, or null when this sender is not the admin email.
	 *
	 * @since 3.14.0
	 *
	 * @return AdminOrderTagResolver|null
	 */
	private function admin_resolver(): ?AdminOrderTagResolver {

		if ( $this->email_id !== AdminOrderTagResolver::EMAIL_ID ) {
			return null;
		}

		if ( $this->admin_tags === null ) {
			$this->admin_tags = new AdminOrderTagResolver( $this->object_id, $this->object_type );
		}

		return $this->admin_tags;
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

		if ( $this->tags === null ) {
			$this->tags = $this->registry();
		}

		return $this->tags->exists( $tag );
	}
}
