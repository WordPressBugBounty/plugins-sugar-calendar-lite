<?php

namespace Sugar_Calendar\SetupChecklist;

use Sugar_Calendar\PartnerPlugins\Catalog;
use Sugar_Calendar\Plugin;

/**
 * Authoritative Setup Checklist model.
 *
 * @since 3.15.0
 */
class Checklist {

	/**
	 * Completion detector.
	 *
	 * @since 3.15.0
	 *
	 * @var CompletionDetector
	 */
	private $detector;

	/**
	 * Partner plugin catalogue.
	 *
	 * @since 3.15.0
	 *
	 * @var Catalog
	 */
	private $catalog;

	/**
	 * Request-local normalized snapshot.
	 *
	 * @since 3.15.0
	 *
	 * @var array|null
	 */
	private $snapshot;

	/**
	 * Request-local presentation data.
	 *
	 * @since 3.15.0
	 *
	 * @var array|null
	 */
	private $view_data;

	/**
	 * Serialized `history` and `opt_in` the current snapshot was built from.
	 *
	 * @since 3.15.0
	 *
	 * @var string|null
	 */
	private $basis;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param CompletionDetector $detector Completion detector.
	 * @param Catalog            $catalog  Partner plugin catalogue.
	 */
	public function __construct( CompletionDetector $detector, Catalog $catalog ) {

		$this->detector = $detector;
		$this->catalog  = $catalog;
	}

	/**
	 * Get the normalized checklist snapshot.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function get_snapshot() {

		if ( $this->snapshot !== null ) {
			return $this->snapshot;
		}

		$groups          = [];
		$visible_items   = [];
		$completed_items = [];

		foreach ( (array) Config::get_groups() as $group_id => $group ) {
			$group = (array) $group;
			$items = $this->get_normalized_items( $group, $visible_items, $completed_items );

			if ( empty( $items ) ) {
				continue;
			}

			$group_total     = count( $items );
			$group_completed = $this->count_completed( $items );

			$group['items']           = $items;
			$group['total']           = $group_total;
			$group['completed_count'] = $group_completed;
			$group['is_complete']     = $group_completed === $group_total;
			$group['collapsed']       = $group['is_complete'];

			$groups[ $group_id ] = $group;
		}

		$total           = count( $visible_items );
		$completed_count = count( $completed_items );

		$this->snapshot = [
			'groups'          => $groups,
			'visible_items'   => $visible_items,
			'completed_items' => $completed_items,
			'progress'        => [
				'total'           => $total,
				'completed_count' => $completed_count,
				'percent'         => $total > 0 ? (int) round( 100 * $completed_count / $total ) : 0,
			],
			'is_complete'     => $total > 0 && $completed_count === $total,
		];
		$this->basis    = self::basis_of( $this->detector->get_current_state() );

		return $this->snapshot;
	}

	/**
	 * Get page presentation data with authoritative snapshot values restored.
	 *
	 * Filters may change copy and actions; identity, visibility, completion and progress are
	 * restored from get_snapshot(), so the page cannot disagree with the menu or cron.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public function get_view_data() {

		if ( $this->view_data !== null ) {
			return $this->view_data;
		}

		$snapshot     = $this->get_snapshot();
		$growth_tools = $this->get_growth_tools();
		$data         = array_merge(
			$snapshot,
			[
				'upsell_cards' => Plugin::instance()->is_pro() ? [] : Config::get_upsell_cards(),
				'growth_tools' => $growth_tools,
				'pages'        => [],
				'email'        => (string) get_option( 'admin_email' ),
			]
		);

		$data['groups'] = $this->add_partner_actions( $data['groups'] );

		/**
		 * Filters page-only checklist action data.
		 *
		 * Pro uses this hook to resolve its current add-on actions without adding remote
		 * catalogue reads to snapshots.
		 *
		 * @since 3.15.0
		 *
		 * @param array $groups Normalized groups keyed by canonical group ID.
		 */
		$data['groups'] = (array) apply_filters( 'sugar_calendar_setup_checklist_actions', $data['groups'] ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- named for the feature, not this class.

		/**
		 * Filters Setup Checklist presentation data.
		 *
		 * The authoritative model fields are restored after this filter. Unknown task IDs
		 * are discarded and cannot change page or snapshot progress.
		 *
		 * @since 3.15.0
		 *
		 * @param array $data Page presentation data.
		 */
		$presented_data = (array) apply_filters( 'sugar_calendar_setup_checklist_view_data', $data ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- named for the feature, not this class.
		$data           = array_merge( $data, $presented_data );

		$data['groups']          = $this->restore_groups( $data['groups'] ?? [], $snapshot['groups'] );
		$data['visible_items']   = $snapshot['visible_items'];
		$data['completed_items'] = $snapshot['completed_items'];
		$data['progress']        = $snapshot['progress'];
		$data['is_complete']     = $snapshot['is_complete'];
		$data['growth_tools']    = $this->restore_growth_tools( $data['growth_tools'] ?? [], $growth_tools );

		$this->view_data = $data;

		return $this->view_data;
	}

	/**
	 * Resolve partner plugin actions for page presentation.
	 *
	 * @since 3.15.0
	 *
	 * @param array $groups Presented groups.
	 *
	 * @return array
	 */
	private function add_partner_actions( $groups ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded -- keeps filtered-label validation beside partner action resolution.

		foreach ( $groups as $group_id => $group ) {
			if ( empty( $group['items'] ) || ! is_array( $group['items'] ) ) {
				continue;
			}

			foreach ( $group['items'] as $item_id => $item ) {
				if ( empty( $item['action']['partner_key'] ) ) {
					continue;
				}

				$state = $this->catalog->get_state( $item['action']['partner_key'] );

				if ( ! in_array( $state, [ 'missing', 'installed' ], true ) ) {
					continue;
				}

				$action           = $item['action'];
				$action['action'] = $state === 'missing' ? 'install' : 'activate';
				$labels           = $action['plugin_labels'] ?? [];
				$label            = is_array( $labels ) ? ( $labels[ $action['action'] ] ?? '' ) : '';

				if ( ! is_string( $label ) || trim( $label ) === '' ) {
					$label = $action['action'] === 'install'
						? __( 'Install', 'sugar-calendar-lite' )
						: __( 'Activate', 'sugar-calendar-lite' );
				}

				$action['label'] = $label;

				$groups[ $group_id ]['items'][ $item_id ]['action'] = $action;
			}
		}

		return $groups;
	}

	/**
	 * Forget every request-local result so the next read recomputes.
	 *
	 * @since 3.15.0
	 */
	public function invalidate() {

		$this->snapshot  = null;
		$this->view_data = null;
		$this->basis     = null;

		$this->detector->reset();
	}

	/**
	 * Make the model read one exact normalized State array.
	 *
	 * Only `history` and `opt_in` feed completion predicates, so a state that
	 * matches the current snapshot on those keys keeps the memoized results.
	 *
	 * @since 3.15.0
	 *
	 * @param array $normalized_state Exact normalized State, usually from a mutation retry.
	 */
	public function adopt_state( array $normalized_state ) {

		if ( $this->snapshot !== null && $this->basis === self::basis_of( $normalized_state ) ) {
			$this->detector->set_current_state( $normalized_state );

			return;
		}

		$this->invalidate();
		$this->detector->set_current_state( $normalized_state );
	}

	/**
	 * Fingerprint the State keys predicates read.
	 *
	 * @since 3.15.0
	 *
	 * @param array $state Normalized State.
	 *
	 * @return string
	 */
	private static function basis_of( array $state ) {

		return maybe_serialize(
			[
				'history' => $state['history'] ?? [],
				'opt_in'  => $state['opt_in'] ?? [],
			]
		);
	}

	/**
	 * Enrich applicable items while preserving catalogue order.
	 *
	 * @since 3.15.0
	 *
	 * @param array $group           Raw catalogue group.
	 * @param array $visible_items   Ordered visible item IDs.
	 * @param array $completed_items Ordered completed item IDs.
	 *
	 * @return array
	 */
	private function get_normalized_items( $group, &$visible_items, &$completed_items ) {

		$items = [];

		foreach ( (array) ( $group['items'] ?? [] ) as $item_id => $item ) {
			if ( ! $this->detector->is_applicable( $item_id ) ) {
				continue;
			}

			$item     = (array) $item;
			$complete = $this->detector->is_complete( $item_id );

			$item['visible']   = true;
			$item['completed'] = $complete;
			$items[ $item_id ] = $item;
			$visible_items[]   = $item_id;

			if ( $complete ) {
				$completed_items[] = $item_id;
			}
		}

		return $items;
	}

	/**
	 * Count completed items in one normalized group.
	 *
	 * @since 3.15.0
	 *
	 * @param array $items Normalized group items.
	 *
	 * @return int
	 */
	private function count_completed( $items ) {

		return count(
			array_filter(
				$items,
				static function ( $item ) {

					return ! empty( $item['completed'] );
				}
			)
		);
	}

	/**
	 * Restore authoritative group and item fields after presentation filters.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $presented_groups     Filtered presentation groups.
	 * @param array $authoritative_groups Snapshot groups.
	 *
	 * @return array
	 */
	private function restore_groups( $presented_groups, $authoritative_groups ) {

		$presented_groups = is_array( $presented_groups ) ? $presented_groups : [];
		$groups           = [];

		foreach ( $authoritative_groups as $group_id => $authoritative_group ) {
			$presented_group = $this->get_presented_entry( $presented_groups, $group_id, $authoritative_group );
			$presented_items = (array) ( $presented_group['items'] ?? [] );
			$items           = [];

			foreach ( $authoritative_group['items'] as $item_id => $authoritative_item ) {
				$item = $this->get_presented_entry( $presented_items, $item_id, $authoritative_item );

				$item['visible']   = true;
				$item['completed'] = $authoritative_item['completed'];
				$items[ $item_id ] = $item;
			}

			$presented_group['items']           = $items;
			$presented_group['total']           = $authoritative_group['total'];
			$presented_group['completed_count'] = $authoritative_group['completed_count'];
			$presented_group['is_complete']     = $authoritative_group['is_complete'];
			$presented_group['collapsed']       = $authoritative_group['collapsed'];
			$groups[ $group_id ]                = $presented_group;
		}

		return $groups;
	}

	/**
	 * Get one usable filtered presentation entry.
	 *
	 * @since 3.15.0
	 *
	 * @param array  $entries  Filtered keyed entries.
	 * @param string $key      Entry key.
	 * @param array  $fallback Authoritative fallback.
	 *
	 * @return array
	 */
	private function get_presented_entry( $entries, $key, $fallback ) {

		return isset( $entries[ $key ] ) && is_array( $entries[ $key ] ) ? $entries[ $key ] : $fallback;
	}

	/**
	 * Add current install state to Growth Tool presentation records.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private function get_growth_tools() {

		$tools = Config::get_growth_tools();

		foreach ( $tools as $key => $tool ) {
			$state = $this->catalog->get_state( $key );

			if ( $state === 'active' ) {
				$tools[ $key ]['action_state'] = 'installed';
				$tools[ $key ]['action_label'] = __( 'Installed', 'sugar-calendar-lite' );
			} elseif ( $state === 'installed' ) {
				$tools[ $key ]['action_state'] = 'activate';
				$tools[ $key ]['action_label'] = __( 'Activate', 'sugar-calendar-lite' );
			} else {
				$tools[ $key ]['action_state'] = 'install';
				$tools[ $key ]['action_label'] = __( 'Install', 'sugar-calendar-lite' );
			}
		}

		return $tools;
	}

	/**
	 * Keep Growth Tool identities and states authoritative after presentation filters.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $presented_tools     Filtered presentation records.
	 * @param array $authoritative_tools Records with current install states.
	 *
	 * @return array
	 */
	private function restore_growth_tools( $presented_tools, $authoritative_tools ) {

		$presented_tools = is_array( $presented_tools ) ? $presented_tools : [];
		$tools           = [];

		foreach ( $authoritative_tools as $key => $authoritative_tool ) {
			$tool = isset( $presented_tools[ $key ] ) && is_array( $presented_tools[ $key ] )
				? $presented_tools[ $key ]
				: $authoritative_tool;

			$tool['key']          = $key;
			$tool['action_state'] = $authoritative_tool['action_state'];
			$tool['action_label'] = $authoritative_tool['action_label'];
			$tools[ $key ]        = $tool;
		}

		return $tools;
	}
}
