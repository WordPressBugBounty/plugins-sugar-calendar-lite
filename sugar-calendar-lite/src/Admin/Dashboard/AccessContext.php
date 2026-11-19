<?php

namespace Sugar_Calendar\Admin\Dashboard;

/**
 * Per-request facts the Dashboard widgets decide their state from.
 *
 * @since 3.18.0
 */
class AccessContext {

	/**
	 * Context data merged over the Lite defaults.
	 *
	 * @since 3.18.0
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @since 3.18.0
	 *
	 * @param array $data Context data. Missing keys fall back to Lite defaults.
	 */
	public function __construct( array $data = [] ) {

		$this->data = array_merge(
			[
				'is_pro'          => false,
				'can_manage'      => false,
				'user_id'         => 0,
				'dismissals'      => [],
				'widget_settings' => [],
			],
			$data
		);
	}

	/**
	 * Whether the Pro build is running.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	public function is_pro(): bool {

		return (bool) $this->data['is_pro'];
	}

	/**
	 * Whether the current user may use the Dashboard and its AJAX endpoints.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	public function can_manage(): bool {

		return (bool) $this->data['can_manage'];
	}

	/**
	 * Current user ID.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	public function get_user_id(): int {

		return (int) $this->data['user_id'];
	}

	/**
	 * Dismissed inline cards, card id => the `CardEpisodes` marker each was dismissed under.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	public function get_dismissals(): array {

		return (array) $this->data['dismissals'];
	}

	/**
	 * One widget's remembered settings.
	 *
	 * @since 3.18.0
	 *
	 * @param string $widget_id Widget identifier.
	 *
	 * @return array
	 */
	public function get_widget_settings( string $widget_id ): array {

		return (array) ( $this->data['widget_settings'][ $widget_id ] ?? [] );
	}
}
