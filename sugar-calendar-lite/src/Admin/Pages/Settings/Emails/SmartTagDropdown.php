<?php

namespace Sugar_Calendar\Admin\Pages\Settings\Emails;

use Sugar_Calendar\SmartTags\SmartTagInterface;

/**
 * Format Smart Tag definitions for the editor's Smart Tags dropdown.
 *
 * The dropdown consumes `[ group_key => [ [ 'value' => tag, 'label' => label ], … ] ]`
 * and title-cases the group key for its section headings.
 *
 * @since 3.14.0
 */
class SmartTagDropdown {

	/**
	 * Group the given tags for one editor.
	 *
	 * Each group is a zero-indexed list, so `wp_json_encode()` emits a JSON array:
	 * the TinyMCE plugin calls `.length`, `.filter` and `.map` on it, and a keyed
	 * array would encode as an object and empty the dropdown.
	 *
	 * @since 3.14.0
	 *
	 * @param SmartTagInterface[] $tags    Tags keyed by slug.
	 * @param string[]            $groups  Heading keys, in display order.
	 * @param string[]            $exclude Slugs this editor must not offer.
	 *
	 * @return array
	 */
	public static function format( array $tags, array $groups, array $exclude = [] ): array {

		$dropdown = [];

		foreach ( $groups as $group ) {
			$entries = [];

			foreach ( $tags as $tag ) {
				// Read the slug off the object, not the array key: the key is the
				// slug for every caller here, but the object is always right.
				if ( $tag->group() !== $group || in_array( $tag->tag(), $exclude, true ) ) {
					continue;
				}

				$entries[] = [
					'value' => $tag->tag(),
					'label' => $tag->label(),
				];
			}

			if ( ! empty( $entries ) ) {
				$dropdown[ $group ] = $entries;
			}
		}

		return $dropdown;
	}
}
