<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Helpers\UI;
use WP_Term;

/**
 * Edit Calendar page.
 *
 * @since 3.0.0
 */
class CalendarEdit extends CalendarAbstract {

	/**
	 * Page slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		return 'sugar-calendar-calendar-edit';
	}

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Edit Calendar', 'sugar-calendar-lite' );
	}

	/**
	 * Page capability.
	 *
	 * @since 3.0.1
	 *
	 * @return string
	 */
	public static function get_capability() {

		/**
		 * Filters the capability required to view the Edit Calendar page.
		 *
		 * @since 3.0.1
		 *
		 * @param string $capability Capability required to view the calendars page.
		 */
		return apply_filters( 'sugar_calendar_admin_pages_calendar_edit_get_capability', 'edit_events' );
	}

	/**
	 * Initialize the page.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function init() {

		$taxonomy = sugar_calendar_get_calendar_taxonomy_id();

		if ( isset( $_GET['calendar_id'] ) ) {
			$calendar_id = absint( $_GET['calendar_id'] );
			$this->term  = get_term( $calendar_id, $taxonomy, OBJECT, 'edit' );
		}

		if ( ! $this->term instanceof WP_Term ) {
			wp_die( esc_html__( 'You attempted to edit an item that does not exist. Perhaps it was deleted?', 'sugar-calendar-lite' ) );
		}
	}

	/**
	 * Output the form hidden fields.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function form_hidden_fields() {

		$taxonomy = sugar_calendar_get_calendar_taxonomy_id();
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
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function form_name_field() {

		UI::text_input(
			[
				'name'        => 'name',
				'id'          => 'name',
				'value'       => $this->term->name,
				'placeholder' => esc_html__( 'Name this Calendar', 'sugar-calendar-lite' ),
			],
			true
		);
	}

	/**
	 * Output additional form fields.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function form_additional_fields() {

		$taxonomy = sugar_calendar_get_calendar_taxonomy_id();

		do_action( "{$taxonomy}_edit_form_fields", $this->term, $taxonomy ); // phpcs:ignore WPForms.Comments.PHPDocHooks.RequiredHookDocumentation,WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Output the form submit button.
	 *
	 * @since 3.0.0
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
					'text' => esc_html__( 'Update Calendar', 'sugar-calendar-lite' ),
				]
			);
			?>
        </p>
		<?php
	}
}
