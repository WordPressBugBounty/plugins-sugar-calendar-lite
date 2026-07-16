<?php

namespace Sugar_Calendar\Admin\Events\Tables;

use DateTimeImmutable;
use DateTimeZone;
use Sugar_Calendar\Helpers\OverlapClusters;
use Sugar_Calendar\Helpers\UI;

/**
 * Grid event view.
 *
 * @since 3.0.0
 */
class Grid extends Base {

	/**
	 * Right-edge gap (px) so an intra-day card doesn't touch the column divider.
	 *
	 * @since 3.12.0
	 */
	const INTRA_DAY_CARD_RIGHT_GAP_PX = 10;

	/**
	 * Left indent (px) applied per overlapping event so nested cards stack visibly.
	 *
	 * @since 3.12.0
	 */
	const INTRA_DAY_OVERLAP_INDENT_PX = 10;

	/**
	 * Base z-index for intra-day cards; each overlapping card stacks one above it.
	 *
	 * @since 3.12.0
	 */
	const INTRA_DAY_Z_INDEX_BASE = 10;

	/**
	 * Maximum event duration (minutes) that uses the compact intra-day card layout.
	 *
	 * @since 3.12.0
	 */
	const COMPACT_CARD_MAX_MINUTES = 30;

	/**
	 * Display the table.
	 *
	 * @since 2.0.0
	 */
	public function display() {

		// Start an output buffer.
		ob_start();

		// Top.
		$this->display_tablenav( 'top' );
		$classes = implode( ' ', $this->get_table_classes() );
		?>

        <div class="<?php echo esc_attr( $classes ); ?>">
			<?php $this->print_column_headers(); ?>

			<?php $this->display_mode(); ?>

			<?php $this->print_column_headers( false ); ?>
        </div>

		<?php

		// Bottom.
		$this->display_tablenav( 'bottom' );

		// End and flush the buffer.
		echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Prints column headers, accounting for hidden and sortable columns.
	 *
	 * @since 3.0.0
	 *
	 * @param bool $with_id Whether to set the ID attribute or not.
	 */
	public function print_column_headers( $with_id = true ) {

		[ $columns, $hidden ] = $this->get_column_info();
		?>

        <div class="row">

			<?php
			foreach ( $columns as $column_key => $column_display_name ) {
				$class = [ 'header', 'column', "column-{$column_key}" ];

				if ( in_array( $column_key, $hidden, true ) ) {
					$class[] = 'hidden';
				}

				$id = $with_id ? "id='$column_key'" : '';

				if ( ! empty( $class ) ) {
					$class = "class='" . implode( ' ', $class ) . "'";
				}

				echo "<div $id $class>$column_display_name</div>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>

        </div>

		<?php
	}

	/**
	 * Output grid layout rules.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function output_grid_layout() {

		?>

        <style id="sugar-calendar-table-grid-column-layout">
            .sugar-calendar-table-events {
                --grid-template-columns: <?php echo $this->get_grid_column_layout(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
            }
        </style>

		<?php
	}

	/**
	 * Get a list of CSS classes for the list table table tag.
	 *
	 * @since 2.0.0
	 *
	 * @return array List of CSS classes for the table tag.
	 */
	protected function get_table_classes() {

		return [
			'sugar-calendar-table',
			'sugar-calendar-table-events',
			'sugar-calendar-table-events--' . $this->get_mode(),
			$this->get_mode(),
			$this->get_status(),
			$this->_args['plural'],
		];
	}

	/**
	 * Get grid layout rules.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	private function get_grid_column_layout() {

		[ $columns, $hidden ] = $this->get_column_info();

		$template = array_map(
			function ( $column ) use ( $columns, $hidden ) {

				$column    = sanitize_key( $column );
				$max       = $column === array_key_first( $columns ) ? '120px' : '1fr';
				$is_hidden = in_array( $column, $hidden, true );
				$size      = $is_hidden ? '0fr' : "minmax(0, {$max})";

				return "[{$column}] {$size}";
			},
			array_keys( $columns )
		);

		$template = implode( ' ', $template );

		return $template;
	}

	/**
	 * Get classes for event in day.
	 *
	 * @since 2.0.0
	 *
	 * @param object $event Event object.
	 * @param int    $cell  Cell index.
	 */
	protected function get_event_classes( $event = 0, $cell = 0 ) {

		$classes = parent::get_event_classes( $event, $cell );
		$classes = "{$classes} sugar-calendar-event-entry";

		return $classes;
	}

	/**
	 * Whether this view renders timed events as positioned intra-day cards.
	 *
	 * Hour-grid views (week, day) opt in by overriding this. The month grid
	 * does not, so its timed events keep the plain inherited link.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	protected function supports_intra_day_cards() {

		return false;
	}

	/**
	 * Get an event link, wrapped as a card for multi-hour intra-day events.
	 *
	 * Views that don't support intra-day cards (month) fall through to the
	 * plain inherited link.
	 *
	 * @since 3.12.0
	 *
	 * @param object|false $event Event object.
	 *
	 * @return string
	 */
	protected function get_event( $event = false ) {

		if ( empty( $event ) ) {
			return '';
		}

		// Base markup (the event link); wrapped below for intra-day cards.
		$link = parent::get_event( $event );

		if (
			! $this->supports_intra_day_cards()
			|| $event->is_all_day()
			|| $event->is_multi( 'j' )
			|| ! $this->event_spans_multiple_hours( $event )
		) {
			return $link;
		}

		$style = $this->get_event_grid_style( $event );

		// Show the start–end range (e.g. "3:00 am - 4:00 am") for the card.
		$time_text = $this->get_event_time( $event->start, $event->start_tz );

		if ( ! empty( $event->end ) && ( $event->start !== $event->end ) ) {
			$time_text .= ' - ' . $this->get_event_time( $event->end, $event->end_tz );
		}

		// Short events (30 minutes or less) lack the vertical room to stack the
		// title and time, so they switch to the compact layout that places the
		// time beside the title. strtotime() returns false on an unparseable
		// (but non-empty) date, so guard before the arithmetic — otherwise
		// false - false casts to 0 and silently mislabels the duration.
		$start_ts = strtotime( $event->start );
		$end_ts   = strtotime( $event->end );

		$duration_minutes = ( $start_ts !== false && $end_ts !== false )
			? (int) round( ( $end_ts - $start_ts ) / 60 )
			: 0;
		$wrap_classes     = 'sugar-calendar-event-entry-wrap sugar-calendar-event-entry-wrap--multi-hour';

		if ( ( $duration_minutes > 0 ) && ( $duration_minutes <= self::COMPACT_CARD_MAX_MINUTES ) ) {
			$wrap_classes .= ' sugar-calendar-event-entry-wrap--compact';
		}

		return sprintf(
			'<div class="%s" style="%s" data-event-id="%d">'
				. '%s'
				. '<span class="sugar-calendar-event-entry-wrap__time">%s</span>'
			. '</div>',
			esc_attr( $wrap_classes ),
			esc_attr( $style ),
			(int) $event->id,
			$link,
			esc_html( $time_text )
		);
	}

	/**
	 * Whether the event spans more than one hour cell.
	 *
	 * @since 3.12.0
	 *
	 * @param object $item Event object.
	 *
	 * @return bool
	 */
	protected function event_spans_multiple_hours( $item ) {

		// The skip-in-cell loop calls this for every event in every hour cell,
		// so memoize per event id: the result is fixed for the request and the
		// timestamps never change. Falls through to a fresh compute when the
		// event has no id.
		static $cache = [];

		$cache_key = isset( $item->id ) ? (int) $item->id : null;

		if ( ( $cache_key !== null ) && isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		if ( empty( $item->start ) || empty( $item->end ) ) {
			return false;
		}

		$start_ts = (int) strtotime( $item->start );
		$end_ts   = (int) strtotime( $item->end );

		if ( $end_ts <= $start_ts ) {
			return false;
		}

		$spans = gmdate( 'H', $start_ts ) !== gmdate( 'H', $end_ts )
			|| (int) gmdate( 'i', $end_ts ) > 0;

		if ( $cache_key !== null ) {
			$cache[ $cache_key ] = $spans;
		}

		return $spans;
	}

	/**
	 * Whether the event starts inside the current cell.
	 *
	 * @since 3.12.0
	 *
	 * @param object $item Event object.
	 *
	 * @return bool
	 */
	protected function event_starts_in_current_cell( $item ) {

		if ( empty( $item->start ) ) {
			return false;
		}

		$cell_start  = (int) $this->get_current_cell( 'start' );
		$cell_end    = (int) $this->get_current_cell( 'end' );
		$event_start = (int) strtotime( $item->start );

		return $event_start >= $cell_start && $event_start <= $cell_end;
	}

	/**
	 * Whether a multi-hour intra-day event should be skipped in the current cell.
	 *
	 * Multi-hour cards render once, in their start cell; the later cells they
	 * span skip the event so it isn't drawn again. Shared by the Week and Day
	 * views' skip_item_in_cell().
	 *
	 * @since 3.12.0
	 *
	 * @param object $item Event object.
	 *
	 * @return bool
	 */
	protected function skip_multi_hour_in_cell( $item ) {

		return $this->supports_intra_day_cards()
			&& $this->event_spans_multiple_hours( $item )
			&& ! $this->event_starts_in_current_cell( $item );
	}

	/**
	 * Build inline geometry style for an intra-day event card.
	 *
	 * Height is 1px per minute (matches .column min-height 60px).
	 *
	 * @since 3.12.0
	 *
	 * @param object $item Event object.
	 *
	 * @return string
	 */
	protected function get_intra_day_event_style( $item ) {

		$start_ts = (int) strtotime( $item->start );
		$end_ts   = (int) strtotime( $item->end );

		// Height is 1px per minute (matches the .column min-height of 60px).
		$height = max( 0, (int) round( ( $end_ts - $start_ts ) / 60 ) );

		// Offset the card down by the start minutes within the hour (1px per
		// minute) so an event beginning at, e.g., 4:25 starts a quarter into the
		// cell and spills into the next hour rather than snapping to the hour line.
		$top = (int) gmdate( 'i', $start_ts );

		// Height is proportional to duration with no minimum, so short events
		// render as thin cards matching their length.
		$styles = [ sprintf( 'height: %dpx', $height ) ];

		// Always leave a gap on the right so cards don't touch the column divider.
		$right = self::INTRA_DAY_CARD_RIGHT_GAP_PX;

		if ( isset( $item->intra_day_columns ) ) {

			// SCB-style proportional layout: the cell divides into N columns and
			// the card spans its column to the right edge, so concurrent events
			// each keep a 1/N-width sliver instead of burying each other. Nesting
			// adds a small staircase indent within a shared column. Stamped by
			// precompute_intra_day_clusters() (Week and Day).
			$columns = max( 1, (int) $item->intra_day_columns );
			$column  = max( 1, (int) $item->intra_day_column );
			$nesting = max( 0, (int) $item->intra_day_nesting );

			$fraction = ( $column - 1 ) / $columns;
			$indent   = $nesting * self::INTRA_DAY_OVERLAP_INDENT_PX;

			if ( $fraction <= 0 ) {
				$styles[] = ( $indent > 0 )
					? sprintf( 'left: %dpx', $indent )
					: 'left: 0';
			} else {
				// Divide (cell width − right gap) into N equal columns, mirroring
				// SCB's repeat(N, 1fr) grid so every column — including the last —
				// is the same width. Dividing the full width instead would shrink
				// only the last card by the gap, making its sliver narrower than
				// the rest.
				$expr = sprintf(
					'%s * (100%% - %dpx)',
					OverlapClusters::format_number( $fraction ),
					self::INTRA_DAY_CARD_RIGHT_GAP_PX
				);

				if ( $indent > 0 ) {
					$expr .= sprintf( ' + %dpx', $indent );
				}

				$styles[] = sprintf( 'left: calc(%s)', $expr );
			}

			if ( ( $columns > 1 ) || ( $nesting > 0 ) ) {
				// Later columns and deeper nesting stack on top. Step of 10 keeps
				// columns dominant over single-digit nesting while leaving the
				// values low (well below any overlay z-index).
				$styles[] = sprintf(
					'z-index: %d',
					self::INTRA_DAY_Z_INDEX_BASE + ( $column * 10 ) + $nesting
				);
			}
		} else {

			// A non-clustered card (a lone multi-hour event) sits flush left.
			$styles[] = 'left: 0';
		}

		$styles[] = sprintf( 'top: %dpx', $top );
		$styles[] = sprintf( 'right: %dpx', $right );

		return implode( '; ', $styles ) . ';';
	}

	/**
	 * Stamp each multi-hour intra-day event with SCB-style cluster layout fields.
	 *
	 * Groups the view's multi-hour events by day and, via Helpers\OverlapClusters,
	 * assigns each a column, the per-cluster column count, and a nesting depth.
	 * get_intra_day_event_style() reads these to lay out overlapping events as
	 * proportional columns. The Week and Day views opt in from display_mode().
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	protected function precompute_intra_day_clusters() {

		foreach ( $this->get_multi_hour_events_by_day() as $day_key => $day_events ) {

			// A lone event can't overlap anything, so it never needs cluster
			// layout — it renders full width via the legacy/default path.
			if ( count( $day_events ) < 2 ) {
				continue;
			}

			$slot_start = new DateTimeImmutable( $day_key . ' 00:00:00', new DateTimeZone( 'UTC' ) );
			$slot_end   = $slot_start->setTime( 23, 59, 59 );

			$items = [];

			foreach ( $day_events as $event ) {
				$items[] = [
					'ref'   => $event,
					'start' => ( new DateTimeImmutable( '@' . (int) strtotime( $event->start ) ) ),
					'end'   => ( new DateTimeImmutable( '@' . (int) strtotime( $event->end ) ) ),
				];
			}

			$clusters = OverlapClusters::build( $items, $slot_start, $slot_end );

			foreach ( $clusters as $node ) {
				$event                    = $node['ref'];
				$event->intra_day_column  = $node['column'];
				$event->intra_day_columns = $node['columns'];
				$event->intra_day_nesting = $node['nesting'];
			}
		}
	}

	/**
	 * Group this view's multi-hour intra-day events by start day.
	 *
	 * Excludes all-day and multi-day events; both render outside the hour grid.
	 * Day key is the UTC start date, matching the grid's gmdate-based placement.
	 *
	 * @since 3.12.0
	 *
	 * @return array<string, array> Map of 'Y-m-d' => events.
	 */
	protected function get_multi_hour_events_by_day() {

		$by_day = [];

		foreach ( (array) $this->all_items as $item ) {

			if ( ! is_object( $item ) ) {
				continue;
			}

			if ( $item->is_all_day() || $item->is_multi( 'j' ) ) {
				continue;
			}

			if ( ! $this->event_spans_multiple_hours( $item ) ) {
				continue;
			}

			$day_key = gmdate( 'Y-m-d', (int) strtotime( $item->start ) );

			if ( ! isset( $by_day[ $day_key ] ) ) {
				$by_day[ $day_key ] = [];
			}

			$by_day[ $day_key ][] = $item;
		}

		return $by_day;
	}

	/**
	 * Build the full inline style for an intra-day event card.
	 *
	 * Combines the shared color CSS variables — empty when the event has no
	 * color — with the intra-day geometry, dropping empty parts so the joined
	 * value never carries a dangling separator.
	 *
	 * @since 3.12.0
	 *
	 * @param object $event Event object.
	 *
	 * @return string
	 */
	protected function get_event_grid_style( $event ) {

		$parts = array_filter(
			[
				$this->get_event_link_styling( $event ),
				$this->get_intra_day_event_style( $event ),
			]
		);

		return implode( ' ', $parts );
	}

	/**
	 * Display attendees notification button.
	 *
	 * @since 3.11.0
	 *
	 * @return void
	 */
	public function event_notifications() {

		UI::button(
			[
				'text' => esc_html__( 'Notify Attendees', 'sugar-calendar-lite' ),
				'type' => 'tertiary',
				'size' => 'sm',
				'id'   => 'sugar-calendar-btn-notify-attendees',
				'link' => '#',
			]
		);
	}
}
