<?php

namespace Sugar_Calendar\SetupChecklist;

use WP_Post;
use WP_Query;

/**
 * Query editable saved pages for the checklist selector.
 *
 * @since 3.15.0
 */
class PageSearch {

	/**
	 * Number of saved page candidates per query.
	 *
	 * @since 3.15.0
	 */
	private const PAGE_SIZE = 20;

	/**
	 * Query one bounded batch of editable saved pages.
	 *
	 * @since 3.15.0
	 *
	 * @param string $search Search text.
	 * @param int    $page   Positive page number.
	 *
	 * @return array
	 */
	public function get_pages( $search, $page ) {

		$query = new WP_Query(
			[
				'post_type'              => 'page',
				'post_status'            => Config::SAVED_POST_STATUSES,
				'posts_per_page'         => self::PAGE_SIZE,
				'paged'                  => $page,
				's'                      => $search,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		_prime_post_caches( $query->posts, false, false );

		$items = [];

		foreach ( $query->posts as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$title   = $post->post_title;
			$items[] = [
				'id'    => (int) $post_id,
				'title' => is_string( $title ) && $title !== '' ? $title : __( '(no title)', 'sugar-calendar-lite' ),
			];
		}

		return [
			'items'    => $items,
			'page'     => $page,
			'has_more' => $page < (int) $query->max_num_pages,
		];
	}
}
