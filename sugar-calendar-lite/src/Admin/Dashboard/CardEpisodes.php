<?php

namespace Sugar_Calendar\Admin\Dashboard;

/**
 * When each inline card's condition last ended, so a card dismissed in one episode comes back in the next.
 *
 * A dismissal opens an episode and counts only under the marker it was made under; an end moves the
 * marker.
 *
 * @since 3.18.0
 */
class CardEpisodes {

	/**
	 * Site option: card id => `marker` (when its condition last ended, 0 before any end) and `open`.
	 *
	 * @since 3.18.0
	 */
	const OPTION = 'sugar_calendar_dashboard_card_episodes';

	/**
	 * The episodes read this request.
	 *
	 * @since 3.18.0
	 *
	 * @var array|null
	 */
	private $episodes;

	/**
	 * The marker a dismissal made now is stored under.
	 *
	 * @since 3.18.0
	 *
	 * @param string $card_id Card ID.
	 *
	 * @return int
	 */
	public function get_marker( string $card_id ): int {

		return (int) ( $this->get_all()[ $card_id ]['marker'] ?? 0 );
	}

	/**
	 * Whether a user's dismissals hide a card: dismissed under its current marker.
	 *
	 * @since 3.18.0
	 *
	 * @param string $card_id    Card ID.
	 * @param array  $dismissals The user's dismissals, card id => marker.
	 *
	 * @return bool
	 */
	public function is_dismissed( string $card_id, array $dismissals ): bool {

		return isset( $dismissals[ $card_id ] ) && (int) $dismissals[ $card_id ] === $this->get_marker( $card_id );
	}

	/**
	 * Whether a card was dismissed and its condition has held since.
	 *
	 * @since 3.18.0
	 *
	 * @param string $card_id Card ID.
	 *
	 * @return bool
	 */
	public function is_open( string $card_id ): bool {

		return ! empty( $this->get_all()[ $card_id ]['open'] );
	}

	/**
	 * Open a card's episode: someone dismissed it while its condition held.
	 *
	 * @since 3.18.0
	 *
	 * @param string $card_id Card ID.
	 */
	public function open( string $card_id ): void {

		if ( $this->is_open( $card_id ) ) {
			return;
		}

		$episodes = $this->get_all();

		$episodes[ $card_id ] = [
			'marker' => $this->get_marker( $card_id ),
			'open'   => true,
		];

		$this->save( $episodes );
	}

	/**
	 * End a card's open episode: its condition stopped holding, so every dismissal made in it lapses.
	 *
	 * @since 3.18.0
	 *
	 * @param string $card_id Card ID.
	 */
	public function end( string $card_id ): void {

		if ( ! $this->is_open( $card_id ) ) {
			return;
		}

		$episodes = $this->get_all();

		// Always a new value, even for a second end within the same second.
		$episodes[ $card_id ] = [
			'marker' => max( time(), $this->get_marker( $card_id ) + 1 ),
			'open'   => false,
		];

		$this->save( $episodes );
	}

	/**
	 * Every card's episode.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	private function get_all(): array {

		if ( $this->episodes === null ) {
			$value          = get_option( self::OPTION, [] );
			$this->episodes = is_array( $value ) ? $value : [];
		}

		return $this->episodes;
	}

	/**
	 * Store every card's episode.
	 *
	 * @since 3.18.0
	 *
	 * @param array $episodes Card id => episode.
	 */
	private function save( array $episodes ): void {

		$this->episodes = $episodes;

		update_option( self::OPTION, $episodes );
	}
}
