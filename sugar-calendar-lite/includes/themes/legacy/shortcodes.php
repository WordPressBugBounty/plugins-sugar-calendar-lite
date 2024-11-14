<?php

/**
 * Return an array of registered shortcode IDs.
 *
 * @since 2.0.13
 *
 * @return array
 */
function sc_get_shortcode_ids() {
	return array(
		'sc_events_list',
		'sc_events_calendar'
	);
}

/**
 * Add Event Calendar shortcodes
 *
 * @since 2.0.0
 */
function sc_add_shortcodes() {
	add_shortcode( 'sc_events_list',     'sc_events_list_shortcode'     );
	add_shortcode( 'sc_events_calendar', 'sc_events_calendar_shortcode' );
}

/**
 * Event Calendar shortcode callback
 *
 * @since 1.0.0
 *
 * @param array $atts
 * @param null|string $content
 *
 * @return string
 */
function sc_events_calendar_shortcode( $atts = array(), $content = null ) {

	// Parse
	$atts = shortcode_atts( array(
		'size'     => 'large',
		'category' => null,
		'type'     => 'month',
		'month'    => null,
		'year'     => null,
		'sow'      => null
	), $atts );

	// Defaults
	$size     = isset( $atts['size']     ) ? $atts['size']     : 'large';
	$category = isset( $atts['category'] ) ? $atts['category'] : null;
	$type     = isset( $atts['type']     ) ? $atts['type']     : 'month';
	$month    = isset( $atts['month']    ) ? $atts['month']    : null;
	$year     = isset( $atts['year']     ) ? $atts['year']     : null;
	$sow      = isset( $atts['sow']      ) ? $atts['sow']      : null;

	// Get valid types
	$valid_types = sc_get_valid_calendar_types();

	// Fallback to "month" if invalid
	if ( ! in_array( $type, $valid_types, true ) ) {
		$type = 'month';
	}

	$load_calendar = true;

	if ( \Sugar_Calendar\Helpers::should_allow_visitor_tz_convert_cal_shortcode() ) {
		/*
		 * If visitor tz convert is enabled, then we shouldn't load the calendar/events
		 * in its first load.
		 * This is because we need first need to get the visitor's timezone, then do the load.
		 * This is done via JS.
		 */
		$load_calendar = false;
	}

	// Get the calendar HTML.
	$calendar = sc_get_events_calendar( $size, $category, $type, $year, $month, $sow, false, $load_calendar );

	// Wrap it in a div (@todo remove ID)
	return '<div id="sc_calendar_wrap">' . $calendar . '</div>';
}

/**
 * Event list shortcode callback.
 *
 * @since 1.0.0
 * @since 3.4.0 Return a no events message when there are no events.
 *
 * @param array $atts    The attributes passed to the shortcode.
 * @param null  $content The content inside the shortcode.
 *
 * @return string
 */
function sc_events_list_shortcode( $atts = [], $content = null ) {

	// Parse.
	$atts = shortcode_atts(
		[
			'display'         => 'upcoming',
			'order'           => '',
			'number'          => '5',
			'category'        => null,
			'show_date'       => null,
			'show_time'       => null,
			'show_categories' => null,
			'show_link'       => null,
		],
		$atts
	);

	// Escape all values.
	$display         = esc_attr( $atts['display'] );
	$order           = esc_attr( $atts['order'] );
	$category        = esc_attr( $atts['category'] );
	$number          = esc_attr( $atts['number'] );
	$show_date       = esc_attr( $atts['show_date'] );
	$show_time       = esc_attr( $atts['show_time'] );
	$show_categories = esc_attr( $atts['show_categories'] );
	$show_link       = esc_attr( $atts['show_link'] );

	// Return arguments.
	$args = [
		'date'       => $show_date,
		'time'       => $show_time,
		'categories' => $show_categories,
		'link'       => $show_link,
	];

	$events_list = sc_get_events_list( $display, $category, $number, $args, $order );

	if ( empty( $events_list ) ) {
		$events_list = '<p>' .
			esc_html(
				/**
				 * Filters the message to display when there are no events.
				 *
				 * @since 3.4.0
				 *
				 * @param string $no_events_message The message to display when there are no events.
				 * @param string $display           The display type of events.
				 * @param array  $args              The arguments passed to the widget.
				 */
				apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
					'sc_events_list_shortcode_no_events',
					\Sugar_Calendar\Helpers::get_no_events_message_for_legacy_event_list( $display ),
					$display,
					$args
				)
			)
			. '</p>';
	}

	return $events_list;
}
