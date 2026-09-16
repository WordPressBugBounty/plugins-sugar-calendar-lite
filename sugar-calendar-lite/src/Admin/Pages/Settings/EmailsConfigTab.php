<?php

namespace Sugar_Calendar\Admin\Pages\Settings;

use Sugar_Calendar\Admin\Pages\Settings\Emails\EmailEditPage;
use Sugar_Calendar\Admin\Pages\Settings\Emails\EmailsList;
use Sugar_Calendar\Admin\Pages\Settings\Emails\EmailStylingSection;
use Sugar_Calendar\EmailStatus;
use Sugar_Calendar\Helpers as Sugar_Calendar_Helpers;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Emails\EmailRegistry;
use Sugar_Calendar\Emails\Preview;
use Sugar_Calendar\Emails\PreviewDocument;
use Sugar_Calendar\Emails\Ticketing\AttendeeTicketEmail;
use Sugar_Calendar\Emails\Ticketing\OrderReceiptEmail;

/**
 * Emails Config Tab.
 *
 * @since 3.7.0
 */
class EmailsConfigTab {

	/**
	 * Hooks.
	 *
	 * @since 3.7.0
	 */
	public function hooks() {

		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );
		add_action( 'sugar_calendar_admin_area_handle_post', [ $this, 'save_email_status' ] );
		add_action( 'sugar_calendar_admin_settings_submit_buttons', [ $this, 'display_list_preview_button' ] );
		add_action( 'sugar_calendar_admin_settings_submit_buttons', [ $this, 'display_editor_preview_button' ] );
		add_action( 'sc_et_settings_emails_section_bottom', [ $this, 'configure_emails_section' ] );
	}

	/**
	 * Enqueue the assets for the Emails section.
	 *
	 * Registered from hooks(), which `Settings::hooks()` calls only when the
	 * emails section is the current view — so these never load on another tab.
	 *
	 * Read that as `hooks()`, NOT `init()`: `Settings::init()` used to make this
	 * call and no longer does. `hooks()` runs on `admin_init` priority 0, while
	 * `init()` is scheduled on priority 10 — after `Area::handle_post()` has
	 * already fired the save action — so the toggle's save handler, registered
	 * alongside this one, silently never ran. See the comment on that call site.
	 *
	 * @since 3.14.0
	 */
	public function enqueue_assets() {

		$style_dependencies = [ 'sugar-calendar-admin-settings' ];
		$script_dependencies = [
			'jquery',
			'sugar-calendar-admin-education',
			'sugar-calendar-vendor-jquery-confirm',
		];

		// Presentation routing only; matches emails_section().
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['email_cfg'] ) ) {
			wp_enqueue_media();

			$style_dependencies[]  = 'sugar-calendar-vendor-minicolors';
			$script_dependencies[] = 'sugar-calendar-admin-color-scheme';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		wp_enqueue_style(
			'sugar-calendar-admin-settings-emails',
			SC_PLUGIN_ASSETS_URL . 'css/admin-settings-emails' . WP::asset_min() . '.css',
			$style_dependencies,
			Sugar_Calendar_Helpers::get_asset_version()
		);

		wp_enqueue_style( 'sugar-calendar-admin-confirm' );

		// Admin alerts is the full jQuery Confirm theme, so only the registered
		// vendor script is needed for the education modal.
		wp_enqueue_script( 'sugar-calendar-vendor-jquery-confirm' );
		wp_enqueue_style( 'sugar-calendar-admin-education' );
		wp_enqueue_script( 'sugar-calendar-admin-education' );

		wp_enqueue_script(
			'sugar-calendar-admin-settings-emails',
			SC_PLUGIN_ASSETS_URL . 'js/admin-settings-emails' . WP::asset_min() . '.js',
			$script_dependencies,
			Sugar_Calendar_Helpers::get_asset_version(),
			true
		);

		// The modal's strings ride this script's OWN object rather than
		// re-localizing the shared `sugar_calendar_admin_education`.
		// Admin\Education::enqueue_assets() already localizes that global on the
		// education handle with only `ajax_url`; a second wp_localize_script() on
		// the same object name prints a second `var` that wins purely by hook
		// order, and would drop `ajax_url` and break notice dismissal. The script
		// grafts `thank_you` onto the shared global from JS instead.
		wp_localize_script(
			'sugar-calendar-admin-settings-emails',
			'sugar_calendar_admin_settings_emails',
			[
				'upgrade_button'    => esc_html__( 'Upgrade to Pro', 'sugar-calendar-lite' ),
				'already_purchased' => esc_html__( 'Already purchased?', 'sugar-calendar-lite' ),
				'education'         => Sugar_Calendar_Helpers::get_education_upgrade_modal_content(),
				'thank_you'         => Sugar_Calendar_Helpers::get_education_upgrade_thank_you_modal_content(),
				'media_title'       => esc_html__( 'Select Header Image', 'sugar-calendar-lite' ),
				'media_button'      => esc_html__( 'Use this image', 'sugar-calendar-lite' ),
				'preview_ajax_url'  => admin_url( 'admin-ajax.php' ),
				'preview_action'    => Preview::CREATE_ACTION,
				'preview_nonce'     => wp_create_nonce( Preview::CREATE_ACTION ),
				'preview_error'     => esc_html__( 'The email preview could not be created. Please try again.', 'sugar-calendar-lite' ),
				'popup_blocked'     => esc_html__( 'Your browser blocked the email preview window. Allow popups and try again.', 'sugar-calendar-lite' ),
			]
		);
	}

	/**
	 * Render the representative preview action on the Emails list screen.
	 *
	 * @since 3.14.0
	 *
	 * @param string|null $tab_slug Current Settings tab slug.
	 */
	public function display_list_preview_button( $tab_slug ) {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presentation routing only; the preview creator verifies its own nonce.
		if ( $tab_slug !== 'emails' || ! empty( $_GET['email_cfg'] ) ) {
			return;
		}

		EmailEditPage::render_preview_button( 'main', AttendeeTicketEmail::ID );
	}

	/**
	 * Render the preview action on a per-email edit screen.
	 *
	 * Registered here rather than from `EmailEditPage::render()`, so the button
	 * never depends on the editor having rendered before the submit row.
	 *
	 * @since 3.14.0
	 *
	 * @param string|null $tab_slug Current Settings tab slug.
	 */
	public function display_editor_preview_button( $tab_slug ) {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presentation routing only; the preview creator verifies its own nonce.
		$email_cfg = empty( $_GET['email_cfg'] ) ? '' : sanitize_key( wp_unslash( $_GET['email_cfg'] ) );

		if ( $tab_slug !== 'emails' || $email_cfg === '' || ! PreviewDocument::supports( $email_cfg ) ) {
			return;
		}

		EmailEditPage::render_preview_button( 'editor', $email_cfg );
	}

	/**
	 * Configure Emails section.
	 *
	 * @since 3.7.0
	 * @since 3.14.0 Split into per-audience card groups; markup moved to
	 *                  EmailsList.
	 */
	public function configure_emails_section() {

		EmailsList::render();
		EmailStylingSection::render();
	}

	/**
	 * Persist the per-email enable toggles.
	 *
	 * **Writes only the ids whose control the current screen actually rendered
	 * writable.** That is the whole safety property, and it is not the same as
	 * "every id in $_POST" or "every sendable id":
	 *
	 * - A disabled checkbox posts nothing, exactly like an unchecked one. Looping
	 *   over registry entries and reading `isset()` therefore records every
	 *   non-writable email as *deliberately switched off* — backwards, and it
	 *   persists a lie about emails the admin was never allowed to touch.
	 * - The edit screen renders exactly one toggle. Writing anything else from it
	 *   would let saving one email clear the other six.
	 *
	 * The nonce and the `sugar-calendar-submit` gate were already checked by
	 * `Area::handle_post()` before this action fired. The page/section check below
	 * is scoping, not authorisation: another settings tab's save must not reach
	 * this option.
	 *
	 * @since 3.14.0
	 *
	 * @param array $post_data Unslashed `sugar-calendar[…]` settings array.
	 */
	public function save_email_status( $post_data ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Verified by Area::handle_post() before this action fires.
		if (
			empty( $_GET['page'] ) || $_GET['page'] !== 'sugarcalendar-settings' ||
			empty( $_GET['section'] ) || $_GET['section'] !== 'emails'
		) {
			return;
		}

		$email_cfg = empty( $_GET['email_cfg'] ) ? '' : sanitize_key( wp_unslash( $_GET['email_cfg'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$statuses = $email_cfg === ''
			? $this->collect_list_screen_statuses()
			: $this->collect_edit_screen_statuses( $email_cfg, (array) $post_data );

		if ( empty( $statuses ) ) {
			return;
		}

		EmailStatus::set_many( $statuses );
	}

	/**
	 * Collect the toggles the emails list screen rendered.
	 *
	 * List rows render `UI::toggle_control( …, $bare = true )`, which skips the
	 * `sugar-calendar[…]` name wrapper — so these arrive at the top level of
	 * $_POST, not in $post_data.
	 *
	 * @since 3.14.0
	 *
	 * @return array Map of email id => bool.
	 */
	private function collect_list_screen_statuses() {

		$statuses = [];

		foreach ( EmailRegistry::all() as $id => $email ) {

			if ( ! EmailRegistry::is_status_writable( $email ) ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by Area::handle_post().
			$statuses[ $id ] = ! empty( $_POST[ 'email_enabled_' . $id ] );
		}

		return $statuses;
	}

	/**
	 * Collect the single toggle one email's edit screen rendered.
	 *
	 * The edit screen renders its toggle non-bare, so the value arrives nested in
	 * $post_data rather than at the top level of $_POST.
	 *
	 * @since 3.14.0
	 *
	 * @param string $email_cfg Email id from the URL, already sanitized.
	 * @param array  $post_data Unslashed `sugar-calendar[…]` settings array.
	 *
	 * @return array Map of email id => bool, at most one entry. Empty when the
	 *               posting screen rendered no Status control.
	 */
	private function collect_edit_screen_statuses( $email_cfg, array $post_data ) {

		// The id must be one this screen would actually route to. Shares its
		// answer with SingleEmailConfig rather than re-listing the defaults.
		if ( ! in_array( $email_cfg, SingleEmailConfig::get_valid_ids(), true ) ) {
			return [];
		}

		if ( ! EmailRegistry::is_status_writable( EmailRegistry::get( $email_cfg ) ) ) {
			return [];
		}

		// The posting screen must have RENDERED a Status toggle. An unchecked
		// checkbox posts nothing — and so does a screen that never showed one, so
		// the toggle key alone cannot distinguish "switched off" from "not mine to
		// set". EmailEditPage::render() posts this hidden marker precisely to say
		// which of the two happened.
		//
		// Without it, ANY screen hooked to
		// `sugar_calendar_admin_settings_email_config_display_{$id}` that renders
		// its own fields rather than calling EmailEditPage — the RSVP add-on's two
		// attendee editors, or any third party's — silently switched its own email
		// off the moment an admin saved a subject change.
		if ( empty( $post_data[ 'email_enabled_present_' . $email_cfg ] ) ) {
			return [];
		}

		return [ $email_cfg => ! empty( $post_data[ 'email_enabled_' . $email_cfg ] ) ];
	}

	/**
	 * Display the email config settings for the Order Receipt Email.
	 *
	 * @since 1.0.0
	 * @deprecated 3.14.0 Use EmailEditPage::render_email() with the registered definition instead.
	 */
	public function display_email_config_sc_et_receipt_email() {

		EmailEditPage::render_email( EmailRegistry::definition( OrderReceiptEmail::ID ) );
	}

	/**
	 * Display the email config settings for the Ticket Email.
	 *
	 * @since 1.0.0
	 * @deprecated 3.14.0 Use EmailEditPage::render_email() with the registered definition instead.
	 */
	public function display_email_config_sc_et_ticket_email() {

		EmailEditPage::render_email( EmailRegistry::definition( AttendeeTicketEmail::ID ) );
	}
}
