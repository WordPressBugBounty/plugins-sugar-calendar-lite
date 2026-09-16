<?php

namespace Sugar_Calendar\Emails;

use Sugar_Calendar\Ics\EventData;

/**
 * Build a delivery-local calendar action from resolved context.
 *
 * @since 3.14.0
 */
final class CalendarAction {

	/**
	 * Build a calendar action from an email context.
	 *
	 * @since 3.14.0
	 *
	 * @param array $context Email context.
	 *
	 * @return array|null
	 */
	public static function from_context( array $context ): ?array { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded, PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound -- The exact public contract and flat validation are intentional.

		if (
			! isset( $context['event'], $context['calendar']['events'][0] )
			|| ! is_array( $context['event'] )
			|| ! is_array( $context['calendar'] )
			|| empty( $context['calendar']['is_public'] )
			|| ! is_array( $context['calendar']['events'][0] )
		) {
			return null;
		}

		$event_data    = $context['calendar']['events'][0];
		$event_id      = isset( $context['event']['sc_event_id'] ) ? (int) $context['event']['sc_event_id'] : 0;
		$object_id     = isset( $context['event']['object_id'] ) ? (int) $context['event']['object_id'] : 0;
		$occurrence_id = isset( $context['event']['occurrence_id'] ) ? (int) $context['event']['occurrence_id'] : 0;

		// phpcs:disable WPForms.PHP.ConditionsYodaDisable.YodaRemove -- Preserve the required stable-ID comparison order.
		if (
			$event_id <= 0
			|| $object_id <= 0
			|| (int) ( $event_data['id'] ?? 0 ) !== $event_id
			|| (int) ( $event_data['object_id'] ?? 0 ) !== $object_id
			|| (int) ( $event_data['occurrence_id'] ?? 0 ) !== $occurrence_id
			|| (int) ( $event_data['start'] ?? 0 ) <= 0
		) {
			return null;
		}
		// phpcs:enable WPForms.PHP.ConditionsYodaDisable.YodaRemove

		/**
		 * Filters the Add to Calendar URL for a Modern email.
		 *
		 * @since 3.14.0
		 *
		 * @param string $url     Calendar URL.
		 * @param array  $context Email context.
		 */
		$filtered_url = apply_filters( 'sugar_calendar_emails_calendar_url', '', $context ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		$url          = is_string( $filtered_url ) ? esc_url_raw( $filtered_url ) : '';
		$scheme       = $url === '' ? '' : wp_parse_url( $url, PHP_URL_SCHEME );

		if ( $url === '' || ! is_string( $scheme ) || $scheme === '' ) {
			return null;
		}

		// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning -- Preserve the required action construction.
		$identity = [
			'site_id'       => get_current_blog_id(),
			'event_id'      => $event_id,
			'occurrence_id' => $occurrence_id,
		];
		$fingerprint = hash( 'sha256', wp_json_encode( $context['calendar'] ) );
		// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning

		return [
			'cache_key'   => implode( ':', $identity ) . ':' . $fingerprint,
			'identity'    => $identity,
			'fingerprint' => $fingerprint,
			'url'         => $url,
			'filename'    => EventData::filename( $event_data ),
			'calendar'    => $context['calendar'],
		];
	}
}
