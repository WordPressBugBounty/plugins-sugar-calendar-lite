<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Steps;

use Sugar_Calendar\Admin\Tools\Migrate\FinishableStep;
use Sugar_Calendar\Features\Tags\Common\Helpers as TagsHelpers;

/**
 * TEC tags, migrated before events so events can link to them.
 *
 * @since 3.18.0
 */
class TagsStep extends TermStep implements FinishableStep {

	/**
	 * The kind of item, used as the progress key.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_entity() {

		return 'tags';
	}

	/**
	 * Tags add nothing to the batch progress, as before the steps existed.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	public function get_batch_size() {

		return 0;
	}

	/**
	 * The number of tags one request reads.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	protected function get_limit() {

		/**
		 * Filter the number of TEC tags to import per iteration.
		 *
		 * @since 3.7.0
		 *
		 * @param int $limit The number of TEC tags to import per iteration.
		 */
		return apply_filters( 'sugar_calendar_admin_tools_importers_the_event_calendar_tags_limit', 100 ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- Released hook name, kept for compatibility.
	}

	/**
	 * The Sugar Calendar taxonomy the terms go to.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	protected function get_taxonomy() {

		return TagsHelpers::get_tags_taxonomy_id();
	}

	/**
	 * Tags check their name against the post tags.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	protected function get_slug_taxonomy() {

		return 'post_tag';
	}

	/**
	 * Record a migrated tag and fire the tag hook.
	 *
	 * @since 3.18.0
	 *
	 * @param object $item       The batch item.
	 * @param array  $term       The TEC tag.
	 * @param int    $sc_term_id The new tag's ID.
	 *
	 * @return bool
	 */
	protected function save( $item, $term, $sc_term_id ) {

		$saved = $this->id_map->save_tag( (int) $item->term_id, $sc_term_id );

		/**
		 * Additional actions for tag migration.
		 *
		 * @since 3.7.0
		 *
		 * @param int    $sc_tag_id    The Sugar Calendar tag ID.
		 * @param object $tec_tag      The TEC tag object.
		 * @param array  $tec_tag_data The tag data.
		 */
		do_action( 'sugar_calendar_admin_tools_importers_the_event_calendar_migrate_tag', $sc_term_id, $item, $term ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- Released hook name, kept for compatibility.

		return $saved !== false;
	}

	/**
	 * Give the events this run migrated their tags.
	 *
	 * @since 3.18.0
	 */
	public function finish() {

		$this->link_to_events();
	}
}
