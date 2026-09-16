<?php

namespace Sugar_Calendar\Integrations\Elementor\LoopGrid;

use Sugar_Calendar\Helpers as BaseHelpers;

/**
 * Builds the WP_Query args for an Elementor Loop Grid showing Sugar Calendar
 * events.
 *
 * @since 3.14.0
 */
class QueryArgs {

	/**
	 * Register hooks.
	 *
	 * Priority 900 so the args land after Elementor Pro has built its own.
	 *
	 * @since 3.14.0
	 */
	public function hooks() {

		add_filter( 'elementor/query/query_args', [ $this, 'filter' ], 900, 2 );
	}

	/**
	 * Filter the query arguments for the Loop Grid widget.
	 *
	 * @since 3.10.0
	 * @since 3.14.0 Moved here from Elementor::filter_loop_grid_query_args().
	 *
	 * @param array                                           $query_args Array containing the query to be used.
	 * @param \ElementorPro\Modules\Posts\Widgets\Base_Widget $widget     Widget object.
	 *
	 * @return array
	 */
	public function filter( $query_args, $widget ) {

		// Cheap name check before get_settings_for_display(): this filter runs
		// for every query-driven widget on the page, and resolving settings
		// triggers dynamic-tag callbacks we have no business firing on someone
		// else's widget.
		if ( $widget->get_name() !== 'loop-grid' ) {
			return $query_args;
		}

		$settings = $widget->get_settings_for_display();

		if ( ! $this->targets_sc_events( $settings ) ) {
			return $query_args;
		}

		$custom_query_args = $this->base_query_args( $this->resolve_requested_page( $query_args ) );

		$include_post_ids = $this->parse_id_list( $settings['sce_include_by_post_id'] ?? '' );

		// When specific posts are included, show only those.
		if ( ! empty( $include_post_ids ) ) {
			$custom_query_args['post__in'] = $include_post_ids;

			return $custom_query_args;
		}

		$sce_args = $this->build_sce_args( $settings, $query_args, $widget );

		/**
		 * Filters the attributes passed to get the Sugar Calendar events for the Loop Grid widget.
		 *
		 * Name predates this class; Pro hooks it, so it cannot be renamed.
		 *
		 * @since 3.10.0
		 *
		 * @param array                                               $sce_atts   Array containing the arguments to get the Sugar Calendar events.
		 * @param array                                               $settings   Array containing the settings of the Loop Grid widget.
		 * @param array                                               $query_args Array containing the query arguments for the Loop Grid widget.
		 * @param \ElementorPro\Modules\LoopBuilder\Widgets\Loop_Grid $widget     Widget object.
		 */
		$sce_atts = apply_filters( 'sugar_calendar_integrations_elementor_loop_grid_attributes', [], $settings, $query_args, $widget ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		$unique_events = UpcomingEvents::fetch( $sce_args, $sce_atts, $this->resolve_target_number( $settings ) );

		$post_ids = empty( $unique_events ) ? [] : wp_list_pluck( $unique_events, 'object_id' );

		if ( empty( $post_ids ) ) {
			return $this->match_nothing( $custom_query_args );
		}

		$custom_query_args['post__in'] = $post_ids;

		$per_page = $this->configured_per_page( $settings );

		if ( $per_page ) {
			$custom_query_args['posts_per_page'] = $per_page;
		}

		return $this->apply_excludes( $custom_query_args, $settings );
	}

	/**
	 * Turn the built args into a query that matches nothing.
	 *
	 * Returning an empty array instead would hand Elementor `new WP_Query( [] )`,
	 * which is not "no results" — WP falls back to its own defaults and the grid
	 * renders the site's latest blog posts. An unmatchable `post__in` is how the
	 * include/exclude paths already express an empty result.
	 *
	 * @since 3.14.0
	 *
	 * @param array $custom_query_args Query args built so far.
	 *
	 * @return array
	 */
	private function match_nothing( $custom_query_args ) {

		$custom_query_args['post__in'] = [ 0 ];

		return $custom_query_args;
	}

	/**
	 * Whether the widget is configured to show Sugar Calendar events.
	 *
	 * @since 3.14.0
	 *
	 * @param array $settings Widget settings.
	 *
	 * @return bool
	 */
	private function targets_sc_events( $settings ) {

		return ! empty( $settings['post_query_post_type'] )
			&& $settings['post_query_post_type'] === sugar_calendar_get_event_post_type_id();
	}

	/**
	 * The base query args every Sugar Calendar Loop Grid shares.
	 *
	 * @since 3.14.0
	 *
	 * @param int $requested_page The page being requested.
	 *
	 * @return array
	 */
	private function base_query_args( $requested_page ) {

		return [
			'post_status' => 'publish',
			'post_type'   => BaseHelpers::get_event_post_types(),
			'paged'       => $requested_page,
			'orderby'     => 'post__in',
		];
	}

	/**
	 * Resolve which page is being requested.
	 *
	 * Elementor resolves the current page for every pagination mode it supports
	 * (classic numbered, AJAX load-more, individual pagination handle) in
	 * Posts_Base::get_current_page() before this filter runs, and hands us the
	 * result as `paged`. `get_query_var( 'page' )` alone only reflects classic
	 * numbered pagination on a non-front singular page, so it's a fallback for
	 * when `paged` is unexpectedly absent.
	 *
	 * @since 3.14.0
	 *
	 * @param array $query_args Incoming query args.
	 *
	 * @return int
	 */
	private function resolve_requested_page( $query_args ) {

		if ( ! empty( $query_args['paged'] ) ) {
			return absint( $query_args['paged'] );
		}

		return absint( get_query_var( 'page', 1 ) );
	}

	/**
	 * Build the args used to fetch Sugar Calendar events.
	 *
	 * @since 3.14.0
	 *
	 * @param array                                           $settings   Widget settings.
	 * @param array                                           $query_args Incoming query args.
	 * @param \ElementorPro\Modules\Posts\Widgets\Base_Widget $widget     Widget object.
	 *
	 * @return array
	 */
	private function build_sce_args( $settings, $query_args, $widget ) {

		$sce_args = [];

		if ( ! empty( $settings['calendars'] ) && is_array( $settings['calendars'] ) ) {
			$sce_args['calendar_ids'] = $settings['calendars'];
		}

		/**
		 * Filters the arguments passed to get the Sugar Calendar events for the Loop Grid widget.
		 *
		 * @since 3.10.0
		 *
		 * @param array                                               $sce_args   Array containing the arguments to get the Sugar Calendar events.
		 * @param array                                               $settings   Array containing the settings of the Loop Grid widget.
		 * @param array                                               $query_args Array containing the query arguments for the Loop Grid widget.
		 * @param \ElementorPro\Modules\LoopBuilder\Widgets\Loop_Grid $widget     Widget object.
		 */
		$sce_args = apply_filters( 'sugar_calendar_integrations_elementor_loop_grid_query_args', $sce_args, $settings, $query_args, $widget );

		if ( ! is_array( $sce_args ) ) {
			$sce_args = [];
		}

		// Hide password-protected events from visitors lacking edit_posts, as
		// the Calendar/Event List blocks do. Widening post_type to include
		// sc_recurring_event would otherwise extend that pre-existing gap to
		// recurring events. See BaseHelpers::should_filter_password_protected_events().
		$sce_args['has_password'] = false;

		return $sce_args;
	}

	/**
	 * The configured cards-per-page, or 0 when the setting is unset.
	 *
	 * Read through one place: this value both sizes the fetch and becomes the
	 * query's `posts_per_page`, and the two must not drift apart.
	 *
	 * @since 3.14.0
	 *
	 * @param array $settings Widget settings.
	 *
	 * @return int
	 */
	private function configured_per_page( $settings ) {

		if ( ! empty( $settings['posts_per_page'] ) && is_numeric( $settings['posts_per_page'] ) ) {
			return absint( $settings['posts_per_page'] );
		}

		return 0;
	}

	/**
	 * The number of cards to size the fetch for.
	 *
	 * @since 3.14.0
	 *
	 * @param array $settings Widget settings.
	 *
	 * @return int
	 */
	private function resolve_target_number( $settings ) {

		$per_page = $this->configured_per_page( $settings );

		if ( $per_page ) {
			return $per_page;
		}

		// Unconfigured: match the default the events helper itself falls back to.
		return 5;
	}

	/**
	 * Parse a comma-separated list of post IDs.
	 *
	 * Non-numeric entries become 0 rather than being dropped, preserving the
	 * pre-existing behavior that a junk "Include by Post ID" value yields an
	 * unmatchable `post__in` (an empty grid) instead of silently ignoring the
	 * setting.
	 *
	 * @since 3.14.0
	 *
	 * @param string $value Raw setting value.
	 *
	 * @return int[]
	 */
	private function parse_id_list( $value ) {

		if ( empty( $value ) ) {
			return [];
		}

		return array_map( 'absint', explode( ',', $value ) );
	}

	/**
	 * Apply the "Exclude by Post ID" setting.
	 *
	 * @since 3.14.0
	 *
	 * @param array $custom_query_args Query args built so far.
	 * @param array $settings          Widget settings.
	 *
	 * @return array
	 */
	private function apply_excludes( $custom_query_args, $settings ) {

		$exclude_post_ids = $this->parse_id_list( $settings['sce_exclude_by_post_id'] ?? '' );

		if ( empty( $exclude_post_ids ) ) {
			return $custom_query_args;
		}

		$custom_query_args['post__not_in'] = $exclude_post_ids;

		if ( ! empty( $custom_query_args['post__in'] ) ) {
			$custom_query_args['post__in'] = array_diff( $custom_query_args['post__in'], $exclude_post_ids );

			// Excluding every remaining event empties post__in, which WP_Query
			// then ignores — showing every event instead of none.
			if ( empty( $custom_query_args['post__in'] ) ) {
				return $this->match_nothing( $custom_query_args );
			}
		}

		return $custom_query_args;
	}
}
