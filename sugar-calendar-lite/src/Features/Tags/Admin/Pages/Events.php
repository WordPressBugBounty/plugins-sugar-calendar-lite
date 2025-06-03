<?php

namespace Sugar_Calendar\Features\Tags\Admin\Pages;

use Sugar_Calendar\Features\Tags\Admin\Pages\EventAbstract;
use Sugar_Calendar\Features\Tags\Admin\Pages\Tags;
use Sugar_Calendar\Features\Tags\Common\Helpers;

/**
 * Admin hooks for the Event page.
 *
 * @since 3.7.0
 */
class Events extends EventAbstract {

	/**
	 * Modify dropdown taxonomies to exclude tags.
	 *
	 * @since 3.7.0
	 *
	 * @param array $taxonomies Taxonomies.
	 *
	 * @return array
	 */
	public function modify_dropdown_taxonomies( $taxonomies ) {

		// Unset tags.
		unset( $taxonomies[ Helpers::get_tags_taxonomy_id() ] );

		return $taxonomies;
	}

	/**
	 * Add the Tags tablenav UI.
	 *
	 * @since 3.7.0
	 *
	 * @param Base $table Table instance.
	 */
	public function add_tags_tablenav( $table ) {

		// Get edit tags URL.
		$edit_tags_url = admin_url( Tags::get_slug() );

		// Get tags for filter dropdown.
		$all_tags = get_terms(
			[
				'taxonomy'   => Helpers::get_tags_taxonomy_id(),
				'hide_empty' => false,
			]
		);

		// Get currently selected tag (if any).
		$current_tag       = isset( $_GET['sc_event_tags'] ) ? sanitize_text_field( wp_unslash( $_GET['sc_event_tags'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_tag_array = [];

		// If no tag is selected, return.
		if ( ! empty( $current_tag ) ) {

			$current_tag_array = explode( ',', $current_tag );

			// Validate term IDs.
			$current_tag_array = Helpers::validate_tags_term_ids( $current_tag_array );
		}

		?>
		<div class="choicesjs-select-wrap sugar-calendar-tags-filter">
			<select id="sugar-calendar-tags-filter" multiple>
				<?php foreach ( $all_tags as $tag ) : ?>
					<option value="<?php echo esc_attr( $tag->term_id ); ?>" <?php selected( in_array( $tag->term_id, $current_tag_array, true ) ); ?>>
						<?php echo esc_html( $tag->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<button type="button" id="sugar-calendar-tags-filter-button" class="button button-secondary sugar-calendar-tags-filter-button">
				<?php esc_html_e( 'Filter', 'sugar-calendar-lite' ); ?>
			</button>
		</div>
		<a href="<?php echo esc_url( $edit_tags_url ); ?>" class="button">
			<?php echo esc_html( Helpers::get_tags_taxonomy_labels( 'manage_tags' ) ); ?>
		</a>
		<?php
	}

	/**
	 * Add tags information to event tooltips in admin.
	 *
	 * @since 3.7.0
	 *
	 * @param array  $pointer_meta Pointer meta information.
	 * @param object $event        Event object.
	 *
	 * @return array
	 */
	public function add_tags_in_admin_tooltips( $pointer_meta, $event ) {

		// Get event tags.
		$tags = get_the_terms( (int) $event->object_id, Helpers::get_tags_taxonomy_id() );

		// Only proceed if we have tags.
		if ( is_array( $tags ) && ! empty( $tags ) ) {
			// Get tag names array.
			$tag_names = wp_list_pluck( $tags, 'name' );

			// Format tags as comma-separated list.
			$tags_list = implode( ', ', $tag_names );

			// Add tags section to the tooltip.
			$pointer_meta['tags_title'] = '<strong>' . Helpers::get_tags_taxonomy_labels( 'name' ) . '</strong>';
			$pointer_meta['tags']       = '<span>' . esc_html( $tags_list ) . '</span>';
		}

		return $pointer_meta;
	}

	/**
	 * Check if current screen is in list mode.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	public function is_list_mode() {

		// Get current screen mode.
		$mode = isset( $_GET['mode'] ) ? sanitize_text_field( wp_unslash( $_GET['mode'] ) ) : '';

		// Return true if mode is 'list'.
		return ! empty( $mode ) && $mode === 'list';
	}

	/**
	 * Check if current screen is editable.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	public function is_form_editable() {

		// Get current screen status.
		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

		// Check edit permissions.
		$can_edit = current_user_can( 'edit_posts' );

		// If it is set, and is not trash, return true if mode is 'list'.
		return $can_edit && ( empty( $status ) || $status !== 'trash' ) && $this->is_list_mode();
	}

	/**
	 * Add tags column select field.
	 *
	 * @since 3.7.0
	 *
	 * @param string $contents Column contents.
	 * @param object $item     Current item.
	 *
	 * @return string
	 */
	public function add_tags_column_html( $contents, $item ) {

		$tags = get_the_terms( (int) $item->object_id, Helpers::get_tags_taxonomy_id() );

		$contents  = $this->get_tags_links( $tags, (int) $item->object_id );
		$contents .= $this->get_tags_form( $tags, 'sc_event_tags[]', true );

		return $contents;
	}

	/**
	 * Get tags link.
	 *
	 * @since 3.7.0
	 *
	 * @param array $tags    Tags array.
	 * @param int   $item_id Object ID.
	 *
	 * @return string
	 */
	public function get_tags_links( $tags, $item_id = 0 ) {

		$tags_html  = '';
		$tags_links = [];
		$tags_ids   = [];

		// Get base URL for tag links (current page).
		$base_url = '';

		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$base_url = remove_query_arg( 'tags', esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		}

		// List of tags in links.
		if ( is_array( $tags ) && ! empty( $tags ) ) {
			foreach ( $tags as $tag ) {
				// Create link to current page with tag filter.
				$tag_url      = add_query_arg( 'sc_event_tags', $tag->term_id, $base_url );
				$tags_links[] = sprintf( '<a href="%s">%s</a>', esc_url( $tag_url ), $tag->name );
				$tags_ids[]   = $tag->term_id;
			}

			$tags_html = implode( ', ', $tags_links );
		} else {
			$tags_html = '—';
		}

		// Edit link.
		$edit_link = sprintf(
			'<a href="#" class="sugar-calendar-column-tags-edit-link">%s</a>',
			Helpers::get_tags_taxonomy_labels( 'edit_item' )
		);

		return sprintf(
			'<div class="sugar-calendar-column-tags-links" data-event-id="%1$d" data-is-editable="%2$d" data-tags="%3$s">
				<div class="sugar-calendar-column-tags-links-list">%4$s</div>
				%5$s
			</div>',
			absint( $item_id ),
			$this->is_form_editable() ? 1 : 0,
			esc_attr( implode( ',', array_filter( $tags_ids ) ) ),
			$tags_html,
			$this->is_form_editable() ? $edit_link : ''
		);
	}

	/**
	 * Get the HTML for the bulk edit tags form.
	 *
	 * @since 3.7.0
	 *
	 * @param Base $table Table instance.
	 */
	public function add_bulk_edit_tags_form( $table ) {

		$column_info = $table->get_column_info();

		$colspan_all   = 1;
		$colspan_title = 0;
		$colspan_tags  = 0;

		if ( isset( $column_info[0] ) ) {

			$columns      = $column_info[0];
			$column_count = count( $columns );

			// Use column count if it's greater than 1.
			if ( $column_count > 1 ) {
				$colspan_all = $column_count;
			}

			// Loop and count the columns.
			foreach ( $columns as $col_name => $col_label ) {

				if (
					$col_name === 'cb'
					||
					$col_name === 'title'
					||
					$col_name === 'start'
					||
					$col_name === 'end'
				) {
					++$colspan_title;
				}

				if (
					$col_name === 'tags'
					||
					$col_name === 'duration'
					||
					$col_name === 'repeat'
				) {
					++$colspan_tags;
				}
			}
		}

		?>
		<tr class="sugar-calendar-bulk-edit-tags-row sugar-calendar-hidden">
			<td colspan="<?php echo esc_attr( $colspan_title ); ?>">
				<div class="sugar-calendar-bulk-edit-box">
					<div class="sugar-calendar-bulk-edit-tags-field-wrapper sugar-calendar-bulk-edit-tags-field-wrapper--events">
						<div class="choicesjs-select-wrap">
							<div class="sugar-calendar-column-tags-form-events">
								<select id="sugar-calendar-bulk-edit-tags-events" multiple size="6"></select>
							</div>
						</div>
					</div>
				</div>
			</td>
			<td colspan="<?php echo esc_attr( $colspan_tags ); ?>">
				<div class="sugar-calendar-bulk-edit-box">
					<div class="sugar-calendar-bulk-edit-tags-field-wrapper sugar-calendar-bulk-edit-tags-field-wrapper--terms">
						<div class="choicesjs-select-wrap">
							<div class="sugar-calendar-column-tags-form">
								<select id="sugar-calendar-bulk-edit-tags-terms" multiple></select>
							</div>
						</div>
					</div>
				</div>
			</td>
		</tr>

		<tr class="sugar-calendar-bulk-edit-tags-row sugar-calendar-hidden">
			<td colspan="<?php echo esc_attr( $colspan_all ); ?>">
				<div class="sugar-calendar-bulk-edit-tags-actions">
					<a href="#" class="button button-secondary sugar-calendar-bulk-edit-tags-cancel"><?php esc_html_e( 'Cancel', 'sugar-calendar-lite' ); ?></a>
					<a href="#" class="button button-primary sugar-calendar-bulk-edit-tags-save">
						<i class="sugar-calendar-spinner spinner sugar-calendar-hidden"></i>
						<?php esc_html_e( 'Update', 'sugar-calendar-lite' ); ?>
					</a>
				</div>
			</td>
		</tr>

		<tr class="sugar-calendar-bulk-edit-tags-row sugar-calendar-bulk-edit-tags-row--message sugar-calendar-hidden">
			<td colspan="<?php echo esc_attr( $colspan_all ); ?>">
				<div class="sugar-calendar-bulk-edit-tags-message">
					<div class="sugar-calendar-message"></div>
				</div>
			</td>
		</tr>
		<?php
	}
}
