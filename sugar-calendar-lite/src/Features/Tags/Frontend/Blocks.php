<?php
namespace Sugar_Calendar\Features\Tags\Frontend;

use Sugar_Calendar\Features\Tags\Common\Helpers;

/**
 * Block helpers.
 *
 * @since 3.7.0
 */
class Blocks {

	/**
	 * Hooks.
	 *
	 * @since 3.7.0
	 */
	public function hooks() {

		add_action( 'sugar_calendar_block_popover_additional_filters', [ $this, 'render_block_tag_filter' ], 20, 1 );
	}

	/**
	 * Render blocks tag filter.
	 *
	 * @since 3.7.0
	 *
	 * @param Sugar_Calendar\Block\Calendar\CalendarView\Block $context The block context.
	 *
	 * @return void
	 */
	public function render_block_tag_filter( $context ) {

		/**
		 * Filters whether to show the tag filter.
		 *
		 * @since 3.7.0
		 *
		 * @param bool  $show    Whether to show the tag filter.
		 * @param Block $context The block context.
		 */
		$show_tag_filter = apply_filters( 'sugar_calendar_features_tags_frontend_blocks_show_tags_filter', true, $context );

		if ( ! $show_tag_filter ) {
			return;
		}

		$tags = $this->get_tags_for_filter( $context );

		if ( empty( $tags ) ) {
			return;
		}

		?>
		<div class="sugar-calendar-block__popover__calendar_selector__container__tags">
			<div class="sugar-calendar-block__popover__calendar_selector__container__heading">
				<?php esc_html_e( 'Tags', 'sugar-calendar-lite' ); ?>
			</div>
			<div class="sugar-calendar-block__popover__calendar_selector__container__options">
				<?php foreach ( $tags as $tag ) : ?>

					<?php
					$tag_checkbox_id = sprintf(
						'sc-tag-%1$s-%2$d',
						esc_attr( $context->get_block_id() ),
						esc_attr( $tag->term_id )
					);
					?>

					<div class="sugar-calendar-block__popover__calendar_selector__container__options__val">
						<label for="<?php echo esc_attr( $tag_checkbox_id ); ?>">
							<input
								type="checkbox"
								id="<?php echo esc_attr( $tag_checkbox_id ); ?>"
								class="sugar-calendar-block__popover__calendar_selector__container__options__val__tag"
								name="tag-<?php echo esc_attr( $tag->term_id ); ?>"
								value="<?php echo esc_attr( $tag->term_id ); ?>"
							/>
							<?php echo esc_html( $tag->name ); ?>
						</label>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get the tags for the filter.
	 *
	 * @since 3.7.0
	 *
	 * @param Sugar_Calendar\Block\Calendar\CalendarView\Block $context The block context.
	 *
	 * @return WP_Term[]
	 */
	public function get_tags_for_filter( $context ) {

		$tags_taxonomy = Helpers::get_tags_taxonomy_id();

		/**
		 * Filters the tags arguments.
		 *
		 * @since 3.7.0
		 *
		 * @param array $tags_args The tags arguments.
		 */
		$tags_args = apply_filters(
			'sugar_calendar_features_tags_frontend_blocks_tags_args',
			[
				'taxonomy'   => $tags_taxonomy,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'hide_empty' => false,
				'number'     => 100,
			]
		);

		$tags = get_terms( $tags_args );

		if ( empty( $tags ) ) {
			return [];
		}

		// Display all tags if no tags are selected from block settings.
		if ( empty( $context->get_tags() ) ) {
			return $tags;
		}

		$selected_tags = array_filter(
			$tags,
			function ( $tag ) use ( $context ) {
				return in_array( $tag->term_id, $context->get_tags(), true );
			}
		);

		// If only one tag is selected, we should not display the filter.
		if ( count( $selected_tags ) === 1 ) {
			return [];
		}

		return $selected_tags;
	}
}
