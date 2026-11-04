<?php

namespace Sugar_Calendar\UsageTracking;

use WP_Post;

/**
 * Keeps a post-meta marker on content that holds a Calendar or Event List embed.
 *
 * The marker is a live fact: it is deleted when the embed goes, so the weekly snapshot
 * can ask one indexed question instead of scanning content.
 *
 * @since 3.17.0
 */
class CalendarEmbedMarker {

	/**
	 * Post meta key holding the marker.
	 *
	 * @since 3.17.0
	 */
	const META_KEY = '_sugar_calendar_has_embed';

	/**
	 * Block names of the Calendar and Event List blocks.
	 *
	 * @since 3.17.0
	 */
	const BLOCK_NAMES = [ 'sugar-calendar/block', 'sugar-calendar/event-list-block' ];

	/**
	 * Strings that must appear before any embed check is worth running.
	 *
	 * @since 3.17.0
	 */
	const NEEDLES = [ 'sugar-calendar', 'sugarcalendar', 'sc_events_' ];

	/**
	 * Shortcode tags, current and legacy, as a regex alternation.
	 *
	 * @since 3.17.0
	 */
	const SHORTCODE_PATTERN = '/\[(?:sugarcalendar_events_calendar|sugarcalendar_events_list|sc_events_calendar|sc_events_list)[\s\/\]]/';

	/**
	 * Register hooks.
	 *
	 * @since 3.17.0
	 */
	public function hooks() {

		add_action( 'wp_after_insert_post', [ $this, 'record_saved_post' ], 10, 2 );
		add_action( 'elementor/document/after_save', [ $this, 'record_saved_document' ] );
	}

	/**
	 * Refresh the marker for a saved post.
	 *
	 * @since 3.17.0
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Saved post.
	 */
	public function record_saved_post( $post_id, $post ) {

		if (
			! $post instanceof WP_Post ||
			wp_is_post_revision( $post_id ) ||
			wp_is_post_autosave( $post_id ) ||
			! in_array( $post->post_type, self::get_post_types(), true )
		) {
			return;
		}

		$this->sync( (int) $post_id, $post->post_content );
	}

	/**
	 * Refresh the marker once Elementor has stored its widget data.
	 *
	 * @since 3.17.0
	 *
	 * @param object $document Elementor document.
	 */
	public function record_saved_document( $document ) {

		if ( ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) ) {
			return;
		}

		$post = get_post( (int) $document->get_main_id() );

		if ( $post instanceof WP_Post ) {
			$this->record_saved_post( $post->ID, $post );
		}
	}

	/**
	 * Set or delete the marker according to what the content holds.
	 *
	 * @since 3.17.0
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Post content.
	 */
	public function sync( $post_id, $content ) {

		$had_marker = get_post_meta( $post_id, self::META_KEY, true ) === '1';
		$has_embed  = $this->has_embed( (string) $content ) || $this->has_elementor_embed( $post_id );

		if ( $has_embed && ! $had_marker ) {
			update_post_meta( $post_id, self::META_KEY, '1' );
		} elseif ( ! $has_embed && $had_marker ) {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}

	/**
	 * Post types whose content can carry a marker.
	 *
	 * @since 3.17.0
	 *
	 * @return string[]
	 */
	public static function get_post_types() {

		$types = array_values( get_post_types( [ 'public' => true ] ) );

		return array_merge( $types, [ 'wp_template', 'wp_template_part' ] );
	}

	/**
	 * Whether a piece of block, shortcode or HTML content holds an embed.
	 *
	 * @since 3.17.0
	 *
	 * @param string $content Content to test.
	 *
	 * @return bool
	 */
	public function content_has_embed( $content ) {

		return $this->has_embed( (string) $content );
	}

	/**
	 * Whether block or shortcode content holds an embed.
	 *
	 * @since 3.17.0
	 *
	 * @param string $content Post content.
	 *
	 * @return bool
	 */
	private function has_embed( $content ) {

		if ( ! $this->contains_needle( $content ) ) {
			return false;
		}

		return preg_match( self::SHORTCODE_PATTERN, $content ) === 1
			|| $this->contains_block( parse_blocks( $content ) );
	}

	/**
	 * Whether the Elementor widget data of a post holds an embed.
	 *
	 * @since 3.17.0
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool
	 */
	private function has_elementor_embed( $post_id ) {

		$data = get_post_meta( $post_id, '_elementor_data', true );

		return is_string( $data )
			&& $this->contains_needle( $data )
			&& ( strpos( $data, 'sugar-calendar-events-calendar' ) !== false || strpos( $data, 'sugar-calendar-events-list' ) !== false );
	}

	/**
	 * Cheap string test that lets most saves skip the real check.
	 *
	 * @since 3.17.0
	 *
	 * @param string $content Content to test.
	 *
	 * @return bool
	 */
	private function contains_needle( $content ) {

		foreach ( self::NEEDLES as $needle ) {
			if ( strpos( $content, $needle ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursively look for the Calendar or Event List block.
	 *
	 * @since 3.17.0
	 *
	 * @param array $blocks Parsed blocks.
	 *
	 * @return bool
	 */
	private function contains_block( $blocks ) {

		foreach ( $blocks as $block ) {

			if ( in_array( $block['blockName'] ?? null, self::BLOCK_NAMES, true ) ) {
				return true;
			}

			if ( ! empty( $block['innerBlocks'] ) && $this->contains_block( $block['innerBlocks'] ) ) {
				return true;
			}
		}

		return false;
	}
}
