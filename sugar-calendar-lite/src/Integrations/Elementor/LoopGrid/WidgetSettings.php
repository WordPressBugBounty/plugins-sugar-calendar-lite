<?php

namespace Sugar_Calendar\Integrations\Elementor\LoopGrid;

use Elementor\Controls_Manager;

/**
 * The Sugar Calendar controls on Elementor's Loop Grid widget.
 *
 * @since 3.14.0
 */
class WidgetSettings {

	/**
	 * Register hooks.
	 *
	 * @since 3.14.0
	 */
	public function hooks() {

		add_action( 'elementor/element/loop-grid/section_query/before_section_end', [ $this, 'add_controls' ], 10, 1 );
		add_action( 'elementor/element/loop-grid/section_query/after_section_end', [ $this, 'hide_controls' ] );
	}

	/**
	 * Add custom controls in the loop grid widget.
	 *
	 * @since 3.10.0
	 * @since 3.14.0 Moved here from Elementor::add_filter_controls_in_loop_grid().
	 *
	 * @param \ElementorPro\Modules\Posts\Widgets\Base_Widget $widget Widget object.
	 */
	public function add_controls( $widget ) {

		if ( $widget->get_name() !== 'loop-grid' ) {
			return;
		}

		// Position the controls right after the Source control.
		$widget->start_injection(
			[
				'type' => 'control',
				'at'   => 'after',
				'of'   => 'post_query_post_type',
			]
		);

		$widget->add_control(
			'calendars',
			[
				'label'       => esc_html__( 'Filter by Calendars', 'sugar-calendar-lite' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => get_terms(
					[
						'hide_empty' => false,
						'taxonomy'   => 'sc_event_category',
						'fields'     => 'id=>name',
					]
				),
				'default'     => [],
				'label_block' => true,
				'condition'   => [
					'post_query_post_type' => sugar_calendar_get_event_post_type_id(),
				],
				'description' => esc_html__( 'Leave empty to show events from all calendars.', 'sugar-calendar-lite' ),
			]
		);

		/**
		 * Fires after adding the SCE filters controls to the Loop Grid widget.
		 *
		 * Name predates this class; Pro hooks it, so it cannot be renamed.
		 *
		 * @since 3.10.0
		 *
		 * @param \ElementorPro\Modules\LoopBuilder\Widgets\Loop_Grid $widget Loop Grid Widget object.
		 */
		do_action( 'sugar_calendar_integrations_elementor_loop_grid_add_filter_controls', $widget ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		$widget->add_control(
			'sce_include_by_post_id',
			[
				'label'       => esc_html__( 'Include by Post ID', 'sugar-calendar-lite' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
				'placeholder' => esc_html__( '1, 2, 3', 'sugar-calendar-lite' ),
				'condition'   => [
					'post_query_post_type' => sugar_calendar_get_event_post_type_id(),
				],
				'description' => esc_html__( 'Enter comma-separated post IDs to include specific events.', 'sugar-calendar-lite' ),
				'ai'          => [
					'active' => false,
				],
			]
		);

		$widget->add_control(
			'sce_exclude_by_post_id',
			[
				'label'       => esc_html__( 'Exclude by Post ID', 'sugar-calendar-lite' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
				'placeholder' => esc_html__( '21, 22', 'sugar-calendar-lite' ),
				'condition'   => [
					'post_query_post_type' => sugar_calendar_get_event_post_type_id(),
				],
				'description' => esc_html__( 'Enter comma-separated post IDs to exclude specific events.', 'sugar-calendar-lite' ),
				'ai'          => [
					'active' => false,
				],
			]
		);

		$widget->end_injection();
	}

	/**
	 * Hide specific controls for Sugar Calendar events in Loop Grid.
	 *
	 * @since 3.10.0
	 * @since 3.14.0 Moved here from Elementor::hide_loop_grid_controls_on_events().
	 *
	 * @param \ElementorPro\Modules\Posts\Widgets\Base_Widget $widget Widget object.
	 */
	public function hide_controls( $widget ) {

		if ( $widget->get_name() !== 'loop-grid' ) {
			return;
		}

		/**
		 * Filters the flag to hide the controls on the Loop Grid widget.
		 *
		 * Name predates this class; renaming it would break existing overrides.
		 *
		 * @since 3.10.0
		 *
		 * @param bool                                            $flag_to_hide_controls Flag to hide the controls.
		 * @param \ElementorPro\Modules\Posts\Widgets\Base_Widget $widget                Widget object.
		 */
		$flag_to_hide_controls = apply_filters( 'sugar_calendar_integrations_elementor_loop_grid_hide_controls_on_events', true, $widget ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		if ( ! $flag_to_hide_controls ) {
			return;
		}

		$controls         = $widget->get_controls();
		$controls_to_hide = [
			// Layout section controls.
			'masonry',
			'alternate_template',
			// Query section controls.
			'post_query_select_date',
			'post_query_date_before',
			'post_query_date_after',
			'post_query_orderby',
			'post_query_order',
			// Include section controls.
			'post_query_query_include',
			'post_query_include',
			'post_query_include_term_ids',
			'post_query_include_authors',
			// Exclude section controls.
			'post_query_query_exclude',
			'post_query_exclude',
			'post_query_exclude_ids',
			'post_query_exclude_term_ids',
			'post_query_exclude_authors',
		];

		foreach ( $controls_to_hide as $control_id ) {

			if ( ! isset( $controls[ $control_id ] ) ) {
				continue;
			}

			$control_config     = $controls[ $control_id ];
			$existing_condition = isset( $control_config['condition'] ) ? $control_config['condition'] : [];

			// Hide when sc_event is the selected source.
			$existing_condition['post_query_post_type!'] = sugar_calendar_get_event_post_type_id();

			$widget->update_control(
				$control_id,
				[
					'condition' => $existing_condition,
				]
			);
		}
	}
}
