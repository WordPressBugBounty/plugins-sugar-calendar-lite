<?php

namespace Sugar_Calendar\Admin\Events\Metaboxes;

use Walker_Category_Checklist;

// Walker_Category_Checklist class.
require_once ABSPATH . 'wp-admin/includes/class-walker-category-checklist.php';

/**
 * Category checkbox walker.
 *
 * @since 3.2.0
 */
class WalkerCategoryCheckbox extends Walker_Category_Checklist {

	/**
	 * Start the element output.
	 *
	 * @since 2.0.0
	 *
	 * @param string $output   Passed by reference. Used to append additional content.
	 * @param object $category The current term object.
	 * @param int    $depth    Depth of the term in reference to parents. Default 0.
	 * @param array  $args     An array of arguments. @see wp_terms_checklist().
	 * @param int    $id       ID of the current term.
	 *
	 * @see   Walker::start_el()
	 */
	public function start_el( &$output, $category, $depth = 0, $args = [], $id = 0 ) {

		// Note that Walker classes are trusting with their previously
		// validated object properties.
		$taxonomy = sanitize_key( $args['taxonomy'] );
		$name     = "tax_input[{$taxonomy}]";

		// Maybe show popular categories tab.
		$args['popular_cats'] = empty( $args['popular_cats'] )
			? []
			: $args['popular_cats'];

		// Maybe add popular category class.
		$class = in_array( $category->term_id, $args['popular_cats'] )
			? ' class="popular-category"'
			: '';

		// Maybe use already selected categories.
		$args['selected_cats'] = empty( $args['selected_cats'] )
			? []
			: $args['selected_cats'];

		// List item ID.
		$item_id  = sanitize_key( "{$taxonomy}-{$category->term_id}" );
		$checked  = in_array( $category->term_id, $args['selected_cats'], true );
		$disabled = empty( $args['disabled'] );
		$text     = apply_filters( 'the_category', $category->name, '', '' ); // phpcs:ignore WPForms.Comments.PHPDocHooks.RequiredHookDocumentation,WPForms.PHP.ValidateHooks.InvalidHookName

		// Calendar color.
		$bg_color = sugar_calendar_get_calendar_color( $category->term_id );
		$color    = sugar_calendar_get_contrast_color( $bg_color );

		// Start an output buffer.
		ob_start(); ?>

		<li id="<?php echo esc_attr( $item_id ); ?>"
			<?php echo esc_attr( $class ); ?>
			style="--sugar-calendar-background-color: <?php echo esc_html( $bg_color ); ?>; --sugar-calendar-foreground-color: <?php echo esc_html( $color ); ?>;">

			<label class="selectit">
				<input value="<?php echo esc_attr( $category->term_id ); ?>" type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" id="in-<?php echo esc_attr( $item_id ); ?>" <?php checked( $checked ); ?> <?php disabled( $disabled, false ); ?> />
				<?php echo esc_html( $text ); ?>
			</label>

			<?php
			// Add the list item to the output.
			$output .= ob_get_clean();
	}
}
