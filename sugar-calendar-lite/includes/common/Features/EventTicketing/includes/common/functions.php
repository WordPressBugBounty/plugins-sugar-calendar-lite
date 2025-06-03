<?php
/**
 * Sugar Calendar Event TIcket Functions
 *
 */
namespace Sugar_Calendar\AddOn\Ticketing\Common\Functions;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Settings as Settings;

/**
 * Add an order.
 *
 * @since 1.0.0
 *
 * @param array $data
 * @return int
 */
function add_order( $data = array() ) {
	$orders = new \Sugar_Calendar\AddOn\Ticketing\Database\Order_Query();

	return $orders->add_item( $data );
}

/**
 * Update an order.
 *
 * @since 1.0.0
 *
 * @param int   $order_id order ID.
 * @param array $data    Updated order data.
 * @return bool Whether or not the order was updated.
 */
function update_order( $order_id = 0, $data = array() ) {
	$orders = new \Sugar_Calendar\AddOn\Ticketing\Database\Order_Query();

	return $orders->update_item( $order_id, $data );
}

/**
 * Get aa order.
 *
 * @since 1.0.0
 *
 * @param int $order_id Order ID.
 * @return Order
 */
function get_order( $order_id = 0 ) {
	$orders = new \Sugar_Calendar\AddOn\Ticketing\Database\Order_Query();

	return $orders->get_item_by( 'id', $order_id );
}

/**
 * Query for orders.
 *
 * @see \Sugar_Calendar\AddOn\Ticketing\Database\Order_Query()::__construct()
 *
 * @since 1.1.4
 *
 * @param array $args Arguments. See `\Sugar_Calendar\AddOn\Ticketing\Database\Order_Query()` for
 *                    accepted arguments.
 * @return \Sugar_Calendar\AddOn\Ticketing\Database\Order[] Array of `Order` objects.
 */
function get_orders( $args = array() ) {

	// Parse args
	$r = wp_parse_args( $args, array(
		'number' => 30,
	) );

	// Instantiate a query object
	$orders = new \Sugar_Calendar\AddOn\Ticketing\Database\Order_Query();

	// Return orders
	return $orders->query( $r );
}

/**
 * Get an order by checkout_id.
 *
 * @since 1.1.0
 *
 * @param string $checkout_id External checkout ID.
 * @return Order
 */
function get_order_by_checkout_id( $checkout_id = '' ) {
	$tickets = new \Sugar_Calendar\AddOn\Ticketing\Database\Order_Query();

	return $tickets->get_item_by( 'checkout_id', $checkout_id );
}

/**
 * Count orders.
 *
 * @since 1.0.0
 *
 * @param array $args Arguments.
 * @return int
 */
function count_orders( $args = array() ) {

	// Parse args
	$r = wp_parse_args( $args, array(
		'count' => true
	) );

	// Query for count(s)
	$orders = new \Sugar_Calendar\AddOn\Ticketing\Database\Order_Query( $r );

	// Return count(s)
	return absint( $orders->found_items );
}

/**
 * Delete an order.
 *
 * @since 1.0.0
 *
 * @param int $order_id Order ID.
 * @return Bool
 */
function delete_order( $order_id = 0 ) {
	$orders = new \Sugar_Calendar\AddOn\Ticketing\Database\Order_Query();

	return $orders->delete_item( $order_id );
}

/**
 * Retrieve I18N order status label.
 *
 * @since 1.0.0
 *
 * @param string $status Order status.
 * @return Bool
 */
function order_status_label( $status = '' ) {

	$label = '';

	switch ( strtolower( $status ) ) {

		case 'all' :
			$label = esc_html__( 'All', 'sugar-calendar-lite' );
			break;

		case 'pending' :
			$label = esc_html__( 'Pending', 'sugar-calendar-lite' );
			break;

		case 'refunded' :
			$label = esc_html__( 'Refunded', 'sugar-calendar-lite' );
			break;

		case 'paid' :
			$label = esc_html__( 'Paid', 'sugar-calendar-lite' );
			break;

		default :
			$label = '&mdash;';
			break;
	}

	return apply_filters( 'sc_et_order_status_label', $label, $status );
}

/**
 * Add a ticket.
 *
 * @since 1.0.0
 *
 * @param array $data
 *
 * @return bool|int Returns the ticket ID if successful, otherwise `false`.
 */
function add_ticket( $data = array() ) {

	// Instantiate a query object
	$tickets = new \Sugar_Calendar\AddOn\Ticketing\Database\Ticket_Query();

	$data['code'] = wp_generate_password( 20, false );

	if ( empty( $data['attendee_id'] ) ) {
		$data['attendee_id'] = 0;
	}

	return $tickets->add_item( $data );
}

/**
 * Update a ticket.
 *
 * @since 1.0.0
 *
 * @param int   $ticket_id ticket ID.
 * @param array $data    Updated ticket data.
 * @return bool Whether or not the ticket was updated.
 */
function update_ticket( $ticket_id = 0, $data = array() ) {
	$tickets = new \Sugar_Calendar\AddOn\Ticketing\Database\Ticket_Query();

	return $tickets->update_item( $ticket_id, $data );
}

/**
 * Get a ticket.
 *
 * @since 1.0.0
 *
 * @param int $ticket_id Ticket ID.
 * @return Ticket
 */
function get_ticket( $ticket_id = 0 ) {
	$tickets = new \Sugar_Calendar\AddOn\Ticketing\Database\Ticket_Query();

	return $tickets->get_item_by( 'id', $ticket_id );
}

/**
 * Query for tickets.
 *
 * @see \Sugar_Calendar\AddOn\Ticketing\Database\Ticket_Query()::__construct()
 *
 * @since 1.1.4
 *
 * @param array $args Arguments. See `\Sugar_Calendar\AddOn\Ticketing\Database\Ticket_Query()` for
 *                    accepted arguments.
 * @return \Sugar_Calendar\AddOn\Ticketing\Database\Ticket[] Array of `Ticket` objects.
 */
function get_tickets( $args = array() ) {

	// Parse args
	$r = wp_parse_args( $args, array(
		'number' => 30,
	) );

	// Instantiate a query object
	$orders = new \Sugar_Calendar\AddOn\Ticketing\Database\Ticket_Query();

	// Return tickets
	return $orders->query( $r );
}

/**
 * Get a ticket by code.
 *
 * @since 1.0.0
 *
 * @param string $code Ticket code.
 * @return Ticket
 */
function get_ticket_by_code( $code = '' ) {
	$tickets = new \Sugar_Calendar\AddOn\Ticketing\Database\Ticket_Query();

	return $tickets->get_item_by( 'code', $code );
}

/**
 * Get tickets for an order.
 *
 * @since 1.0.0
 *
 * @param int $order_id Order ID.
 * @return Ticket
 */
function get_order_tickets( $order_id = 0 ) {
	return get_tickets( array(
		'order_id' => $order_id,
		'order'    => 'ASC'
	) );
}

/**
 * Retrieve available ticket count.
 *
 * @since 1.0.0
 *
 * @param array $event_id Event ID.
 *
 * @return int
 */
function get_available_tickets( $event_id = 0 ) {

	$available = -1; // Default to infinite.
	$quantity  = get_event_meta( $event_id, 'ticket_quantity', true );

	/**
	 * Filter the available ticket count.
	 *
	 * This filter allows to override the available ticket count for an event.
	 *
	 * @since 3.6.0
	 *
	 * @param int|false $available Available ticket count. If this is other value than `false`
	 *                             then it will return that value.
	 * @param string    $quantity  Ticket quantity.
	 * @param int       $event_id  Event ID.
	 */
	$pre_available = apply_filters(
		'sc_et_pre_get_available_tickets',
		false,
		$quantity,
		$event_id
	);

	if ( $pre_available !== false ) {
		return $pre_available;
	}

	if ( ! empty( $quantity ) ) {
		$purchased = count_tickets( [ 'event_id' => $event_id ] );
		$available = max( $quantity - $purchased, 0 );
	}

	return $available;
}

/**
 * Count tickets.
 *
 * @since 1.0.0
 *
 * @param array $args Arguments.
 * @return int
 */
function count_tickets( $args = array() ) {

	// Parse args
	$r = wp_parse_args( $args, array(
		'count' => true
	) );

	// Query for count(s)
	$tickets = new \Sugar_Calendar\AddOn\Ticketing\Database\Ticket_Query( $r );

	// Return count(s)
	return absint( $tickets->found_items );
}

/**
 * Delete a ticket.
 *
 * @since 1.0.0
 *
 * @param int $ticket_id Ticket ID.
 * @return Bool
 */
function delete_ticket( $ticket_id = 0 ) {
	$tickets = new \Sugar_Calendar\AddOn\Ticketing\Database\Ticket_Query();

	return $tickets->delete_item( $ticket_id );
}

/**
 * Add an attendee.
 *
 * @since 1.0.0
 *
 * @param array $data
 * @return int
 */
function add_attendee( $data = array() ) {
	$attendees = new \Sugar_Calendar\AddOn\Ticketing\Database\Attendee_Query();

	return $attendees->add_item( $data );
}

/**
 * Update an attendee.
 *
 * @since 1.0.0
 *
 * @param int   $attendee_id attendee ID.
 * @param array $data        Updated attendee data.
 * @return bool Whether or not the attendee was updated.
 */
function update_attendee( $attendee_id = 0, $data = array() ) {
	$attendees = new \Sugar_Calendar\AddOn\Ticketing\Database\Attendee_Query();

	return $attendees->update_item( $attendee_id, $data );
}

/**
 * Get an attendee.
 *
 * @since 1.0.0
 *
 * @param int $attendee_id Attendee ID.
 * @return Attendee
 */
function get_attendee( $attendee_id = 0 ) {
	$attendees = new \Sugar_Calendar\AddOn\Ticketing\Database\Attendee_Query();

	return $attendees->get_item_by( 'id', $attendee_id );
}

/**
 * Query for attendees.
 *
 * @see \Sugar_Calendar\AddOn\Ticketing\Database\Attendee_Query()::__construct()
 *
 * @since 1.1.4
 *
 * @param array $args Arguments. See `\Sugar_Calendar\AddOn\Ticketing\Database\Attendee_Query()` for
 *                    accepted arguments.
 * @return \Sugar_Calendar\AddOn\Ticketing\Database\Attendee[] Array of `Attendee` objects.
 */
function get_attendees( $args = array() ) {

	// Parse args
	$r = wp_parse_args( $args, array(
		'number' => 30,
	) );

	// Instantiate a query object
	$orders = new \Sugar_Calendar\AddOn\Ticketing\Database\Attendee_Query();

	// Return orders
	return $orders->query( $r );
}

/**
 * Get an attendee by email.
 *
 * @since 1.0.0
 *
 * @param string $email Attendee email.
 * @return Attendee
 */
function get_attendee_by_email( $email = '' ) {
	$attendees = new \Sugar_Calendar\AddOn\Ticketing\Database\Attendee_Query();

	return $attendees->get_item_by( 'email', $email );
}

/**
 * Get a list of attendees based on the order ID
 *
 * @since 1.0.0
 *
 * @param int $order_id The order ID to retrieve attendees for
 * @return Array
 */
function get_attendees_by_order_id( $order_id = 0 ) {

	// Bail if no order ID
	if ( empty( $order_id ) ) {
		return array();
	}

	// Get tickets for order
	$tickets = get_tickets( array(
		'order_id' => $order_id,
		'number'   => 10000
	) );

	// Bail if no tickets for order
	if ( empty( $tickets ) ) {
		return array();
	}

	// Get attendees from tickets
	$retval = get_attendees( array(
		'id__in' => array_values( wp_list_pluck( $tickets, 'attendee_id' ) )
	) );

	// Return attendees
	return $retval;
}

/**
 * Delete an attendee.
 *
 * @since 1.0.0
 *
 * @param int $attendee_id Attendee ID.
 * @return Bool
 */
function delete_attendee( $attendee_id = 0 ) {
	$attendees = new \Sugar_Calendar\AddOn\Ticketing\Database\Attendee_Query();

	return $attendees->delete_item( $attendee_id );
}

/**
 * Add a discount.
 *
 * @since 1.0.0
 *
 * @param array $data
 * @return int
 */
function add_discount( $data = array() ) {
	$discounts = new \Sugar_Calendar\AddOn\Ticketing\Database\Discount_Query();

	return $discounts->add_item( $data );
}

/**
 * Update a discount.
 *
 * @since 1.0.0
 *
 * @param int   $discount_id order ID.
 * @param array $data    Updated discount data.
 * @return bool Whether or not the discount was updated.
 */
function update_discount( $discount_id = 0, $data = array() ) {
	$discounts = new \Sugar_Calendar\AddOn\Ticketing\Database\Discount_Query();

	return $discounts->update_item( $discount_id, $data );
}

/**
 * Get a discount.
 *
 * @since 1.0.0
 *
 * @param int $discount_id Discount ID.
 * @return Discount
 */
function get_discount( $discount_id = 0 ) {
	$discounts = new \Sugar_Calendar\AddOn\Ticketing\Database\Discount_Query();

	return $discounts->get_item_by( 'id', $discount_id );
}

/**
 * Query for discounts.
 *
 * @see \Sugar_Calendar\AddOn\Ticketing\Database\Discount_Query()::__construct()
 *
 * @since 1.1.4
 *
 * @param array $args Arguments. See `\Sugar_Calendar\AddOn\Ticketing\Database\Discount_Query()` for
 *                    accepted arguments.
 * @return \Sugar_Calendar\AddOn\Ticketing\Database\Discount[] Array of `Discount` objects.
 */
function get_discounts( $args = array() ) {

	// Parse args
	$r = wp_parse_args( $args, array(
		'number' => 30,
	) );

	// Instantiate a query object
	$orders = new \Sugar_Calendar\AddOn\Ticketing\Database\Discount_Query();

	// Return discounts
	return $orders->query( $r );
}

/**
 * Delete a discount.
 *
 * @since 1.0.0
 *
 * @param int $discount_id Discount ID.
 * @return Bool
 */
function delete_discount( $discount_id = 0 ) {
	$discounts = new \Sugar_Calendar\AddOn\Ticketing\Database\Discount_Query();

	return $discounts->delete_item( $discount_id );
}

/**
 * Get Currencies
 *
 * @since 1.0.0
 * @return array $currencies A list of the available currencies
 */
function get_currencies() {

	$currencies = array(
		'USD' => esc_html__( 'US Dollar', 'sugar-calendar-lite' ),
		'EUR' => esc_html__( 'Euro', 'sugar-calendar-lite' ),
		'ARS' => esc_html__( 'Argentine Peso', 'sugar-calendar-lite' ),
		'AUD' => esc_html__( 'Australian Dollar', 'sugar-calendar-lite' ),
		'BDT' => esc_html__( 'Bangladeshi Taka', 'sugar-calendar-lite' ),
		'BTC' => esc_html__( 'Bitcoin', 'sugar-calendar-lite' ),
		'BRL' => esc_html__( 'Brazilian Real', 'sugar-calendar-lite' ),
		'BGN' => esc_html__( 'Bulgarian Lev', 'sugar-calendar-lite' ),
		'CAD' => esc_html__( 'Canadian Dollar', 'sugar-calendar-lite' ),
		'CLP' => esc_html__( 'Chilean Peso', 'sugar-calendar-lite' ),
		'CNY' => esc_html__( 'Chinese Yuan', 'sugar-calendar-lite' ),
		'COP' => esc_html__( 'Colombian Peso', 'sugar-calendar-lite' ),
		'HRK' => esc_html__( 'Croatia Kuna', 'sugar-calendar-lite' ),
		'CZK' => esc_html__( 'Czech Koruna', 'sugar-calendar-lite' ),
		'DKK' => esc_html__( 'Danish Krone', 'sugar-calendar-lite' ),
		'DOP' => esc_html__( 'Dominican Peso', 'sugar-calendar-lite' ),
		'EGP' => esc_html__( 'Egyptian Pound', 'sugar-calendar-lite' ),
		'HKD' => esc_html__( 'Hong Kong Dollar', 'sugar-calendar-lite' ),
		'HUF' => esc_html__( 'Hungarian Forint', 'sugar-calendar-lite' ),
		'ISK' => esc_html__( 'Icelandic Krona', 'sugar-calendar-lite' ),
		'IDR' => esc_html__( 'Indonesia Rupiah', 'sugar-calendar-lite' ),
		'INR' => esc_html__( 'Indian Rupee', 'sugar-calendar-lite' ),
		'ILS' => esc_html__( 'Israeli Shekel', 'sugar-calendar-lite' ),
		'IRR' => esc_html__( 'Iranian Rial', 'sugar-calendar-lite' ),
		'JPY' => esc_html__( 'Japanese Yen', 'sugar-calendar-lite' ),
		'KES' => esc_html__( 'Kenyan Shilling', 'sugar-calendar-lite' ),
		'KZT' => esc_html__( 'Kazakhstani Tenge', 'sugar-calendar-lite' ),
		'KIP' => esc_html__( 'Lao Kip', 'sugar-calendar-lite' ),
		'MYR' => esc_html__( 'Malaysian Ringgit', 'sugar-calendar-lite' ),
		'MXN' => esc_html__( 'Mexican Peso', 'sugar-calendar-lite' ),
		'NPR' => esc_html__( 'Nepali Rupee', 'sugar-calendar-lite' ),
		'NGN' => esc_html__( 'Nigerian Naira', 'sugar-calendar-lite' ),
		'NOK' => esc_html__( 'Norwegian Krone', 'sugar-calendar-lite' ),
		'NZD' => esc_html__( 'New Zealand Dollar', 'sugar-calendar-lite' ),
		'PKR' => esc_html__( 'Pakistani Rupee', 'sugar-calendar-lite' ),
		'PYG' => esc_html__( 'Paraguayan Guaraní', 'sugar-calendar-lite' ),
		'PHP' => esc_html__( 'Philippine Peso', 'sugar-calendar-lite' ),
		'PLN' => esc_html__( 'Polish Zloty', 'sugar-calendar-lite' ),
		'GBP' => esc_html__( 'Pounds Sterling', 'sugar-calendar-lite' ),
		'RON' => esc_html__( 'Romanian Leu', 'sugar-calendar-lite' ),
		'RUB' => esc_html__( 'Russian Ruble', 'sugar-calendar-lite' ),
		'SAR' => esc_html__( 'Saudi Arabian Riyal', 'sugar-calendar-lite' ),
		'SGD' => esc_html__( 'Singapore Dollar', 'sugar-calendar-lite' ),
		'ZAR' => esc_html__( 'South African Rand', 'sugar-calendar-lite' ),
		'KRW' => esc_html__( 'South Korean Won', 'sugar-calendar-lite' ),
		'SEK' => esc_html__( 'Swedish Krona', 'sugar-calendar-lite' ),
		'CHF' => esc_html__( 'Swiss Franc', 'sugar-calendar-lite' ),
		'TWD' => esc_html__( 'Taiwan New Dollar', 'sugar-calendar-lite' ),
		'THB' => esc_html__( 'Thai Baht', 'sugar-calendar-lite' ),
		'TND' => esc_html__( 'Tunisian Dinar', 'sugar-calendar-lite' ),
		'TRY' => esc_html__( 'Turkish Lira', 'sugar-calendar-lite' ),
		'AED' => esc_html__( 'United Arab Emirates Dirham', 'sugar-calendar-lite' ),
		'UAH' => esc_html__( 'Ukrainian Hryvnia', 'sugar-calendar-lite' ),
		'VND' => esc_html__( 'Vietnamese Dong', 'sugar-calendar-lite' ),
	);

	/**
	 * Filters the list of supported currencies.
	 *
	 * @since 1.0.0
	 *
	 * @param array $currencies Key/value pairs of currencies where the key is the currency slug
	 *                          and the value is the translatable labels.
	 */
	return apply_filters( 'sc_et_currencies', $currencies );
}

/**
 * Get the currency
 *
 * @since 1.0.0
 * @return string The currency code
 */
function get_currency() {

	$currency = Settings\get_setting( 'currency', 'USD' );

	/**
	 * Filters the currency.
	 *
	 * @since 1.0.0
	 *
	 * @param string $currency Slug for the current currency.
	 */
	return apply_filters( 'sc_et_currency', $currency );
}

/**
 * Sanitize boolean.
 *
 * @since 3.4.0
 *
 * @param mixed $value Value to sanitize.
 *
 * @return bool
 */
function sanitize_boolean( $value ) {

	return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
}

/**
 * Sanitize Amount.
 *
 * Returns a sanitized amount by stripping out thousands separators.
 *
 * @since 1.0.0
 * @since 3.3.0 Added default `$amount` value which is `0`.
 *
 * @param string $amount Amount to format.
 *
 * @return string $amount Newly sanitized amount
 */
function sanitize_amount( $amount ) {

	// Default.
	if ( empty( $amount ) ) {
		$amount = 0;
	}

	$is_negative   = false;
	$thousands_sep = Settings\get_setting( 'thousands_separator', ',' );
	$decimal_sep   = Settings\get_setting( 'decimal_separator', '.' );

	// Sanitize the amount
	if ( $decimal_sep === ',' && false !== ( $found = strpos( $amount, $decimal_sep ) ) ) {
		if ( ( $thousands_sep === '.' || $thousands_sep === ' ' ) && false !== ( $found = strpos( $amount, $thousands_sep ) ) ) {
			$amount = str_replace( $thousands_sep, '', $amount );
		} elseif ( empty( $thousands_sep ) && false !== ( $found = strpos( $amount, '.' ) ) ) {
			$amount = str_replace( '.', '', $amount );
		}

		$amount = str_replace( $decimal_sep, '.', $amount );
	} elseif ( $thousands_sep === ',' && false !== ( $found = strpos( $amount, $thousands_sep ) ) ) {
		$amount = str_replace( $thousands_sep, '', $amount );
	}

	if ( $amount < 0 ) {
		$is_negative = true;
	}

	$amount = preg_replace( '/[^0-9\.]/', '', $amount );

	/**
	 * Filter number of decimals to use for prices
	 *
	 * @since 1.0.0
	 *
	 * @param int $number Number of decimals
	 * @param int|string $amount Price
	 */
	$decimals = apply_filters( 'sc_et_sanitize_amount_decimals', get_decimal_count(), $amount );
	$amount   = number_format( (double) $amount, $decimals, '.', '' );

	if ( true === $is_negative ) {
		$amount *= -1;
	}

	/**
	 * Filter the sanitized price before returning
	 *
	 * @since 1.0.0
	 *
	 * @param string $amount Price
	 */
	return apply_filters( 'sc_et_sanitize_amount', $amount );
}

/**
 * Returns a nicely formatted amount.
 *
 * @since 1.0.0
 *
 * @param string $amount   Price amount to format
 * @param string $decimals Whether or not to use decimals.  Useful when set to false for non-currency numbers.
 *
 * @return string $amount Newly formatted amount or Price Not Available
 */
function format_amount( $amount, $decimals = true ) {

	$thousands_sep = Settings\get_setting( 'thousands_separator', ',' );
	$decimal_sep   = Settings\get_setting( 'decimal_separator', '.' );

	// Format the amount
	if ( $decimal_sep === ',' && false !== ( $sep_found = strpos( $amount, $decimal_sep ) ) ) {
		$whole  = substr( $amount, 0, $sep_found );
		$part   = substr( $amount, $sep_found + 1, ( strlen( $amount ) - 1 ) );
		$amount = $whole . '.' . $part;
	}

	// Strip , from the amount (if set as the thousands separator)
	if ( $thousands_sep === ',' && false !== ( $found = strpos( $amount, $thousands_sep ) ) ) {
		$amount = floatval( str_replace( ',', '', $amount ) );
	}

	if ( empty( $amount ) ) {
		$amount = 0;
	}

	if ( true === $decimals ) {

		/**
		 * Filters the number of decimals to use when formatting amounts.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $decimals Number of decimals to use.
		 * @param float $amount   Amount to format.
		 */
		$decimals = apply_filters( 'sc_et_format_amount_decimals', get_decimal_count(), $amount );
	} else {
		$decimals = 0;
	}

	$formatted = number_format( $amount, $decimals, $decimal_sep, $thousands_sep );

	/**
	 * Filters the formatted amount.
	 *
	 * @since 1.0.0
	 *
	 * @param string $formatted     Formatted amount.
	 * @param float  $amount        Amount to format.
	 * @param int    $decimals      Number of decimals used to format the amount.
	 * @param string $decimal_sep   Decimal separator used when formatting the amount.
	 * @param string $thousands_sep Thousands separator used when formatting the amount.
	 */
	return apply_filters( 'sc_et_format_amount', $formatted, $amount, $decimals, $decimal_sep, $thousands_sep );
}

/**
 * Retrieves the number of decimals to round to
 *
 * @since 1.0.0
 * @return int Number of decimal places
 */
function get_decimal_count() {

	$currency = get_currency();

	switch ( $currency ) {
		case 'RIAL' :
		case 'JPY' :
		case 'TWD' :
		case 'KRW' :
			$decimals = 0;
			break;

		case 'BTC' :
			$decimals = 9;
			break;

		default:
			$decimals = 2;
			break;
	}

	/**
	 * Filter the number decimals to round to.
	 *
	 * @since 1.0.0
	 *
	 * @param int $decimals Number of decimals. Default 2.
	 */
	return (int) apply_filters( 'sc_et_decimal_count', $decimals );
}

/**
 * Determines if the shop is using a zero-decimal currency
 *
 * @access      public
 * @since       1.0
 * @return      bool
 */
function is_zero_decimal_currency() {

	$retval   = false;
	$currency = get_currency();

	switch ( $currency ) {
		case 'BIF' :
		case 'CLP' :
		case 'DJF' :
		case 'GNF' :
		case 'JPY' :
		case 'KMF' :
		case 'KRW' :
		case 'MGA' :
		case 'PYG' :
		case 'RWF' :
		case 'VND' :
		case 'VUV' :
		case 'XAF' :
		case 'XOF' :
		case 'XPF' :
			$retval = true;
			break;
	}

	return $retval;
}

/**
 * Formats the currency display.
 *
 * @since 1.0.0
 * @since 3.3.0 Handle empty `$amount`.
 *
 * @param string $amount The amount.
 *
 * @return array $currency Currencies displayed correctly
 */
function currency_filter( $amount ) {

	if ( empty( $amount ) ) {
		$amount = 0.0;
	}

	$currency = get_currency();
	$position = Settings\get_setting( 'currency_position', 'before' );
	$negative = $amount < 0;

	// Remove proceeding "-" -
	if ( true === $negative ) {
		$amount = substr( $amount, 1 );
	}

	$amount = sanitize_amount( $amount );

	if ( 'before' === $position ) {

		switch ( $currency ) {
			case 'GBP' :
				$formatted = '&pound;' . $amount;
				break;

			case 'BRL' :
				$formatted = 'R&#36;' . $amount;
				break;

			case 'BTC' :
				$formatted = '&#579;' . $amount;
				break;

			case 'EUR' :
				$formatted = '&euro;' . $amount;
				break;

			case 'USD' :
			case 'AUD' :
			case 'CAD' :
			case 'HKD' :
			case 'MXN' :
			case 'SGD' :
				$formatted = '&#36;' . $amount;
				break;

			case 'RON' :
				$formatted = 'lei' . $amount;
				break;

			case 'UAH' :
				$formatted = '&#8372;' . $amount;
				break;

			case 'JPY' :
				$formatted = '&yen;' . $amount;
				break;

			case 'KRW' :
				$formatted = '&#8361;' . $amount;
				break;

			case 'PKR' :
				$formatted = '&#8360;' . $amount;
				break;

			default :
			    $formatted = $currency . ' ' . $amount;
				break;
		}

		/**
		 * Filters the formatted amount when the currency is displayed before the amount.
		 *
		 * The dynamic portion of the hook, `$currency`, refers to the currency.
		 *
		 * @since 1.0.0
		 *
		 * @param string $formatted The formatted amount.
		 * @param string $currency  Currency used to format the amount.
		 * @param float  $amount    Amount to be formatted.
		 */
		$formatted = apply_filters( 'sc_et_' . strtolower( $currency ) . '_currency_filter_before', $formatted, $currency, $amount );

	} else {

		switch ( $currency ) {
			case 'GBP' :
				$formatted = $amount . '&pound;';
				break;

			case 'BRL' :
				$formatted = $amount . 'R&#36;';
				break;

			case 'EUR' :
				$formatted = $amount . '&euro;';
				break;

			case 'USD' :
			case 'AUD' :
			case 'CAD' :
			case 'HKD' :
			case 'MXN' :
			case 'SGD' :
				$formatted = $amount . '&#36;';
				break;

			case 'RON' :
				$formatted = $amount . 'lei';
				break;

			case 'UAH' :
				$formatted = $amount . '&#8372;';
				break;

			case 'JPY' :
				$formatted = $amount . '&yen;';
				break;

			case 'KRW' :
				$formatted = $amount . '&#8361;';
				break;

			case 'IRR' :
				$formatted = $amount . '&#65020;';
				break;

			case 'RUB' :
				$formatted = $amount . '&#8381;';
				break;

			default :
			    $formatted = $amount . ' ' . $currency;
				break;
		}

		/**
		 * Filters the formatted amount when the currency is displayed following the amount.
		 *
		 * The dynamic portion of the hook, `$currency`, refers to the currency.
		 *
		 * @since 1.0.0
		 *
		 * @param string $formatted The formatted amount.
		 * @param string $currency  Currency used to format the amount.
		 * @param float  $amount    Amount to be formatted.
		 */
		$formatted = apply_filters( 'sc_et_' . strtolower( $currency ) . '_currency_filter_after', $formatted, $currency, $amount );
	}

	// Prepend the mins sign before the currency sign
	if ( true === $negative ) {
		$formatted = '-' . $formatted;
	}

	return $formatted;
}

/**
 * Determines if we are in sandbox mode
 *
 * @since 1.0.0
 * @return bool True if we are in sandbox mode
 */
function is_sandbox() {

    $is_sandbox = ( defined( 'SC_GATEWAY_SANDBOX_MODE' ) && SC_GATEWAY_SANDBOX_MODE )
		? true
		: Settings\get_setting( 'sandbox' );

	/**
	 * Filters whether or not sandbox mode is enabled.
	 *
	 * @param bool $is_sandbox
	 */
    return (bool) apply_filters( 'sc_et_is_sandbox', $is_sandbox );

}

/**
 * Retrieve Stripe publishable key
 *
 * @since 1.0.0
 * @return string Stripe publishable key
 */
function get_stripe_publishable_key() {

	if ( is_sandbox() ) {
		$key = get_option( 'sc_stripe_test_publishable' );
	} else {
		$key = get_option( 'sc_stripe_live_publishable' );
	}

	return $key;
}

/**
 * Retrieve Stripe secret key
 *
 * @since 1.0.0
 * @return string Stripe secret key
 */
function get_stripe_secret_key() {

	if ( is_sandbox() ) {
		$key = get_option( 'sc_stripe_test_secret' );
	} else {
		$key = get_option( 'sc_stripe_live_secret' );
	}

	return $key;
}

/**
 * Send order receipt email.
 *
 * @since 1.0.0
 *
 * @param int $order_id ID of the order to send receipt for.
 *
 * @return bool
 */
function send_order_receipt_email( $order_id = 0 ) {

	// Get order
	$order = get_order( $order_id );

	// Bail if no order
	if ( empty( $order ) ) {
		return;
	}

	$emails              = new \Sugar_Calendar\AddOn\Ticketing\Emails;
	$emails->object_id   = $order_id;
	$emails->object_type = 'order';
	$emails->heading     = esc_html__( 'Order Receipt', 'sugar-calendar-lite' );

	return $emails->send(
		$order->email,
		Settings\get_setting( 'receipt_subject' ),
		Settings\get_setting( 'receipt_message' )
	);
}

/**
 * Send ticket email
 *
 * @since 1.0.0
 * @param $ticket_id ID of the ticket to send email for
 * @return bool
 */
function send_ticket_email( $ticket_id = 0 ) {

	// Get ticket
	$ticket = get_ticket( $ticket_id );

	// Bail if no Ticket
	if ( empty( $ticket ) ) {
		return;
	}

	// Get attendee
	$attendee = get_attendee( $ticket->attendee_id );

	// Bail if no attendee
	if ( empty( $attendee->id ) ) {
		return;
	}

	$emails              = new \Sugar_Calendar\AddOn\Ticketing\Emails;
	$emails->object_id   = $ticket_id;
	$emails->object_type = 'ticket';
	$emails->heading     = esc_html__( 'Event Ticket', 'sugar-calendar-lite' );

	return $emails->send(
		$attendee->email,
		Settings\get_setting( 'ticket_subject' ),
		Settings\get_setting( 'ticket_message' )
	);
}

/**
 * Email template tag: name
 *
 * @since 1.0.0
 * @param int $order_id
 * @return string name
 */
function get_email_tag_name( $order_id = 0 ) {
	$order = get_order( $order_id );
	return $order->first_name . ' ' . $order->last_name;
}

/**
 * Email template tag: email
 *
 * @since 1.0.0
 * @param int $order_id
 * @return string email
 */
function get_email_tag_email( $order_id = 0 ) {
	$order = get_order( $order_id );
	return $order->email;
}

/**
 * Email template tag: order_id
 *
 * @since 1.0.0
 * @param int $order_id
 * @return int order ID
 */
function get_email_tag_order_id( $order_id = 0 ) {
	$order = get_order( $order_id );
	return $order->id;
}

/**
 * Email template tag: order_amount
 *
 * @since 1.0.0
 * @param int $order_id
 * @return string Formatted order total
 */
function get_email_tag_order_amount( $order_id = 0 ) {
	$order = get_order( $order_id );
	return currency_filter( $order->total );
}

/**
 * Email template tag: order_date
 *
 * @since 1.0.0
 * @param int $order_id
 * @return string Order date
 */
function get_email_tag_order_date( $order_id = 0 ) {
	$order = get_order( $order_id );
	return date_i18n( sc_get_date_format(), $order->date_created );
}

/**
 * Email template tag: receipt_url
 *
 * @since 1.0.0
 * @param int $order_id
 * @return string Receipt URL
 */
function get_email_tag_receipt_url( $order_id = 0 ) {
	$order = get_order( $order_id );
	$page  = Settings\get_setting( 'receipt_page' );
	$url   = add_query_arg(
		array(
			'order_id' => $order_id,
			'email'    => $order->email
		),
		get_permalink( $page )
	);

	return esc_url( $url );
}

/**
 * Email template tag: tickets
 *
 * @since 1.0.0
 * @param int $order_id
 * @return string Ticket List
 */
function get_email_tag_tickets( $order_id = 0 ) {
	$tickets = get_order_tickets( $order_id );
	$retval  = '<ul>';

	$page = Settings\get_setting( 'ticket_page' );
	$link = get_permalink( $page );
	$home = home_url();

	foreach ( $tickets as $ticket ) {
		$retval .= '<li>';
			$retval .= '<div>#' . $ticket->id . '</div>';
			$retval .= '<div>' . $ticket->code . '</div>';

			if ( ! empty( $ticket->attendee_id ) ) {
				$attendee = get_attendee( $ticket->attendee_id );
				$retval .= '<div>' . $attendee->first_name . ' ' . $attendee->last_name . '</div>';
			}

			$retval .= '<div>';
				$retval .= '<a href="' . wp_nonce_url( add_query_arg( array( 'sc_et_action' => 'print', 'ticket_code' => $ticket->code ), $home ), $ticket->code ) . '">' . esc_html__( 'Print', 'sugar-calendar-lite' ) . '</a>';
				$retval .= '&nbsp;|&nbsp;<a href="' . add_query_arg( array( 'order_id' => $order_id, 'ticket_code' => $ticket->code ), $link ) . '">' . esc_html__( 'View', 'sugar-calendar-lite' ) . '</a>';
			$retval .= '</div>';
		$retval .= '</li>';
	}

	$retval .= '</ul>';

	return $retval;
}

/**
 * Email template tag: event_id
 *
 * @since 1.0.0
 * @param int $order_id
 * @return int Event ID
 */
function get_email_tag_event_id( $order_id = 0 ) {
	$order = get_order( $order_id );
	return $order->event_id;
}

/**
 * Email template tag: event_title
 *
 * @since 1.0.0
 * @param int $object_id
 * @param string $object_type
 * @return int Event ID
 */
function get_email_tag_event_title( $object_id = 0, $object_type = 'order' ) {

	if ( 'order' === $object_type ) {
		$object = get_order( $object_id );
	} else {
		$object = get_ticket( $object_id );
	}

	$event = sugar_calendar_get_event( $object->event_id );

	return $event->title;
}

/**
 * Email template tag: event_url.
 *
 * @since 1.0.0
 *
 * @param int    $object_id   Object ID.
 * @param string $object_type Object type. Can be either 'order' or 'ticket'.
 *
 * @return string Event URL
 */
function get_email_tag_event_url( $object_id = 0, $object_type = 'order' ) {

	if ( $object_type === 'order' ) {
		$object = get_order( $object_id );
	} else {
		$object = get_ticket( $object_id );
	}

	/**
	 * Filters the event object for the event URL tag.
	 *
	 * @since 3.6.0
	 *
	 * @param \Sugar_Calendar\Event $event       The event object.
	 * @param string                $object_type Can be either 'order' or 'ticket'.
	 * @param object                $object      Can either be the Order or Ticket object.
	 */
	$event = apply_filters(
		'sc_et_receipt_email_tag_event_url_event',
		sugar_calendar_get_event( $object->event_id ),
		$object_type,
		$object
	);

	/**
	 * Filters the email tag event URL.
	 *
	 * @since 3.6.0
	 *
	 * @param string                $url   The event URL.
	 * @param \Sugar_Calendar\Event $event The event object.
	 */
	return apply_filters(
		'sc_et_receipt_email_tag_event_url',
		esc_url( get_permalink( $event->object_id ) ),
		$event
	);
}

/**
 * Email template tag: event_date.
 *
 * @since 1.0.0
 *
 * @param int    $object_id   Object ID.
 * @param string $object_type Object type. Can be either 'order' or 'ticket'.
 *
 * @return string Event date
 */
function get_email_tag_event_date( $object_id = 0, $object_type = 'order' ) {

	if ( $object_type === 'order' ) {
		$object = get_order( $object_id );
	} else {
		$object = get_ticket( $object_id );
	}

	/**
	 * Filters the event object for the event date tag.
	 *
	 * @since 3.6.0
	 *
	 * @param \Sugar_Calendar\Event $event       The event object.
	 * @param string                $object_type Can be either 'order' or 'ticket'.
	 * @param object                $object      Can either be the Order or Ticket object.
	 */
	$event = apply_filters(
		'sc_et_receipt_email_tag_event_date_event',
		sugar_calendar_get_event( $object->event_id ),
		$object_type,
		$object
	);

	$retval = $event->format_date( sc_get_date_format(), $event->start );

	return $retval;
}

/**
 * Email template tag: event_start_time
 *
 * @since 1.0.0
 * @param int $object_id
 * @param string $object_type
 * @return string Event start time
 */
function get_email_tag_event_start_time( $object_id = 0, $object_type = 'order' ) {

	if ( 'order' === $object_type ) {
		$object = get_order( $object_id );
	} else {
		$object = get_ticket( $object_id );
	}

	$event  = sugar_calendar_get_event( $object->event_id );
	$retval = $event->format_date( sc_get_time_format(), $event->start );

	return $retval;
}

/**
 * Email template tag: event_end_time
 *
 * @since 1.0.0
 * @param int $object_id
 * @param string $object_type
 * @return string Event end time
 */
function get_email_tag_event_end_time( $object_id = 0, $object_type = 'order' ) {

	if ( 'order' === $object_type ) {
		$object = get_order( $object_id );
	} else {
		$object = get_ticket( $object_id );
	}

	$event  = sugar_calendar_get_event( $object->event_id );
	$retval = $event->format_date( sc_get_time_format(), $event->end );

	return $retval;
}

/**
 * Email template tag: ticket_id
 *
 * @since 1.0.0
 * @param int $ticket_id
 * @return int Ticket ID
 */
function get_email_tag_ticket_id( $ticket_id = 0 ) {
	$ticket = get_ticket( $ticket_id );
	return $ticket->id;
}

/**
 * Email template tag: ticket_code
 *
 * @since 1.0.0
 * @param int $ticket_id
 * @return string Ticket code
 */
function get_email_tag_ticket_code( $ticket_id = 0 ) {
	$ticket = get_ticket( $ticket_id );
	return $ticket->code;
}

/**
 * Email template tag: ticket_url
 *
 * @since 1.0.0
 * @param int $ticket_id
 * @return string Ticket URL
 */
function get_email_tag_ticket_url( $ticket_id = 0 ) {
	$ticket = get_ticket( $ticket_id );
	$page   = Settings\get_setting( 'ticket_page' );
	$url    = add_query_arg(
		array(
			'order_id'    => $ticket->order_id,
			'ticket_code' => $ticket->code
		),
		get_permalink( $page )
	);

	return esc_url( $url );
}

/**
 * Email template tag: attendee_name
 *
 * @since 1.0.0
 * @param int $ticket_id
 * @return string Attendee name
 */
function get_email_tag_attendee_name( $ticket_id = 0 ) {
	$ticket   = get_ticket( $ticket_id );
	$attendee = get_attendee( $ticket->attendee_id );
	return $attendee->first_name . ' ' . $attendee->last_name;
}

/**
 * Email template tag: attendee_email
 *
 * @since 1.0.0
 * @param int $ticket_id
 * @return string Attendee email
 */
function get_email_tag_attendee_email( $ticket_id = 0 ) {
	$ticket   = get_ticket( $ticket_id );
	$attendee = get_attendee( $ticket->attendee_id );
	return $attendee->email;
}

/**
 * Whether or not to display tickets.
 *
 * @since 3.2.0
 *
 * @param \Sugar_Calendar\Event $event The event object.
 *
 * @return bool
 */
function should_display_tickets( $event ) {

	/**
	 * Filters whether or not to display tickets.
	 *
	 * @since 3.2.0
	 *
	 * @param bool                  $display_tickets Whether or not to display tickets.
	 * @param \Sugar_Calendar\Event $event The event object.
	 */
	return apply_filters(
		'sc_et_should_display_tickets',
		get_stripe_publishable_key() && get_stripe_secret_key(),
		$event
	);
}

/**
 * Return Stripe connect URL.
 *
 * @since 3.7.0
 *
 * @param bool   $is_sandbox           Whether Stripe is in sandbox mode.
 * @param string $url_payment_settings Payment settings URL.
 *
 * @return string
 */
function get_stripe_connect_url( $is_sandbox, $url_payment_settings ) {

	$stripe_connect_url = add_query_arg(
		[
			'live_mode'         => rawurlencode( (int) ! $is_sandbox ),
			'state'             => rawurlencode( str_pad( wp_rand( wp_rand(), PHP_INT_MAX ), 100, wp_rand(), STR_PAD_BOTH ) ),
			'customer_site_url' => rawurlencode( $url_payment_settings ),
		],
		'https://sugarcalendar.com/?sc_gateway_connect_init=stripe_connect'
	);

	return $stripe_connect_url;
}

/**
 * Return Stripe credentials URL.
 *
 * @since 3.7.0
 *
 * @param bool   $is_sandbox           Whether Stripe is in sandbox mode.
 * @param string $state                Stripe state auth parameter.
 * @param string $url_payment_settings Payment settings URL.
 *
 * @return string
 */
function get_stripe_credentials_url( $is_sandbox, $state, $url_payment_settings ) {

	$sc_credentials_url = add_query_arg(
		[
			'live_mode'         => rawurlencode( (int) ! $is_sandbox ),
			'state'             => rawurlencode( $state ),
			'customer_site_url' => rawurlencode( $url_payment_settings ),
		],
		'https://sugarcalendar.com/?sc_gateway_connect_credentials=stripe_connect'
	);

	return $sc_credentials_url;
}

/**
 * Update Stripe credentials.
 *
 * @since 3.7.0
 *
 * @param string     $publishable_key Stripe publishable key.
 * @param string     $secret_key      Stripe secret key.
 * @param int|string $user_id         Current user ID.
 * @param bool       $is_sandbox      Whether Stripe is in sandbox mode.
 *
 * @return void
 */
function update_stripe_credentials( $publishable_key, $secret_key, $user_id, $is_sandbox ) {

	if ( $is_sandbox === true ) {
		update_option( 'sc_stripe_test_publishable', sanitize_text_field( $publishable_key ), false );
		update_option( 'sc_stripe_test_secret', sanitize_text_field( $secret_key ), false );
	} else {
		update_option( 'sc_stripe_live_publishable', sanitize_text_field( $publishable_key ), false );
		update_option( 'sc_stripe_live_secret', sanitize_text_field( $secret_key ), false );
	}

	update_option( 'sc_stripe_connect_account_id', sanitize_text_field( $user_id ), false );
}

/**
 * Whether Stripe is connected.
 *
 * @since 3.7.0
 *
 * @return bool
 */
function stripe_is_connected() {

	$stripe_connect_account_id = get_option( 'sc_stripe_connect_account_id' );

	return (
		! empty( $stripe_connect_account_id ) &&
		get_stripe_publishable_key() &&
		get_stripe_secret_key()
	);
}
