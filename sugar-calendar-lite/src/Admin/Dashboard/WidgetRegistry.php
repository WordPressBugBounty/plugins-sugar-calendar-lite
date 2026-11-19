<?php

namespace Sugar_Calendar\Admin\Dashboard;

use Sugar_Calendar\Admin\Dashboard\Widgets\AbstractWidget;
use Sugar_Calendar\Admin\Dashboard\Widgets\Activity;
use Sugar_Calendar\Admin\Dashboard\Widgets\ChecklistOverview;
use Sugar_Calendar\Admin\Dashboard\Widgets\GettingStarted;
use Sugar_Calendar\Admin\Dashboard\Widgets\GrowthTools;
use Sugar_Calendar\Admin\Dashboard\Widgets\Integrations;
use Sugar_Calendar\Admin\Dashboard\Widgets\License;
use Sugar_Calendar\Admin\Dashboard\Widgets\ProUpsell;
use Sugar_Calendar\Admin\Dashboard\Widgets\SetupNotice;
use Sugar_Calendar\Admin\Dashboard\Widgets\UpcomingEvents;

/**
 * Holds the Dashboard widgets: evaluates their states once per request and renders each column.
 *
 * @since 3.18.0
 */
class WidgetRegistry {

	/**
	 * Visible widgets per column: `[ column => [ [ 'widget' => AbstractWidget, 'variant' => string ], ... ] ]`.
	 *
	 * @since 3.18.0
	 *
	 * @var array
	 */
	private $widgets = [];

	/**
	 * Evaluate widget states and group the visible widgets per column, sorted.
	 *
	 * @since 3.18.0
	 *
	 * @param AccessContext $access Access context.
	 */
	public function init( AccessContext $access ): void {

		$items = $this->get_visible_items( $access );

		// A tie would otherwise fall back to usort()'s stability; the id is an explicit tiebreaker.
		usort(
			$items,
			static function ( $a, $b ) {

				$by_order = $a['order'] <=> $b['order'];

				return $by_order !== 0 ? $by_order : strcmp( $a['widget']->get_id(), $b['widget']->get_id() );
			}
		);

		foreach ( $items as $item ) {
			$this->widgets[ $item['column'] ][] = [
				'widget'  => $item['widget'],
				'variant' => $item['variant'],
			];
		}
	}

	/**
	 * Render every visible widget of a column.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $column Column: `main` or `sidebar`.
	 * @param array         $data   Range data.
	 * @param AccessContext $access Access context.
	 *
	 * @return string
	 */
	public function render_column( string $column, array $data, AccessContext $access ): string {

		$html  = '';
		$items = $this->widgets[ $column ] ?? [];

		foreach ( $items as $item ) {
			$html .= $item['widget']->render( $item['variant'], $data, $access );
		}

		return $html;
	}

	/**
	 * A widget by its identifier, as the page would render it.
	 *
	 * @since 3.18.0
	 *
	 * @param string $id Widget identifier.
	 *
	 * @return AbstractWidget|null
	 */
	public function get_widget( string $id ): ?AbstractWidget {

		foreach ( $this->get_widgets() as $widget ) {
			if ( $widget->get_id() === $id ) {
				return $widget;
			}
		}

		return null;
	}

	/**
	 * The visible widgets with their variant, sort position and column, in registry order.
	 *
	 * @since 3.18.0
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return array Items: `widget`, `variant`, `order` and `column`.
	 */
	private function get_visible_items( AccessContext $access ): array {

		$items = [];

		foreach ( $this->get_widgets() as $widget ) {
			$state = $widget->get_state( $access );

			if ( ! $state->is_visible() ) {
				continue;
			}

			$items[] = [
				'widget'  => $widget,
				'variant' => $state->get_variant(),
				'order'   => $widget::ORDER,
				'column'  => $widget->get_column(),
			];
		}

		return $items;
	}

	/**
	 * Widget instances to evaluate.
	 *
	 * @since 3.18.0
	 *
	 * @return AbstractWidget[]
	 */
	private function get_widgets(): array {

		$widgets = [];

		foreach ( $this->get_widget_classes() as $class ) {
			$widgets[] = new $class();
		}

		return $widgets;
	}

	/**
	 * The widget classes, in registry order. The Pro registry swaps in its subclasses here.
	 *
	 * @since 3.18.0
	 *
	 * @return string[] Class names, each an AbstractWidget.
	 */
	protected function get_widget_classes(): array {

		return [
			Activity::class,
			SetupNotice::class,
			Integrations::class,
			ProUpsell::class,
			License::class,
			UpcomingEvents::class,
			ChecklistOverview::class,
			GettingStarted::class,
			GrowthTools::class,
		];
	}
}
