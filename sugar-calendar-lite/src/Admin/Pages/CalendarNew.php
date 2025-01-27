<?php

namespace Sugar_Calendar\Admin\Pages;

use stdClass;
use Sugar_Calendar\Helpers\UI;
use WP_Term;

/**
 * New Calendar page.
 *
 * @since 3.0.0
 */
class CalendarNew extends CalendarAbstract {

	/**
	 * Current calendar.
	 *
	 * @since 3.0.0
	 *
	 * @var WP_Term
	 */
	protected $term;

	/**
	 * Page slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		return 'sugar-calendar-calendar-new';
	}

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Add New Calendar', 'sugar-calendar-lite' );
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
		 * Filters the capability required to view the Add New Calendar page.
		 *
		 * @since 3.0.1
		 *
		 * @param string $capability Capability required to view the calendars page.
		 */
		return apply_filters( 'sugar_calendar_admin_pages_calendar_new_get_capability', 'edit_events' );
	}

	/**
	 * Initialize the page.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function init() {

		$this->term = new WP_Term( new stdClass() );
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
        <input type="hidden" name="action" value="add-tag"/>
        <input type="hidden" name="taxonomy" value="<?php echo esc_attr( $taxonomy ); ?>"/>
		<?php wp_nonce_field( 'add-tag', '_wpnonce_add-tag' ); ?>
		<?php
	}

	/**
	 * Output the form event name field.
	 *
	 * @since 3.0.0
	 * @since 3.4.0 Added the `required` attribute.
	 *
	 * @return void
	 */
	public function form_name_field() {

		UI::text_input(
			[
				'name'        => 'tag-name',
				'id'          => 'tag-name',
				'value'       => $this->term->name,
				'placeholder' => esc_html__( 'Name this Calendar', 'sugar-calendar-lite' ),
				'required'    => true,
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

		do_action( "{$taxonomy}_add_form_fields", $this->term, $taxonomy ); // phpcs:ignore WPForms.Comments.PHPDocHooks.RequiredHookDocumentation,WPForms.PHP.ValidateHooks.InvalidHookName
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
					'text' => esc_html__( 'Add New Calendar', 'sugar-calendar-lite' ),
				]
			);
			?>
        </p>
		<?php
	}
}
