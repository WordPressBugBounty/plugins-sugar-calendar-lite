<?php

namespace Sugar_Calendar\Admin\Dashboard;

use Sugar_Calendar\Admin\Dashboard\Widgets\AbstractWidget;
use Sugar_Calendar\Helpers\ApplicationFee;
use Sugar_Calendar\Helpers\Helpers;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\stripe_is_connected;

// Built from data, so named here for Tailwind's scanner: sc:icon-[fa6-solid--ticket]
// sc:icon-[fa6-solid--lightbulb] sc:icon-[fa6-solid--plug] (no period after a class: the scanner would skip it).

/**
 * The Activity widget's dismissible cards: free tickets, the application fee and Reconnect.
 *
 * @since 3.18.0
 */
class ActivityCards {

	/**
	 * The inline cards' IDs, the only ones a dismissal may store.
	 *
	 * @since 3.18.0
	 */
	const CARD_IDS = [ 'free_tickets', 'application_fee', 'reconnect_payments' ];

	/**
	 * Transient set while a dismissed free-tickets card's condition was checked in the last day.
	 *
	 * @since 3.18.0
	 */
	const FREE_TICKETS_CHECKED_TRANSIENT = 'sugar_calendar_dashboard_free_tickets_checked';

	/**
	 * What the cards' conditions read from the site.
	 *
	 * @since 3.18.0
	 *
	 * @var ActivityLists
	 */
	private $lists;

	/**
	 * When each card's condition last ended.
	 *
	 * @since 3.18.0
	 *
	 * @var CardEpisodes
	 */
	private $episodes;

	/**
	 * Constructor.
	 *
	 * @since 3.18.0
	 *
	 * @param ActivityLists $lists What the cards' conditions read from the site.
	 */
	public function __construct( ActivityLists $lists ) {

		$this->lists    = $lists;
		$this->episodes = new CardEpisodes();
	}

	/**
	 * The cards to show, from those named: each one that applies and that the user has not dismissed
	 * since its condition last recurred. A card that no longer applies ends its episode.
	 *
	 * @since 3.18.0
	 *
	 * @param array         $card_ids Card IDs, in display order.
	 * @param AccessContext $access   Access context.
	 *
	 * @return array Cards: `id`, `icon`, `title`, `text`, `cta`.
	 */
	public function get_visible( array $card_ids, AccessContext $access ): array {

		$cards = [];

		foreach ( $card_ids as $card_id ) {
			$is_dismissed = $this->episodes->is_dismissed( $card_id, $access->get_dismissals() );

			if ( $is_dismissed && ! $this->is_check_due( $card_id ) ) {
				continue;
			}

			if ( ! $this->applies( $card_id ) ) {
				$this->episodes->end( $card_id );

				continue;
			}

			if ( ! $is_dismissed ) {
				$cards[] = $this->get_card( $card_id );
			}
		}

		return $cards;
	}

	/**
	 * End the open episodes of the named cards that no longer apply, for a change made away from the Dashboard.
	 *
	 * @since 3.18.0
	 *
	 * @param array $card_ids Card IDs.
	 */
	public function end_episodes( array $card_ids ): void {

		foreach ( $card_ids as $card_id ) {
			if ( $this->episodes->is_open( $card_id ) && ! $this->applies( $card_id ) ) {
				$this->episodes->end( $card_id );
			}
		}
	}

	/**
	 * Render cards.
	 *
	 * @since 3.18.0
	 *
	 * @param array $cards Cards from get_visible().
	 *
	 * @return string
	 */
	public function render( array $cards ): string {

		$html = '';

		foreach ( $cards as $card ) {
			$html .= Templates::get_html( 'inline-card', [ 'card' => $card ] );
		}

		return $html;
	}

	/**
	 * Free-tickets card, offering the upgrade, while most of the last 30 days' tickets were free.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	protected function get_free_tickets_card(): array {

		return [
			'id'    => 'free_tickets',
			'icon'  => 'fa6-solid--ticket',
			'title' => __( 'Most of your tickets seem to be free', 'sugar-calendar-lite' ),
			'text'  => __( 'Skip the checkout. Collect one-click RSVPs instead.', 'sugar-calendar-lite' ),
			'cta'   => [
				'label'        => __( 'Upgrade to Pro', 'sugar-calendar-lite' ),
				'url'          => Helpers::get_upgrade_link(
					[
						'medium'  => AbstractWidget::UTM_MEDIUM,
						'content' => 'free-tickets-banner',
					]
				),
				'target_blank' => true,
			],
		];
	}

	/**
	 * Application-fee card, offering the upgrade, while Stripe checkout charges the fee.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	protected function get_fee_card(): array {

		return [
			'id'    => 'application_fee',
			'icon'  => 'fa6-solid--lightbulb',
			/* translators: %s - the fee, e.g. 3%. */
			'title' => sprintf( __( 'You’re paying a %s fee per transaction', 'sugar-calendar-lite' ), ApplicationFee::PERCENT . '%' ),
			'text'  => __( 'Remove it with the Advanced Event Ticketing addon, available on Plus and higher plans.', 'sugar-calendar-lite' ),
			'cta'   => [
				'label'        => __( 'Upgrade', 'sugar-calendar-lite' ),
				'url'          => Helpers::get_upgrade_link(
					[
						'medium'  => AbstractWidget::UTM_MEDIUM,
						'content' => 'fee-banner',
					]
				),
				'target_blank' => true,
			],
		];
	}

	/**
	 * Reconnect card for past orders with no payment method.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	protected function get_reconnect_card(): array {

		return [
			'id'    => 'reconnect_payments',
			'icon'  => 'fa6-solid--plug',
			'title' => __( 'Reconnect a Payment Method', 'sugar-calendar-lite' ),
			'text'  => __( 'Your past orders are still here. Connect Stripe again to keep selling tickets.', 'sugar-calendar-lite' ),
			'cta'   => [
				'label'        => __( 'Connect Stripe', 'sugar-calendar-lite' ),
				'url'          => ( new StripeConnect() )->get_connect_url(),
				'target_blank' => false,
			],
		];
	}

	/**
	 * Whether a card's condition holds, dismissals aside.
	 *
	 * @since 3.18.0
	 *
	 * @param string $card_id Card ID.
	 *
	 * @return bool
	 */
	private function applies( string $card_id ): bool {

		if ( $card_id === 'free_tickets' ) {
			return $this->lists->has_mostly_free_tickets();
		}

		if ( $card_id === 'application_fee' ) {
			return stripe_is_connected() && ApplicationFee::is_charged();
		}

		return $card_id === 'reconnect_payments' && $this->lists->needs_payment_reconnect();
	}

	/**
	 * A card's content.
	 *
	 * @since 3.18.0
	 *
	 * @param string $card_id Card ID.
	 *
	 * @return array
	 */
	private function get_card( string $card_id ): array {

		if ( $card_id === 'free_tickets' ) {
			return $this->get_free_tickets_card();
		}

		return $card_id === 'application_fee' ? $this->get_fee_card() : $this->get_reconnect_card();
	}

	/**
	 * Whether to check a dismissed card's condition for its end. The free-tickets count reads every
	 * ticket, so it runs at most once a day; the others read options.
	 *
	 * @since 3.18.0
	 *
	 * @param string $card_id Card ID.
	 *
	 * @return bool
	 */
	private function is_check_due( string $card_id ): bool {

		if ( $card_id !== 'free_tickets' ) {
			return true;
		}

		if ( get_transient( self::FREE_TICKETS_CHECKED_TRANSIENT ) !== false ) {
			return false;
		}

		set_transient( self::FREE_TICKETS_CHECKED_TRANSIENT, 1, DAY_IN_SECONDS );

		return true;
	}
}
