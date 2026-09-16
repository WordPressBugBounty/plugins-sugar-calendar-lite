<?php

namespace Sugar_Calendar\Features\FeaturedEvents\Admin\Pages;

use Sugar_Calendar\Admin\Events\Tables\Base;
use Sugar_Calendar\Admin\Events\Tables\Basic;
use Sugar_Calendar\Features\FeaturedEvents\Common\Featured;
use Sugar_Calendar\Features\FeaturedEvents\Common\Markup;

/**
 * Featured Events UI on the Events list admin page.
 *
 * @since 3.14.0
 */
class Events {

	/**
	 * Value of the admin `status` arg that turns the "Featured" filter on.
	 *
	 * Not a real post status; it rides the status arg to inherit its plumbing.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	private const FILTER_STATUS = 'featured';

	/**
	 * Add the "Featured" link after the core status links.
	 *
	 * Last, so the post statuses WordPress itself contributes keep their order.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $links    Status link anchors keyed by status.
	 * @param Base   $table    Table rendering the links.
	 * @param string $base_url Persistent URL the sibling links are built on.
	 *
	 * @return array
	 */
	public function add_status_link( $links, $table = null, $base_url = '' ) {

		$url = add_query_arg( [ 'status' => self::FILTER_STATUS ], $base_url );

		// Counted off the view's own items, as the sibling status counts are.
		$count = ( $table instanceof Base ) ? Featured::count_featured( $table->get_all_items() ) : 0;

		$anchor = sprintf(
			'<a href="%1$s" class="%2$s">%3$s <span class="count">(%4$s)</span></a>',
			esc_url( $url ),
			$this->is_filtering() ? 'current' : '',
			esc_html( Markup::get_label() ),
			esc_html( number_format_i18n( $count ) )
		);

		$links[ self::FILTER_STATUS ] = $anchor;

		return $links;
	}

	/**
	 * Restrict the calendar views to featured events while the filter is on.
	 *
	 * Scoped here, at the display gate, rather than in the query: the status-link counts
	 * are derived from the query, so narrowing it would make "All" report the featured
	 * count instead of the total.
	 *
	 * @since 3.14.0
	 *
	 * @param array $filtered_ids Row IDs cleared for display.
	 *
	 * @return array
	 */
	public function filter_row_ids( $filtered_ids ) {

		if ( ! $this->is_filtering() ) {
			return $filtered_ids;
		}

		return Featured::filter_row_ids( (array) $filtered_ids );
	}

	/**
	 * Stop `status=featured` being matched against the event status.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $filter The filter arguments.
	 * @param string $status The requested status.
	 *
	 * @return array
	 */
	public function clear_items_filter( $filter, $status ) {

		return $status === self::FILTER_STATUS ? [] : $filter;
	}

	/**
	 * Restrict the List view's rows to featured events while the filter is on.
	 *
	 * The List view renders objects rather than consulting the ID gate the calendar
	 * views use, so it needs its own pass.
	 *
	 * @since 3.14.0
	 *
	 * @param array $items Event objects about to render.
	 *
	 * @return array
	 */
	public function filter_list_items( $items ) {

		if ( ! $this->is_filtering() ) {
			return $items;
		}

		return array_values( array_filter( (array) $items, [ Featured::class, 'is_featured' ] ) );
	}

	/**
	 * Render the featured star ahead of an event title.
	 *
	 * @since 3.14.0
	 *
	 * @param string $before_title Markup rendered before the title.
	 * @param object $event        Event object.
	 * @param Base   $table        Table rendering the link.
	 *
	 * @return string
	 */
	public function add_star_to_event_link( $before_title, $event, $table = null ) {

		if ( ! Featured::is_featured( $event ) ) {
			return $before_title;
		}

		// The list table's titles wrap; the calendar chips are a single line.
		$context = $table instanceof Basic ? 'admin-list' : 'calendar-chip';

		return $before_title . Markup::get_star( $context );
	}

	/**
	 * Render the featured star before the event pointer title.
	 *
	 * @since 3.14.0
	 *
	 * @param string $before_title Markup rendered before the title.
	 * @param object $event        Event object.
	 *
	 * @return string
	 */
	public function add_star_to_pointer( $before_title, $event ) {

		if ( ! Featured::is_featured( $event ) ) {
			return $before_title;
		}

		return $before_title . Markup::get_star( 'pointer-header' );
	}

	/**
	 * Prime the event meta cache for everything a calendar view will render.
	 *
	 * One query for the whole set, which core's own per-row location and color reads
	 * then ride. Unrelated to the featured flag, which has its own memoized lookup.
	 *
	 * @since 3.14.0
	 *
	 * @param array $all_items Event objects for the current view.
	 *
	 * @return array
	 */
	public function prime_meta_cache( $all_items ) {

		$event_ids = array_filter( array_map( 'absint', wp_list_pluck( (array) $all_items, 'id' ) ) );

		if ( ! empty( $event_ids ) ) {
			update_meta_cache( 'sc_event', $event_ids );
		}

		return $all_items;
	}

	/**
	 * Whether the "Featured" filter is currently on.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	private function is_filtering() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_REQUEST['status'] ) ? sanitize_key( $_REQUEST['status'] ) : '';

		return $status === self::FILTER_STATUS;
	}
}
