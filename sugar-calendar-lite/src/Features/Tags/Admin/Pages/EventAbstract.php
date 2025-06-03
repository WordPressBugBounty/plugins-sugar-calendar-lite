<?php

namespace Sugar_Calendar\Features\Tags\Admin\Pages;

use Sugar_Calendar\Features\Tags\Common\Helpers;

/**
 * Abstract Event page.
 *
 * @since 3.7.0
 */
abstract class EventAbstract {

	/**
	 * Additional localized scripts for the events page.
	 *
	 * @since 3.7.0
	 *
	 * @param array $localize_script Localize script.
	 *
	 * @return array
	 */
	public function localize_script( $localize_script ) {

		$localize_script['choicesjs_config'] = $this->get_choicesjs_column_config();
		$localize_script['all_tags_choices'] = $this->get_all_tags_choices();
		$localize_script['strings']          = $this->get_localize_strings();

		return $localize_script;
	}

	/**
	 * Get Choices.js configuration for column values.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	public function get_choicesjs_column_config() {

		return [
			'removeItemButton'  => true,
			'shouldSort'        => false,
			'loadingText'       => esc_html__( 'Loading...', 'sugar-calendar-lite' ),
			'noResultsText'     => esc_html__( 'No results found', 'sugar-calendar-lite' ),
			'noChoicesText'     => esc_html__( 'No tags to choose from', 'sugar-calendar-lite' ),
			'itemSelectText'    => '',
			'searchEnabled'     => true,
			'searchChoices'     => true,
			'searchFloor'       => 1,
			'searchResultLimit' => 100,
			'searchFields'      => [ 'label' ],
			'allowHTML'         => true,
			'fuseOptions'       => [
				'threshold' => 0.1,
				'distance'  => 1000,
				'location'  => 2,
			],
		];
	}

	/**
	 * Get all tags as choices for Choices.js.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	public function get_all_tags_choices() {

		static $choices = null;

		if ( is_array( $choices ) ) {
			return $choices;
		}

		$choices = [];

		$tags = get_terms(
			[
				'taxonomy'   => Helpers::get_tags_taxonomy_id(),
				'hide_empty' => false,
			]
		);

		if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
			foreach ( $tags as $tag ) {
				$choices[] = [
					'value' => (string) $tag->term_id,
					'slug'  => $tag->slug,
					'label' => sanitize_term_field( 'name', $tag->name, $tag->term_id, Helpers::get_tags_taxonomy_id(), 'display' ),
					'count' => (int) $tag->count,
				];
			}
		}

		return $choices;
	}

	/**
	 * Get localized strings for JavaScript.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	public function get_localize_strings() {

		return [
			'nonce'                => wp_create_nonce( 'sugar-calendar-events-tags-nonce' ),
			'add_new_tag'          => esc_html__( 'Press Enter or "," key to add new tag', 'sugar-calendar-lite' ),
			'no_results_text'      => esc_html__( 'No results found', 'sugar-calendar-lite' ),
			'error'                => esc_html__( 'Something went wrong. Please try again.', 'sugar-calendar-lite' ),
			'bulk_edit_tags_title' => esc_html__( 'Bulk Edit Tags', 'sugar-calendar-lite' ),
			'bulk_edit_one_event'  => wp_kses(
				__( '<strong>1 event</strong> selected for Bulk Edit.', 'sugar-calendar-lite' ),
				[ 'strong' => [] ]
			),
			'bulk_edit_n_events'   => wp_kses( /* translators: %d - number of events selected for Bulk Edit. */
				__( '<strong>%d events</strong> selected for Bulk Edit.', 'sugar-calendar-lite' ),
				[ 'strong' => [] ]
			),
			'select_events'        => esc_html__( 'Please select events to edit.', 'sugar-calendar-lite' ),
			'cancel'               => esc_html__( 'Cancel', 'sugar-calendar-lite' ),
			'save'                 => esc_html__( 'Save Changes', 'sugar-calendar-lite' ),
		];
	}

	/**
	 * Get tags select2 form.
	 *
	 * @since 3.7.0
	 *
	 * @param array  $tags         Tags.
	 * @param string $name         Name of the form.
	 * @param bool   $with_buttons Whether to include buttons.
	 *
	 * @return string
	 */
	public function get_tags_form( $tags, $name, $with_buttons = true ) {

		$tags_options = $this->get_tags_options( $tags );

		$name_attr = $name ? sprintf( 'name="%s"', esc_attr( $name ) ) : '';

		$buttons = wp_sprintf(
			'<i class="dashicons dashicons-dismiss sugar-calendar-column-tags-edit-cancel" title="%1$s"></i>
			<i class="dashicons dashicons-yes-alt sugar-calendar-column-tags-edit-save" title="%2$s"></i>
			<i class="sugar-calendar-spinner spinner sugar-calendar-hidden"></i>',
			esc_attr__( 'Cancel', 'sugar-calendar-lite' ),
			esc_attr__( 'Save changes', 'sugar-calendar-lite' )
		);

		// Tags select2 form.
		return sprintf(
			'<span class="choicesjs-select-wrap">
				<div class="sugar-calendar-column-tags-form sugar-calendar-hidden">
					<select %1$s multiple>%2$s</select>
					%3$s
				</div>
			</span>',
			$name_attr,
			$tags_options,
			$with_buttons ? $buttons : ''
		);
	}

	/**
	 * Get tags options.
	 *
	 * @since 3.7.0
	 *
	 * @param array $tags Tags.
	 *
	 * @return string
	 */
	public function get_tags_options( $tags ) {

		$options = '';

		if ( is_array( $tags ) && ! empty( $tags ) ) {
			foreach ( $tags as $tag ) {
				$options .= wp_sprintf( '<option value="%1$s" selected>%2$s XYZ</option>', $tag->term_id, $tag->name );
			}
		}

		return wp_kses(
			$options,
			[
				'option' => [
					'value' => [],
					'selected' => [],
				],
			]
		);
	}

	/**
	 * Process AJAX request to save event tags.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function ajax_save_tags() {

		// Check nonce.
		if ( ! check_ajax_referer( 'sugar-calendar-events-tags-nonce', 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Security check failed.', 'sugar-calendar-lite' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( esc_html__( 'You do not have permission to edit events.', 'sugar-calendar-lite' ) );
		}

		// Get event ID.
		$event_ids = isset( $_POST['events'] ) ? array_map( 'absint', $_POST['events'] ) : [];

		if ( empty( $event_ids ) ) {
			wp_send_json_error( esc_html__( 'No event specified.', 'sugar-calendar-lite' ) );
		}

		// Get tags data.
		$tags_data = isset( $_POST['tags'] ) ? array_map( [ $this, 'ajax_sanitize_tag' ], $_POST['tags'] ) : [];
		$tag_ids   = [];

		// Process tags.
		foreach ( $tags_data as $tag ) {

			if ( isset( $tag['value'] ) && is_numeric( $tag['value'] ) ) {

				// Existing tag.
				$tag_ids[] = absint( $tag['value'] );

			} elseif ( isset( $tag['label'] ) ) {

				// New tag.
				$new_tag = wp_insert_term( sanitize_text_field( $tag['label'] ), Helpers::get_tags_taxonomy_id() );

				if ( ! is_wp_error( $new_tag ) ) {
					$tag_ids[] = $new_tag['term_id'];
				}
			}
		}

		// Update event tags.
		foreach ( $event_ids as $event_id ) {
			wp_set_object_terms( $event_id, $tag_ids, Helpers::get_tags_taxonomy_id() );
		}

		// Get updated tags.
		$tags = [];

		foreach ( $event_ids as $event_id ) {

			$terms = get_the_terms( $event_id, Helpers::get_tags_taxonomy_id() );

			if ( is_array( $terms ) && ! empty( $terms ) ) {

				foreach ( $terms as $term ) {

					$tags[ $term->term_id ] = $term;
				}
			}
		}

		// Get tag data for response.
		$tags_html    = '';
		$tags_ids     = [];
		$tags_options = '';

		if ( is_array( $tags ) && ! empty( $tags ) ) {

			$tags_links = [];

			foreach ( $tags as $tag_id => $tag ) {
				$tags_links[]  = wp_sprintf( '<a href="%1$s">%2$s</a>', get_term_link( $tag ), $tag->name );
				$tags_ids[]    = $tag_id;
				$tags_options .= wp_sprintf( '<option value="%1$s" selected>%2$s</option>', $tag_id, $tag->name );
			}

			$tags_html = implode( ', ', $tags_links );

		} else {
			$tags_html = '—';
		}

		// Filters html.
		$tags_html = wp_kses(
			$tags_html,
			[
				'a' => [
					'href' => [],
				],
			]
		);

		// Update all tags choices.
		$all_tags_choices = $this->get_all_tags_choices();

		// Send response.
		wp_send_json_success(
			[
				'tags_links'       => $tags_html,
				'tags_ids'         => implode( ',', array_filter( $tags_ids ) ),
				'tags_options'     => $tags_options,
				'all_tags_choices' => $all_tags_choices,
			]
		);
	}

	/**
	 * Sanitize tag data.
	 *
	 * @since 3.7.0
	 *
	 * @param array $tag Tag data.
	 *
	 * @return array
	 */
	public function ajax_sanitize_tag( $tag ) {

		$tag = [
			'value' => isset( $tag['value'] ) ? sanitize_text_field( $tag['value'] ) : '',
			'label' => isset( $tag['label'] ) ? sanitize_text_field( $tag['label'] ) : '',
		];

		return $tag;
	}
}
