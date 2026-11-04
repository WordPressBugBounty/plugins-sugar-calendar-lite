<?php

namespace Sugar_Calendar\UsageTracking;

/**
 * Site setup keys for the usage-tracking snapshot.
 *
 * @since 3.17.0
 */
class SiteSetupData {

	/**
	 * Register hooks.
	 *
	 * @since 3.17.0
	 */
	public function hooks() {

		( new CalendarEmbedMarker() )->hooks();
	}

	/**
	 * Get the site setup keys.
	 *
	 * @since 3.17.0
	 *
	 * @return array
	 */
	public function get() {

		return [
			'sugar_calendar_events_page_status' => $this->get_events_page_status(),
			'sugar_calendar_has_calendar_page'  => $this->has_calendar_page(),
		];
	}

	/**
	 * Say whether the events archive works and who renders it.
	 *
	 * @since 3.17.0
	 *
	 * @return string One of disabled, plain_permalinks, theme_template, default.
	 */
	private function get_events_page_status() {

		$post_type = get_post_type_object( 'sc_event' );

		if ( ! $post_type || empty( $post_type->has_archive ) ) {
			return 'disabled';
		}

		if ( get_option( 'permalink_structure' ) === '' ) {
			return 'plain_permalinks';
		}

		if ( locate_template( 'archive-sc_event.php' ) !== '' ) {
			return 'theme_template';
		}

		return 'default';
	}

	/**
	 * Whether a Calendar or Event List is shown in a published post or an active widget.
	 *
	 * @since 3.17.0
	 *
	 * @return bool
	 */
	private function has_calendar_page() {

		if ( is_active_widget( false, false, 'sc_calendar_widget' ) || is_active_widget( false, false, 'sc_event_list_widget' ) || $this->has_widget_embed() ) {
			return true;
		}

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s AND p.post_status = 'publish'
				LIMIT 1",
				CalendarEmbedMarker::META_KEY
			)
		);
		// phpcs:enable

		return $found !== null;
	}

	/**
	 * Whether an active Block, Custom HTML or Text widget holds an embed.
	 *
	 * Reads the autoloaded widget options, so it costs no query.
	 *
	 * @since 3.17.0
	 *
	 * @return bool
	 */
	private function has_widget_embed() {

		$marker  = new CalendarEmbedMarker();
		$fields  = [
			'block'       => 'content',
			'custom_html' => 'content',
			'text'        => 'text',
		];
		$options = [];

		foreach ( $fields as $id_base => $field ) {
			$options[ $id_base ] = get_option( 'widget_' . $id_base, [] );
		}

		foreach ( $this->get_active_widget_ids() as $widget_id ) {
			if ( $this->widget_has_embed( $marker, $options, $fields, $widget_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the IDs of widgets placed in a registered sidebar, so inactive and orphaned areas are left out.
	 *
	 * @since 3.17.0
	 *
	 * @return string[]
	 */
	private function get_active_widget_ids() {

		// Not wp_get_sidebars_widgets(): core marks it private and Plugin Check rejects it.
		$sidebars = get_option( 'sidebars_widgets', [] );

		if ( ! is_array( $sidebars ) ) {
			return [];
		}

		$ids = [];

		foreach ( $sidebars as $area => $widget_ids ) {
			if ( is_registered_sidebar( $area ) && is_array( $widget_ids ) ) {
				$ids = array_merge( $ids, array_map( 'strval', $widget_ids ) );
			}
		}

		return $ids;
	}

	/**
	 * Whether one widget instance holds an embed.
	 *
	 * @since 3.17.0
	 *
	 * @param CalendarEmbedMarker $marker  Embed detector.
	 * @param array               $options Widget options keyed by id_base.
	 * @param array               $fields  Content field keyed by id_base.
	 * @param string              $id      Widget ID, such as block-3.
	 *
	 * @return bool
	 */
	private function widget_has_embed( $marker, $options, $fields, $id ) {

		if ( ! preg_match( '/^(.+)-(\d+)$/', $id, $match ) || ! isset( $fields[ $match[1] ] ) ) {
			return false;
		}

		$content = $options[ $match[1] ][ (int) $match[2] ][ $fields[ $match[1] ] ] ?? '';

		return is_string( $content ) && $marker->content_has_embed( $content );
	}
}
