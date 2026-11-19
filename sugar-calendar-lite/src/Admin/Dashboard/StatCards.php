<?php

namespace Sugar_Calendar\Admin\Dashboard;

use Sugar_Calendar\Helpers\Money;

// Built from data, so named here for Tailwind's scanner: sc:icon-[fa6-solid--calendar-days]
// sc:icon-[fa6-solid--users] sc:icon-[fa6-solid--receipt] sc:icon-[fa6-solid--money-bill-1]
// sc:icon-[fa6-solid--chevron-up] sc:icon-[fa6-solid--chevron-down] (no period after a class: the scanner would skip it).

/**
 * The four stat cards above the columns.
 *
 * Not a widget: the row has no card shell, title or state, and the range refresh redraws it on its
 * own. The page and `Ajax::get_stats()` both render it from the range data.
 *
 * @since 3.18.0
 */
class StatCards {

	/**
	 * Render the row; the cards are their own cards.
	 *
	 * The row is the size container the cards' two-up rule queries (`templates/stat-card.php`), and
	 * `data-widget="stat_cards"` is what the range refresh replaces.
	 *
	 * @since 3.18.0
	 *
	 * @param array $data Range data from `Stats::get_widget_data()`.
	 *
	 * @return string
	 */
	public function render( array $data ): string {

		$html = '';

		foreach ( $this->get_cards( $data ) as $card ) {
			$html .= Templates::get_html( 'stat-card', [ 'card' => $card ] );
		}

		return sprintf(
			'<div class="sc:@container sc:self-stretch sc:flex sc:flex-wrap sc:items-start sc:gap-5 sc:mb-[30px] sc-reset" data-widget="stat_cards">%s</div>',
			$html
		);
	}

	/**
	 * Card definitions.
	 *
	 * @since 3.18.0
	 *
	 * @param array $data Range data.
	 *
	 * @return array
	 */
	private function get_cards( array $data ): array {

		$totals = $data['stat_totals'] ?? [];
		$deltas = $data['stat_deltas'] ?? [];
		$cards  = [];

		// Stat => label, icon and the card type.
		$stats = [
			'events'    => [ __( 'Events', 'sugar-calendar-lite' ), 'fa6-solid--calendar-days', 'events' ],
			'attendees' => [ __( 'Attendees', 'sugar-calendar-lite' ), 'fa6-solid--users', 'registrations' ],
			'orders'    => [ __( 'Orders', 'sugar-calendar-lite' ), 'fa6-solid--receipt', 'orders' ],
			'revenue'   => [ __( 'Revenue', 'sugar-calendar-lite' ), 'fa6-solid--money-bill-1', 'revenue' ],
		];

		foreach ( $stats as $stat => [ $label, $icon, $type ] ) {
			$cards[] = [
				'label' => $label,
				'value' => $this->format_value( $stat, $totals[ $stat ] ?? 0 ),
				'delta' => $deltas[ $stat ] ?? null,
				'icon'  => $icon,
				'type'  => $type,
			];
		}

		return $cards;
	}

	/**
	 * Format a stat total for its card: revenue as whole money, the rest as a localized count.
	 *
	 * @since 3.18.0
	 *
	 * @param string    $stat  Stat key.
	 * @param int|float $value Raw total.
	 *
	 * @return string
	 */
	private function format_value( string $stat, $value ): string {

		return $stat === 'revenue' ? Money::format_whole( (float) $value ) : number_format_i18n( (int) $value );
	}
}
