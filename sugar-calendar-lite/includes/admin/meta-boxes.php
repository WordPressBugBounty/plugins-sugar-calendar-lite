<?php
/**
 * Event Meta-boxes
 *
 * @package Plugins/Site/Event/Admin/Metaboxes
 */

namespace Sugar_Calendar\Admin\Editor\Meta;

use Sugar_Calendar\Common\Editor as Editor;


/**
 * Filters the user option for Event meta-box ordering, and overrides it when
 * editing with Blocks.
 *
 * This ensures that users who have customized their meta-box layouts will still
 * be able to see meta-boxes no matter the Editing Type (block, classic).
 *
 * @since 2.0.20
 *
 * @param array $original
 *
 * @return mixed
 */
function noop_user_option( $original = array() ) {

	// Bail if using Classic Editor
	if ( 'classic' === Editor\current() ) {
		return $original;
	}

	// Return false
	return false;
}

/**
 * Does the event that is trying to be saved have an end date & time?
 *
 * @since 2.0.5
 *
 * @return bool
 */
function has_start() {

	return ! (
		empty( $_POST['start_date'] )
		&& empty( $_POST['start_time_hour'] )
		&& empty( $_POST['start_time_minute'] )
	);
}


/**
 * Maybe save event location in eventmeta
 *
 * @since 2.0.0
 *
 * @param array $event
 *
 * @return array
 */
function add_location_to_save( $event = array() ) {

	// Get event location
	$event['location'] = ! empty( $_POST['location'] )
		? wp_kses( $_POST['location'], array() )
		: '';

	// Return the event
	return $event;
}

/**
 * Maybe save event color in eventmeta
 *
 * @since 2.0.0
 *
 * @param array $event
 *
 * @return array
 */
function add_color_to_save( $event = array() ) {

	// Bail if missing ID or type
	if ( empty( $event['object_id'] ) || empty( $event['object_type'] ) ) {
		return $event;
	}

	// Set the event color
	$event['color'] = sugar_calendar_get_event_color( $event['object_id'], $event['object_type'] );

	// Return the event
	return $event;
}

/**
 * Display calendar taxonomy meta box
 *
 * This is a copy of post_categories_meta_box() which allows us to remove the
 * "Most Used" tab functionality for the "Calendars" taxonomy.
 *
 * @since 2.0.0
 *
 * @param WP_Post $post
 * @param array   $box      {
 *                          Categories meta box arguments.
 *
 * @type string   $id       Meta box 'id' attribute.
 * @type string   $title    Meta box title.
 * @type callable $callback Meta box display callback.
 * @type array    $args     {
 *                          Extra meta box arguments.
 *
 * @type string   $taxonomy Taxonomy. Default 'category'.
 *                          }
 *                          }
 */
function calendars( $post = null, $box = array() ) {

	// Fallback
	$args = ( ! isset( $box['args'] ) || ! is_array( $box['args'] ) )
		? array()
		: $box['args'];

	// Parse arguments
	$r = wp_parse_args( $args, array(
		'taxonomy' => 'category',
	) );

	// Taxonomy vars
	$taxonomy = get_taxonomy( $r['taxonomy'] );
	$tax_name = esc_attr( $taxonomy->name );
	$default  = sugar_calendar_get_default_calendar();

	// Dropdown arguments
	$parent_dropdown_args = apply_filters( 'post_edit_category_parent_dropdown_args', array(
		'taxonomy'         => $taxonomy->name,
		'hide_empty'       => 0,
		'name'             => 'new' . $taxonomy->name . '_parent',
		'orderby'          => 'name',
		'hierarchical'     => $taxonomy->hierarchical,
		'show_option_none' => '&mdash; ' . $taxonomy->labels->parent_item . ' &mdash;',
	) );

	// Check term cache first
	$selected = get_object_term_cache( $post->ID, $taxonomy->name );

	// Pluck IDs from cache
	if ( false !== $selected ) {
		$selected = wp_list_pluck( $selected, 'term_id' );

		// No cache, so query for selected
	} else {

		// Args
		$tax_args = array_merge( $r, array(
			'fields' => 'ids',
		) );

		// Query
		$selected = wp_get_object_terms( $post->ID, $taxonomy->name, $tax_args );
	}

	// Use default
	if ( empty( $selected ) && ! empty( $default ) ) {
		$selected = array( $default );
	}

	// Checklist arguments
	$checklist_args = array(
		'taxonomy'      => $taxonomy->name,
		'selected_cats' => $selected,
		'checked_ontop' => false,
	); ?>

    <div id="taxonomy-<?php echo $tax_name; ?>" class="categorydiv">

        <div id="<?php echo $tax_name; ?>-all">
			<?php

			$name = ( 'category' === $taxonomy->name )
				? 'post_category'
				: 'tax_input[' . $taxonomy->name . ']';

			// Allows for an empty term set to be sent. 0 is an invalid Term ID
			// and will be ignored by empty() checks.
			echo "<input type='hidden' name='{$name}[]' value='0' />"; ?>

            <ul id="<?php echo $tax_name; ?>checklist" data-wp-lists="list:<?php echo $tax_name; ?>" class="categorychecklist form-no-clear">
				<?php wp_terms_checklist( $post->ID, $checklist_args ); ?>
            </ul>

            <a id="<?php echo $tax_name; ?>-clear" href="#<?php echo $tax_name; ?>-clear" class="hide-if-no-js button taxonomy-clear">
				<?php esc_html_e( 'Clear', 'sugar-calendar-lite' ); ?>
            </a>
        </div>

		<?php if ( current_user_can( $taxonomy->cap->edit_terms ) ) : ?>

            <div id="<?php echo $tax_name; ?>-adder" class="wp-hidden-children">
                <a id="<?php echo $tax_name; ?>-add-toggle" href="#<?php echo $tax_name; ?>-add" class="hide-if-no-js taxonomy-add-new">
					<?php echo $taxonomy->labels->add_new_item; ?>
                </a>

                <p id="<?php echo $tax_name; ?>-add" class="category-add wp-hidden-child">
                    <label class="screen-reader-text" for="new<?php echo $tax_name; ?>"><?php echo $taxonomy->labels->add_new_item; ?></label>
                    <input type="text" name="new<?php echo $tax_name; ?>" id="new<?php echo $tax_name; ?>" class="form-required form-input-tip" value="<?php echo esc_attr( $taxonomy->labels->new_item_name ); ?>" aria-required="true"/>
                    <label class="screen-reader-text" for="new<?php echo $tax_name; ?>_parent">
						<?php echo $taxonomy->labels->parent_item_colon; ?>
                    </label>

					<?php wp_dropdown_categories( $parent_dropdown_args ); ?>

                    <input type="button" id="<?php echo $tax_name; ?>-add-submit" data-wp-lists="add:<?php echo $tax_name; ?>checklist:<?php echo $tax_name; ?>-add" class="button category-add-submit" value="<?php echo esc_attr( $taxonomy->labels->add_new_item ); ?>"/>

					<?php wp_nonce_field( 'add-' . $taxonomy->name, '_ajax_nonce-add-' . $taxonomy->name, false ); ?>

                    <span id="<?php echo $tax_name; ?>-ajax-response"></span>
                </p>
            </div>

		<?php endif; ?>

    </div>

	<?php
}
