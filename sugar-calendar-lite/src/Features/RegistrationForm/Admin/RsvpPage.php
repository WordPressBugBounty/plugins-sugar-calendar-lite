<?php

namespace Sugar_Calendar\Features\RegistrationForm\Admin;

use Sugar_Calendar\Features\RegistrationForm\Frontend\AnswerRequest;
use Sugar_Calendar\Features\RegistrationForm\SchemaRepository;
use Sugar_Calendar\Helpers\UI;

/**
 * Registration answers on the RSVP attendee editor.
 *
 * The host is sc-rsvp: it owns the panels, their keys and the clone prototype, and
 * fires sugar_calendar_rsvp_admin_attendee_panel inside each one (including the
 * prototype, keyed with Renderer::KEY_PLACEHOLDER). This class renders answers into
 * that seam and writes them back on a successful save, never referencing an sc-rsvp
 * class; everything it needs arrives as a hook argument, the same one-way
 * relationship Frontend\RsvpCheckout has.
 *
 * render_answers() has no placeholder special case: a key the resolver does not know
 * resolves to an empty respondent and renders blank controls, which is what the
 * prototype needs.
 *
 * Markup deviation (spec §6.1): sc-rsvp's own fields are div rows while
 * ResponsesPanel emits form-table rows by contract, so the answers open their own
 * table below them and CSS aligns the two label columns.
 *
 * @since 3.13.0
 */
class RsvpPage {

	/**
	 * Transient prefix for errors that must survive this editor's own re-render.
	 *
	 * Mirrors OrderPage::ERRORS_TRANSIENT_PREFIX's shape but needs its own prefix:
	 * order ids and RSVP post ids are independent sequences that will collide.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const ERRORS_TRANSIENT_PREFIX = 'sc_regform_admin_rsvp_errors_';

	/**
	 * Prefix sc-rsvp builds a saved guest's panel key from ('id' . $attendee_id).
	 *
	 * Duplicated from the add-on's Pages\RsvpEdit::guest_panel_key() rather than called,
	 * since this class names no sc-rsvp class (see the class docblock). The two literals
	 * are pinned together by RsvpPageSaveTest's newly-added-guest coverage, which fails
	 * if either side changes the format alone.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const GUEST_PANEL_KEY_PREFIX = 'id';

	/**
	 * Respondents, memoised per RSVP post id.
	 *
	 * The seam fires once per panel, so an N-guest RSVP would otherwise cost N
	 * SELECTs and N runs of the resolver's bind; ResponseRepository is uncached.
	 *
	 * @since 3.13.0
	 *
	 * @var array[]
	 */
	private $respondents = [];

	/**
	 * Save errors, memoised per RSVP post id.
	 *
	 * Lets errors_for() read and delete the transient at most once per post id per
	 * request: a second panel render must not re-read a transient this request already
	 * deleted, and a second RSVP must not cross-read the first one's errors. isset()
	 * on this array is what distinguishes "not read yet" from "read and found none".
	 *
	 * @since 3.13.0
	 *
	 * @var array<int,array>
	 */
	private $errors = [];

	/**
	 * Register hooks.
	 *
	 * @since 3.13.0
	 */
	public function hooks() {

		// The clone prototype comes through this same action, so there is nothing
		// else to hook for it.
		add_action( 'sugar_calendar_rsvp_admin_attendee_panel', [ $this, 'render_answers' ], 10, 3 );

		// Fires once per save, after sc-rsvp has committed every attendee. See
		// save()'s docblock for why the answers must wait for that map.
		add_action( 'sugar_calendar_rsvp_admin_attendees_saved', [ $this, 'save' ], 10, 3 );
	}

	/**
	 * Render one panel's answers block.
	 *
	 * Bails silently on a brand-new RSVP (Rsvp::get() returns false for the Add New
	 * screen's auto-draft) and on an event with no enabled form; both are supported
	 * states, not errors.
	 *
	 * @since 3.13.0
	 *
	 * @param string $panel_key   The panel key: 'main', 'id{n}', or the clone prototype's placeholder.
	 * @param int    $attendee_id The guest's attendee id, or 0 for 'main' and the placeholder.
	 * @param mixed  $rsvp        The Rsvp model, or false on a brand-new RSVP.
	 */
	public function render_answers( $panel_key, $attendee_id, $rsvp ) {

		if ( empty( $rsvp ) || empty( $rsvp->event_id ) ) {
			return;
		}

		$schema = (array) SchemaRepository::get( (int) $rsvp->event_id );

		// ResponsesPanel::render() re-checks this; bailing here too skips the
		// resolver's DB round trip for an event with no form.
		if ( empty( $schema['enabled'] ) || empty( $schema['fields'] ) ) {
			return;
		}

		// A form that collects once has no per-guest answers.
		if (
			(string) $panel_key !== AnswerRequest::MAIN_KEY
			&&
			( ! isset( $schema['collect'] ) || $schema['collect'] !== 'each_attendee' )
		) {
			return;
		}

		$respondents = $this->respondents_for_rsvp( $rsvp );

		$respondent = (string) $panel_key === AnswerRequest::MAIN_KEY
			? $respondents['main']
			: $this->find_respondent( $respondents['attendees'], (int) $attendee_id );

		UI::form_table_open();

		ResponsesPanel::render( $schema, $respondent, $this->errors_for( $rsvp, $panel_key ), (string) $panel_key );

		UI::form_table_close();
	}

	/**
	 * Persist the answers submitted from the RSVP editor.
	 *
	 * Driven by sc-rsvp's own save, not save_post, because the host hands over a
	 * `panel_key => attendee_id` map. A guest added in the browser has no attendee id
	 * until this save creates it, and only save_metabox() knows which submitted entry
	 * became which id. The seam fires only on a fully successful save.
	 *
	 * Guarantee: the pre-flight below refuses a submission before any answer is
	 * written, so a rejected edit leaves no half-saved answers. It cannot cover the
	 * identity edits from the same submission, which save_metabox() has already
	 * committed by the time this runs; sc-rsvp has no pre-write veto seam.
	 *
	 * @since 3.13.0
	 *
	 * @param int   $post_id      RSVP post id.
	 * @param mixed $rsvp         The saved Rsvp model.
	 * @param array $attendee_ids Panel key => attendee row id.
	 */
	public function save( $post_id, $rsvp, array $attendee_ids ) {

		// Defence in depth: redundant today, since the seam fires only after
		// save_metabox()'s own nonce + manage_options gate, but a listener that
		// trusts its caller's gate becomes exploitable when that caller changes.
		// `manage_options` matches Area::save_metabox() (rsvp-admin-cap-gates.md).
		if ( ! $this->request_is_authorised() ) {
			return;
		}

		if ( empty( $rsvp ) || empty( $rsvp->event_id ) ) {
			return;
		}

		// Rebuilt, never reused: the memoised set was resolved during this screen's
		// render, before the guests this request just created existed.
		unset( $this->respondents[ (int) $post_id ] );

		$respondents = $this->respondents_for_rsvp( $rsvp );
		$flat        = $this->flatten( $respondents );
		$by_panel    = $this->panel_keys_to_attendee_keys( $respondents, $attendee_ids );
		$posted      = $this->posted_answers_by_attendee_key( $by_panel );

		/*
		 * Pre-flight, the same rule OrderPage applies: collect every reason to refuse
		 * before any answer is written, so a rejected edit never half-saves. handle()'s
		 * own validation is deliberately relaxed (an organizer may clear a field the
		 * attendee left blank); validate() adds the one rule worth refusing over, an
		 * empty answer that would destroy a stored one.
		 */
		$errors = AnswerSaveHandler::validate( (int) $rsvp->event_id, $flat, $posted );

		if ( $errors !== [] ) {
			$this->store_errors( (int) $post_id, $errors, $by_panel, $attendee_ids );

			return;
		}

		$errors = AnswerSaveHandler::handle( (int) $rsvp->event_id, 'rsvp', (int) $post_id, $flat, $posted );

		// Surfaced, not swallowed. The pre-flight makes most of these unreachable, but
		// ResponseValidator still returns type/option errors on the relaxed path.
		if ( $errors !== [] ) {
			$this->store_errors( (int) $post_id, $errors, $by_panel, $attendee_ids );
		}
	}

	/**
	 * Stash this submission's errors for the panels that will render next.
	 *
	 * The re-key is load-bearing. Errors arrive keyed by this submission's panel keys,
	 * and a guest the browser just added carries the JS-minted 'new{n}' (single-edit.js).
	 * On re-render that guest is saved, so its panel is registered as 'id{attendee_id}'
	 * instead; without the re-key errors_for() misses, the transient is already
	 * consumed, and the organiser sees blank answers with no error at all.
	 *
	 * Keys already in render space map to themselves, so this is safe for every
	 * submission.
	 *
	 * @since 3.13.0
	 *
	 * @param int   $post_id      RSVP post id.
	 * @param array $errors       Field errors keyed by attendee key.
	 * @param array $by_panel     Panel key => attendee key, for this submission.
	 * @param array $attendee_ids Panel key => attendee row id, from the host's seam.
	 */
	private function store_errors( $post_id, array $errors, array $by_panel, array $attendee_ids ) {

		set_transient(
			self::errors_transient_key( $post_id ),
			$this->to_render_panel_keys( $this->to_panel_keys( $errors, $by_panel ), $attendee_ids ),
			MINUTE_IN_SECONDS * 5
		);
	}

	/**
	 * Re-key an errors map into the panel keys the next render will use.
	 *
	 * A guest created by this submission changes key ('new3' -> 'id8'); everything else
	 * keeps the key it had. See store_errors() for why this matters.
	 *
	 * @since 3.13.0
	 *
	 * @param array $errors       Field errors keyed by this submission's panel keys.
	 * @param array $attendee_ids Panel key => attendee row id, from the host's seam.
	 *
	 * @return array Field errors keyed by the panel keys the next render will use.
	 */
	private function to_render_panel_keys( array $errors, array $attendee_ids ) {

		$out = [];

		foreach ( $errors as $panel_key => $fields ) {

			$panel_key = (string) $panel_key;

			// 'main' is the same key on both sides; a guest's comes from the id the host
			// just assigned. A guest missing from the map keeps its submitted key: it
			// will not match a rendered panel, but dropping it loses the error outright.
			if ( $panel_key !== AnswerRequest::MAIN_KEY && ! empty( $attendee_ids[ $panel_key ] ) ) {
				$panel_key = self::GUEST_PANEL_KEY_PREFIX . (int) $attendee_ids[ $panel_key ];
			}

			$out[ $panel_key ] = $fields;
		}

		return $out;
	}

	/**
	 * Whether the current request is allowed to write to this editor.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	private function request_is_authorised() {

		return current_user_can( 'manage_options' );
	}

	/**
	 * Every respondent this RSVP's answers may be written against.
	 *
	 * Same shape OrderPage::write() builds: guests, then main. Orphans are excluded;
	 * AnswerSaveHandler::is_editable() would refuse them anyway.
	 *
	 * @since 3.13.0
	 *
	 * @param array $respondents Resolved respondents.
	 *
	 * @return array[]
	 */
	private function flatten( array $respondents ) {

		$flat   = $respondents['attendees'];
		$flat[] = $respondents['main'];

		return $flat;
	}

	/**
	 * Panel key => attendee key, for this request's submission.
	 *
	 * The DOM is keyed by panel (main / id{n} / new{n}) because a browser-added guest
	 * has no attendee id, and so no attendee key, until the save that just created it.
	 * AnswerSaveHandler works in attendee keys, and this is the one place the two meet.
	 * It is built entirely from the host's map: no email matching, no positional
	 * fallback, no parsing of the key's digits. A panel key the host did not report is
	 * dropped, covering crafted and stale keys alike.
	 *
	 * Built once and passed to both re-key directions, so they cannot disagree.
	 *
	 * @since 3.13.0
	 *
	 * @param array $respondents  Resolved respondents.
	 * @param array $attendee_ids Panel key => attendee row id, from the seam.
	 *
	 * @return array<string,string>
	 */
	private function panel_keys_to_attendee_keys( array $respondents, array $attendee_ids ) {

		$attendee_keys_by_id = $this->attendee_keys_by_attendee_id( $respondents );
		$main_key            = (string) $respondents['main']['attendee_key'];
		$map                 = [];

		foreach ( $attendee_ids as $panel_key => $attendee_id ) {

			$panel_key = (string) $panel_key;

			if ( $panel_key === AnswerRequest::MAIN_KEY ) {
				$map[ $panel_key ] = $main_key;

				continue;
			}

			if ( isset( $attendee_keys_by_id[ (int) $attendee_id ] ) ) {
				$map[ $panel_key ] = $attendee_keys_by_id[ (int) $attendee_id ];
			}
		}

		return $map;
	}

	/**
	 * Attendee id => attendee key, from this RSVP's resolved guest respondents.
	 *
	 * Split out of panel_keys_to_attendee_keys() so that method stays a single pass
	 * over $attendee_ids, which is what the phpcs complexity sniff flagged.
	 *
	 * @since 3.13.0
	 *
	 * @param array $respondents Resolved respondents.
	 *
	 * @return array<int,string>
	 */
	private function attendee_keys_by_attendee_id( array $respondents ) {

		$map = [];

		foreach ( $respondents['attendees'] as $respondent ) {

			if ( $respondent['attendee_id'] !== null ) {
				$map[ (int) $respondent['attendee_id'] ] = (string) $respondent['attendee_key'];
			}
		}

		return $map;
	}

	/**
	 * Re-key the posted answers from panel-key space into attendee-key space.
	 *
	 * @since 3.13.0
	 *
	 * @param array $by_panel Panel key => attendee key, from panel_keys_to_attendee_keys().
	 *
	 * @return array Shaped for AnswerSaveHandler::handle()'s $posted argument.
	 */
	private function posted_answers_by_attendee_key( array $by_panel ) {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- request_is_authorised() plus Area::save_metabox()'s own nonce+cap gate have already run before this seam fires; AnswerSaveHandler::submitted() wp_unslash()es and sanitizes every value before it is used.
		$raw_answers = $_POST[ ResponsesPanel::POST_KEY ] ?? [];
		$posted      = is_array( $raw_answers ) ? $raw_answers : [];

		$by_key = [];

		foreach ( $by_panel as $panel_key => $attendee_key ) {

			if ( isset( $posted[ $panel_key ] ) ) {
				$by_key[ $attendee_key ] = $posted[ $panel_key ];
			}
		}

		return [ ResponsesPanel::POST_KEY => $by_key ];
	}

	/**
	 * Re-key an errors map from attendee-key space back into panel-key space.
	 *
	 * Built by flipping panel_keys_to_attendee_keys()'s map rather than recomputing
	 * one, since two maps over the same panels can disagree.
	 *
	 * @since 3.13.0
	 *
	 * @param array $errors   Field errors keyed by attendee key.
	 * @param array $by_panel Panel key => attendee key.
	 *
	 * @return array Field errors keyed by panel key.
	 */
	private function to_panel_keys( array $errors, array $by_panel ) {

		$panel_keys_by_attendee_key = array_flip( $by_panel );
		$out                        = [];

		foreach ( $errors as $attendee_key => $fields ) {

			if ( isset( $panel_keys_by_attendee_key[ $attendee_key ] ) ) {
				$out[ $panel_keys_by_attendee_key[ $attendee_key ] ] = $fields;
			}
		}

		return $out;
	}

	/**
	 * Transient key for one RSVP's save errors, scoped to the current user.
	 *
	 * Per-user so two admins editing the same RSVP never read each other's errors.
	 *
	 * @since 3.13.0
	 *
	 * @param int $rsvp_post_id RSVP post id.
	 *
	 * @return string
	 */
	public static function errors_transient_key( $rsvp_post_id ) {

		return self::ERRORS_TRANSIENT_PREFIX . (int) $rsvp_post_id . '_' . get_current_user_id();
	}

	/**
	 * Find a guest respondent by attendee id.
	 *
	 * No match (the placeholder's attendee id of 0 never matches a real guest) returns
	 * an empty respondent shape rather than bailing, so the clone prototype still
	 * renders blank controls (RespondentResolver::respondent()'s shape).
	 *
	 * @since 3.13.0
	 *
	 * @param array[] $attendees   Resolved guest respondents.
	 * @param int     $attendee_id The panel's attendee id.
	 *
	 * @return array
	 */
	private function find_respondent( array $attendees, $attendee_id ) {

		foreach ( $attendees as $respondent ) {

			if ( (int) $respondent['attendee_id'] === (int) $attendee_id ) {
				return $respondent;
			}
		}

		return [
			'attendee_key' => '',
			'attendee_id'  => null,
			'host_row_id'  => 0,
			'row_id'       => null,
			'position'     => 0,
			'status'       => '',
			'answers'      => [],
			'is_orphan'    => false,
		];
	}

	/**
	 * This RSVP's respondents, memoised per post id.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed $rsvp The Rsvp model.
	 *
	 * @return array{main: array, attendees: array[], orphans: array[]}
	 */
	private function respondents_for_rsvp( $rsvp ) {

		$post_id = (int) $rsvp->post_id;

		if ( isset( $this->respondents[ $post_id ] ) ) {
			return $this->respondents[ $post_id ];
		}

		$attendees = [];

		foreach ( $rsvp->get_additional_attendees() as $attendee ) {
			$attendees[] = [
				'attendee_id' => empty( $attendee->id ) ? 0 : (int) $attendee->id,

				// RSVP guests have no host row to link back to; the order page uses
				// this for a ticket id.
				'host_row_id' => 0,
			];
		}

		$this->respondents[ $post_id ] = RespondentResolver::resolve( 'rsvp', $post_id, $attendees );

		return $this->respondents[ $post_id ];
	}

	/**
	 * Field-id => error code for one panel, from a rejected save.
	 *
	 * Reads the transient save()'s pre-flight writes, keyed by panel, so an error lands
	 * back on the control the organizer typed into rather than on an attendee key the
	 * DOM never used.
	 *
	 * @since 3.13.0
	 *
	 * @param mixed  $rsvp      The Rsvp model.
	 * @param string $panel_key The panel's key.
	 *
	 * @return array
	 */
	private function errors_for( $rsvp, $panel_key ) {

		$post_id = (int) $rsvp->post_id;

		if ( ! isset( $this->errors[ $post_id ] ) ) {
			$this->errors[ $post_id ] = $this->read_errors_transient( $post_id );
		}

		return isset( $this->errors[ $post_id ][ $panel_key ] ) ? (array) $this->errors[ $post_id ][ $panel_key ] : [];
	}

	/**
	 * Read and clear one RSVP's save-errors transient.
	 *
	 * Split out of errors_for() to keep that method a single lookup, which is what the
	 * phpcs complexity sniff flagged there.
	 *
	 * @since 3.13.0
	 *
	 * @param int $post_id RSVP post id.
	 *
	 * @return array
	 */
	private function read_errors_transient( $post_id ) {

		$key    = self::errors_transient_key( $post_id );
		$errors = get_transient( $key );

		if ( ! is_array( $errors ) ) {
			return [];
		}

		delete_transient( $key );

		return $errors;
	}
}
