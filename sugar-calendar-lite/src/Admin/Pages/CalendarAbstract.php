<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\PageAbstract;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use WP_Term;

/**
 * Abstract Calendar page.
 *
 * @since 3.0.0
 */
abstract class CalendarAbstract extends PageAbstract {

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
	abstract public static function get_slug();

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	abstract public static function get_label();

	/**
	 * Initialize the page.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	abstract public function init();

	/**
	 * Output the form hidden fields.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	abstract public function form_hidden_fields();

	/**
	 * Output the form event name field.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	abstract public function form_name_field();

	/**
	 * Output additional form fields.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	abstract public function form_additional_fields();

	/**
	 * Output the form submit button.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	abstract public function form_submit();

	/**
	 * Whether the page appears in menus.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public static function has_menu_item() {

		return false;
	}

	/**
	 * Which menu item to highlight
	 * if the page doesn't appear in dashboard menu.
	 *
	 * @since 3.0.0
	 *
	 * @return null|string;
	 */
	public static function highlight_menu_item() {

		return Calendars::get_slug();
	}

	/**
	 * Register page hooks.
	 *
	 * @since 3.0.0
	 */
	public function hooks() {

		add_action( 'admin_init', [ $this, 'init' ] );
		add_filter( 'screen_options_show_screen', '__return_false' );
		add_filter( 'term_updated_messages', [ $this, 'calendar_updated_messages' ] );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Filter notice messages after a create/update request.
	 *
	 * @since 3.0.0
	 * @since 3.4.0 Moved list to a static method.
	 *
	 * @param array $messages Map of messages.
	 *
	 * @return mixed
	 */
	public function calendar_updated_messages( $messages ) {

		$taxonomy = sugar_calendar_get_calendar_taxonomy_id();

		$messages[ $taxonomy ] = self::get_calendar_updated_messages();

		return $messages;
	}

	/**
	 * Get list of calendar update messages.
	 *
	 * @since 3.4.0
	 *
	 * @return array
	 */
	public static function get_calendar_updated_messages() {

		return [
			0 => '',
			1 => __( 'Calendar added.', 'sugar-calendar-lite' ),
			2 => __( 'Calendar deleted.', 'sugar-calendar-lite' ),
			3 => __( 'Calendar updated.', 'sugar-calendar-lite' ),
			4 => __( 'Calendar not added.', 'sugar-calendar-lite' ),
			5 => __( 'Calendar not updated.', 'sugar-calendar-lite' ),
			6 => __( 'Calendars deleted.', 'sugar-calendar-lite' ),
		];
	}

	/**
	 * Output create/update messages.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function admin_notices() {

		$taxonomy = sugar_calendar_get_calendar_taxonomy_id();
		$message  = null;

		require_once ABSPATH . 'wp-admin/includes/edit-tag-messages.php';

		if ( $message !== false ) {
			$class = ( isset( $_REQUEST['error'] ) ) ? WP::ADMIN_NOTICE_ERROR : WP::ADMIN_NOTICE_SUCCESS;

			WP::add_admin_notice( $message, $class );
		}

		WP::display_admin_notices();
	}

	/**
	 * Register metaboxes.
	 *
	 * @since 3.0.0
	 *
	 * @param string $slug Current page slug.
	 *
	 * @return void
	 */
	public function add_meta_boxes( $slug ) {

		add_meta_box(
			'calendar_settings',
			esc_html__( 'Options', 'sugar-calendar-lite' ),
			[ $this, 'display_settings' ],
			static::get_slug(),
			'normal',
			'high'
		);
	}

	/**
	 * Display page.
	 *
	 * @since 3.0.0
	 */
	public function display() {

		$form_url = WP::admin_url( 'edit-tags.php' );
		?>
        <div class="sugar-calendar-admin-subheader">
            <h4><?php echo esc_html( static::get_label() ); ?></h4>
        </div>

        <div id="sugar-calendar-calendar" class="wrap sugar-calendar-admin-wrap">

            <div class="sugar-calendar-admin-content">

                <h1 class="screen-reader-text"><?php echo esc_html( static::get_label() ); ?></h1>

                <form method="post" action="<?php echo esc_url( $form_url ); ?>" class="sugar-calendar-calendar-form">

					<?php static::form_hidden_fields(); ?>

					<?php static::form_name_field(); ?>

					<?php
					do_action( 'add_meta_boxes', static::get_slug(), null ); // phpcs:ignore WPForms.Comments.PHPDocHooks.RequiredHookDocumentation,WPForms.PHP.ValidateHooks.InvalidHookName
					?>

					<?php do_meta_boxes( static::get_slug(), 'normal', null ); ?>

					<?php static::form_submit(); ?>

                </form>
            </div>
        </div>

		<?php
	}

	/**
	 * Output calendar settings fields.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function display_settings() {

		?>
        <div class="sugar-calendar-metabox__field-row">
            <label for="tag-slug"><?php esc_html_e( 'Slug', 'sugar-calendar-lite' ); ?></label>
            <div class="sugar-calendar-metabox__field">

				<?php
				UI::text_input(
					[
						'name'        => 'slug',
						'id'          => 'tag-slug',
						'value'       => $this->term->slug,
						'description' => esc_html__( 'The “slug” is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.', 'sugar-calendar-lite' ),
						'preserved'   => true,
					],
					true
				);
				?>

            </div>
        </div>
        <div class="sugar-calendar-metabox__field-row">
            <label for="parent"><?php esc_html_e( 'Parent Calendar', 'sugar-calendar-lite' ); ?></label>
            <div class="sugar-calendar-metabox__field">

				<?php
				$tax  = get_taxonomy( sugar_calendar_get_calendar_taxonomy_id() );
				$args = [
					'taxonomy'         => $tax->name,
					'hierarchical'     => true,
					'selected'         => $this->term->parent,
					'exclude_tree'     => $this->term->term_id,
					'id'               => 'parent',
					'name'             => 'parent',
					'hide_empty'       => false,
					'orderby'          => 'name',
					'show_option_none' => __( 'None', 'sugar-calendar-lite' ),
					'description'      => $tax->labels->parent_field_description,
					'preserved'        => true,
				];

				UI::calendar_dropdown_control( $args, true );
				?>

            </div>
        </div>
        <div class="sugar-calendar-metabox__field-row">
            <label for="parent"><?php esc_html_e( 'Description', 'sugar-calendar-lite' ); ?></label>
            <div class="sugar-calendar-metabox__field">
				<?php
				UI::textarea(
					[
						'name'        => 'description',
						'id'          => 'tag-description',
						'value'       => $this->term->description,
						'description' => $tax->labels->desc_field_description,
						'preserved'   => true,
					],
					true
				);
				?>
            </div>
        </div>


		<?php static::form_additional_fields(); ?>

		<?php
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style( 'sugar-calendar-admin-calendar' );

		wp_enqueue_script( 'sugar-calendar-admin-calendar' );

		wp_localize_script(
			'sugar-calendar-admin-calendar',
			'sugar_calendar_admin_calendar',
			[
				'palette' => [
					'#fe9e68',
					'#ff7368',
					'#df5b9a',
					'#8659c2',
					'#5685bd',
					'#4bb9a7',
					'#57d466',
					'#ffc469',
				],
			]
		);
	}
}
