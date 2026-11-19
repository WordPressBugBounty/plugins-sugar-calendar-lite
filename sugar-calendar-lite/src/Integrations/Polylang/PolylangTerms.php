<?php

namespace Sugar_Calendar\Integrations\Polylang;

/**
 * Keep the colour and timezone of a calendar's Polylang translations the same.
 *
 * @since 3.17.0
 */
class PolylangTerms {

	/**
	 * Calendar term meta shared by translations.
	 *
	 * @since 3.17.0
	 *
	 * @var string[]
	 */
	const CALENDAR_TERM_METAS = [ 'color', 'timezone' ];

	/**
	 * Calendars created in this request and not yet linked to a translation, keyed by term ID.
	 *
	 * @since 3.18.0
	 *
	 * @var true[]
	 */
	private $unlinked_new_calendars = [];

	/**
	 * Register hooks.
	 *
	 * @since 3.17.0
	 */
	public function hooks() {

		add_filter( 'pll_copy_term_metas', [ $this, 'add_calendar_term_metas' ], 10, 3 );

		// After the calendar form saves its own fields (10), before Polylang links and syncs the new term (900).
		add_action( 'create_term', [ $this, 'copy_metas_into_new_calendar_translation' ], 500, 3 );

		// Runs after create_term, so a calendar the "+" form already linked there is not remembered.
		add_action( 'created_term', [ $this, 'remember_new_calendar' ], 10, 3 );
		add_action( 'set_object_terms', [ $this, 'copy_metas_into_linked_new_calendar' ], 10, 4 );
	}

	/**
	 * Remember a calendar created in this request until Polylang links it to a translation.
	 *
	 * @since 3.18.0
	 *
	 * @param int    $term_id  New term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public function remember_new_calendar( $term_id, $tt_id, $taxonomy ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- $tt_id is part of the created_term action signature.

		if ( $taxonomy === sugar_calendar_get_calendar_taxonomy_id() ) {
			$this->unlinked_new_calendars[ (int) $term_id ] = true;
		}
	}

	/**
	 * Give a calendar Polylang created for a translated event the colour and timezone of the calendar it translates.
	 *
	 * Polylang creates that calendar with `wp_insert_term()` and links it only afterwards, so its own copy never runs.
	 * Only a calendar created in this request is filled, so later saves never refill a value cleared on purpose.
	 *
	 * @since 3.18.0
	 *
	 * @param int    $object_id Object the terms were set on, here a term ID.
	 * @param array  $terms     Terms set.
	 * @param array  $tt_ids    Term taxonomy IDs set.
	 * @param string $taxonomy  Taxonomy of the terms set.
	 */
	public function copy_metas_into_linked_new_calendar( $object_id, $terms, $tt_ids, $taxonomy ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- $terms and $tt_ids are part of the set_object_terms action signature.

		if ( $taxonomy !== 'term_translations' || empty( $this->unlinked_new_calendars[ (int) $object_id ] ) ) {
			return;
		}

		// Polylang first puts a new term in a group of its own; wait for the link to a translation.
		$source_id = $this->get_other_translation( (int) $object_id );

		if ( ! $source_id ) {
			return;
		}

		unset( $this->unlinked_new_calendars[ (int) $object_id ] );

		$this->copy_metas_into_empty_calendar( $source_id, (int) $object_id );
	}

	/**
	 * Copy a calendar's metas into its new translation, unless the translation already has a colour or timezone.
	 *
	 * @since 3.18.0
	 *
	 * @param int $source_id Calendar the values come from.
	 * @param int $term_id   New calendar translation.
	 */
	private function copy_metas_into_empty_calendar( $source_id, $term_id ) {

		// A value already set wins, and Polylang's copy would overwrite it.
		if ( $this->has_any_calendar_meta( $term_id ) ) {
			return;
		}

		/**
		 * Polylang's own meta copy: it honours `pll_copy_term_metas` and doesn't sync the writes back to the source.
		 *
		 * @since 3.18.0
		 *
		 * @param int    $from Term the metas come from.
		 * @param int    $to   New translation.
		 * @param string $lang Language slug of the new translation.
		 */
		do_action( 'pll_duplicate_term', $source_id, $term_id, pll_get_term_language( $term_id ) ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Polylang integration API hook.
	}

	/**
	 * A translation of a term other than the term itself.
	 *
	 * @since 3.18.0
	 *
	 * @param int $term_id Term ID.
	 *
	 * @return int Term ID, or 0 when the term has no translation.
	 */
	private function get_other_translation( $term_id ) {

		foreach ( pll_get_term_translations( $term_id ) as $translation_id ) {
			if ( (int) $translation_id !== $term_id ) {
				return (int) $translation_id;
			}
		}

		return 0;
	}

	/**
	 * Let Polylang copy a calendar's colour and timezone to its translations.
	 *
	 * @since 3.17.0
	 *
	 * @param string[] $keys    Term meta keys to copy.
	 * @param bool     $sync    Whether this is a sync of an existing translation, not a new one.
	 * @param int      $term_id Term the values come from.
	 *
	 * @return string[]
	 */
	public function add_calendar_term_metas( $keys, $sync, $term_id ) {

		// Polylang's own post meta rule: always on a new translation, on later saves only with Custom fields sync.
		if ( ! $this->is_calendar( $term_id ) || ( $sync && ! PolylangApi::is_custom_fields_sync_on() ) ) {
			return $keys;
		}

		return array_merge( (array) $keys, self::CALENDAR_TERM_METAS );
	}

	/**
	 * Give a calendar made with Polylang's "+" the colour and timezone of the calendar it translates.
	 *
	 * Polylang copies a new post's custom fields before its editor opens, but not a new term's.
	 * Without this, the empty fields of the new calendar would be synced over its translations.
	 *
	 * @since 3.17.0
	 *
	 * @param int    $term_id  New term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public function copy_metas_into_new_calendar_translation( $term_id, $tt_id, $taxonomy ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- $tt_id is part of the create_term action signature.

		// WordPress checked the add-tag nonce before creating the term; Polylang's form adds `from_tag`.
		$from_term_id = isset( $_POST['from_tag'] ) ? absint( $_POST['from_tag'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $taxonomy !== sugar_calendar_get_calendar_taxonomy_id() || ! $this->is_calendar( $from_term_id ) ) {
			return;
		}

		foreach ( self::CALENDAR_TERM_METAS as $key ) {
			$value = get_term_meta( $from_term_id, $key, true );

			// A value picked in the form wins.
			if ( $value !== '' && get_term_meta( $term_id, $key, true ) === '' ) {
				update_term_meta( $term_id, $key, $value );
			}
		}
	}

	/**
	 * Whether a calendar has a colour or timezone of its own.
	 *
	 * @since 3.18.0
	 *
	 * @param int $term_id Term ID.
	 *
	 * @return bool
	 */
	private function has_any_calendar_meta( $term_id ) {

		foreach ( self::CALENDAR_TERM_METAS as $key ) {
			if ( get_term_meta( $term_id, $key, true ) !== '' ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a term is a calendar.
	 *
	 * @since 3.17.0
	 *
	 * @param int $term_id Term ID.
	 *
	 * @return bool
	 */
	private function is_calendar( $term_id ) {

		return ! empty( $term_id ) && get_term_field( 'taxonomy', $term_id ) === sugar_calendar_get_calendar_taxonomy_id();
	}
}
