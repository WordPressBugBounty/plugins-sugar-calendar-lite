<?php
/**
 * Shared usage-breakdown maths for the credit cards.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\OAuthRelay\Credits;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Turns the relay's `usage` map into the rows `CreditsPresenter::segments()`
 * reads.
 *
 * The relay sends `usage` as a map keyed by slug (`{"zoom":1}`), not a list of
 * row objects: integrations key it by integration, AI by credit category. Both
 * want the same rows and the same colors held stable across requests, so the
 * maths lives here and each service passes its own color-map option.
 *
 * @since 3.15.0
 */
class UsageBreakdown {

	/**
	 * Format a usage map into rows with percentages and palette indexes.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed  $usage            Raw usage map (slug => spent count).
	 * @param int    $total            Total credit limit, for the percentage.
	 * @param string $color_map_option Option holding this card's slug => index map.
	 *
	 * @return array[] Each: slug, count, percentage, index. Highest usage first.
	 */
	public static function rows( $usage, int $total, string $color_map_option ): array {

		if ( empty( $usage ) || ! is_array( $usage ) || $total <= 0 ) {
			return [];
		}

		$rows = [];

		foreach ( $usage as $slug => $count ) {
			$count = (int) $count;

			if ( $count <= 0 ) {
				continue;
			}

			$rows[] = [
				'slug'       => sanitize_key( $slug ),
				'count'      => $count,
				'percentage' => min( (int) round( ( $count / $total ) * 100 ), 100 ),
			];
		}

		// Highest usage first.
		usort(
			$rows,
			static function ( $a, $b ) {

				return $b['count'] <=> $a['count'];
			}
		);

		return self::assign_color_indexes( $rows, $color_map_option );
	}

	/**
	 * Stamp each usage row with its stable palette index.
	 *
	 * A slug keeps the same index as the set grows: a new slug takes
	 * `max(existing) + 1` (first slug -> 0), persisted to an option so the color
	 * is stable across requests. The presenter maps the index to a hex color.
	 * The option write happens here (fetch/format time), not in the presenter,
	 * so a render never triggers a DB write.
	 *
	 * @since 3.15.0
	 *
	 * @param array[] $rows   Usage rows (each with a `slug`).
	 * @param string  $option Option holding the slug => index map.
	 *
	 * @return array[] The rows with an added integer `index`.
	 */
	private static function assign_color_indexes( array $rows, string $option ): array {

		$map = get_option( $option, [] );

		// Keep only int values: a corrupted option must not break max().
		$map     = is_array( $map ) ? array_filter( $map, 'is_int' ) : [];
		$changed = false;

		foreach ( $rows as $row ) {
			if ( ! isset( $map[ $row['slug'] ] ) ) {
				$map[ $row['slug'] ] = ! empty( $map ) ? max( $map ) + 1 : 0;
				$changed             = true;
			}
		}

		if ( $changed ) {
			update_option( $option, $map, false );
		}

		foreach ( $rows as &$row ) {
			$row['index'] = $map[ $row['slug'] ] ?? 0;
		}

		unset( $row );

		return $rows;
	}
}
