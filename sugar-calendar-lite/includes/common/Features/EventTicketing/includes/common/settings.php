<?php

namespace Sugar_Calendar\AddOn\Ticketing\Settings;

use Sugar_Calendar\Emails\Styling;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Get a setting
 *
 * @since 1.0
 * @return mixed The stored setting or default
 */
function get_setting( $key = '', $default = '' ) {

	$options = get_option( 'sc_et_settings', [] );

	if ( ! isset( $options[ $key ] ) ) {
		$options[ $key ] = $default;
	}

	$ret = $options[ $key ];

	return apply_filters( 'sc_et_get_setting', $ret, $key, $default, $options );
}

/**
 * The Color Scheme settings for the ticket Receipt and Details pages.
 *
 * Everything a consumer needs travels with the key, so adding a color is one
 * edit here rather than three coordinated ones. Lives here rather than with the
 * settings screen because the front-end templates read it too, and the admin
 * includes are only loaded in wp-admin.
 *
 * @since 3.14.0
 *
 * @return array Setting key => [ default, label, css_var ].
 */
function get_color_scheme_fields() {

	return [
		'ticketing_pages_color_background' => [
			'default' => '#e9eaec',
			'label'   => esc_html__( 'Background', 'sugar-calendar-lite' ),
			'css_var' => '--sc-ticket-bg',
		],
		'ticketing_pages_color_body'       => [
			'default' => '#ffffff',
			'label'   => esc_html__( 'Body', 'sugar-calendar-lite' ),
			'css_var' => '--sc-ticket-body',
		],
		'ticketing_pages_color_text'       => [
			'default' => '#333333',
			'label'   => esc_html__( 'Text', 'sugar-calendar-lite' ),
			'css_var' => '--sc-ticket-text',
		],
		'ticketing_pages_color_accent'     => [
			'default' => '#FF641F',
			'label'   => esc_html__( 'Accent', 'sugar-calendar-lite' ),
			'css_var' => '--sc-ticket-accent',
		],
	];
}

/**
 * Default colors for the ticket Receipt and Details pages.
 *
 * @since 3.14.0
 *
 * @return array Setting key => #rrggbb default.
 */
function get_color_scheme_defaults() {

	return wp_list_pluck( get_color_scheme_fields(), 'default' );
}

/**
 * Whether the ticket pages render their pre-3.14 markup.
 *
 * The switch for the receipt body, the details body, the print document, the
 * dead-end messages and the standalone page template. The two body renderers
 * pass it through a per-order filter first, so this is their default rather
 * than their answer.
 *
 * @since 3.14.0
 *
 * @return bool
 */
function use_legacy_template() {

	return (bool) get_setting( 'use_legacy_template', false );
}

/**
 * Update a setting
 *
 * @since 1.0
 * @return mixed The stored setting or default
 */
function update_setting( $key = '', $value = '' ) {

	$options = get_option( 'sc_et_settings', [] );
	$options = is_array( $options ) ? $options : [];

	$options[ $key ] = $value;

	return update_option( 'sc_et_settings', $options );
}

/**
 * Read an email-copy setting without collapsing an intentional blank into its
 * default value.
 *
 * @since 3.14.0
 *
 * @param string $key      Setting key.
 * @param string $fallback Canonical default.
 *
 * @return string
 */
function get_email_copy_setting( $key, $fallback ) {

	// Both candidates are the same value here, so the result is identical
	// whatever route the caller is on. Route-sensitive callers use
	// get_email_copy_resolution() with two distinct candidates instead.
	$resolution = get_email_copy_resolution( $key, $fallback, $fallback );

	return $resolution['value'];
}

/**
 * Resolve an email-copy setting together with the provenance of its value.
 *
 * Reads the complete option once, records whether a `sc_et_get_setting`
 * callback is registered BEFORE applying the filter, applies the released
 * filter exactly once with the pre-#517 default as `$default`, and normalizes
 * the result exactly as `get_email_copy_setting()` does.
 *
 * `route_sensitive` is the whole point of the shape: it is true only when the
 * value is neither stored nor owned by a registered filter callback. Saved
 * copy, and any value a callback controls — including one that returns its
 * input unchanged — stays authoritative on every delivery route.
 *
 * @since 3.14.0
 *
 * @param string $key            Setting key.
 * @param string $plain_default  Pre-#517 default, also the filter's `$default`.
 * @param string $modern_default Default used only when a delivery genuinely
 *                               produces a Modern document.
 *
 * @return array {
 *     @type string $value           Normalized resolved value.
 *     @type string $plain_default   Pre-#517 default.
 *     @type string $modern_default  Modern default.
 *     @type bool   $route_sensitive Whether the final route may substitute a default.
 * }
 */
function get_email_copy_resolution( $key, $plain_default, $modern_default ) {

	$settings     = get_option( 'sc_et_settings', [] );
	$settings     = is_array( $settings ) ? $settings : [];
	$stored       = array_key_exists( $key, $settings );
	$filter_owned = false !== has_filter( 'sc_et_get_setting' );
	$value        = $stored ? $settings[ $key ] : $plain_default;

	/**
	 * Filters a resolved Event Ticketing setting.
	 *
	 * @since 3.14.0
	 *
	 * @param string $value         Resolved setting value.
	 * @param string $key           Setting key.
	 * @param string $plain_default Canonical default.
	 * @param array  $settings      Complete Event Ticketing settings.
	 */
	$value        = apply_filters( 'sc_et_get_setting', $value, $key, $plain_default, $settings );
	$normal_value = normalize_email_copy_value( $value );

	return [
		'value'           => $normal_value,
		'plain_default'   => $plain_default,
		'modern_default'  => $modern_default,
		'route_sensitive' => ! $stored && ! $filter_owned,
	];
}

/**
 * Normalize any resolved or filtered email-copy value to a string.
 *
 * @since 3.14.0
 *
 * @param mixed $value Resolved or filtered value.
 *
 * @return string
 */
function normalize_email_copy_value( $value ) {

	if ( is_string( $value ) ) {
		return $value;
	}

	if ( is_scalar( $value ) ) {
		return (string) $value;
	}

	if ( is_object( $value ) && method_exists( $value, '__toString' ) ) {
		return (string) $value;
	}

	return '';
}

/**
 * Select the copy that belongs to a delivery route that is already decided.
 *
 * Hook-free by contract: it reads no option and applies no filter, so it is
 * safe to call after `sc_email_send_before` without moving the established
 * `sc_et_get_setting` call site. Unknown, malformed, omitted, `default`,
 * `none`, and third-party templates all select `plain_default`.
 *
 * Because `get_email_copy_resolution()` always applied `sc_et_get_setting`
 * with the pre-#517 `$default`, this same selector keeps settings, preview,
 * and delivery aligned even when a callback returns its input unchanged.
 *
 * @since 3.14.0
 *
 * @param array|mixed $resolution Internal resolution from get_email_copy_resolution().
 * @param string      $template   Template the delivery actually committed to.
 *
 * @return string
 */
function resolve_email_copy_for_template( $resolution, $template ) {

	if ( ! is_array( $resolution ) || ! array_key_exists( 'value', $resolution ) ) {
		return normalize_email_copy_value( $resolution );
	}

	if ( empty( $resolution['route_sensitive'] ) ) {
		return normalize_email_copy_value( $resolution['value'] );
	}

	$key = $template === Styling::TEMPLATE_MODERN
		? 'modern_default'
		: 'plain_default';

	$value = array_key_exists( $key, $resolution )
		? $resolution[ $key ]
		: $resolution['value'];

	return normalize_email_copy_value( $value );
}

/**
 * Get the receipt email's canonical subject.
 *
 * @since 3.14.0
 *
 * @return string
 */
function get_default_receipt_subject() {

	return __( 'Your event ticket purchase!', 'sugar-calendar-lite' );
}

/**
 * Get the receipt email's canonical message.
 *
 * @since 3.14.0
 *
 * @return string
 */
function get_default_receipt_message() {

	/* translators: Email body. Preserve all Sugar Calendar smart tags, HTML, whitespace, and line breaks. */
	return __(
		'Hello {name},

Your event ticket purchase to <strong>{event_title}</strong> was successful! See below for the specifics of when and where the event is taking place!

<strong>Event date and time</strong>: {event_date} at {event_start_time}

See event location and further details <a href="{event_url}">here</a>.

Your tickets:

{tickets}

<a href="{receipt_url}">View this receipt and event details in your browser</a>.',
		'sugar-calendar-lite'
	);
}

/**
 * Get the receipt email's canonical message for a Modern document.
 *
 * The Modern layout renders the full order and ticket details below this editable
 * introduction. This default gives the purchaser an event summary while leaving
 * ticket-specific rows to the layout.
 *
 * @since 3.14.0
 *
 * @return string
 */
function get_default_modern_receipt_message() {

	/* translators: Modern receipt email body. Preserve all Sugar Calendar Smart Tags, whitespace, and line breaks. */
	return __(
		'Hello {name},

Your ticket purchase to {event_title} was successful! See below for the specifics of when and where the event is taking place.

Event date and time: {event_date} at {event_start_time}

Thank you for your purchase!',
		'sugar-calendar-lite'
	);
}

/**
 * Get the attendee ticket email's canonical subject.
 *
 * @since 3.14.0
 *
 * @return string
 */
function get_default_ticket_subject() {

	return __( 'Your event ticket!', 'sugar-calendar-lite' );
}

/**
 * Get the attendee ticket email's canonical message.
 *
 * @since 3.14.0
 *
 * @return string
 */
function get_default_ticket_message() {

	/* translators: Email body. Preserve all Sugar Calendar smart tags, HTML, whitespace, and line breaks. */
	return __(
		"Hello {attendee_name},

Your event ticket to <strong>{event_title}</strong> is below! See the following details for specifics of when and where the event is taking place!

<strong>Event date and time</strong>: {event_date} at {event_start_time}

See event location and further details <a href=\"{event_url}\">here</a>.

Your ticket:
<ul>
 \t<li>ID: {ticket_id}</li>
 \t<li>Code: {ticket_code}</li>
</ul>
<a href=\"{ticket_url}\">View this ticket in your browser</a>.",
		'sugar-calendar-lite'
	);
}

/**
 * Get the attendee ticket email's canonical message for a Modern document.
 *
 * Shorter than `get_default_ticket_message()` on purpose: the Modern layout
 * already renders the event and ticket detail rows this copy sits above.
 *
 * @since 3.14.0
 *
 * @return string
 */
function get_default_modern_ticket_message() {

	/* translators: Modern ticket email body. Preserve all Sugar Calendar Smart Tags, whitespace, and line breaks. */
	return __(
		'Hello {attendee_name},

Your event ticket to {event_title} is below! See the following details for specifics of when and where the event is taking place!',
		'sugar-calendar-lite'
	);
}

/**
 * Get the ticket-sale admin email's canonical subject.
 *
 * @since 3.14.0
 *
 * @return string
 */
function get_default_ticket_sale_admin_subject() {

	/* translators: Ticket-sale admin email subject. Preserve all SC Smart Tags. */
	return __( 'New ticket sale for {event_title}: Order #{order_id}', 'sugar-calendar-lite' );
}

/**
 * Get the ticket-sale admin email's canonical message.
 *
 * @since 3.14.0
 *
 * @return string
 */
function get_default_ticket_sale_admin_message() {

	/* translators: Ticket-sale admin email body. Preserve all SC Smart Tags, whitespace, and line breaks. */
	$message = __(
		'A new ticket order has been placed.

ORDER
Order ID: {order_id}
Submitted: {order_date} at {order_time}
Purchaser: {name}
Email: {email}
Total: {order_amount}
Transaction ID: {transaction_id}

EVENT
Event: {event_title}
Event ID: {event_id}
Starts: {event_date} at {event_start_time}
Ends: {event_end_date} at {event_end_time}
Location: {event_location}

TICKETS ({ticket_count})
{admin_tickets_info}

LINKS
Manage order: {order_admin_url}
Event page: {event_url}',
		'sugar-calendar-lite'
	);

	return wpautop( $message, false );
}

/**
 * Get the receipt email subject.
 *
 * @since 3.14.0
 *
 * @return string
 */
function get_receipt_subject() {

	return get_email_copy_setting( 'receipt_subject', get_default_receipt_subject() );
}

/**
 * Get the receipt email message.
 *
 * @since 3.14.0
 *
 * @return string
 */
function get_receipt_message() {

	return get_email_copy_setting( 'receipt_message', get_default_receipt_message() );
}

/**
 * Get the receipt email's internal copy resolution.
 *
 * The value plus the provenance the delivery, settings editor, and preview all
 * need to pick the right fallback once the route is known. First-party internal
 * shape: do not pass it to third-party code.
 *
 * @since 3.14.0
 *
 * @return array
 */
function get_receipt_message_resolution() {

	return get_email_copy_resolution(
		'receipt_message',
		get_default_receipt_message(),
		get_default_modern_receipt_message()
	);
}

/**
 * Get the attendee ticket email subject.
 *
 * @since 3.14.0
 *
 * @return string
 */
function get_ticket_subject() {

	return get_email_copy_setting( 'ticket_subject', get_default_ticket_subject() );
}

/**
 * Get the attendee ticket email message.
 *
 * @since 3.14.0
 *
 * @return string
 */
function get_ticket_message() {

	return get_email_copy_setting( 'ticket_message', get_default_ticket_message() );
}

/**
 * Get the attendee ticket email's internal copy resolution.
 *
 * The value plus the provenance the delivery, settings editor, and preview all
 * need to pick the right fallback once the route is known. First-party internal
 * shape: do not pass it to third-party code.
 *
 * @since 3.14.0
 *
 * @return array
 */
function get_ticket_message_resolution() {

	return get_email_copy_resolution(
		'ticket_message',
		get_default_ticket_message(),
		get_default_modern_ticket_message()
	);
}

/**
 * Get the admin ticket-sale notification subject.
 *
 * @since 3.14.0
 *
 * @return string
 */
function get_ticket_sale_admin_subject() {

	return get_email_copy_setting( 'ticket_sale_admin_subject', get_default_ticket_sale_admin_subject() );
}

/**
 * Get the admin ticket-sale notification message.
 *
 * @since 3.14.0
 *
 * @return string
 */
function get_ticket_sale_admin_message() {

	return get_email_copy_setting( 'ticket_sale_admin_message', get_default_ticket_sale_admin_message() );
}
