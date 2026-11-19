<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data;

use WP_Post;

/**
 * A TEC event: its post, its meta, and the fields read from that meta.
 *
 * @since 3.18.0
 */
final class TecEvent {

	/**
	 * Post status TEC gives an imported event that was deleted, so its importer doesn't bring it back.
	 *
	 * @since 3.18.0
	 *
	 * @var string
	 */
	const IGNORED_STATUS = 'tribe-ignored';

	/**
	 * The TEC event post.
	 *
	 * @since 3.18.0
	 *
	 * @var WP_Post
	 */
	private $post;

	/**
	 * All of the post's meta, as `get_post_meta()` returns it.
	 *
	 * @since 3.18.0
	 *
	 * @var array
	 */
	private $meta;

	/**
	 * The dates, timezone, all-day flag, URL and repeat rules.
	 *
	 * @since 3.18.0
	 *
	 * @var array
	 */
	private $fields;

	/**
	 * Constructor.
	 *
	 * @since 3.18.0
	 *
	 * @param WP_Post $post   The TEC event post.
	 * @param array   $meta   All of the post's meta.
	 * @param array   $fields The fields read from the meta.
	 */
	public function __construct( WP_Post $post, $meta, $fields ) {

		$this->post   = $post;
		$this->meta   = is_array( $meta ) ? $meta : [];
		$this->fields = $fields;
	}

	/**
	 * The TEC event post.
	 *
	 * @since 3.18.0
	 *
	 * @return WP_Post
	 */
	public function get_post() {

		return $this->post;
	}

	/**
	 * Whether TEC ignores the event: an imported event someone deleted.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	public function is_ignored() {

		return $this->post->post_status === self::IGNORED_STATUS;
	}

	/**
	 * Whether the post has any meta.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	public function has_meta() {

		return ! empty( $this->meta );
	}

	/**
	 * The first value of a meta key.
	 *
	 * @since 3.18.0
	 *
	 * @param string $key The meta key.
	 *
	 * @return mixed False when the key is missing.
	 */
	public function get_meta( $key ) {

		return $this->meta[ $key ][0] ?? false;
	}

	/**
	 * The start date, moved to the day's edges for an all-day event.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_start_date() {

		return $this->fields['start_date'];
	}

	/**
	 * The end date, moved to the day's edges for an all-day event.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_end_date() {

		return $this->fields['end_date'];
	}

	/**
	 * The timezone, for start and end alike: TEC has one per event.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_timezone() {

		return $this->fields['timezone'];
	}

	/**
	 * Whether the event is all day.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	public function is_all_day() {

		return $this->fields['all_day'];
	}

	/**
	 * The event's website link.
	 *
	 * @since 3.18.0
	 *
	 * @return string|false
	 */
	public function get_url() {

		return $this->fields['url'];
	}

	/**
	 * The `_EventRecurrence` rules, empty for a one-off event.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	public function get_recurrence() {

		return $this->fields['recurrence'];
	}
}
