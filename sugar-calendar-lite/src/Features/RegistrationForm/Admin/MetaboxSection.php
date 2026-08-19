<?php

namespace Sugar_Calendar\Features\RegistrationForm\Admin;

use Sugar_Calendar\Admin\ReactJsxRuntime;
use Sugar_Calendar\AddOn\Ticketing\Helpers\Helpers as TicketingHelpers;
use Sugar_Calendar\Event;
use Sugar_Calendar\Features\RegistrationForm\SchemaRepository;
use Sugar_Calendar_Event_Ticketing\Features\EventTickets;
use WP_Error;
use WP_Post;

/**
 * The "Registration Form" event-editor metabox section shell.
 *
 * PR 1 ships the section registration, the schema hidden-input plumbing, and
 * the save handler. The React Form Editor (Track A, PR 2) mounts into the
 * shell; the Lite education state (PR 3) replaces the shell on Lite.
 *
 * @since 3.13.0
 */
class MetaboxSection {

	/**
	 * POST field carrying the schema JSON.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const INPUT_NAME = 'sc_registration_form_schema';

	/**
	 * Query arg flagging a refused schema save on the post-save redirect.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const NOTICE_QUERY_ARG = 'sc_registration_form_notice';

	/**
	 * Whether the LAST in-scope schema save of this request was refused.
	 *
	 * Describes one save_post firing, not the request as a whole; see the
	 * reset in save_schema().
	 *
	 * @since 3.13.0
	 *
	 * @var bool
	 */
	private $save_failed = false;

	/**
	 * Register hooks.
	 *
	 * @since 3.13.0
	 */
	public function hooks() {

		add_action( 'sugar_calendar_admin_meta_box_setup_sections', [ $this, 'register_section' ] );
		add_action( 'save_post', [ $this, 'save_schema' ], 20, 2 );
		add_filter( 'redirect_post_location', [ $this, 'append_invalid_notice' ], 10, 2 );
		add_action( 'admin_notices', [ $this, 'render_invalid_notice' ] );
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Register the Registration Form section on the Event metabox.
	 *
	 * @since 3.13.0
	 *
	 * @param object $metabox Event metabox instance.
	 */
	public function register_section( $metabox ) {

		$metabox->add_section(
			[
				'id'       => 'registration',
				'label'    => esc_html__( 'Registration Form', 'sugar-calendar-lite' ),
				'icon'     => 'feedback',
				'order'    => 62,
				'callback' => [ $this, 'display' ],
			]
		);
	}

	/**
	 * Render the section shell: React mount node + schema hidden input.
	 *
	 * @since 3.13.0
	 *
	 * @param Event $event The event object (may be empty on add-new).
	 */
	public function display( $event = null ) {

		$event_id = ! empty( $event->id ) ? (int) $event->id : 0;
		$schema   = $event_id ? SchemaRepository::get( $event_id ) : null;

		// Renderer::for_event() refuses this event, so a form built here would
		// silently collect nothing; warn but keep the editor, since the organizer
		// may move the event off WooCommerce checkout later.
		if ( $event_id && get_event_meta( $event_id, 'woocommerce_checkout', true ) ) {
			printf(
				'<div class="sugar-calendar-metabox__field-row"><p class="sc-registration-form__unsupported">%1$s</p></div>',
				esc_html__( 'Registration forms are not supported with WooCommerce checkout. Answers will not be collected for this event.', 'sugar-calendar-lite' )
			);
		} elseif ( $event_id && $this->after_checkout_would_silently_drop_answers( $event_id, $event, $schema ) ) {
			// sugar_calendar_rsvp_frontend_after_rsvp_box (sc-rsvp add-on) is what
			// lets an RSVP-only event host After Checkout; an older sc-rsvp build
			// without that seam silently renders nothing here.
			printf(
				'<div class="sugar-calendar-metabox__field-row"><p class="sc-registration-form__unsupported">%1$s</p></div>',
				esc_html__( 'Registration forms collected "After Checkout" require a newer version of the RSVP add-on. Answers will not be collected for this event until it is updated.', 'sugar-calendar-lite' )
			);
		}

		?>
		<div class="sugar-calendar-metabox__field-row">
			<div id="sc-registration-form-editor"></div>
			<input
				type="hidden"
				name="<?php echo esc_attr( self::INPUT_NAME ); ?>"
				id="<?php echo esc_attr( self::INPUT_NAME ); ?>"
				value="<?php echo esc_attr( $schema ? wp_json_encode( $schema ) : '' ); ?>"
			/>
		</div>
		<?php
	}

	/**
	 * Whether After Checkout would silently collect nothing for this event.
	 *
	 * True when the schema targets After Checkout on an RSVP-only event whose
	 * installed sc-rsvp build predates the after-checkout seams.
	 *
	 * @since 3.13.0
	 *
	 * @param int        $event_id Sugar Calendar event id.
	 * @param Event      $event    The event object.
	 * @param array|null $schema   The stored schema, or null.
	 *
	 * @return bool
	 */
	private function after_checkout_would_silently_drop_answers( $event_id, $event, $schema ) {

		if ( ! is_array( $schema ) || ( $schema['show'] ?? '' ) !== 'after_checkout' ) {
			return false;
		}

		$ticketing_enabled = class_exists( TicketingHelpers::class ) && TicketingHelpers::is_event_ticketing_enabled( $event );

		if ( $ticketing_enabled ) {
			return false;
		}

		$rsvp_enabled = function_exists( 'sc_rsvp' ) && sc_rsvp()->get_event_integration()->is_rsvp_enable( $event_id );

		if ( ! $rsvp_enabled ) {
			return false;
		}

		return ! $this->rsvp_addon_has_after_checkout_seams();
	}

	/**
	 * Whether the installed sc-rsvp add-on declares the after-checkout seams.
	 *
	 * Absence means "no" (the safe default). Overridable so tests can exercise
	 * both branches without redefining a real constant.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	protected function rsvp_addon_has_after_checkout_seams() {

		return defined( 'SUGAR_CALENDAR_RSVP_HAS_AFTER_CHECKOUT_SEAMS' ) && SUGAR_CALENDAR_RSVP_HAS_AFTER_CHECKOUT_SEAMS;
	}

	/**
	 * Persist the posted schema on event save.
	 *
	 * Runs on generic save_post priority 20, after Metaboxes::save() (10) has
	 * created/updated the event row, so the sc event id exists.
	 *
	 * @since 3.13.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_schema( $post_id, $post ) {

		if ( ! $this->is_valid_save_request( $post_id, $post ) ) {
			return;
		}

		/*
		 * save_post can fire twice for one post: AdvancedRecurring's
		 * create_sc_event_from_virtual_occurrence_event() runs this handler
		 * against a post with no wp_sc_events row yet. Reset here so that
		 * refusal doesn't mislabel the real save that follows.
		 */
		$this->save_failed = false;

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Presence, nonce, and capability are already checked in is_valid_save_request(); the JSON blob itself is validated/sanitized by SchemaValidator.
		$raw = wp_unslash( $_POST[ self::INPUT_NAME ] );

		// An array (sc_registration_form_schema[]=x) would make json_decode()
		// throw a TypeError on PHP 8 — a fatal mid-save_post.
		if ( ! is_string( $raw ) ) {
			$this->save_failed = true;

			return;
		}

		/*
		 * An empty value means the editor app never wrote anything (assets not
		 * built, a JS error), so nothing was refused.
		 */
		if ( $raw === '' ) {
			return;
		}

		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) ) {
			$this->save_failed = true;

			return;
		}

		$event = sugar_calendar_get_event_by_object( $post_id, 'post', [ 'object_subtype' => $post->post_type ] );

		if ( empty( $event->id ) ) {
			$this->save_failed = true;

			return;
		}

		$result = SchemaRepository::save( (int) $event->id, $decoded );

		if ( $result instanceof WP_Error ) {
			$this->save_failed = true;
		}
	}

	/**
	 * Whether this save_post firing is an in-scope, authorized schema save.
	 *
	 * @since 3.13.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 *
	 * @return bool
	 */
	private function is_valid_save_request( $post_id, $post ) {

		if ( ! isset( $_POST[ self::INPUT_NAME ] ) ) {
			return false;
		}

		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, [ 'sc_event', 'sc_recurring_event' ], true ) ) {
			return false;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce value.
		if ( empty( $_POST['sc_mb_nonce'] ) || ! wp_verify_nonce( $_POST['sc_mb_nonce'], 'sugar_calendar_nonce' ) ) {
			return false;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Flag a refused schema save on the post-save redirect URL.
	 *
	 * WP::add_admin_notice() is request-scoped and does not survive the
	 * redirect, so we ride a query arg instead.
	 *
	 * @since 3.13.0
	 *
	 * @param string $location Redirect URL.
	 * @param int    $post_id  Post ID.
	 *
	 * @return string
	 */
	public function append_invalid_notice( $location, $post_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $post_id is part of the redirect_post_location filter signature.

		if ( $this->save_failed ) {
			$location = add_query_arg( self::NOTICE_QUERY_ARG, 'invalid', $location );
		}

		return $location;
	}

	/**
	 * Render the refused-save notice on the event editor.
	 *
	 * @since 3.13.0
	 */
	public function render_invalid_notice() {

		$notice = isset( $_GET[ self::NOTICE_QUERY_ARG ] ) ? sanitize_key( $_GET[ self::NOTICE_QUERY_ARG ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag.

		if ( $notice !== 'invalid' ) {
			return;
		}

		echo '<div class="notice notice-error is-dismissible"><p>' .
			esc_html__( 'Registration Form settings were not saved. Please review the form fields and try again.', 'sugar-calendar-lite' ) .
		'</p></div>';
	}

	/**
	 * Enqueue the Form Editor app on the event editor.
	 *
	 * The manifest is generated by the JSX build (`gulp jsx`) and is absent
	 * on a source-only checkout until assets are built.
	 *
	 * @since 3.13.0
	 */
	public function enqueue_assets() {

		$admin = sugar_calendar()->get_admin();

		if ( ! $admin->is_page( 'event_edit' ) && ! $admin->is_page( 'event_new' ) ) {
			return;
		}

		$asset_file_path = SC_PLUGIN_DIR . 'assets/jsx/build/admin/registration-form.asset.php';

		if ( ! file_exists( $asset_file_path ) ) {
			return;
		}

		$asset_file = include $asset_file_path;

		// Guard against a truncated or malformed manifest (e.g. mid-build).
		if ( ! is_array( $asset_file ) || ! isset( $asset_file['dependencies'], $asset_file['version'] ) ) {
			return;
		}

		ReactJsxRuntime::ensure_registered();

		wp_enqueue_script(
			'sugar-calendar-admin-registration-form',
			SC_PLUGIN_ASSETS_URL . 'jsx/build/admin/registration-form.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context resolution.

		wp_localize_script(
			'sugar-calendar-admin-registration-form',
			'sugar_calendar_registration_form',
			$this->get_bootstrap_data( $post_id )
		);
	}

	/**
	 * Resolve the SC event behind an editor post id, virtual-occurrence safe.
	 *
	 * A recurring occurrence's edit URL carries a virtual post id whose cached
	 * ->post_type is overwritten to 'sc_event'; resolve via the real id and a
	 * fresh get_post_type() lookup instead of trusting either.
	 *
	 * @since 3.13.0
	 *
	 * @param int $post_id Post id from the request (may be virtual).
	 *
	 * @return object|null Event with a non-empty ->id, or null.
	 */
	public function resolve_event( $post_id ) {

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$event = sugar_calendar_get_event_by_object(
			$post->ID,
			'post',
			[ 'object_subtype' => get_post_type( $post->ID ) ]
		);

		return empty( $event->id ) ? null : $event;
	}

	/**
	 * The ticket types the editor may offer as "Custom" targeting choices.
	 *
	 * Includes the general ticket, which EventTickets reports first as id 0 —
	 * the id general-admission attendees post, and the one the schema contract
	 * accepts for exactly that reason. Leaving it out made the default ticket
	 * type impossible to target.
	 *
	 * These are the SAVED types, and only the app's fallback: the Tickets tab
	 * adds, renames and removes types without a reload, so the live list comes
	 * from its rows in the DOM.
	 *
	 * @since 3.13.0
	 *
	 * @param int $event_id Sugar Calendar event id.
	 *
	 * @return array[] Each entry: [ 'id' => int, 'label' => string ].
	 */
	private function get_ticket_type_choices( $event_id ) {

		if ( ! class_exists( EventTickets::class ) ) {
			return [];
		}

		$choices = [];

		foreach ( (array) EventTickets::get_instance( $event_id )->get_ticket_types() as $ticket_type ) {

			$id = (int) ( $ticket_type['id'] ?? -1 );

			if ( $id < 0 ) {
				continue;
			}

			$choices[] = [
				'id'    => $id,
				'label' => (string) ( $ticket_type['ticket_name'] ?? '' ),
			];
		}

		return $choices;
	}

	/**
	 * Build the app bootstrap payload.
	 *
	 * The schema itself is NOT localized — the app reads it from the hidden
	 * input rendered by display(), the single source of truth.
	 *
	 * `ticketing_enabled` / `rsvp_enabled` are the SAVED state, and only the
	 * fallback the app uses when a host's toggle isn't in the DOM (add-on
	 * inactive). The live values come from the `enable_tickets` / `rsvp_enable`
	 * checkboxes in the sibling metabox tabs, so the editor agrees with what an
	 * unsaved toggle is about to persist. The choice list is therefore always
	 * shipped — gating it on the saved flag would leave the targeting row unable
	 * to appear when Tickets is switched on without a save.
	 *
	 * @since 3.13.0
	 *
	 * @param int $post_id Event post ID (0 on add-new; may be a virtual
	 *                     occurrence id on recurring events).
	 *
	 * @return array
	 */
	public function get_bootstrap_data( $post_id = 0 ) {

		$event             = $post_id ? $this->resolve_event( $post_id ) : null;
		$event_id          = empty( $event->id ) ? 0 : (int) $event->id;
		$ticketing_enabled = false;
		$rsvp_enabled      = false;
		$ticket_types      = [];

		if ( $event_id && class_exists( TicketingHelpers::class ) ) {
			$ticketing_enabled = TicketingHelpers::is_event_ticketing_enabled( $event );
			$ticket_types      = $this->get_ticket_type_choices( $event_id );
		}

		if ( $event_id && function_exists( 'sc_rsvp' ) ) {
			$rsvp_enabled = sc_rsvp()->get_event_integration()->is_rsvp_enable( $event_id );
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return [
			'is_block_editor'   => $screen ? (bool) $screen->is_block_editor() : false,
			'ticketing_enabled' => (bool) $ticketing_enabled,
			'rsvp_enabled'      => (bool) $rsvp_enabled,
			'ticket_types'      => $ticket_types,
			'strings'           => EditorStrings::all(),
		];
	}
}
