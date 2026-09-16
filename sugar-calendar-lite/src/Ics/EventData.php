<?php

namespace Sugar_Calendar\Ics;

use Sugar_Calendar\Helpers as SugarCalendarHelpers;
use WP_Post;

/**
 * Normalize Sugar Calendar events for ICS serialization.
 *
 * @since 3.14.0
 */
class EventData {

	/**
	 * Normalize an event for serialization.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $event          Event object.
	 * @param bool  $filter_content Apply public content filters before stripping markup.
	 *
	 * @return array
	 */
	public static function from_event( $event, bool $filter_content = false ): array { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		if ( ! is_object( $event ) ) {
			return [];
		}

		$recurrence_end = ! empty( $event->recurrence_end )
			&& method_exists( $event, 'is_empty_date' )
			&& ! $event->is_empty_date( $event->recurrence_end )
				? strtotime( $event->recurrence_end )
				: '';

		$args = wp_parse_args(
			[
				'id'                  => isset( $event->id ) ? (int) $event->id : 0,
				'object_id'           => isset( $event->object_id ) ? (int) $event->object_id : 0,
				'occurrence_id'       => isset( $event->occurrence_id ) ? (int) $event->occurrence_id : 0,
				'status'              => isset( $event->status ) ? (string) $event->status : '',
				'start'               => isset( $event->start ) ? strtotime( $event->start ) : false,
				'start_tz'            => isset( $event->start_tz ) ? (string) $event->start_tz : '',
				'end'                 => isset( $event->end ) ? strtotime( $event->end ) : false,
				'end_tz'              => isset( $event->end_tz ) ? (string) $event->end_tz : '',
				'all_day'             => ! empty( $event->all_day ),
				'summary'             => isset( $event->title ) ? (string) $event->title : '',
				'description'         => self::description( $event, $filter_content ),
				'url'                 => ! empty( $event->object_id ) ? (string) get_post_permalink( (int) $event->object_id ) : '',
				'uid'                 => isset( $event->uuid ) ? (string) $event->uuid : '',
				'location'            => isset( $event->location ) ? (string) $event->location : '',
				'created'             => isset( $event->date_created ) ? strtotime( $event->date_created ) : false,
				'modified'            => isset( $event->date_modified ) ? strtotime( $event->date_modified ) : false,
				'recurrence'          => isset( $event->recurrence ) ? (string) $event->recurrence : '',
				'recurrence_end'      => $recurrence_end,
				'recurrence_interval' => isset( $event->recurrence_interval ) ? (int) $event->recurrence_interval : 0,
				'recurrence_count'    => isset( $event->recurrence_count ) ? (int) $event->recurrence_count : 0,
				'advanced_recurring'  => isset( $event->advanced_recurring ) ? (array) $event->advanced_recurring : [],
			],
			(array) $event
		);

		if ( $args['occurrence_id'] > 0 ) {
			$uid_base = $args['uid'] !== ''
				? $args['uid']
				: hash( 'sha256', get_current_blog_id() . ':' . $args['id'] . ':' . home_url( '/' ) );

			$args['uid']                 = $uid_base . '-occurrence-' . $args['occurrence_id'];
			$args['recurrence']          = '';
			$args['recurrence_end']      = '';
			$args['recurrence_interval'] = 0;
			$args['recurrence_count']    = 0;
			$args['advanced_recurring']  = [];
		}

		return $args;
	}

	/**
	 * Get an event description for ICS serialization.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $event          Event object.
	 * @param bool  $filter_content Apply public content filters before stripping markup.
	 *
	 * @return string
	 */
	public static function description( $event, bool $filter_content = false ): string { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh -- Preserve legacy normalization while applying public restrictions before stripping markup.

		if (
			! is_object( $event )
			|| empty( $event->object_id )
			|| post_password_required( $event->object_id )
		) {
			return '';
		}

		$content = (string) ( $event->content ?? '' );

		// Public restrictions may depend on markup or registered shortcode callbacks.
		if ( ! $filter_content ) {
			$content = strip_shortcodes( wp_strip_all_tags( $content ) );
		}

		$filtered = SugarCalendarHelpers::get_event_content( (int) $event->object_id, $content );

		if ( $filter_content ) {
			// Run restrictions after helpers that can rebuild text from stored content.
			$filtered = apply_filters( 'the_content', $filtered ); // phpcs:ignore WPForms.Comments.PHPDocHooks, WPForms.PHP.ValidateHooks, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Existing WordPress content-filter contract.

			return is_string( $filtered ) ? wp_strip_all_tags( strip_shortcodes( $filtered ) ) : '';
		}

		return $filtered === $content ? $content : wp_strip_all_tags( $filtered );
	}

	/**
	 * Build a single-event calendar payload.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $event          Event object.
	 * @param bool  $filter_content Apply public content filters before stripping markup.
	 *
	 * @return array
	 */
	public static function calendar( $event, bool $filter_content = false ): array {

		return [
			'name'      => isset( $event->title ) ? (string) $event->title : '',
			'desc'      => get_bloginfo( 'name' ),
			'is_public' => self::is_public( $event ),
			'events'    => [ self::from_event( $event, $filter_content ) ],
		];
	}

	/**
	 * Determine whether an event is publicly available.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $event Event object.
	 *
	 * @return bool
	 */
	public static function is_public( $event ): bool {

		$post = ! empty( $event->object_id ) ? get_post( (int) $event->object_id ) : null;

		return $post instanceof WP_Post
			&& $post->post_status === 'publish'
			&& is_post_publicly_viewable( $post )
			&& (string) $post->post_password === '';
	}

	/**
	 * Build a sanitized ICS filename.
	 *
	 * @since 3.14.0
	 *
	 * @param array $event_data Normalized event data.
	 *
	 * @return string
	 */
	public static function filename( array $event_data ): string {

		$slug = sanitize_title( (string) $event_data['summary'] );
		$slug = $slug === '' ? 'event' : $slug;

		return sanitize_file_name(
			$slug . '-' . gmdate( 'Y-m-d', (int) $event_data['start'] ) . '.ics'
		);
	}
}
