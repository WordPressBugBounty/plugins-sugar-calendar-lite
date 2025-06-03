<?php

namespace Sugar_Calendar\Features\Tags\Admin\Pages;

use stdClass;
use Sugar_Calendar\Helpers\UI;
use WP_Term;
use Sugar_Calendar\Features\Tags\Admin\Pages\TagAbstract;
use Sugar_Calendar\Features\Tags\Common\Helpers;
/**
 * New Tag page.
 *
 * @since 3.7.0
 */
class TagNew extends TagAbstract {

	/**
	 * Current tag.
	 *
	 * @since 3.7.0
	 *
	 * @var WP_Term
	 */
	protected $term;

	/**
	 * Page slug.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		return 'sugar-calendar-tag-new';
	}

	/**
	 * Page label.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return Helpers::get_tags_taxonomy_labels( 'add_new_item' );
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
		 * Filters the capability required to view the Add New Tag page.
		 *
		 * @since 3.7.0
		 *
		 * @param string $capability Capability required to view the tags page.
		 */
		return apply_filters( 'sugar_calendar_features_tags_admin_pages_tag_new_capability', 'edit_events' );
	}

	/**
	 * Initialize the page.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function init() {

		$this->term = new WP_Term( new stdClass() );
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
		<input type="hidden" name="action" value="add-tag"/>
		<input type="hidden" name="taxonomy" value="<?php echo esc_attr( $taxonomy ); ?>"/>
		<?php wp_nonce_field( 'add-tag', '_wpnonce_add-tag' ); ?>
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
				'name'        => 'tag-name',
				'id'          => 'tag-name',
				'value'       => $this->term->name,
				'placeholder' => Helpers::get_tags_taxonomy_labels( 'new_item_name' ),
				'required'    => true,
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
		 * Fires after the Add Tag form fields are displayed.
		 *
		 * @since 3.7.0
		 *
		 * @param WP_Term $term     Current taxonomy term object.
		 * @param string  $taxonomy Current taxonomy slug.
		 */
		do_action( 'sugar_calendar_features_tags_admin_pages_tag_new_form_fields', $this->term, $taxonomy );
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
					'text' => Helpers::get_tags_taxonomy_labels( 'add_new_item' ),
				]
			);
			?>
		</p>
		<?php
	}
}
