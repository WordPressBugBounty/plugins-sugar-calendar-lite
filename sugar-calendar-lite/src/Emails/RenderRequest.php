<?php

namespace Sugar_Calendar\Emails;

use Sugar_Calendar\AddOn\Ticketing\Emails;
use function Sugar_Calendar\AddOn\Ticketing\Settings\resolve_email_copy_for_template;

/**
 * Immutable per-render input captured from a sender and its render config.
 *
 * Also the render-frame token: `ContentEnrichment::activate()` receives it
 * and `Emails::is_render_frame()` compares it by identity. Immutability is
 * by convention (private properties, getters only); PHP 7.4 has no readonly.
 *
 * @since 3.14.0
 */
class RenderRequest {

	/**
	 * The sender this request was captured from.
	 *
	 * @since 3.14.0
	 *
	 * @var Emails
	 */
	private $sender;

	/**
	 * Registry identity of the notification being sent.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	private $email_id;

	/**
	 * Object id for the active render.
	 *
	 * @since 3.14.0
	 *
	 * @var mixed
	 */
	private $object_id;

	/**
	 * The sender's heading value as set.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	private $heading;

	/**
	 * The sender's `html` value as set, untyped.
	 *
	 * @since 3.14.0
	 *
	 * @var mixed
	 */
	private $html;

	/**
	 * Whether a failed Modern render must use the legacy plain-text pipeline.
	 *
	 * @since 3.14.0
	 *
	 * @var bool
	 */
	private $legacy_plain_text;

	/**
	 * Raw subject, before wp_unslash().
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	private $subject;

	/**
	 * Raw message: a string, or the first-party copy resolution array.
	 *
	 * @since 3.14.0
	 *
	 * @var string|array
	 */
	private $message;

	/**
	 * Render configuration as passed to Emails::render().
	 *
	 * @since 3.14.0
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Smart Tag overrides: slug => resolved string.
	 *
	 * @since 3.14.0
	 *
	 * @var array<string, string>
	 */
	private $overrides = [];

	/**
	 * First-party content prepared for this render, or null.
	 *
	 * @since 3.14.0
	 *
	 * @var ContentEnrichment|null
	 */
	private $content_enrichment;

	/**
	 * Use from_sender().
	 *
	 * @since 3.14.0
	 */
	private function __construct() {}

	/**
	 * Capture one render's inputs.
	 *
	 * `Emails` has no `__get`, so the sender passes its own inputs explicitly.
	 *
	 * @since 3.14.0
	 *
	 * @param Emails       $sender  The sender.
	 * @param array        $inputs  Keys: email_id, object_id, heading, html, legacy_plain_text.
	 * @param string       $subject Raw subject.
	 * @param string|array $message Raw message or copy resolution.
	 * @param array        $config  Render configuration as passed to Emails::render().
	 *
	 * @return self
	 */
	public static function from_sender( Emails $sender, array $inputs, $subject, $message, array $config ): self { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh -- One capture pass covers every sender input with its own null-coalescing default.

		$request                    = new self();
		$request->sender            = $sender;
		$request->email_id          = (string) ( $inputs['email_id'] ?? '' );
		$request->object_id         = $inputs['object_id'] ?? null;
		$request->heading           = (string) ( $inputs['heading'] ?? '' );
		$request->html              = array_key_exists( 'html', $inputs ) ? $inputs['html'] : true;
		$request->legacy_plain_text = ! empty( $inputs['legacy_plain_text'] );
		$request->subject           = (string) $subject;
		$request->message           = $message;
		$request->config            = $config;

		if ( isset( $config['smart_tag_overrides'] ) && is_array( $config['smart_tag_overrides'] ) ) {
			foreach ( $config['smart_tag_overrides'] as $tag => $value ) {
				if ( is_string( $tag ) && is_string( $value ) ) {
					$request->overrides[ $tag ] = $value;
				}
			}
		}

		if ( isset( $config['content_enrichment'] ) && $config['content_enrichment'] instanceof ContentEnrichment ) {
			$request->content_enrichment = $config['content_enrichment'];
		}

		return $request;
	}

	/**
	 * The sender this request was captured from.
	 *
	 * @since 3.14.0
	 *
	 * @return Emails
	 */
	public function sender(): Emails {

		return $this->sender;
	}

	/**
	 * Registry identity of the notification being sent.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	public function email_id(): string {

		return $this->email_id;
	}

	/**
	 * Object id for the active render.
	 *
	 * @since 3.14.0
	 *
	 * @return mixed
	 */
	public function object_id() {

		return $this->object_id;
	}

	/**
	 * The sender's heading value as set.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	public function heading(): string {

		return $this->heading;
	}

	/**
	 * The sender's `html` value as set, untyped.
	 *
	 * @since 3.14.0
	 *
	 * @return mixed
	 */
	public function html() {

		return $this->html;
	}

	/**
	 * Whether a failed Modern render must use the legacy plain-text pipeline.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public function legacy_plain_text(): bool {

		return $this->legacy_plain_text;
	}

	/**
	 * The sender's template slug, resolved at call time so `sc_email_template` fires where it does today.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	public function template(): string {

		return (string) $this->sender->get_template();
	}

	/**
	 * Raw subject, before wp_unslash().
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	public function subject(): string {

		return $this->subject;
	}

	/**
	 * Whether a config key was passed at all (presence, not truthiness).
	 *
	 * @since 3.14.0
	 *
	 * @param string $key Config key.
	 *
	 * @return bool
	 */
	public function has( string $key ): bool {

		return array_key_exists( $key, $this->config );
	}

	/**
	 * A config value, or the default when the key is absent.
	 *
	 * @since 3.14.0
	 *
	 * @param string $key      Config key.
	 * @param mixed  $fallback Value returned when the key is absent.
	 *
	 * @return mixed
	 */
	public function config( string $key, $fallback = null ) {

		return array_key_exists( $key, $this->config ) ? $this->config[ $key ] : $fallback;
	}

	/**
	 * Smart Tag overrides: slug => resolved string.
	 *
	 * @since 3.14.0
	 *
	 * @return array<string, string>
	 */
	public function smart_tag_overrides(): array {

		return $this->overrides;
	}

	/**
	 * First-party content prepared for this render, or null.
	 *
	 * @since 3.14.0
	 *
	 * @return ContentEnrichment|null
	 */
	public function content_enrichment(): ?ContentEnrichment {

		return $this->content_enrichment;
	}

	/**
	 * The administrator order source for this render: true, an array, or null.
	 *
	 * @since 3.14.0
	 *
	 * @return mixed
	 */
	public function admin_order_record() {

		return $this->config['admin_order_record'] ?? null;
	}

	/**
	 * Whether administrator preview mode is active for this render.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public function admin_order_preview(): bool {

		return isset( $this->config['admin_order_preview'] ) && $this->config['admin_order_preview'] === true;
	}

	/**
	 * Select the body for the route this delivery actually committed to.
	 *
	 * An ordinary string caller keeps the released behavior exactly, including
	 * the `wp_unslash()` pass `send()` used to apply unconditionally. Only the
	 * exact first-party resolution array produced by
	 * `Settings\get_email_copy_resolution()` is route-sensitive, and even then
	 * only when the value is neither stored nor owned by a registered
	 * `sc_et_get_setting` callback.
	 *
	 * Applies no filter and reads no option, so it does not move the established
	 * `sc_et_get_setting` call site out from in front of `sc_email_send_before`.
	 *
	 * @since 3.14.0
	 *
	 * @param bool $modern Whether the delivery produces a Modern document.
	 *
	 * @return string
	 */
	public function message_for( bool $modern ): string {

		if ( ! is_array( $this->message ) ) {
			return wp_unslash( (string) $this->message );
		}

		$template = $modern
			? Styling::TEMPLATE_MODERN
			: Styling::TEMPLATE_PLAIN_TEXT;
		$value    = resolve_email_copy_for_template( $this->message, $template );

		return wp_unslash( (string) $value );
	}

	/**
	 * The effective content type for a route-final html value.
	 *
	 * Reproduces `Emails::get_content_type()` with its cache cleared: plain
	 * text when html is empty, otherwise the filtered default, then the
	 * `sc_email_content_type` filter. Writes no sender state.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $html Route-final html value.
	 *
	 * @return string
	 */
	public function content_type_for( $html ): string {

		/**
		 * Filters the default content type for HTML emails.
		 *
		 * @since 1.0.0
		 *
		 * @param string $content_type Default content type.
		 * @param Emails $sender       Email sender instance.
		 */
		$content_type = empty( $html )
			? 'text/plain'
			: apply_filters( 'sc_email_default_content_type', 'text/html', $this->sender ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName, WPForms.Comments.PHPDocHooks.RequiredHookDocumentation -- Documented above; the sniff cannot see through the ternary branch.

		/**
		 * Filters the content type of the current email.
		 *
		 * @since 1.0.0
		 *
		 * @param string $content_type Email content type.
		 * @param Emails $sender       Email sender instance.
		 */
		return (string) apply_filters( 'sc_email_content_type', $content_type, $this->sender ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}
}
