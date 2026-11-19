<?php

namespace Sugar_Calendar\Admin\Dashboard\Widgets;

use Sugar_Calendar\Admin\Dashboard\AccessContext;
use Sugar_Calendar\Admin\Dashboard\Templates;
use Sugar_Calendar\Admin\Dashboard\WidgetState;
use Sugar_Calendar\Admin\Pages\SetupChecklist;
use Sugar_Calendar\SetupChecklist\Config;
use Sugar_Calendar\SetupChecklist\Editor;

// Built from data, so named here for Tailwind's scanner: sc:icon-[fa6-solid--circle-check]
// sc:icon-[fa6-regular--circle] (no period after a class: the scanner would skip it).

/**
 * Setup Checklist summary.
 *
 * Hidden once the checklist is dismissed (its own actions stop working then) or every group
 * is complete.
 *
 * @since 3.18.0
 */
class ChecklistOverview extends AbstractWidget {

	/**
	 * Column.
	 *
	 * @since 3.18.0
	 */
	const COLUMN = 'sidebar';

	/**
	 * Sort position.
	 *
	 * @since 3.18.0
	 */
	const ORDER = 30;

	/**
	 * Items shown per group before "+N More Steps".
	 *
	 * @since 3.18.0
	 */
	const ITEMS_PER_SECTION = 3;

	/**
	 * Widget identifier.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'checklist_overview';
	}

	/**
	 * Widget title.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_title(): string {

		return __( 'Setup Checklist Overview', 'sugar-calendar-lite' );
	}

	/**
	 * Visible while the checklist is live and some group it shows is incomplete.
	 *
	 * @since 3.18.0
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 */
	public function get_state( AccessContext $access ): WidgetState {

		if ( $this->is_checklist_dismissed() ) {
			return new WidgetState( false );
		}

		// Decided from the groups the widget renders, so an emptied group cannot leave a blank card.
		foreach ( $this->get_sections() as $section ) {
			if ( ! $section['complete'] ) {
				return new WidgetState( true, 'data' );
			}
		}

		return new WidgetState( false );
	}

	/**
	 * Render the groups.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Range data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 */
	protected function render_body( string $variant, array $data, AccessContext $access ): string {

		return Templates::get_html(
			'widgets/checklist-overview',
			[
				'sections'      => $this->get_sections(),
				'checklist_url' => SetupChecklist::get_url(),
			]
		);
	}

	/**
	 * Checklist groups, from the Setup Checklist's snapshot.
	 *
	 * The snapshot is the pure read; `get_view_data()` also writes the progress transient.
	 *
	 * @since 3.18.0
	 *
	 * @return array Section id => `title`, `complete`, `items` (item id => `title`, `complete`, `url`).
	 */
	protected function get_checklist_sections(): array {

		$snapshot = sugar_calendar()->get_setup_checklist()->get_snapshot();
		$groups   = (array) ( $snapshot['groups'] ?? [] );
		$sections = [];

		foreach ( $groups as $group_id => $group ) {
			$items       = [];
			$group_items = (array) ( $group['items'] ?? [] );

			foreach ( $group_items as $item_id => $item ) {
				$items[ $item_id ] = $this->get_item( (string) $item_id, (array) $item );
			}

			$sections[ $group_id ] = [
				'title'    => (string) ( $group['title'] ?? '' ),
				'complete' => ! empty( $group['is_complete'] ),
				'items'    => $items,
			];
		}

		return $sections;
	}

	/**
	 * Whether the checklist was dismissed.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	protected function is_checklist_dismissed(): bool {

		return sugar_calendar()->get_setup_checklist()->get_state()->is_dismissed();
	}

	/**
	 * One step in the template's shape.
	 *
	 * @since 3.18.0
	 *
	 * @param string $item_id Checklist item id.
	 * @param array  $item    Snapshot item.
	 *
	 * @return array `title`, `complete`, `url`.
	 */
	private function get_item( string $item_id, array $item ): array {

		return [
			'title'    => (string) ( $item['title'] ?? '' ),
			'complete' => ! empty( $item['completed'] ),
			'url'      => $this->get_item_url( $item_id, (array) ( $item['action'] ?? [] ) ),
		];
	}

	/**
	 * Where a step's label goes: a link step's own screen, else the checklist, where the
	 * step's button runs. Create an Event opens the hand-off the checklist's own button opens,
	 * which starts the classic editor's guide; the plain URL when the checklist refuses it.
	 *
	 * @since 3.18.0
	 *
	 * @param string $item_id Checklist item id.
	 * @param array  $action  The step's checklist action.
	 *
	 * @return string
	 */
	private function get_item_url( string $item_id, array $action ): string {

		$url = (string) ( $action['url'] ?? '' );

		if ( ( $action['type'] ?? '' ) !== 'link' || $url === '' ) {
			return SetupChecklist::get_url();
		}

		if ( $item_id !== Config::ITEM_CREATE_EVENT ) {
			return $url;
		}

		$handoff = ( new Editor() )->get_event_handoff_url();

		return is_wp_error( $handoff ) ? $url : $handoff;
	}

	/**
	 * Unfinished groups first, each cut to ITEMS_PER_SECTION with its pending steps first, with how
	 * many unfinished steps were cut.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	private function get_sections(): array {

		$sections  = [];
		$admin_url = admin_url();

		foreach ( $this->get_checklist_sections() as $id => $section ) {
			$items = $this->sort_unfinished_first( (array) ( $section['items'] ?? [] ) );

			// A group whose items were all filtered out upstream would render empty.
			if ( empty( $items ) ) {
				continue;
			}

			$shown = $this->prepare_items( array_slice( $items, 0, self::ITEMS_PER_SECTION, true ), $admin_url );

			$sections[ $id ] = [
				'title'             => $section['title'],
				'complete'          => ! empty( $section['complete'] ),
				'items'             => $shown,
				'hidden_open_count' => $this->count_open( array_slice( $items, self::ITEMS_PER_SECTION ) ),
			];
		}

		return $this->sort_unfinished_first( $sections );
	}

	/**
	 * Unfinished rows, then complete ones, each in checklist order: done steps only fill a group's
	 * spare rows, and complete groups sit below the ones left to do.
	 *
	 * @since 3.18.0
	 *
	 * @param array $rows Steps or groups keyed by ID, each with `complete`.
	 *
	 * @return array
	 */
	private function sort_unfinished_first( array $rows ): array {

		$unfinished = [];
		$complete   = [];

		foreach ( $rows as $id => $row ) {
			if ( empty( $row['complete'] ) ) {
				$unfinished[ $id ] = $row;
			} else {
				$complete[ $id ] = $row;
			}
		}

		return $unfinished + $complete;
	}

	/**
	 * How many of the steps are not done yet.
	 *
	 * "+N More Steps" promises steps left to do, so done steps past the cut are not counted.
	 *
	 * @since 3.18.0
	 *
	 * @param array $items Items keyed by ID.
	 *
	 * @return int
	 */
	private function count_open( array $items ): int {

		return count(
			array_filter(
				$items,
				static function ( $item ) {

					return empty( $item['complete'] );
				}
			)
		);
	}

	/**
	 * Set each item's link: none once done, and whether it leaves wp-admin.
	 *
	 * @since 3.18.0
	 *
	 * @param array  $items     Items keyed by ID.
	 * @param string $admin_url The wp-admin URL.
	 *
	 * @return array
	 */
	private function prepare_items( array $items, string $admin_url ): array {

		foreach ( $items as $item_id => $item ) {

			// A finished step has nothing left to do.
			$url = empty( $item['complete'] ) ? (string) ( $item['url'] ?? '' ) : '';

			$items[ $item_id ]['url'] = $url;

			// Leaving wp-admin opens a new tab.
			$items[ $item_id ]['external'] = $url !== '' && strpos( $url, $admin_url ) !== 0;
		}

		return $items;
	}
}
