<?php

namespace Sugar_Calendar\SetupChecklist;

use Sugar_Calendar\Admin\Pages\SetupChecklist as SetupChecklistPage;
use Sugar_Calendar\Helpers\ActionResult;
use WP_Error;
use WP_Post;

/**
 * The item_action and pages tasks: resolve the clicked row, check its input, and let its owner run it.
 *
 * Each row's click is claimed on `sugar_calendar_setup_checklist_item_action` by the class that owns
 * its service, so this class never names a service; a row nobody claims falls back to its `url`.
 *
 * @since 3.15.0
 */
class ItemActions {

	/**
	 * Authoritative checklist model.
	 *
	 * @since 3.15.0
	 *
	 * @var Checklist
	 */
	private $checklist;

	/**
	 * Submitted-field reader and validator.
	 *
	 * @since 3.15.0
	 *
	 * @var Request
	 */
	private $request;

	/**
	 * Editable saved-page search, the embed row's page selector.
	 *
	 * @since 3.15.0
	 *
	 * @var PageSearch
	 */
	private $page_search;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param Checklist  $checklist   Authoritative checklist model.
	 * @param Request    $request     Submitted-field reader and validator.
	 * @param PageSearch $page_search Editable saved-page search.
	 */
	public function __construct( Checklist $checklist, Request $request, PageSearch $page_search ) {

		$this->checklist   = $checklist;
		$this->request     = $request;
		$this->page_search = $page_search;
	}

	/**
	 * Run the current server-selected action for one canonical item.
	 *
	 * @since 3.15.0
	 *
	 * @param array $state Undismissed State from `Ajax::handle()`.
	 *
	 * @return array|WP_Error
	 */
	public function run( $state ) {

		$item_id = $this->request->post_key( 'item' );

		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}

		$item = $this->get_actionable_item( $state, $item_id );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		$input = $this->prepare_item_input( $item_id );

		if ( is_wp_error( $input ) ) {
			return $input;
		}

		/**
		 * Fires after an administrator submits a validated checklist item action.
		 *
		 * @since 3.15.0
		 *
		 * @param string $item_id Canonical checklist item ID.
		 * @param array  $state   The undismissed State this request read, so listeners need not read it again.
		 */
		do_action( 'sugar_calendar_setup_checklist_item_cta_clicked', $item_id, $state ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- named for the feature, not this class.

		$unavailable = new WP_Error( 'checklist_item_action_unavailable', __( 'This checklist action is unavailable.', 'sugar-calendar-lite' ) );

		/**
		 * Filters a validated checklist item operation.
		 *
		 * The owner of each row's service claims it here; return the passed result
		 * unchanged to decline a row. A row nobody claims uses its own `url`.
		 *
		 * @since 3.15.0
		 *
		 * @param array|WP_Error $result     Shared unavailable result.
		 * @param string         $item_id    Canonical checklist item ID.
		 * @param string         $return_url Local admin return URL.
		 * @param array          $item       The row as presented, including its action data.
		 * @param array          $input      Validated row input: `page` for a `page_id` field, `email` for an `email` field.
		 */
		$result = apply_filters( 'sugar_calendar_setup_checklist_item_action', $unavailable, $item_id, SetupChecklistPage::get_url(), $item, $input ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- named for the feature, not this class.

		if ( $result !== $unavailable ) {
			return $result;
		}

		if ( ! empty( $item['action']['url'] ) ) {
			return ActionResult::build( 'success', '', $item['action']['url'], false );
		}

		return $unavailable;
	}

	/**
	 * Handle a bounded editable-page query for the embed row's page selector.
	 *
	 * @since 3.15.0
	 *
	 * @return array|WP_Error
	 */
	public function search_pages() {

		$input = $this->request->page_search();

		if ( is_wp_error( $input ) ) {
			return $input;
		}

		return array_merge( ActionResult::build( 'success', '', '', false ), $this->page_search->get_pages( $input['search'], $input['page'] ) );
	}

	/**
	 * Resolve one currently applicable, incomplete item from authoritative data.
	 *
	 * @since 3.15.0
	 *
	 * @param array  $state   Undismissed State from `Ajax::handle()`.
	 * @param string $item_id Canonical checklist item ID.
	 *
	 * @return array|WP_Error
	 */
	private function get_actionable_item( $state, $item_id ) {

		$this->checklist->adopt_state( $state );

		$item = $this->find_item( $this->checklist->get_view_data()['groups'], $item_id );

		if ( $item === null || empty( $item['action']['action'] ) ) {
			return new WP_Error( 'checklist_unknown_item', __( 'This checklist item is not currently available.', 'sugar-calendar-lite' ) );
		}

		return ! empty( $item['completed'] )
			? new WP_Error( 'checklist_item_complete', __( 'This checklist item is already complete.', 'sugar-calendar-lite' ) )
			: $item;
	}

	/**
	 * Validate the extra fields the row declares in its `input` key.
	 *
	 * @since 3.15.0
	 *
	 * @param string $item_id Canonical checklist item ID.
	 *
	 * @return array|WP_Error
	 */
	private function prepare_item_input( $item_id ) {

		$input = [];

		foreach ( Config::get_item_input( $item_id ) as $field ) {
			$value = $this->prepare_input_field( $field );

			if ( is_wp_error( $value ) ) {
				return $value;
			}

			$input += $value;
		}

		return $input;
	}

	/**
	 * Validate one declared input field: `page_id` yields `page`, `email` yields `email`.
	 *
	 * @since 3.15.0
	 *
	 * @param string $field Declared POST field name.
	 *
	 * @return array|WP_Error
	 */
	private function prepare_input_field( $field ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh -- validates the two field payloads.

		if ( $field === 'page_id' ) {
			$page_id = $this->request->post_positive_int( 'page_id' );

			if ( is_wp_error( $page_id ) ) {
				return $page_id;
			}

			$page = $this->get_editable_page( $page_id );

			return is_wp_error( $page ) ? $page : [ 'page' => $page ];
		}

		if ( $field === 'email' ) {
			$email = $this->request->post_string( 'email' );

			if ( is_wp_error( $email ) ) {
				return $email;
			}

			$email = sanitize_email( $email );

			if ( $email === '' || ! is_email( $email ) ) {
				return new WP_Error( 'checklist_invalid_email', __( 'Enter a valid email address.', 'sugar-calendar-lite' ) );
			}

			return [ 'email' => $email ];
		}

		return [];
	}

	/**
	 * Find a canonical item in normalized groups.
	 *
	 * @since 3.15.0
	 *
	 * @param array  $groups  Normalized checklist groups.
	 * @param string $item_id Canonical item ID.
	 *
	 * @return array|null
	 */
	private function find_item( $groups, $item_id ) {

		foreach ( $groups as $group ) {
			if ( isset( $group['items'][ $item_id ] ) && is_array( $group['items'][ $item_id ] ) ) {
				return $group['items'][ $item_id ];
			}
		}

		return null;
	}

	/**
	 * Validate a selected saved page and its editor support.
	 *
	 * @since 3.15.0
	 *
	 * @param int $page_id Page ID.
	 *
	 * @return WP_Post|WP_Error
	 */
	private function get_editable_page( $page_id ) {

		$page = get_post( $page_id );

		if ( ! $page instanceof WP_Post || $page->post_type !== 'page' || ! in_array( $page->post_status, Config::SAVED_POST_STATUSES, true ) ) {
			return new WP_Error( 'checklist_page_invalid', __( 'Select an existing saved page.', 'sugar-calendar-lite' ) );
		}

		if ( ! current_user_can( 'edit_post', $page->ID ) ) {
			return new WP_Error( 'checklist_page_forbidden', __( 'You are not allowed to edit this page.', 'sugar-calendar-lite' ) );
		}

		if ( ! use_block_editor_for_post( $page ) ) {
			return new WP_Error( 'checklist_page_editor_unsupported', __( 'This page does not use the block editor. Open it from Pages to edit it normally.', 'sugar-calendar-lite' ) );
		}

		return $page;
	}
}
