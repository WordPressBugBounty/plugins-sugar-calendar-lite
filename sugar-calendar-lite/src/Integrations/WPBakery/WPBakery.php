<?php

namespace Sugar_Calendar\Integrations\WPBakery;

/**
 * WPBakery integration.
 *
 * @since 3.11.0
 */
class WPBakery {

	/**
	 * WPBakery's own shortcode tag prefix, e.g. `[vc_row]`, `[vc_column_text]`.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const SHORTCODE_PREFIX = 'vc_';

	/**
	 * Longest attribute run matched inside a `[vc_*]` tag. Real tags carry far less;
	 * the cap is what keeps the scan linear on hostile content.
	 *
	 * @since 3.14.0
	 *
	 * @var int
	 */
	const MAX_ATTRIBUTE_LENGTH = 2000;

	/**
	 * Initialize the WPBakery integration.
	 *
	 * @since 3.11.0
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.11.0
	 */
	private function hooks() {

		// Check if WPBakery is active.
		if ( ! $this->is_wpbakery_active() ) {
			return;
		}

		// Register WPBakery-specific hooks here.
		add_filter( 'sc_event_supports', [ $this, 'add_editor_support' ] );
		add_filter( 'sugar_calendar_helpers_get_event_excerpt', [ $this, 'filter_event_excerpt' ], 10, 2 );
		add_filter( 'vc_check_post_type_validation', [ $this, 'filter_recurring_event_post_type' ], 10, 2 );
		add_filter( 'sugar_calendar_helpers_get_event_content', [ $this, 'filter_event_content' ], 10, 2 );
	}

	/**
	 * Check if WPBakery is active.
	 *
	 * Extracted from `hooks()` (rather than a bare `defined()` check inline)
	 * so a test can override this method to cover the inactive branch
	 * without permanently defining `WPB_VC_VERSION` for the rest of the
	 * PHPUnit process.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	protected function is_wpbakery_active() {

		return defined( 'WPB_VC_VERSION' );
	}

	/**
	 * Add editor support for WPBakery.
	 *
	 * @since 3.11.0
	 *
	 * @param array $supports Array of what is `sc_event` support.
	 *
	 * @return array
	 */
	public function add_editor_support( $supports ) {

		$supports[] = 'editor';

		return $supports;
	}

	/**
	 * Filter the event excerpt for WPBakery content.
	 *
	 * @since 3.11.0
	 * @since 3.14.0 Checks the raw post_content, which the passed $excerpt never contains.
	 *
	 * @param string $excerpt         The event excerpt.
	 * @param int    $event_object_id The event object ID / post ID.
	 *
	 * @return string
	 */
	public function filter_event_excerpt( $excerpt, $event_object_id ) {

		$content = $this->resolve_stripped_content( $event_object_id, true );

		// An excerpt has nothing safer to fall back to than the one it was given,
		// so both signals land on the same value here.
		if ( $content === null || $content === false ) {
			return $excerpt;
		}

		// wp_trim_excerpt() skips truncation when its $text is non-empty, as it is here, so apply the same filters ourselves.
		$excerpt_length = (int) apply_filters( 'excerpt_length', 55 ); // phpcs:ignore WPForms.Comments.PHPDocHooks.RequiredHookDocumentation,WPForms.PHP.ValidateHooks.InvalidHookName -- Core WordPress hook, intentionally invoked.

		// A filter returning 0 collapses the excerpt to the "more" marker; a negative dumps unbounded content.
		if ( $excerpt_length < 1 ) {
			$excerpt_length = 55;
		}

		$excerpt_more = (string) apply_filters( 'excerpt_more', ' [&hellip;]' ); // phpcs:ignore WPForms.Comments.PHPDocHooks.RequiredHookDocumentation,WPForms.PHP.ValidateHooks.InvalidHookName -- Core WordPress hook, intentionally invoked.

		$content = wp_trim_words( $content, $excerpt_length, $excerpt_more );

		// Returning early: wp_trim_excerpt() treats empty $text as "generate one from the global $post", which is not this event.
		if ( trim( $content ) === '' ) {
			return '';
		}

		return wp_trim_excerpt( $content );
	}

	/**
	 * Filter the full event content for WPBakery markup — no truncation,
	 * no manual-excerpt short-circuit (unlike filter_event_excerpt()).
	 *
	 * @since 3.14.0
	 *
	 * @param string $content         The event content.
	 * @param int    $event_object_id The event object ID / post ID.
	 *
	 * @return string Empty for a password-protected event, and empty when the strip could not
	 *                be completed — the caller's own value may still carry a raw element's body.
	 */
	public function filter_event_content( $content, $event_object_id ) {

		$stripped = $this->resolve_stripped_content( $event_object_id, false );

		if ( $stripped === null ) {
			return $content;
		}

		// Empty, not $content: a caller without a password gate of its own must still
		// be safe, and on a failed strip $content can hold a raw element's base64 body.
		return $stripped === false ? '' : $stripped;
	}

	/**
	 * A post's WPBakery-stripped content, or a signal that the caller should not
	 * use it.
	 *
	 * Both filters share this guard chain — the password bail above all, which is
	 * the worst place for the two to drift apart.
	 *
	 * @since 3.14.0
	 *
	 * @param int  $event_object_id     The event object ID / post ID.
	 * @param bool $keep_manual_excerpt Whether a manually-set excerpt wins over stripping.
	 *
	 * @return string|null|false The stripped content; null to keep the caller's own
	 *                           value; false to fail closed, because the event is
	 *                           password-protected or the strip failed.
	 */
	private function resolve_stripped_content( $event_object_id, $keep_manual_excerpt ) {

		// get_post() falls back to the global $post when passed an empty ID.
		if ( empty( $event_object_id ) ) {
			return null;
		}

		$post_obj = get_post( $event_object_id );

		if ( ! $post_obj ) {
			return null;
		}

		// A password-protected event's real content must never be exposed — mirrors
		// core's own get_the_content() behavior.
		if ( post_password_required( $post_obj ) ) {
			return false;
		}

		// A manually-set excerpt must never be overwritten by WPBakery's content stripping.
		if ( $keep_manual_excerpt && ! empty( $post_obj->post_excerpt ) ) {
			return null;
		}

		if ( strpos( $post_obj->post_content, '[' . self::SHORTCODE_PREFIX ) === false ) {
			return null;
		}

		$stripped = $this->strip_markup( $post_obj->post_content );

		// Null means a PCRE failure — fail closed, don't leak the raw content.
		return $stripped === null ? false : $stripped;
	}

	/**
	 * Strips WPBakery's [vc_*] markup from raw post content.
	 *
	 * @since 3.14.0
	 *
	 * @param string $post_content The raw post content.
	 *
	 * @return string|null Null on a PCRE failure — callers must fail closed.
	 */
	private function strip_markup( $post_content ) {

		// Attribute runs are bounded: unbounded, a body of bare "[vc_" costs seconds of CPU
		// per megabyte, and preg_replace() returns a string rather than failing closed.
		$attributes = '[^\]]{0,' . self::MAX_ATTRIBUTE_LENGTH . '}';

		// Raw HTML/JS bodies are base64, so drop tag and body whole or the blob leaks; an unclosed tag consumes the rest, which is safer than leaking it.
		$content = preg_replace(
			'/\[' . self::SHORTCODE_PREFIX . 'raw_(html|js)\b' . $attributes . '\](?:.*?\[\/' . self::SHORTCODE_PREFIX . 'raw_\1\]|.*)/s',
			'',
			$post_content
		);

		// Fail closed only here — a failure below leaves visible tags, not a base64 body.
		if ( $content === null ) {
			return null;
		}

		// Same reason: an unmatched raw element (attributes past the cap) must not fall through,
		// because strip_shortcodes() drops its tags and keeps the base64 body.
		if ( preg_match( '/\[' . self::SHORTCODE_PREFIX . 'raw_(?:html|js)\b/', $content ) !== 0 ) {
			return null;
		}

		// Remove the remaining WPBakery shortcode tags but keep the inner content.
		$content = $this->safe_preg_replace( '/\[\/?' . self::SHORTCODE_PREFIX . $attributes . '\]/', '', $content );

		// Strip any other currently-registered shortcodes left behind (e.g. a
		// different plugin's [gallery]) so they don't print literally.
		return strip_shortcodes( $content );
	}

	/**
	 * Wraps preg_replace() to fall back to the subject it was given (not the
	 * original, unprocessed post content) on a PCRE failure — so a failure
	 * on a later stripping pass can't undo an earlier one.
	 *
	 * @since 3.14.0
	 *
	 * @param string $pattern     The regex pattern.
	 * @param string $replacement The replacement string.
	 * @param string $subject     The string to search and replace.
	 *
	 * @return string
	 */
	private function safe_preg_replace( $pattern, $replacement, $subject ) {

		$result = preg_replace( $pattern, $replacement, $subject );

		return $result === null ? $subject : $result;
	}

	/**
	 * Let a recurring event inherit whatever WPBakery access was granted to a regular event.
	 *
	 * Sugar Calendar switches an event's post type from `sc_event` to
	 * `sc_recurring_event` the moment it's saved with a recurrence pattern.
	 * `sc_recurring_event` is not registered as a public post type, so it can
	 * never appear as an option in WPBakery's own Role Manager settings —
	 * there is no checkbox for a site owner to enable it directly. This
	 * filter makes WPBakery treat `sc_recurring_event` the same way it treats
	 * `sc_event`, so recurring events keep working the moment the site owner
	 * has already enabled WPBakery for regular events.
	 *
	 * Deliberately returns a real `true`/`false` (never falls through to
	 * `null`) once it decides to handle `sc_recurring_event` — a denied
	 * `sc_event` means a denied `sc_recurring_event`, full inheritance both
	 * ways. `sc_recurring_event` can't get its own Role Manager capability
	 * from WPBakery's UI anyway (see above), so there's no real-world case
	 * where a `null` here would let a meaningful fallback decision through
	 * instead.
	 *
	 * @since 3.14.0
	 *
	 * @param bool|null $valid The default validation result.
	 * @param string    $type  The post type being checked.
	 *
	 * @return bool|null
	 */
	public function filter_recurring_event_post_type( $valid, $type ) {

		// Respect an earlier filter's decision, and only handle our post type.
		if ( $valid !== null || $type !== 'sc_recurring_event' ) {
			return $valid;
		}

		return (bool) vc_check_post_type( 'sc_event' );
	}
}
