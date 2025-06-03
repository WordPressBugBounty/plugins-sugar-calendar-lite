<?php

namespace Sugar_Calendar\Features\Tags\Admin\Pages;

use Sugar_Calendar\Admin\PageAbstract;
use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use WP_Term;

use Sugar_Calendar\Features\Tags\Common\Helpers;
use Sugar_Calendar\Features\Tags\Admin\Pages\Tags;

/**
 * Abstract Tag page.
 *
 * @since 3.7.0
 */
abstract class TagAbstract extends PageAbstract {

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
	abstract public static function get_slug();

	/**
	 * Page label.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	abstract public static function get_label();

	/**
	 * Initialize the page.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	abstract public function init();

	/**
	 * Output the form hidden fields.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	abstract public function form_hidden_fields();

	/**
	 * Output the form event name field.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	abstract public function form_name_field();

	/**
	 * Output additional form fields.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	abstract public function form_additional_fields();

	/**
	 * Output the form submit button.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	abstract public function form_submit();

	/**
	 * Whether the page appears in menus.
	 *
	 * @since 3.7.0
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
	 * @since 3.7.0
	 *
	 * @return null|string;
	 */
	public static function highlight_menu_item() {

		return Tags::get_slug();
	}

	/**
	 * Register page hooks.
	 *
	 * @since 3.7.0
	 */
	public function hooks() {

		add_action( 'admin_init', [ $this, 'init' ] );
		add_filter( 'screen_options_show_screen', '__return_false' );
		add_filter( 'term_updated_messages', [ $this, 'tag_updated_messages' ] );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'sugar_calendar_tags_admin_pages_add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Filter notice messages after a create/update request.
	 *
	 * @since 3.7.0
	 *
	 * @param array $messages Map of messages.
	 *
	 * @return mixed
	 */
	public function tag_updated_messages( $messages ) {

		$taxonomy = Helpers::get_tags_taxonomy_id();

		$messages[ $taxonomy ] = self::get_tag_updated_messages();

		return $messages;
	}

	/**
	 * Get list of tag update messages.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	public static function get_tag_updated_messages() {

		return [
			0 => '',
			1 => __( 'Tag added.', 'sugar-calendar-lite' ),
			2 => __( 'Tag deleted.', 'sugar-calendar-lite' ),
			3 => __( 'Tag updated.', 'sugar-calendar-lite' ),
			4 => __( 'Tag not added.', 'sugar-calendar-lite' ),
			5 => __( 'Tag not updated.', 'sugar-calendar-lite' ),
			6 => __( 'Tags deleted.', 'sugar-calendar-lite' ),
		];
	}

	/**
	 * Output create/update messages.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function admin_notices() {

		$taxonomy = Helpers::get_tags_taxonomy_id();
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
	 * @since 3.7.0
	 *
	 * @param string $slug Current page slug.
	 *
	 * @return void
	 */
	public function add_meta_boxes( $slug ) {

		add_meta_box(
			'tag_settings',
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
	 * @since 3.7.0
	 */
	public function display() {

		$form_url = WP::admin_url( 'edit-tags.php' );
		?>
        <div class="sugar-calendar-admin-subheader sugar-calendar-admin-subheader--details">

            <h4><?php echo esc_html( static::get_label() ); ?></h4>

			<?php
			UI::button(
				[
					'text'  => Helpers::get_tags_taxonomy_labels( 'back_to_items' ),
					'size'  => 'sm',
					'class' => 'sugar-calendar-btn-new-item',
					'link'  => admin_url( Tags::get_slug() ),
				]
			);
			?>
        </div>

        <div id="sugar-calendar-calendar" class="wrap sugar-calendar-admin-wrap">

            <div class="sugar-calendar-admin-content">

                <h1 class="screen-reader-text"><?php echo esc_html( static::get_label() ); ?></h1>

                <form method="post" action="<?php echo esc_url( $form_url ); ?>" class="sugar-calendar-calendar-form">

					<?php static::form_hidden_fields(); ?>

					<?php static::form_name_field(); ?>

					<?php
					/**
					 * Fires when adding meta boxes on the tag add/edit page.
					 *
					 * @since 3.7.0
					 *
					 * @param string $slug The current page slug.
					 * @param null   $null Null value.
					 */
					do_action( 'sugar_calendar_tags_admin_pages_add_meta_boxes', static::get_slug(), null );
					?>

					<?php do_meta_boxes( static::get_slug(), 'normal', null ); ?>

					<?php static::form_submit(); ?>

                </form>
            </div>
        </div>

		<?php
	}

	/**
	 * Output tag settings fields.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function display_settings() {

		$taxonomy = Helpers::get_tags_taxonomy_id();

		?>
        <div class="sugar-calendar-metabox__field-row">
            <label for="tag-slug"><?php esc_html_e( 'Slug', 'sugar-calendar-lite' ); ?></label>
            <div class="sugar-calendar-metabox__field">

				<?php
				UI::text_input(
					[
						'name'        => 'slug',
						'id'          => 'tag-slug',
						'value'       => $this->term->slug ?? '',
						'description' => esc_html__( 'The "slug" is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.', 'sugar-calendar-lite' ),
						'preserved'   => true,
					],
					true
				);
				?>

            </div>
        </div>
        <div class="sugar-calendar-metabox__field-row">
            <label for="tag-description"><?php esc_html_e( 'Description', 'sugar-calendar-lite' ); ?></label>
            <div class="sugar-calendar-metabox__field">
				<?php
				UI::textarea(
					[
						'name'        => 'description',
						'id'          => 'tag-description',
						'value'       => $this->term->description ?? '',
						'description' => esc_html__( 'The description is not prominent by default; however, some themes may show it.', 'sugar-calendar-lite' ),
						'preserved'   => true,
					],
					true
				);
				?>
            </div>
        </div>

		<?php static::form_additional_fields(); ?>

		<?php
		/**
		 * Fires when displaying tag edit form fields.
		 *
		 * @since 3.7.0
		 *
		 * @param WP_Term $term     Current taxonomy term object.
		 * @param string  $taxonomy Current taxonomy slug.
		 */
		do_action( 'sugar_calendar_features_tags_admin_pages_tag_abstract_edit_form', $this->term, $taxonomy );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style( 'sugar-calendar-admin-calendar' );

		wp_enqueue_style(
			'sugar-calendar-admin-tags',
			SC_PLUGIN_ASSETS_URL . 'css/admin-tags' . WP::asset_min() . '.css',
			[ 'sugar-calendar-admin-settings', 'sugar-calendar-admin-education' ],
			BaseHelpers::get_asset_version()
		);
	}
}
