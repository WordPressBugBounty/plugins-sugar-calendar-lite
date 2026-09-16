<?php

namespace Sugar_Calendar;

/**
 * Per-email enable flags.
 *
 * One core-owned option, an array keyed by the ids in
 * `Sugar_Calendar\Emails\EmailRegistry`. Core owns it even though the
 * ticketing feature and the RSVP add-on both read it: the alternative — ticketing
 * flags in `sc_et_settings`, RSVP flags in `sc_rsvp_*` — is two storage schemes
 * and two guard shapes for one concept.
 *
 * This lives at the top of `src/` rather than under `Admin\Pages\Settings\Emails`
 * on purpose. It is runtime policy, not admin rendering: the send paths that read
 * it run on front-end and REST requests where nothing under `Admin\Pages` should
 * be loaded.
 *
 * **Absence means enabled.** A fresh install has no option row and must keep
 * sending; only an explicit `false` switches an email off. That is what makes the
 * feature safe to ship with no migration and no upgrade routine.
 *
 * @since 3.14.0
 */
class EmailStatus {

	/**
	 * Option name holding the id => bool map.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const OPTION = 'sugar_calendar_email_status';

	/**
	 * Whether an email is enabled.
	 *
	 * @since 3.14.0
	 *
	 * @param string $email_id Registry email id.
	 *
	 * @return bool
	 */
	public static function is_enabled( string $email_id ): bool {

		$statuses = (array) get_option( self::OPTION, [] );

		// array_key_exists(), not isset() or a ?? default: a stored `false` and an
		// absent key are different states here, and isset() cannot tell them apart
		// for a null value either. Absence is the "enabled" default.
		if ( ! array_key_exists( $email_id, $statuses ) ) {
			return true;
		}

		return (bool) $statuses[ $email_id ];
	}

	/**
	 * Merge a set of flags into the stored map.
	 *
	 * Callers pass every id whose control the current screen rendered — including
	 * the ones being switched **on**, so a re-enable is written as an explicit
	 * `true` rather than by deleting the key. Ids the caller does not supply are
	 * left exactly as they were: a save from one email's edit screen must not
	 * disturb the other six.
	 *
	 * One `update_option()` call, not one per id.
	 *
	 * @since 3.14.0
	 *
	 * @param array $statuses Map of registry email id => bool.
	 *
	 * @return bool Whether the option was updated.
	 */
	public static function set_many( array $statuses ): bool {

		if ( empty( $statuses ) ) {
			return false;
		}

		$current = (array) get_option( self::OPTION, [] );

		foreach ( $statuses as $email_id => $enabled ) {
			$current[ sanitize_key( $email_id ) ] = (bool) $enabled;
		}

		// Not autoloaded: this is read on the send paths, which already load the
		// order or RSVP they are sending for.
		return update_option( self::OPTION, $current, false );
	}
}
