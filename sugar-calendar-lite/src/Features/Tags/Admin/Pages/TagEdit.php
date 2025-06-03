<?php

namespace Sugar_Calendar\Features\Tags\Admin\Pages;

use Sugar_Calendar\Helpers\UI;
use WP_Term;
use Sugar_Calendar\Features\Tags\Common\Helpers;

/**
 * Edit Tag page.
 *
 * @since 3.7.0
 */
class TagEdit extends TagAbstract {

	/**
	 * Page slug.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		return 'sugar-calendar-tag-edit';
	}

	/**
	 * Page label.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return Helpers::get_tags_taxonomy_labels( 'edit_item' );
	}

	/**
	 * Page capability.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public static function get_capability() {

		/**
		 * Filters the capability required to view the Edit Tag page.
		 *
		 * @since 3.7.0
		 *
		 * @param string $capability Capability required to view the tags page.
		 */
		return apply_filters( 'sugar_calendar_features_tags_admin_pages_tag_edit_capability', 'edit_events' );
	}

	/**
	 * Initialize the page.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function init() {

		$taxonomy = Helpers::get_tags_taxonomy_id();

		if ( isset( $_GET['tag_id'] ) ) {
			$tag_id     = absint( $_GET['tag_id'] );
			$this->term = get_term( $tag_id, $taxonomy, OBJECT, 'edit' );
		}

		if ( ! $this->term instanceof WP_Term ) {
			wp_die( esc_html__( 'You attempted to edit an item that does not exist. Perhaps it was deleted?', 'sugar-calendar-lite' ) );
		}
	}

	/**
	 * Output the form hidden fields.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function form_hidden_fields() {

		$taxonomy = Helpers::get_tags_taxonomy_id();
		?>
        <input type="hidden" name="action" value="editedtag"/>
        <input type="hidden" name="tag_ID" value="<?php echo esc_attr( $this->term->term_id ); ?>"/>
        <input type="hidden" name="taxonomy" value="<?php echo esc_attr( $taxonomy ); ?>"/>
		<?php wp_nonce_field( 'update-tag_' . $this->term->term_id ); ?>
		<?php
	}

	/**
	 * Output the form event name field.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function form_name_field() {

		UI::text_input(
			[
				'name'        => 'name',
				'id'          => 'name',
				'value'       => $this->term->name,
				'placeholder' => Helpers::get_tags_taxonomy_labels( 'new_item_name' ),
			],
			true
		);
	}

	/**
	 * Output additional form fields.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function form_additional_fields() {

		$taxonomy = Helpers::get_tags_taxonomy_id();

		/**
		 * Fires after the Edit Tag form fields are displayed.
		 *
		 * @since 3.7.0
		 *
		 * @param WP_Term $term     Current taxonomy term object.
		 * @param string  $taxonomy Current taxonomy slug.
		 */
		do_action( 'sugar_calendar_features_tags_admin_pages_tag_edit_form_fields', $this->term, $taxonomy );
	}

	/**
	 * Output the form submit button.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function form_submit() {

		?>
        <p class="submit">
			<?php
			UI::button(
				[
					'name' => 'submit',
					'text' => Helpers::get_tags_taxonomy_labels( 'update_item' ),
				]
			);
			?>
        </p>
		<?php
	}
}
