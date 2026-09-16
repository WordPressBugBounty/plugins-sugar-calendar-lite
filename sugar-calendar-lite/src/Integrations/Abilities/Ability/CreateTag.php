<?php
/**
 * Ability: create an event tag.
 *
 * @since 3.14.0
 */

namespace Sugar_Calendar\Integrations\Abilities\Ability;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Features\Tags\Common\Helpers as TagsHelpers;

/**
 * CreateTag — creates an event tag term, reusing CreateCalendar's flow with a
 * different taxonomy.
 *
 * @since 3.14.0
 */
class CreateTag extends CreateCalendar {

	/**
	 * Ability slug appended to the namespace prefix.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_slug(): string {

		return 'create-tag';
	}

	/**
	 * Human-readable ability label.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_label(): string {

		return __( 'Create Tag', 'sugar-calendar-lite' );
	}

	/**
	 * Human-readable ability description.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function get_description(): string {

		return __(
			'Creates an event tag. Returns the existing tag when one already has that name.',
			'sugar-calendar-lite'
		);
	}

	/**
	 * The taxonomy this ability writes to.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function taxonomy(): string {

		return TagsHelpers::get_tags_taxonomy_id();
	}

	/**
	 * Message shown when the caller may not create tags.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	protected function denied_message(): string {

		return __( 'You do not have permission to create tags.', 'sugar-calendar-lite' );
	}
}
