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
	 * Register hooks.
	 *
	 * @since 3.17.0
	 */
	public function hooks() {

		add_filter( 'pll_copy_term_metas', [ $this, 'add_calendar_term_metas' ], 10, 3 );

		// After the calendar form saves its own fields (10), before Polylang links and syncs the new term (900).
		add_action( 'create_term', [ $this, 'copy_metas_into_new_calendar_translation' ], 500, 3 );
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
