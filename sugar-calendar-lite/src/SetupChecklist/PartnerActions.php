<?php

namespace Sugar_Calendar\SetupChecklist;

use Sugar_Calendar\Admin\Pages\SetupChecklist as SetupChecklistPage;
use Sugar_Calendar\Helpers\ActionResult;
use Sugar_Calendar\PartnerPlugins\Catalog;
use Sugar_Calendar\PartnerPlugins\Installer;
use WP_Error;

/**
 * Partner plugin installs from the checklist: rows carrying a `partner_key`, and the growth_tool task.
 *
 * @since 3.15.0
 */
class PartnerActions {

	/**
	 * Partner catalogue.
	 *
	 * @since 3.15.0
	 *
	 * @var Catalog
	 */
	private $catalog;

	/**
	 * Partner installer.
	 *
	 * @since 3.15.0
	 *
	 * @var Installer
	 */
	private $installer;

	/**
	 * Submitted-field reader and validator.
	 *
	 * @since 3.15.0
	 *
	 * @var Request
	 */
	private $request;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param Catalog   $catalog   Partner catalogue.
	 * @param Installer $installer Partner installer.
	 * @param Request   $request   Submitted-field reader and validator.
	 */
	public function __construct( Catalog $catalog, Installer $installer, Request $request ) {

		$this->catalog   = $catalog;
		$this->installer = $installer;
		$this->request   = $request;
	}

	/**
	 * Hooks.
	 *
	 * @since 3.15.0
	 */
	public function hooks() {

		add_filter( 'sugar_calendar_setup_checklist_item_action', [ $this, 'filter_item_action' ], 10, 4 );
	}

	/**
	 * Claim a row whose action names a partner, and pass every other row through.
	 *
	 * @since 3.15.0
	 *
	 * @param array|WP_Error $result     Shared fallback result.
	 * @param string         $item_id    Canonical checklist item ID.
	 * @param string         $return_url Local admin return URL.
	 * @param array          $item       The row as presented, including its action data.
	 *
	 * @return array|WP_Error
	 */
	public function filter_item_action( $result, $item_id, $return_url, $item ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- filter contract supplies the id and URL before the row.

		if ( empty( $item['action']['partner_key'] ) ) {
			return $result;
		}

		$key   = $item['action']['partner_key'];
		$state = $this->catalog->get_state( $key );

		if ( $state === 'active' ) {
			$admin_url = $this->catalog->get_admin_url( $key );

			// An active partner whose row is still incomplete, like WP Mail SMTP without a mailer, is finished on its own screen.
			if ( $admin_url !== '' ) {
				return ActionResult::build( 'success', '', $admin_url, false, $state );
			}

			return new WP_Error( 'checklist_plugin_stale_action', __( 'This plugin is already active. Refresh the checklist to update its status.', 'sugar-calendar-lite' ), [ 'plugin_state' => $state ] );
		}

		return $this->run( $key );
	}

	/**
	 * Install or activate one allowlisted Growth Tool.
	 *
	 * @since 3.15.0
	 *
	 * @return array|WP_Error
	 */
	public function install_growth_tool() {

		$key = $this->request->post_key( 'key' );

		if ( is_wp_error( $key ) || ! array_key_exists( $key, Config::get_growth_tools() ) ) {
			return new WP_Error( 'checklist_growth_tool_unknown', __( 'This Growth Tool is not available.', 'sugar-calendar-lite' ) );
		}

		$state = $this->catalog->get_state( $key );

		if ( $state === 'active' ) {
			return ActionResult::build( 'success', __( 'Plugin already active.', 'sugar-calendar-lite' ), '', false, $state );
		}

		return $this->run( $key );
	}

	/**
	 * Install or activate a catalogue partner.
	 *
	 * @since 3.15.0
	 *
	 * @param string $key Partner key.
	 *
	 * @return array|WP_Error
	 */
	private function run( $key ) {

		$target = $this->catalog->get_target( $key );

		if ( $target === null ) {
			return new WP_Error( 'checklist_plugin_unknown', __( 'This plugin is not available from the checklist.', 'sugar-calendar-lite' ) );
		}

		return $this->installer->run( $target, SetupChecklistPage::get_url() );
	}
}
