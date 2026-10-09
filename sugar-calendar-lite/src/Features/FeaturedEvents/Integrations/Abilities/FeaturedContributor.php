<?php
/**
 * The `featured` slice of the event write abilities.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Features\FeaturedEvents\Integrations\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Features\FeaturedEvents\Common\Featured;
use Sugar_Calendar\Integrations\Abilities\AbstractEventFieldContributor;

/**
 * FeaturedContributor — lets an ability mark an event as featured.
 *
 * Mirrors the editor's checkbox: the meta is `'1'` or an empty string, and
 * `Featured::get_meta_query()` treats anything non-empty as featured.
 *
 * @since 3.15.0
 */
class FeaturedContributor extends AbstractEventFieldContributor {

	/**
	 * Input property name.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const PROPERTY = 'featured';

	/**
	 * Widen the input schema.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $properties Properties so far.
	 * @param mixed $partial    Whether this is the update variant.
	 *
	 * @return mixed
	 */
	public static function add_schema( $properties, $partial ) {

		if ( ! is_array( $properties ) ) {
			return $properties;
		}

		$properties[ self::PROPERTY ] = [
			'type'        => 'boolean',
			'description' => __( 'Send true to mark the event as featured, which highlights it in calendar blocks and shortcodes; false removes the mark.', 'sugar-calendar-lite' ),
		];

		return $properties;
	}

	/**
	 * Set or clear the event's featured flag.
	 *
	 * @since 3.15.0
	 *
	 * @param int   $event_id   Sugar Calendar event id.
	 * @param array $normalized Normalized input.
	 * @param array $result     Result so far, by reference.
	 *
	 * @return void
	 */
	public static function apply( $event_id, $normalized, &$result ) {

		$extra = isset( $normalized['extra'] ) ? (array) $normalized['extra'] : [];

		if ( ! array_key_exists( self::PROPERTY, $extra ) ) {
			return;
		}

		$event_id = (int) $event_id;
		$featured = ! empty( $extra[ self::PROPERTY ] );
		$was      = ! empty( get_event_meta( $event_id, Featured::META_KEY, true ) );

		if ( $was === $featured ) {
			return;
		}

		update_event_meta( $event_id, Featured::META_KEY, $featured ? '1' : '' );

		$result['previous'][ self::PROPERTY ] = $was;

		$result['applied'][] = $featured
			? __( 'Marked the event as featured.', 'sugar-calendar-lite' )
			: __( 'Removed the featured flag.', 'sugar-calendar-lite' );
	}

	/**
	 * Add the stored featured flag to the post-write snapshot.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $snapshot Snapshot so far.
	 * @param mixed $event_id Sugar Calendar event id.
	 *
	 * @return mixed
	 */
	public static function snapshot( $snapshot, $event_id ) {

		if ( ! is_array( $snapshot ) ) {
			return $snapshot;
		}

		$snapshot[ self::PROPERTY ] = ! empty( get_event_meta( (int) $event_id, Featured::META_KEY, true ) );

		return $snapshot;
	}
}
