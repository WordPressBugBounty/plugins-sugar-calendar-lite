<?php

namespace Sugar_Calendar\Features\Tags\Admin\Pages;

use Sugar_Calendar\Features\Tags\Admin\Pages\EventAbstract;
use Sugar_Calendar\Features\Tags\Common\Helpers;

/**
 * Admin hooks for the Event page.
 *
 * @since 3.7.0
 */
class Event extends EventAbstract {

	/**
	 * Add custom meta box for Tags in Event Edit admin page.
	 *
	 * @since 3.7.0
	 *
	 * @param string $post_type Post type.
	 */
	public function add_tags_meta_box( $post_type ) {

		// Get the event post type.
		$event_post_type = sugar_calendar_get_event_post_type_id();

		// Only add metabox to event post type.
		if ( $post_type !== $event_post_type ) {
			return;
		}

		// Remove default Tags metabox.
		remove_meta_box(
			'tagsdiv-' . Helpers::get_tags_taxonomy_id(),
			$event_post_type,
			'side'
		);

		// Add custom Tags metabox.
		add_meta_box(
			'sc_event_tags_metabox',
			__( 'Tags', 'sugar-calendar-lite' ),
			[ $this, 'render_tags_meta_box' ],
			$event_post_type,
			'side',
			'default'
		);
	}

	/**
	 * Render the Tags meta box content.
	 *
	 * @since 3.7.0
	 *
	 * @param object $post Post object.
	 */
	public function render_tags_meta_box( $post ) {

		$tags = get_the_terms( (int) $post->ID, Helpers::get_tags_taxonomy_id() );

		$contents = $this->get_tags_form( $tags, 'sc_event_tags[]', false );

		$contents .= wp_nonce_field( 'sc_event_tags_metabox', 'sc_event_tags_metabox_nonce', true, false );

		echo $contents;
	}

	/**
	 * Save tags meta box data.
	 *
	 * @since 3.7.0
	 *
	 * @param int    $object_id ID of the connected object.
	 */
	public function save_tags_meta_box( $object_id ) {

		// Bail if nonce is missing or invalid.
		if (
			! isset( $_POST['sc_event_tags_metabox_nonce'] )
			||
			! wp_verify_nonce(
				sanitize_text_field(
					wp_unslash( $_POST['sc_event_tags_metabox_nonce'] )
				),
				'sc_event_tags_metabox'
			)
		) {
			return;
		}

		// Get sc_event_tags from $_POST.
		$tags = isset( $_POST['sc_event_tags'] ) && is_array( $_POST['sc_event_tags'] )
			? array_map(
				'sanitize_text_field',
				wp_unslash( $_POST['sc_event_tags'] )
			)
			: [];

		$tag_ids = [];

		// Loop through tags.
		if ( ! empty( $tags ) ) {

			foreach ( $tags as $tag ) {

				// If tag is numeric, add to numeric tags.
				if ( is_numeric( $tag ) ) {

					$tag_ids[] = absint( $tag );

				} else {

					// Create new tag.
					$new_tag = wp_insert_term( sanitize_text_field( $tag ), Helpers::get_tags_taxonomy_id() );

					if ( ! is_wp_error( $new_tag ) ) {
						$tag_ids[] = intval( $new_tag['term_id'] );
					}
				}
			}
		}

		// Set tags.
		wp_set_object_terms(
			$object_id,
			Helpers::validate_tags_term_ids( $tag_ids ),
			Helpers::get_tags_taxonomy_id()
		);
	}
}
