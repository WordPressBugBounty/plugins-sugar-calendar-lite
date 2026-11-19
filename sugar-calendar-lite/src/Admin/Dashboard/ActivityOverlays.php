<?php

namespace Sugar_Calendar\Admin\Dashboard;

use Sugar_Calendar\Admin\Pages\EventNew;

/**
 * The popups the Activity tabs show when they have nothing to list.
 *
 * @since 3.18.0
 */
class ActivityOverlays {

	/**
	 * The Attendees popup.
	 *
	 * @since 3.18.0
	 *
	 * @param string $variant Attendees variant: `no-events`, `no-registrations` or `empty-range`.
	 *
	 * @return array `title`, `text`, `cta` and `disclaimer`, as the widget-overlay template reads them.
	 */
	public static function get_attendees( string $variant ): array {

		if ( $variant === 'empty-range' ) {
			return self::build(
				__( 'No Attendees in This Period', 'sugar-calendar-lite' ),
				__( 'Nobody registered for an event in this period. Try a different date range.', 'sugar-calendar-lite' )
			);
		}

		$cta = $variant === 'no-events'
			? [
				'label' => __( 'Add New Event', 'sugar-calendar-lite' ),
				'url'   => admin_url( EventNew::get_slug() ),
			]
			: [];

		return self::build(
			__( 'No Attendees Yet', 'sugar-calendar-lite' ),
			__( 'When attendees buy tickets or RSVP to your events, their registrations will show up here.', 'sugar-calendar-lite' ),
			$cta
		);
	}

	/**
	 * The Orders popup.
	 *
	 * @since 3.18.0
	 *
	 * @param string $variant Orders variant: `connect`, `empty` or `empty-range`.
	 *
	 * @return array `title`, `text`, `cta` and `disclaimer`, as the widget-overlay template reads them.
	 */
	public static function get_orders( string $variant ): array {

		if ( $variant === 'connect' ) {
			return self::build(
				__( 'Accept Payments with Stripe', 'sugar-calendar-lite' ),
				__( 'Connect a Stripe account to sell tickets and see orders, sales, and refunds right here on your dashboard.', 'sugar-calendar-lite' ),
				[
					'label' => __( 'Connect with Stripe', 'sugar-calendar-lite' ),
					'url'   => ( new StripeConnect() )->get_connect_url(),
				],
				__( 'The data shown behind this popup is for example purposes only.', 'sugar-calendar-lite' )
			);
		}

		if ( $variant === 'empty-range' ) {
			return self::build(
				__( 'No Orders in This Period', 'sugar-calendar-lite' ),
				__( 'No tickets were bought in this period. Try a different date range.', 'sugar-calendar-lite' )
			);
		}

		return self::build(
			__( 'No Orders Yet', 'sugar-calendar-lite' ),
			__( 'Orders will show up here once people start buying tickets to your events.', 'sugar-calendar-lite' )
		);
	}

	/**
	 * A popup's view.
	 *
	 * @since 3.18.0
	 *
	 * @param string $title      Heading.
	 * @param string $text       Copy.
	 * @param array  $cta        Button `label` and `url`; empty renders none.
	 * @param string $disclaimer Footer note; empty renders none.
	 *
	 * @return array
	 */
	private static function build( string $title, string $text, array $cta = [], string $disclaimer = '' ): array {

		return [
			'title'      => $title,
			'text'       => $text,
			'cta'        => $cta,
			'disclaimer' => $disclaimer,
		];
	}
}
