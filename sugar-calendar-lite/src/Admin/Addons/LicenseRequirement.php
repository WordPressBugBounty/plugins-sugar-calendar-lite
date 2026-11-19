<?php

namespace Sugar_Calendar\Admin\Addons;

/**
 * Whether the site's license plan meets an add-on's license requirement.
 *
 * Requirements checks a running add-on with it; the Setup Checklist asks it about one that is not running.
 *
 * @since 3.18.0
 */
class LicenseRequirement {

	/**
	 * Plans an add-on accepts unless its requirements name others: Plus, Pro and the top levels.
	 *
	 * @since 3.18.0
	 */
	const DEFAULT_PLANS = [ 'plus', 'pro', 'elite', 'agency', 'ultimate' ];

	/**
	 * Whether the site's plan meets an add-on's entry in Requirements::get_requirements(), without
	 * running the add-on: the entry's own list, or the default plans.
	 *
	 * @since 3.18.0
	 *
	 * @param array $requirements One add-on's requirements.
	 *
	 * @return bool
	 */
	public static function allows( array $requirements ): bool {

		return self::is_met( self::to_list( $requirements['license'] ?? self::DEFAULT_PLANS ) );
	}

	/**
	 * Whether the site's license plan is in a list of plans. An empty list is not checked.
	 *
	 * @since 3.18.0
	 *
	 * @param string[] $plans License plans the requirement accepts.
	 *
	 * @return bool
	 */
	public static function is_met( array $plans ): bool {

		return empty( $plans ) || in_array( sugar_calendar()->get_license_type(), $plans, true );
	}

	/**
	 * A plan list as an array: a comma-separated string or an array, trimmed, empties dropped.
	 *
	 * @since 3.18.0
	 *
	 * @param mixed $plans Plan list.
	 *
	 * @return string[]
	 */
	private static function to_list( $plans ): array {

		if ( is_string( $plans ) ) {
			$plans = explode( ',', $plans );
		}

		return is_array( $plans ) ? array_filter( array_map( 'trim', $plans ) ) : [];
	}
}
