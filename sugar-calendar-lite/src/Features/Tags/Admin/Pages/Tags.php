<?php

namespace Sugar_Calendar\Features\Tags\Admin\Pages;

use WP_Term;

use Sugar_Calendar\Admin\PageAbstract;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;

use Sugar_Calendar\Features\Tags\Common\Helpers;
use Sugar_Calendar\Features\Tags\Admin\Pages\TagNew;
use Sugar_Calendar\Helpers as BaseHelpers;

/**
 * Tags page.
 *
 * @since 3.7.0
 */
class Tags extends PageAbstract {

	/**
	 * Page slug.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		$taxonomy = Helpers::get_tags_taxonomy_id();

		return "edit-tags.php?taxonomy={$taxonomy}";
	}

	/**
	 * Page label.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return Helpers::get_tags_taxonomy_labels( 'name' );
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

		return static::get_slug();
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
		 * Filters the capability required to view the tags page.
		 *
		 * @since 3.7.0
		 *
		 * @param string $capability Capability required to view the tags page.
		 */
		return apply_filters( 'sugar_calendar_admin_pages_tags_get_capability', 'edit_events' );
	}

	/**
	 * Register page hooks.
	 *
	 * @since 3.7.0
	 */
	public function hooks() {

		// Enqueue assets.
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );

		// Remove default wp screen options.
		add_filter( 'screen_options_show_screen', '__return_false' );

		// Display the Sugar Calendar subheader.
		add_action( 'in_admin_header', [ $this, 'display_admin_subheader' ] );

		// Filter the edit link for tag entries.
		add_filter( 'get_edit_term_link', [ $this, 'get_edit_term_link' ], 10, 3 );

		// Redirect to newly created tag.
		add_filter( 'redirect_term_location', [ $this, 'redirect_after_save' ], 10, 2 );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style(
			'sugar-calendar-admin-calendars',
			SC_PLUGIN_ASSETS_URL . 'css/admin-calendars' . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);
	}

	/**
	 * Display the subheader.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function display_admin_subheader() {

		?>
		<div class="sugar-calendar-admin-subheader">
			<h4><?php echo esc_html( Helpers::get_tags_taxonomy_labels( 'name' ) ); ?></h4>

			<?php
			UI::button(
				[
					'text'  => Helpers::get_tags_taxonomy_labels( 'add_new_item' ),
					'size'  => 'sm',
					'class' => 'sugar-calendar-btn-new-item',
					'link'  => TagNew::get_url(),
				]
			);
			?>
		</div>

		<?php
		/**
		 * Runs before the page content is displayed.
		 *
		 * @since 3.7.0
		 */
		do_action( 'sugar_calendar_admin_page_before' );
	}

	/**
	 * Get list of tag update messages.
	 *
	 * @since 3.7.0
	 *
	 * @param array $messages Map of messages.
	 *
	 * @return array
	 */
	public function get_tag_updated_messages( $messages ) {

		$taxonomy = Helpers::get_tags_taxonomy_id();

		$messages[ $taxonomy ] = TagAbstract::get_tag_updated_messages();

		return $messages;
	}

	/**
	 * Filter the edit link for tag entries.
	 *
	 * @since 3.7.0
	 *
	 * @param string $location Current link location.
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return mixed|string
	 */
	public function get_edit_term_link( $location, $term_id, $taxonomy ) {

		if ( $taxonomy === Helpers::get_tags_taxonomy_id() ) {
			$location = add_query_arg( 'tag_id', $term_id, TagEdit::get_url() );
		}

		return $location;
	}

	/**
	 * Filter the redirect location after a tag create/update request.
	 *
	 * @since 3.7.0
	 *
	 * @param string  $location Redirect location.
	 * @param WP_Term $taxonomy Current term.
	 *
	 * @return string
	 */
	public function redirect_after_save( $location, $taxonomy ) {

		if (
			! $this->is_valid_taxonomy( $taxonomy )
			||
			! $this->is_valid_request_action()
		) {
			return $location;
		}

		// Defined in edit-tags.php.
		global $ret;

		if ( $this->is_successful_creation( $ret ) ) {

			$location = $this->get_after_success_redirect_url( $location, $ret['term_id'] );

		} elseif ( is_wp_error( $ret ) ) {

			$location = $this->add_preserved_submitted_values( $location );
		}

		return $location;
	}

	/**
	 * Check if the taxonomy is valid.
	 *
	 * @since 3.7.0
	 *
	 * @param WP_Taxonomy $taxonomy Taxonomy object.
	 *
	 * @return boolean
	 */
	private function is_valid_taxonomy( $taxonomy ) {

		return $taxonomy->name === Helpers::get_tags_taxonomy_id();
	}

	/**
	 * Check if the request action is valid.
	 *
	 * @since 3.7.0
	 *
	 * @return boolean
	 */
	private function is_valid_request_action() {

		if ( ! isset( $_REQUEST['action'] ) ) {
			return false;
		}

		$valid_actions = [ 'add-tag', 'editedtag' ];

		return in_array( $_REQUEST['action'], $valid_actions, true );
	}

	/**
	 * Check if the creation was successful and not an update.
	 *
	 * @since 3.7.0
	 *
	 * @param mixed $ret Value returned from wp_insert_term/wp_update_term.
	 *
	 * @return boolean
	 */
	private function is_successful_creation( $ret ) {

		// WP_Error or null.
		if ( ! is_array( $ret ) ) {
			return false;
		}

		// Not the expected action.
		if ( ! isset( $_REQUEST['action'] ) || $_REQUEST['action'] !== 'add-tag' ) {
			return false;
		}

		// No term ID.
		if ( ! isset( $ret['term_id'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the redirect URL after a successful create/update.
	 *
	 * @since 3.7.0
	 *
	 * @param string $location Current redirect location.
	 * @param int    $term_id  Term ID.
	 *
	 * @return string
	 */
	private function get_after_success_redirect_url( $location, $term_id ) {

		// In case of successfully created, we redirect to TagEdit.
		$tax_name   = Helpers::get_tags_taxonomy_id();
		$edit_url   = add_query_arg( 'tag_id', $term_id, TagEdit::get_url() );
		$edit_url   = add_query_arg( 'message', 1, $edit_url );
		$post_types = get_taxonomy( $tax_name )->object_type;

		// Add the post_type arg to the URL.
		if ( $post_types && isset( $_REQUEST['post_type'] ) && in_array( sanitize_key( $_REQUEST['post_type'] ), $post_types, true ) ) {
			$edit_url = add_query_arg( 'post_type', sanitize_key( $_REQUEST['post_type'] ), $edit_url );
		}

		// And finally we redirect.
		return $edit_url;
	}

	/**
	 * Add preserved submitted values to the redirect URL.
	 *
	 * @since 3.7.0
	 *
	 * @param string $location Current redirect location.
	 *
	 * @return string
	 */
	private function add_preserved_submitted_values( $location ) {

		// Preserving submitted values.
		if ( isset( $_REQUEST['tag-name'] ) && is_string( $_REQUEST['tag-name'] ) ) {
			$location = add_query_arg( 'tag-name', urlencode( sanitize_text_field( wp_unslash( $_REQUEST['tag-name'] ) ) ), $location );
		}

		if ( isset( $_REQUEST['slug'] ) && is_string( $_REQUEST['slug'] ) ) {
			$location = add_query_arg( 'slug', urlencode( sanitize_text_field( wp_unslash( $_REQUEST['slug'] ) ) ), $location );
		}

		if ( isset( $_REQUEST['description'] ) && is_string( $_REQUEST['description'] ) ) {
			$location = add_query_arg( 'description', urlencode( sanitize_textarea_field( wp_unslash( $_REQUEST['description'] ) ) ), $location );
		}

		return $location;
	}
}
