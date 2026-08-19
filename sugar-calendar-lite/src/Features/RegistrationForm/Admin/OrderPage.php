<?php

namespace Sugar_Calendar\Features\RegistrationForm\Admin;

use Sugar_Calendar\Features\RegistrationForm\RespondentNaming;
use Sugar_Calendar\Features\RegistrationForm\SchemaRepository;
use Sugar_Calendar\Helpers\UI;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\add_attendee;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_attendee;
use function Sugar_Calendar\AddOn\Ticketing\Settings\get_setting;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_tickets;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\update_attendee;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\update_ticket;

/**
 * Registration answers on the ticketing order/ticket details page.
 *
 * Injected through actions the page fires inside its own <form>, so its nonce,
 * capability check and update() handler are all reused as they are. The attendee
 * name/email fields are ticketing data rather than registration data, but render
 * here because they share the panel with the answers.
 *
 * @since 3.13.0
 */
class OrderPage {

	/**
	 * Transient prefix for errors that must survive the post-save redirect.
	 *
	 * The update() handler ends in wp_safe_redirect(), and WP::add_admin_notice() is
	 * request-scoped, so a notice pushed before the redirect never renders.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const ERRORS_TRANSIENT_PREFIX = 'sc_regform_admin_errors_';

	/**
	 * POST array the attendee identity inputs write into.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const ATTENDEE_POST_KEY = 'sc_regform_attendee';

	/**
	 * Save errors for this request, read once per instance.
	 *
	 * An instance property, not a static: the first read deletes the transient, so a
	 * static would leak an empty result into the next order handled in the process.
	 *
	 * @since 3.13.0
	 *
	 * @var array|null
	 */
	private $errors = null;

	/**
	 * Resolved respondents, keyed by order id.
	 *
	 * See respondents_for_order() for why the memoisation is load-bearing.
	 *
	 * @since 3.13.0
	 *
	 * @var array<int,array>
	 */
	private $respondents = [];

	/**
	 * Ticket type id of each of an order's tickets: [ order id => [ ticket id => type id ] ].
	 *
	 * Filled alongside the respondents, whose host_row_id is a ticket id — that is
	 * the only link between a respondent and the type the form targets.
	 *
	 * @since 3.13.0
	 *
	 * @var array<int,array<int,int>>
	 */
	private $ticket_types = [];

	/**
	 * Register hooks.
	 *
	 * @since 3.13.0
	 */
	public function hooks() {

		add_action( 'admin_notices', [ $this, 'maybe_replace_host_notice' ], 9 );
		add_action( 'sc_et_admin_order_top', [ $this, 'render_notice' ] );
		add_action( 'sc_et_admin_order_panels', [ $this, 'render_panels' ] );
		add_filter( 'sc_et_admin_order_update_errors', [ $this, 'validate' ], 10, 3 );
		add_action( 'sc_et_admin_order_updated', [ $this, 'save' ], 10, 2 );
		add_filter( 'sugar_calendar_add_on_ticketing_admin_pages_order_edit_ticket_row_actions', [ $this, 'ticket_row_edit_action' ], 10, 3 );
	}

	/**
	 * Add an "Edit" action jumping from a ticket row to that ticket's panel.
	 *
	 * The tickets table knows only ticket ids, while panel ids are built from the
	 * attendee keys RespondentResolver mints, so the anchor is resolved here where
	 * both are known. A ticket with no panel gets no link rather than a dead anchor.
	 *
	 * @since 3.13.0
	 *
	 * @param array  $actions Action slug => HTML.
	 * @param object $ticket  The ticket.
	 * @param object $order   The order.
	 *
	 * @return array
	 */
	public function ticket_row_edit_action( $actions, $ticket, $order ) {

		$key = $this->attendee_key_for_ticket( $order, (int) $ticket->id );

		if ( $key === '' ) {
			return $actions;
		}

		$actions['sc-regform-edit'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_attr( '#' . self::panel_id( $key ) ),
			esc_html__( 'Edit', 'sugar-calendar-lite' )
		);

		return $actions;
	}

	/**
	 * The attendee key of the panel rendered for one ticket, or '' if there is none.
	 *
	 * @since 3.13.0
	 *
	 * @param object $order     The order.
	 * @param int    $ticket_id The ticket id.
	 *
	 * @return string
	 */
	private function attendee_key_for_ticket( $order, $ticket_id ) {

		$respondents = $this->respondents_for_order( $order );

		foreach ( $respondents['attendees'] as $respondent ) {

			if ( (int) $respondent['host_row_id'] === $ticket_id ) {
				return (string) $respondent['attendee_key'];
			}
		}

		return '';
	}

	/**
	 * The DOM id of one respondent's panel.
	 *
	 * Shared by the panel and anything linking to it, so anchors cannot drift.
	 *
	 * @since 3.13.0
	 *
	 * @param string $key Attendee key.
	 *
	 * @return string
	 */
	private static function panel_id( $key ) {

		return 'sc-regform-panel-' . sanitize_html_class( $key );
	}

	/**
	 * Refuse the whole order update when this page's fields are not valid.
	 *
	 * Runs before anything is written, so a refused submission changes nothing: not the
	 * answers, the attendee names, or the order status. The returned value only vetoes
	 * the update; the errors reach the organizer through the transient, since view.php
	 * redirects immediately.
	 *
	 * @since 3.13.0
	 *
	 * @param array  $errors   Reasons collected so far by other consumers.
	 * @param int    $order_id The order id.
	 * @param object $order    The order.
	 *
	 * @return array
	 */
	public function validate( $errors, $order_id, $order ) {

		$respondents = $this->respondents_for_order( $order );

		$flat   = $respondents['attendees'];
		$flat[] = $respondents['main'];

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- view.php's verify_order_update_request() verified the nonce before this filter fired.
		$posted = $_POST;

		$found = [];

		foreach ( $this->validate_attendees( $flat, $posted ) as $key => $fields ) {
			$found[ $key ]['identity'] = $fields;
		}

		$answer_errors = AnswerSaveHandler::validate(
			empty( $order->event_id ) ? 0 : (int) $order->event_id,
			$flat,
			$posted
		);

		foreach ( $answer_errors as $key => $fields ) {
			$found[ $key ]['answers'] = $fields;
		}

		if ( $found === [] ) {
			return $errors;
		}

		// 5 minutes: survives the redirect, but no stale error on a later visit.
		set_transient( self::errors_transient_key( (int) $order_id ), $found, 5 * MINUTE_IN_SECONDS );

		return array_merge( (array) $errors, $found );
	}

	/**
	 * Collect every reason an attendee's posted identity fields must be refused.
	 *
	 * Every attendee this page submits must carry a name and a valid email — including
	 * one that reached the order anonymously, because the checkout's
	 * `attendee_fields_is_required` setting was off. Consequence, accepted
	 * deliberately: an order with unnamed attendees cannot be updated until they are
	 * named.
	 *
	 * @since 3.13.0
	 *
	 * @param array $respondents Respondents.
	 * @param array $posted      The raw POST array.
	 *
	 * @return array<string,array<string,string>> Error codes keyed by attendee key
	 *                                            then 'name'/'email'.
	 */
	private function validate_attendees( array $respondents, array $posted ) {

		$submitted = self::submitted_identity( $posted );

		if ( $submitted === [] ) {
			return [];
		}

		$errors = [];

		foreach ( $respondents as $respondent ) {

			$key = (string) $respondent['attendee_key'];

			if ( ! isset( $submitted[ $key ] ) || ! is_array( $submitted[ $key ] ) ) {
				continue;
			}

			// The purchaser respondent is not a ticket and renders no identity fields,
			// so a POST carrying them is not something this page produced.
			if ( empty( $respondent['host_row_id'] ) ) {
				continue;
			}

			$field_errors = self::identity_errors_for( $respondent, $submitted[ $key ] );

			if ( $field_errors !== [] ) {
				$errors[ $key ] = $field_errors;
			}
		}

		return $errors;
	}

	/**
	 * One attendee's identity errors.
	 *
	 * @since 3.13.0
	 *
	 * @param array $respondent The respondent this payload belongs to.
	 * @param array $fields     Posted first_name / last_name / email.
	 *
	 * @return array<string,string> Error codes keyed by 'name'/'email'.
	 */
	private static function identity_errors_for( array $respondent, array $fields ) {

		$data     = self::attendee_data( $fields );
		$attendee = empty( $respondent['attendee_id'] ) ? null : get_attendee( (int) $respondent['attendee_id'] );
		$errors   = [];

		// Each input is refused for one of two reasons: it is empty where the checkout
		// asks for it, or the edit would erase a value already stored. The second is not
		// implied by the first — it describes losing collected data rather than never
		// collecting it, so it holds even where identity is optional.
		//
		// One error per input: a typo is the actionable half, so it wins over both.
		if ( $data['email'] !== '' && ! is_email( $data['email'] ) ) {
			$errors['email'] = 'invalid_email';
		} elseif ( $data['email'] === '' && ( self::identity_required() || self::attendee_value( $attendee, 'email' ) !== '' ) ) {
			$errors['email'] = 'required_email';
		}

		$missing = self::identity_required() && $data['first_name'] === '' && $data['last_name'] === '';

		if ( $missing || self::name_erased( $data, $attendee ) ) {
			$errors['name'] = 'required_name';
		}

		return $errors;
	}

	/**
	 * Whether an attendee this page saves must carry a name and email.
	 *
	 * Follows the checkout's own "Attendee Information is Required" setting: an order
	 * that was allowed to collect anonymous attendees must stay editable without
	 * inventing names for them. The marker on the inputs reads the same method, so
	 * what the form asterisks and what the save refuses can never drift apart.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	private static function identity_required() {

		return (bool) get_setting( 'attendee_fields_is_required', false );
	}

	/**
	 * Whether this submission would erase a stored half of the name.
	 *
	 * Checked per half, since wp_sc_attendees stores first and last separately and the
	 * row renders one input for each. Treating the pair as one value let an edit
	 * silently drop a first name because the last name was still there.
	 *
	 * @since 3.13.0
	 *
	 * @param array       $data     Sanitized posted name and email.
	 * @param object|null $attendee The stored attendee record, or null.
	 *
	 * @return bool
	 */
	private static function name_erased( array $data, $attendee ) {

		foreach ( [ 'first_name', 'last_name' ] as $half ) {

			if ( $data[ $half ] === '' && self::attendee_value( $attendee, $half ) !== '' ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The identity half of the POST, unslashed.
	 *
	 * @since 3.13.0
	 *
	 * @param array $posted The raw POST array.
	 *
	 * @return array
	 */
	private static function submitted_identity( array $posted ) {

		if ( ! isset( $posted[ self::ATTENDEE_POST_KEY ] ) || ! is_array( $posted[ self::ATTENDEE_POST_KEY ] ) ) {
			return [];
		}

		return (array) wp_unslash( $posted[ self::ATTENDEE_POST_KEY ] );
	}

	/**
	 * Persist the registration part of an order update.
	 *
	 * Runs on sc_et_admin_order_updated, after view.php verified the nonce and the
	 * manage_options capability, so those are deliberately not re-checked. Only
	 * reached on a submission validate() accepted; the write path still collects
	 * errors so a caller skipping that pre-flight is refused too.
	 *
	 * @since 3.13.0
	 *
	 * @param int    $order_id The order id.
	 * @param object $order    The order.
	 */
	public function save( $order_id, $order ) {

		$respondents = $this->respondents_for_order( $order );

		// Main last, so attendee order stays intact in the error map.
		$flat   = $respondents['attendees'];
		$flat[] = $respondents['main'];

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- view.php's verify_order_update_request() verified the nonce before this action fired.
		$posted = $_POST;

		$errors = $this->write( $order, $order_id, $flat, $posted );

		if ( $errors === [] ) {
			return;
		}

		// 5 minutes: survives the redirect, but no stale error on a later visit.
		set_transient( self::errors_transient_key( (int) $order_id ), $errors, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Write both halves of the submission and collect what each refused.
	 *
	 * Identity and answer errors stay in separate namespaces: the former render beside
	 * the name/email inputs, the latter are looked up by schema field id.
	 *
	 * @since 3.13.0
	 *
	 * @param object $order    The order.
	 * @param int    $order_id The order id.
	 * @param array  $flat     Every respondent this page rendered.
	 * @param array  $posted   The raw POST array.
	 *
	 * @return array Errors by attendee key, then namespace, then field.
	 */
	private function write( $order, $order_id, array $flat, array $posted ) {

		$errors = [];

		foreach ( $this->save_attendees( $flat, $posted ) as $key => $fields ) {
			$errors[ $key ]['identity'] = $fields;
		}

		// Re-resolve: save_attendees() can have created an attendee record and relinked
		// its ticket, so writing answers against $flat would store attendee_id 0.
		$this->forget_respondents( (int) $order_id );

		$resolved = $this->respondents_for_order( $order );
		$flat     = $resolved['attendees'];
		$flat[]   = $resolved['main'];

		$answer_errors = AnswerSaveHandler::handle(
			empty( $order->event_id ) ? 0 : (int) $order->event_id,
			'order',
			(int) $order_id,
			$flat,
			$posted
		);

		foreach ( $answer_errors as $key => $fields ) {
			$errors[ $key ]['answers'] = $fields;
		}

		return $errors;
	}

	/**
	 * Write each attendee's edited name and email.
	 *
	 * @since 3.13.0
	 *
	 * @param array $respondents Respondents.
	 * @param array $posted      The raw POST array.
	 *
	 * @return array<string,array<string,string>> Errors keyed by attendee key then field.
	 */
	private function save_attendees( array $respondents, array $posted ) {

		$submitted = self::submitted_identity( $posted );

		if ( $submitted === [] ) {
			return [];
		}

		$errors = [];

		foreach ( $respondents as $respondent ) {

			$key = (string) $respondent['attendee_key'];

			if ( ! isset( $submitted[ $key ] ) || ! is_array( $submitted[ $key ] ) ) {
				continue;
			}

			$error = $this->save_one_attendee( $respondent, $submitted[ $key ] );

			if ( $error !== '' ) {
				$errors[ $key ] = [ 'email' => $error ];
			}
		}

		return $errors;
	}

	/**
	 * Write one attendee's identity fields.
	 *
	 * The write target is $respondent['attendee_id'], resolved from this order's own
	 * tickets. The form carries no id field: a crafted one could rewrite any attendee.
	 *
	 * @since 3.13.0
	 *
	 * @param array $respondent The respondent this payload belongs to.
	 * @param array $fields     Posted first_name / last_name / email.
	 *
	 * @return string An error code, or '' on success.
	 */
	private function save_one_attendee( array $respondent, array $fields ) {

		$data = self::attendee_data( $fields );

		// Fail loud rather than silently dropping a typo'd address.
		if ( $data['email'] !== '' && ! is_email( $data['email'] ) ) {
			return 'invalid_email';
		}

		$attendee_id = empty( $respondent['attendee_id'] ) ? 0 : (int) $respondent['attendee_id'];

		if ( $attendee_id > 0 ) {
			update_attendee( $attendee_id, $data );

			return '';
		}

		$this->create_attendee( $respondent, $data );

		return '';
	}

	/**
	 * One attendee's posted identity fields, sanitized.
	 *
	 * @since 3.13.0
	 *
	 * @param array $fields Posted first_name / last_name / email.
	 *
	 * @return array Always all three keys, so callers never have to re-check.
	 */
	private static function attendee_data( array $fields ) {

		return [
			'first_name' => isset( $fields['first_name'] ) ? sanitize_text_field( $fields['first_name'] ) : '',
			'last_name'  => isset( $fields['last_name'] ) ? sanitize_text_field( $fields['last_name'] ) : '',
			'email'      => isset( $fields['email'] ) ? sanitize_text_field( $fields['email'] ) : '',
		];
	}

	/**
	 * Create a record for an attendee that had none, and link it to its ticket.
	 *
	 * A ticket bought without attendee details has attendee_id 0 and no
	 * wp_sc_attendees row, so add_attendee() alone would leave a record nothing
	 * references and each save would mint another. The ticket link is what sticks.
	 *
	 * @since 3.13.0
	 *
	 * @param array $respondent The respondent, carrying its ticket id.
	 * @param array $data       Sanitized name and email.
	 */
	private function create_attendee( array $respondent, array $data ) {

		// An untouched panel's blank inputs must not mint an empty attendee.
		if ( $data['first_name'] === '' && $data['last_name'] === '' && $data['email'] === '' ) {
			return;
		}

		// Ticket resolved before the insert: the purchaser's host_row_id is always 0, so
		// inserting first let a POST carrying sc_regform_attendee[main][…] mint an
		// unreferenced attendee row on every Update.
		$ticket_id = empty( $respondent['host_row_id'] ) ? 0 : (int) $respondent['host_row_id'];

		if ( $ticket_id <= 0 ) {
			return;
		}

		$attendee_id = (int) add_attendee( $data );

		if ( $attendee_id <= 0 ) {
			return;
		}

		update_ticket( $ticket_id, [ 'attendee_id' => $attendee_id ] );
	}

	/**
	 * Drop view.php's notice when this one takes over.
	 *
	 * On admin_notices rather than in hooks(): the add-on registers its callback after
	 * this page's hooks() runs, so an earlier removal finds nothing to remove.
	 *
	 * @since 3.13.0
	 */
	public function maybe_replace_host_notice() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		if ( ! $this->replaces_host_notice() ) {
			return;
		}

		// A string callback, so it must match the one hooks.php registered verbatim.
		remove_action( 'admin_notices', 'Sugar_Calendar\AddOn\Ticketing\Admin\notices' );
	}

	/**
	 * Whether this page's own notice takes over view.php's "Order not updated.".
	 *
	 * Both report the one failure, and only ours can name the attendee at fault — so on
	 * a refusal this feature caused, the generic one goes and its sentence is folded
	 * into ours. Reading the errors here is what clears the transient; render_notice()
	 * reuses the memo.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	private function replaces_host_notice() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$notice   = isset( $_GET['sc-notice-id'] ) ? sanitize_key( $_GET['sc-notice-id'] ) : '';
		$type     = isset( $_GET['sc-notice-type'] ) ? sanitize_key( $_GET['sc-notice-type'] ) : '';
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $notice !== 'order-update' || $type === 'updated' ) {
			return false;
		}

		return $this->errors_for_order_id( $order_id ) !== [];
	}

	/**
	 * Tell the organizer which panels were refused, and where to look.
	 *
	 * Rendered from the errors transient: the redirect target is a new request, and
	 * view.php's generic "Order not updated." cannot say which attendee is at fault.
	 *
	 * @since 3.13.0
	 *
	 * @param object $order The order.
	 */
	public function render_notice( $order ) {

		$errors = $this->errors( $order );

		if ( $errors === [] ) {
			return;
		}

		$links = $this->notice_links( $order, $errors );

		echo '<div class="notice notice-error sc-regform-admin-notice"><p>';

		// Carries view.php's sentence too — see maybe_replace_host_notice(). Names the
		// panels rather than the order: a status change on the same submit still went
		// through, so "changes not saved" would be wrong.
		if ( $links === [] ) {
			echo esc_html__( 'Attendee details not saved. Please fill the required fields and retry.', 'sugar-calendar-lite' );
		} else {
			printf(
				/* translators: %s - the panels at fault, each linking to itself, e.g. "Attendee #1 and Attendee #2". */
				esc_html__( 'Attendee details not saved. Please fill the required fields for %s and retry.', 'sugar-calendar-lite' ),
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each link is escaped as it is built.
				wp_sprintf( '%l', $links )
			);
		}

		echo '</p></div>';
	}

	/**
	 * The "jump to the panel that needs attention" links, one per panel at fault.
	 *
	 * Driven by the label map rather than the error keys, so a key with no panel on
	 * this page (a per-attendee form's purchaser, or one a crafted POST invented)
	 * contributes no dead anchor.
	 *
	 * @since 3.13.0
	 *
	 * @param object $order  The order.
	 * @param array  $errors Errors by attendee key.
	 *
	 * @return string[] Escaped anchor HTML.
	 */
	private function notice_links( $order, array $errors ) {

		$links = [];

		foreach ( $this->panel_labels( $order ) as $key => $label ) {

			if ( ! isset( $errors[ $key ] ) ) {
				continue;
			}

			$links[] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_attr( '#' . self::panel_id( $key ) ),
				esc_html( $label )
			);
		}

		return $links;
	}

	/**
	 * The panel title of every respondent that has one, keyed by attendee key.
	 *
	 * @since 3.13.0
	 *
	 * @param object $order The order.
	 *
	 * @return array<string,string>
	 */
	private function panel_labels( $order ) {

		$respondents = $this->respondents_for_order( $order );
		$labels      = [];

		if ( self::renders_purchaser_panel( $this->schema( $order ) ) ) {
			$labels[ (string) $respondents['main']['attendee_key'] ] = self::purchaser_label( $order );
		}

		foreach ( $respondents['attendees'] as $respondent ) {
			$labels[ (string) $respondent['attendee_key'] ] = self::attendee_label( $respondent );
		}

		return $labels;
	}

	/**
	 * Whether the purchaser-level answers panel renders for this schema.
	 *
	 * Shared by the panel and the notice's link map, so the notice cannot link to a
	 * panel the page decided not to render.
	 *
	 * @since 3.13.0
	 *
	 * @param array $schema The stored schema.
	 *
	 * @return bool
	 */
	private static function renders_purchaser_panel( array $schema ) {

		$collect = isset( $schema['collect'] ) ? (string) $schema['collect'] : 'main_attendee';

		return ! empty( $schema['enabled'] ) && ! empty( $schema['fields'] ) && $collect === 'main_attendee';
	}

	/**
	 * The purchaser panel's title.
	 *
	 * @since 3.13.0
	 *
	 * @param object $order The order.
	 *
	 * @return string Raw; the caller escapes it.
	 */
	private static function purchaser_label( $order ) {

		return sprintf(
			/* translators: %s - the purchaser's name. */
			__( 'Registration — %s', 'sugar-calendar-lite' ),
			RespondentNaming::purchaser( $order )
		);
	}

	/**
	 * One attendee panel's title.
	 *
	 * @since 3.13.0
	 *
	 * @param array $respondent The respondent.
	 *
	 * @return string Raw; the caller escapes it.
	 */
	private static function attendee_label( array $respondent ) {

		return sprintf(
			/* translators: %d - the attendee's position in the order. */
			__( 'Attendee #%d', 'sugar-calendar-lite' ),
			(int) $respondent['position'] + 1
		);
	}

	/**
	 * Every panel this feature contributes to the order page.
	 *
	 * One callback rather than one per hook, so the respondents resolve once and the
	 * error transient (deleted on first read) is read once.
	 *
	 * @since 3.13.0
	 *
	 * @param object $order The order.
	 */
	public function render_panels( $order ) {

		// Before the panels, so this wins the one enqueue: the Status control shares
		// this form, and view.php applies a status change even when it refuses the
		// panels, so a blank answer must not hold the submit that carries it.
		AnswerValidation::enqueue( false );

		$respondents = $this->respondents_for_order( $order );
		$schema      = $this->schema( $order );
		$errors      = $this->errors( $order );

		$this->purchaser_panel( $order, $schema, $respondents['main'], $errors );

		foreach ( $respondents['attendees'] as $respondent ) {
			$this->attendee_panel( $schema, $respondent, $errors, $this->asks_attendee( $order, $schema, $respondent ) );
		}

		foreach ( $respondents['orphans'] as $orphan ) {

			$this->open_panel(
				__( 'Attendee — (removed)', 'sugar-calendar-lite' ),
				'sc-regform-admin-attendee sc-regform-admin-attendee--orphan',
				(string) $orphan['attendee_key']
			);

			ResponsesPanel::render( $schema, $orphan );

			$this->close_panel();
		}
	}

	/**
	 * The purchaser-level answers panel.
	 *
	 * Gated on the schema's `collect`, not on a stored row existing: an event whose
	 * form was enabled after this order was placed has no main row, and the organiser
	 * still needs blank controls to record an answer taken over the phone.
	 *
	 * @since 3.13.0
	 *
	 * @param object $order  The order.
	 * @param array  $schema The stored schema.
	 * @param array  $main   The main respondent (always present).
	 * @param array  $errors Errors by attendee key.
	 */
	private function purchaser_panel( $order, array $schema, array $main, array $errors ) {

		if ( ! self::renders_purchaser_panel( $schema ) ) {
			return;
		}

		// Titles are passed raw because open_panel() escapes them; escaping here too
		// would mangle any translation containing an apostrophe or ampersand.
		$this->open_panel(
			self::purchaser_label( $order ),
			'sc-regform-admin-purchaser',
			(string) $main['attendee_key']
		);

		ResponsesPanel::render( $schema, $main, $this->answer_errors( $errors, $main['attendee_key'] ) );

		$this->close_panel();
	}

	/**
	 * One attendee's panel: identity fields, then the answers block.
	 *
	 * Renders for every ticket, including on an order whose event has no form: the
	 * name and email fields are ticketing data, and ResponsesPanel renders nothing
	 * when there is no form.
	 *
	 * @since 3.13.0
	 *
	 * @param array $schema     The stored schema.
	 * @param array $respondent The respondent.
	 * @param array $errors     Errors by attendee key.
	 * @param bool  $asks       Whether the form asks this attendee anything.
	 */
	private function attendee_panel( array $schema, array $respondent, array $errors, $asks = true ) {

		$key = (string) $respondent['attendee_key'];

		$this->open_panel(
			self::attendee_label( $respondent ),
			'sc-regform-admin-attendee',
			$key
		);

		$this->attendee_identity_fields( $respondent, $this->identity_errors( $errors, $key ) );

		if ( $asks ) {
			ResponsesPanel::render( $schema, $respondent, $this->answer_errors( $errors, $key ) );
		}

		$this->close_panel();
	}

	/**
	 * Whether the form asks this attendee anything.
	 *
	 * The admin counterpart of Frontend\Renderer::applicable_attendees(): purchaser-
	 * level collection asks attendees nothing, and ticket targeting asks only the
	 * types it names. Controls for an attendee the form skips invite answers that no
	 * surface reads back — the receipt, the emails and the export all apply the same
	 * two rules.
	 *
	 * @since 3.13.0
	 *
	 * @param object $order      The order.
	 * @param array  $schema     The stored schema.
	 * @param array  $respondent The respondent.
	 *
	 * @return bool
	 */
	private function asks_attendee( $order, array $schema, array $respondent ) {

		$collect = isset( $schema['collect'] ) ? (string) $schema['collect'] : 'main_attendee';

		if ( $collect !== 'each_attendee' ) {
			return false;
		}

		$types = isset( $schema['ticket_types'] ) ? $schema['ticket_types'] : 'all';

		// 'all' — and any single-ticket event, whose tickets carry type 0.
		if ( ! is_array( $types ) ) {
			return true;
		}

		// Targeting can be narrowed after an order was placed. An answer already
		// given stays editable, or it would be stranded: unreachable here, still in
		// the export.
		if ( ! empty( $respondent['answers'] ) ) {
			return true;
		}

		$order_id  = empty( $order->id ) ? 0 : (int) $order->id;
		$ticket_id = empty( $respondent['host_row_id'] ) ? 0 : (int) $respondent['host_row_id'];
		$type      = isset( $this->ticket_types[ $order_id ][ $ticket_id ] )
			? (int) $this->ticket_types[ $order_id ][ $ticket_id ]
			: 0;

		return in_array( $type, array_map( 'intval', $types ), true );
	}

	/**
	 * One respondent's answer errors (field id => code).
	 *
	 * @since 3.13.0
	 *
	 * @param array  $errors All errors, by attendee key.
	 * @param string $key    Attendee key.
	 *
	 * @return array
	 */
	private function answer_errors( array $errors, $key ) {

		return isset( $errors[ $key ]['answers'] ) ? (array) $errors[ $key ]['answers'] : [];
	}

	/**
	 * One respondent's identity errors ('name'/'email' => code).
	 *
	 * Kept apart from the answer errors: these render beside their inputs, while
	 * answer errors are looked up by schema field id.
	 *
	 * @since 3.13.0
	 *
	 * @param array  $errors All errors, by attendee key.
	 * @param string $key    Attendee key.
	 *
	 * @return array
	 */
	private function identity_errors( array $errors, $key ) {

		return isset( $errors[ $key ]['identity'] ) ? (array) $errors[ $key ]['identity'] : [];
	}

	/**
	 * Name and email inputs for one attendee.
	 *
	 * Two name inputs, since wp_sc_attendees stores first and last separately. An
	 * attendee with no record still gets inputs; filling them creates it on save.
	 * No hidden id field: the save resolves the attendee id from the order's tickets.
	 *
	 * @since 3.13.0
	 *
	 * @param array $respondent The respondent.
	 * @param array $errors     Identity errors: 'name'/'email' => code.
	 */
	private function attendee_identity_fields( array $respondent, array $errors ) {

		$attendee = empty( $respondent['attendee_id'] ) ? null : get_attendee( (int) $respondent['attendee_id'] );
		$key      = (string) $respondent['attendee_key'];
		$name     = self::ATTENDEE_POST_KEY . '[' . $key . ']';
		$id       = 'sc-regform-attendee-' . sanitize_html_class( $key );

		// Marked exactly when validate_attendees() enforces it — the marker and the
		// refusal must state the same rule.
		$required = self::identity_required();

		// The Name row labels the first of its two inputs, since a <label for> may
		// point at exactly one control.
		$this->open_row( __( 'Name', 'sugar-calendar-lite' ), $id . '-first-name', $required );

		// The pair is wrapped so the two inputs and the gap between them add up to the
		// width of the single field on every row below.
		printf(
			'<div class="sc-regform-admin-name-pair"><input type="text" id="%4$s-first-name" class="sc-regform-admin-name-input" name="%1$s[first_name]" value="%2$s" placeholder="%5$s" /><input type="text" id="%4$s-last-name" class="sc-regform-admin-name-input" name="%1$s[last_name]" value="%3$s" placeholder="%6$s" aria-label="%6$s" /></div>',
			esc_attr( $name ),
			esc_attr( self::attendee_value( $attendee, 'first_name' ) ),
			esc_attr( self::attendee_value( $attendee, 'last_name' ) ),
			esc_attr( $id ),
			esc_attr__( 'First name', 'sugar-calendar-lite' ),
			esc_attr__( 'Last name', 'sugar-calendar-lite' )
		);

		$this->close_row( isset( $errors['name'] ) ? (string) $errors['name'] : '' );

		$this->open_row( __( 'Email', 'sugar-calendar-lite' ), $id . '-email', $required );

		printf(
			'<input type="email" id="%3$s-email" class="regular-text" name="%1$s[email]" value="%2$s" />',
			esc_attr( $name ),
			esc_attr( self::attendee_value( $attendee, 'email' ) ),
			esc_attr( $id )
		);

		$this->close_row( isset( $errors['email'] ) ? (string) $errors['email'] : '' );
	}

	/**
	 * One property of an attendee record that may not exist.
	 *
	 * A ticket bought without attendee details has no wp_sc_attendees row, so
	 * $attendee is legitimately null and every field renders empty.
	 *
	 * @since 3.13.0
	 *
	 * @param object|null $attendee The attendee record, or null.
	 * @param string      $property Property name.
	 *
	 * @return string
	 */
	private static function attendee_value( $attendee, $property ) {

		return empty( $attendee->$property ) ? '' : (string) $attendee->$property;
	}

	/**
	 * Open one label + control row.
	 *
	 * The caller prints the control between open_row() and close_row() rather than
	 * passing markup in: running server-built markup back through wp_kses() with a
	 * hand-written allowlist silently stripped attributes added later.
	 *
	 * @since 3.13.0
	 *
	 * @param string $label      Raw label text; escaped by the helper.
	 * @param string $control_id Id of the control this labels, when it has one.
	 * @param bool   $required   Whether to mark the row required.
	 */
	private function open_row( $label, $control_id = '', $required = false ) {

		UI::form_table_row_open( $label, $control_id, $required );
	}

	/**
	 * Close a row, optionally with the rejected-value message.
	 *
	 * @since 3.13.0
	 *
	 * @param string $code This row's error code, or '' when it has none.
	 */
	private function close_row( $code ) {

		$message = self::identity_error_message( $code );

		if ( $message !== '' ) {
			// No aria on the paragraph: per admin convention, the notice at the top of
			// the page is what announces the failure.
			printf(
				'<p class="sc-regform-admin-field__error">%1$s</p>',
				esc_html( $message )
			);
		}

		UI::form_table_row_close();
	}

	/**
	 * The organizer-facing message for one identity error code.
	 *
	 * Nothing about the submission was saved, so no message may say a single value
	 * "was not saved": that reads as though the rest of the edit landed.
	 *
	 * @since 3.13.0
	 *
	 * @param string $code Error code.
	 *
	 * @return string Empty for a code with no message, which includes ''.
	 */
	private static function identity_error_message( $code ) {

		// phpcs:disable WPForms.Formatting.EmptyLineBeforeReturn.AddEmptyLineBeforeReturnStatement

		switch ( $code ) {
			case 'invalid_email':
				return __( 'Enter a valid email address.', 'sugar-calendar-lite' );

			case 'required_email':
				return __( 'An email address is required.', 'sugar-calendar-lite' );

			case 'required_name':
				return __( 'A name is required.', 'sugar-calendar-lite' );
		}
		// phpcs:enable WPForms.Formatting.EmptyLineBeforeReturn.AddEmptyLineBeforeReturnStatement

		return '';
	}

	/**
	 * Resolve this order's respondents.
	 *
	 * Tickets are the attendee list: one panel per ticket, in ticket order.
	 *
	 * Memoised per order id because the tickets table fires its row-actions filter
	 * once per ticket, so ticket_row_edit_action() lands here N times on top of
	 * render_panels() and ResponseRepository is an uncached $wpdb wrapper. Keyed by
	 * order id so a request handling two orders cannot cross them.
	 *
	 * @since 3.13.0
	 *
	 * @param object $order The order.
	 *
	 * @return array{main: array, attendees: array[], orphans: array[]}
	 */
	public function respondents_for_order( $order ) {

		$order_id = empty( $order->id ) ? 0 : (int) $order->id;

		if ( isset( $this->respondents[ $order_id ] ) ) {
			return $this->respondents[ $order_id ];
		}

		$attendees = [];

		/*
		 * Not get_order_tickets(): it inherits get_tickets()'s 'number' => 30 default
		 * while ResponseRepository::get_for_order() is unbounded, so on a larger order
		 * every attendee past the 30th would lose its panel and their answers would
		 * render as an untouchable "removed" orphan. 10000 matches the bound
		 * get_attendees_by_order_id() already uses.
		 */
		$tickets = get_tickets(
			[
				'order_id' => $order_id,
				'order'    => 'ASC',
				'number'   => 10000,
			]
		);

		$this->ticket_types[ $order_id ] = [];

		foreach ( (array) $tickets as $ticket ) {
			$ticket_id = empty( $ticket->id ) ? 0 : (int) $ticket->id;

			$attendees[] = [
				'attendee_id' => empty( $ticket->attendee_id ) ? 0 : (int) $ticket->attendee_id,

				// Carried so the save path can link a newly created attendee back to
				// its ticket; see create_attendee().
				'host_row_id' => $ticket_id,
			];

			$this->ticket_types[ $order_id ][ $ticket_id ] = empty( $ticket->ticket_type_id )
				? 0
				: (int) $ticket->ticket_type_id;
		}

		$this->respondents[ $order_id ] = RespondentResolver::resolve( 'order', $order_id, $attendees );

		return $this->respondents[ $order_id ];
	}

	/**
	 * Forget the memoised respondents for one order.
	 *
	 * Called after the identity writes, which can create an attendee record and
	 * relink its ticket, so the answers must write against a rebuilt set.
	 *
	 * @since 3.13.0
	 *
	 * @param int $order_id Order id.
	 */
	private function forget_respondents( $order_id ) {

		unset( $this->respondents[ (int) $order_id ] );
	}

	/**
	 * The schema governing this order's event.
	 *
	 * @since 3.13.0
	 *
	 * @param object $order The order.
	 *
	 * @return array Never null; see the cast.
	 */
	private function schema( $order ) {

		// SchemaRepository::get() returns null when the meta is absent or corrupt, the
		// common case since most ticketing sites have no registration form. Casting
		// once here keeps every consumer's array type honest.
		return (array) SchemaRepository::get( empty( $order->event_id ) ? 0 : (int) $order->event_id );
	}

	/**
	 * Read and clear the errors left by a rejected save.
	 *
	 * @since 3.13.0
	 *
	 * @param object $order The order.
	 *
	 * @return array
	 */
	private function errors( $order ) {

		return $this->errors_for_order_id( empty( $order->id ) ? 0 : (int) $order->id );
	}

	/**
	 * The same read, for the callers that have an id rather than the order.
	 *
	 * @since 3.13.0
	 *
	 * @param int $order_id Order id.
	 *
	 * @return array
	 */
	private function errors_for_order_id( $order_id ) {

		if ( $this->errors !== null ) {
			return $this->errors;
		}

		$key    = self::errors_transient_key( (int) $order_id );
		$errors = get_transient( $key );

		if ( ! is_array( $errors ) ) {
			$this->errors = [];

			return $this->errors;
		}

		delete_transient( $key );

		$this->errors = $errors;

		return $this->errors;
	}

	/**
	 * Transient key for one order's save errors, scoped to the current user.
	 *
	 * Per-user so two admins editing the same order never read each other's
	 * errors.
	 *
	 * @since 3.13.0
	 *
	 * @param int $order_id Order id.
	 *
	 * @return string
	 */
	public static function errors_transient_key( $order_id ) {

		return self::ERRORS_TRANSIENT_PREFIX . (int) $order_id . '_' . get_current_user_id();
	}

	/**
	 * Open one respondent's panel.
	 *
	 * Uses the same postbox helper OrderEdit uses for its own panels. The DOM id comes
	 * from the attendee key rather than a counter because postboxes.js stores the
	 * collapsed state under it, and a counter would drift when ticket order changes.
	 *
	 * @since 3.13.0
	 *
	 * @param string $title   Raw panel title; escaped by the helper.
	 * @param string $classes Extra classes.
	 * @param string $key     Attendee key, for the DOM id.
	 */
	private function open_panel( $title, $classes, $key ) {

		// Every panel this feature adds carries the shared class: the comp's grid is
		// restated in CSS against it, and core's form-table defaults suit the wider
		// Settings-page column the other panels on this page use.
		UI::postbox_open( self::panel_id( $key ), $title, 'sc-regform-admin-panel ' . $classes );
		UI::form_table_open();
	}

	/**
	 * Close a panel.
	 *
	 * @since 3.13.0
	 */
	private function close_panel() {

		UI::form_table_close();
		UI::postbox_close();
	}
}
