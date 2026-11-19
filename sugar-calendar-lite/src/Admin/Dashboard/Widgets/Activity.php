<?php

namespace Sugar_Calendar\Admin\Dashboard\Widgets;

use Sugar_Calendar\AddOn\Ticketing\Admin\Pages\OrdersTab;
use Sugar_Calendar\Admin\Dashboard\AccessContext;
use Sugar_Calendar\Admin\Dashboard\ActivityCards;
use Sugar_Calendar\Admin\Dashboard\ActivityLists;
use Sugar_Calendar\Admin\Dashboard\ActivityOverlays;
use Sugar_Calendar\Admin\Dashboard\ActivityRows;
use Sugar_Calendar\Admin\Dashboard\ActivityTeaser;
use Sugar_Calendar\Admin\Dashboard\Templates;
use Sugar_Calendar\Admin\Dashboard\WidgetState;
use Sugar_Calendar\Helpers\Money;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\ticketing_provider_available_for_admin;

// Built from data, so named here for Tailwind's scanner: sc:icon-[fa6-regular--credit-card]
// sc:icon-[fa6-regular--money-bill-1] (no period after a class: the scanner would skip it).

/**
 * The Attendees | Orders widget.
 *
 * The card is always `data`; each tab resolves its own variant from what the site has and what the
 * range holds. Its lists, cards and rows are separate classes, each with a Pro subclass the Pro
 * widget picks; its popups' copy is `ActivityOverlays`.
 *
 * @since 3.18.0
 */
class Activity extends AbstractWidget {

	/**
	 * Column.
	 *
	 * @since 3.18.0
	 */
	const COLUMN = 'main';

	/**
	 * Sort position.
	 *
	 * @since 3.18.0
	 */
	const ORDER = 20;

	/**
	 * The recipe spaces its own tabs, chart and table.
	 *
	 * @since 3.18.0
	 */
	const BODY_FLUSH = true;

	/**
	 * Rows in each list.
	 *
	 * @since 3.18.0
	 */
	const LIST_LIMIT = 5;

	/**
	 * Per-user choices (the tab and the Orders metric) and their values; the first is the default.
	 *
	 * @since 3.18.0
	 */
	const SETTINGS = [
		'tab'    => [ 'attendees', 'orders' ],
		'metric' => [ 'payments', 'sales' ],
	];

	/**
	 * What the tabs read from the site.
	 *
	 * @since 3.18.0
	 *
	 * @var ActivityLists
	 */
	private $lists;

	/**
	 * The inline cards.
	 *
	 * @since 3.18.0
	 *
	 * @var ActivityCards
	 */
	private $cards;

	/**
	 * The table rows.
	 *
	 * @since 3.18.0
	 *
	 * @var ActivityRows
	 */
	private $rows;

	/**
	 * Constructor.
	 *
	 * @since 3.18.0
	 */
	public function __construct() {

		$this->lists = $this->create_lists();
		$this->cards = $this->create_cards( $this->lists );
		$this->rows  = $this->create_rows();
	}

	/**
	 * Widget identifier.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'activity';
	}

	/**
	 * Widget title; the tabs take its place.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_title(): string {

		return '';
	}

	/**
	 * Always visible.
	 *
	 * @since 3.18.0
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 */
	public function get_state( AccessContext $access ): WidgetState {

		return new WidgetState( true, 'data' );
	}

	/**
	 * End the open episodes of the named cards that no longer apply, for a change made away from the
	 * Dashboard. The episode listeners come through here, so on Pro the Pro cards and lists answer.
	 *
	 * @since 3.18.0
	 *
	 * @param array $card_ids Card IDs.
	 */
	public function end_card_episodes( array $card_ids ): void {

		$this->cards->end_episodes( $card_ids );
	}

	/**
	 * Graph series metadata for the JS.
	 *
	 * @since 3.18.0
	 *
	 * @return array Metric => `label`, `token` (the line's `--sc-*` colour), `color` (its fallback) and, for an amount, `money` (`Money::get_format()`).
	 */
	public function get_series_meta(): array {

		return [
			'attendees' => [
				'label' => __( 'Attendees', 'sugar-calendar-lite' ),
				'token' => '--sc-color-brand-blue-50',
				'color' => '#2271b1',
			],
			'payments'  => [
				'label' => __( 'Total Payments', 'sugar-calendar-lite' ),
				'token' => '--sc-color-utility-green-30',
				'color' => '#00ba37',
			],
			'sales'     => [
				'label' => __( 'Total Sales', 'sugar-calendar-lite' ),
				'token' => '--sc-color-utility-green-30',
				'color' => '#00ba37',
				'money' => Money::get_format(),
			],
		];
	}

	/**
	 * Render the tabs and both tab panels; the recipe puts the tabs inside the body.
	 *
	 * Public: the range refresh (`Ajax::get_stats()`) sends the whole body again.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Range data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 */
	public function render_body( string $variant, array $data, AccessContext $access ): string {

		$settings = $this->get_settings( $access );

		return Templates::get_html(
			'widgets/activity',
			[
				'series'    => $data['series'] ?? [],
				'tabs'      => [
					'attendees' => __( 'Attendees', 'sugar-calendar-lite' ),
					'orders'    => __( 'Orders', 'sugar-calendar-lite' ),
				],
				'active'    => $settings['tab'],
				'attendees' => $this->get_attendees_view( $data, $access ),
				'orders'    => $this->get_orders_view( $data, $access, $settings['metric'] ),
			]
		);
	}

	/**
	 * Attendees tab variant: `data`, `no-events` or `no-registrations`.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	protected function get_attendees_variant(): string {

		if ( ! $this->lists->has_events() ) {
			return 'no-events';
		}

		return $this->lists->has_attendees() ? 'data' : 'no-registrations';
	}

	/**
	 * Orders tab variant: `data`, `reconnect` (past orders, no payment method),
	 * `connect` (no orders, no payment method) or `empty` (no orders yet).
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	protected function get_orders_variant(): string {

		if ( $this->lists->needs_payment_reconnect() ) {
			return 'reconnect';
		}

		if ( $this->lists->has_orders() ) {
			return 'data';
		}

		return ticketing_provider_available_for_admin() ? 'empty' : 'connect';
	}

	/**
	 * What the tabs read from the site.
	 *
	 * @since 3.18.0
	 *
	 * @return ActivityLists
	 */
	protected function create_lists(): ActivityLists {

		return new ActivityLists();
	}

	/**
	 * The inline cards.
	 *
	 * @since 3.18.0
	 *
	 * @param ActivityLists $lists What the cards' conditions read from the site.
	 *
	 * @return ActivityCards
	 */
	protected function create_cards( ActivityLists $lists ): ActivityCards {

		return new ActivityCards( $lists );
	}

	/**
	 * The table rows.
	 *
	 * @since 3.18.0
	 *
	 * @return ActivityRows
	 */
	protected function create_rows(): ActivityRows {

		return new ActivityRows();
	}

	/**
	 * Attendees panel view: the site's variant, or `empty-range` when the range holds no attendees.
	 *
	 * @since 3.18.0
	 *
	 * @param array         $data   Range data.
	 * @param AccessContext $access Access context.
	 *
	 * @return array
	 */
	private function get_attendees_view( array $data, AccessContext $access ): array {

		$variant = $this->get_attendees_variant();
		$rows    = [];
		$cards   = [];

		if ( $variant === 'data' ) {
			$rows = $this->rows->prepare_attendee_rows( $this->lists->get_latest_attendees( self::LIST_LIMIT, $data['date_range'] ) );

			// Asked whatever the range holds, so the card's episode can end on a quiet range too.
			$cards = $this->cards->get_visible( [ 'free_tickets' ], $access );

			// A site with registrations but none in the range gets the popup, not an empty table.
			$variant = $rows === [] ? 'empty-range' : 'data';
		}

		return [
			'variant' => $variant,
			'rows'    => $rows,
			'cards'   => $variant === 'data' ? $this->cards->render( $cards ) : '',
			'overlay' => ActivityOverlays::get_attendees( $variant ),
		];
	}

	/**
	 * Orders panel view: the site's variant, or `empty-range` when the range holds no orders.
	 *
	 * @since 3.18.0
	 *
	 * @param array         $data   Range data.
	 * @param AccessContext $access Access context.
	 * @param string        $metric Selected metric.
	 *
	 * @return array
	 */
	private function get_orders_view( array $data, AccessContext $access, string $metric ): array {

		$variant  = $this->get_orders_variant();
		$has_data = in_array( $variant, [ 'data', 'reconnect' ], true );
		$rows     = $has_data ? $this->rows->prepare_order_rows( $this->lists->get_latest_orders( self::LIST_LIMIT, $data['date_range'] ) ) : [];

		// Both cards are asked on every load, so either can record its episode's end; at most one applies.
		$cards = $this->cards->get_visible( [ 'application_fee', 'reconnect_payments' ], $access );

		// A site with orders but none in the range gets the popup, not an empty table. A site that lost
		// its payment method keeps its table, so the reconnect card stays in view.
		if ( $variant === 'data' && $rows === [] ) {
			$variant  = 'empty-range';
			$has_data = false;
		}

		$teaser = $variant === 'connect' ? ( new ActivityTeaser() )->get_view( $this->rows ) : [];

		return [
			'variant'    => $variant,
			'has_data'   => $has_data,
			'metric'     => $metric,
			'metrics'    => $has_data ? $this->rows->prepare_order_metrics( $data, $metric ) : [],
			'rows'       => $rows,
			'cards'      => $has_data ? $this->cards->render( $cards ) : '',
			'orders_url' => OrdersTab::get_url(),
			'overlay'    => ActivityOverlays::get_orders( $variant ),
			'teaser'     => $teaser,
		];
	}
}
