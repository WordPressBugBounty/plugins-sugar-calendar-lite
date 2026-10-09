<?php

namespace Sugar_Calendar\SetupChecklist;

use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\Helpers\ActionResult;
use Sugar_Calendar\Helpers\WP;
use WP_Error;
use WP_Query;

/**
 * Turn WordPress events into checklist history: a newsletter signup and an authorized archive visit.
 *
 * Owns the archive visit end to end, so it also claims the visit-events-page row's click.
 *
 * @since 3.15.0
 */
class HistoryObserver {

	/**
	 * Original main event archive query, before the frontend virtual-page conversion.
	 *
	 * @since 3.15.0
	 *
	 * @var WP_Query|null
	 */
	private $archive_query;

	/**
	 * Site that owns the captured archive query.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	private $archive_blog_id = 0;

	/**
	 * Per-site checklist state.
	 *
	 * @since 3.15.0
	 *
	 * @var State
	 */
	private $state;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param State $state Per-site checklist state.
	 */
	public function __construct( State $state ) {

		$this->state = $state;
	}

	/**
	 * Register checklist fact listeners.
	 *
	 * @since 3.15.0
	 */
	public function hooks() {

		add_action( 'sugar_calendar_newsletter_subscribe_after', [ $this, 'record_newsletter_signup' ] );
		add_filter( 'the_posts', [ $this, 'capture_archive_query' ], 9, 2 );
		add_action( 'wp_enqueue_scripts', [ $this, 'record_archive_visit' ] );
		add_action( 'switch_blog', [ $this, 'reset' ] );
		add_filter( 'sugar_calendar_setup_checklist_item_action', [ $this, 'filter_item_action' ], 10, 2 );
	}

	/**
	 * Claim the visit-events-page row, whose click opens the live events archive.
	 *
	 * @since 3.15.0
	 *
	 * @param array|WP_Error $result  Shared fallback result.
	 * @param string         $item_id Canonical checklist item ID.
	 *
	 * @return array|WP_Error
	 */
	public function filter_item_action( $result, $item_id ) {

		if ( $item_id !== Config::ITEM_VISIT_EVENTS_PAGE ) {
			return $result;
		}

		$url = get_post_type_archive_link( sugar_calendar_get_event_post_type_id() );

		return ActionResult::navigation( is_string( $url ) && $url !== '' ? $url : new WP_Error( 'checklist_archive_unavailable', __( 'The events page is unavailable.', 'sugar-calendar-lite' ) ) );
	}

	/**
	 * Clear request-local archive eligibility after switching sites.
	 *
	 * @since 3.15.0
	 */
	public function reset() {

		$this->archive_query   = null;
		$this->archive_blog_id = 0;
	}

	/**
	 * Record a newsletter signup from any caller.
	 *
	 * @since 3.15.0
	 */
	public function record_newsletter_signup() {

		$this->state->record_history( Config::ITEM_NEWSLETTER );
	}

	/**
	 * Remember the genuine main event archive before SC converts it to a virtual page.
	 *
	 * @since 3.15.0
	 *
	 * @param array    $posts Main query posts.
	 * @param WP_Query $query Current query.
	 *
	 * @return array
	 */
	public function capture_archive_query( $posts, $query ) {

		if ( ! $query->is_main_query() ) {
			return $posts;
		}

		$this->archive_query   = null;
		$this->archive_blog_id = 0;

		if (
			$query->is_post_type_archive( sugar_calendar_get_event_post_type_id() ) &&
			! $query->is_feed() &&
			! $query->is_preview() &&
			! $query->is_404()
		) {
			$this->archive_query   = $query;
			$this->archive_blog_id = get_current_blog_id();
		}

		return $posts;
	}

	/**
	 * Record an authorized archive render and notify only when the fact is new.
	 *
	 * @since 3.15.0
	 */
	public function record_archive_visit() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh -- Every condition protects the exact authorized render boundary.

		global $wp_query;

		if (
			$this->archive_query === null ||
			$wp_query !== $this->archive_query ||
			get_current_blog_id() !== $this->archive_blog_id ||
			! is_user_logged_in() ||
			! current_user_can( 'manage_options' ) ||
			is_feed() ||
			is_preview() ||
			is_404()
		) {
			return;
		}

		if ( $this->state->has_history( Config::ITEM_VISIT_EVENTS_PAGE ) ) {
			return;
		}

		$recorded = $this->state->record_history( Config::ITEM_VISIT_EVENTS_PAGE );

		if ( $recorded !== true ) {
			return;
		}

		wp_enqueue_script(
			'sugar-calendar-setup-checklist-archive',
			SC_PLUGIN_ASSETS_URL . 'js/setup-checklist-archive' . WP::asset_min() . '.js',
			[ 'jquery' ],
			BaseHelpers::get_asset_version(),
			true
		);

		wp_localize_script(
			'sugar-calendar-setup-checklist-archive',
			'sugar_calendar_setup_checklist_archive',
			[
				'ajax_url' => WP::admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'sugar_calendar_setup_checklist' ),
			]
		);
	}
}
