<?php

namespace Sugar_Calendar\UsageTracking;

use Elementor\Core\Base\Document;
use Sugar_Calendar\Admin\Tools\Export\ExportRequest;
use Sugar_Calendar\Features\Tags\Common\Helpers as TagsHelpers;
use Sugar_Calendar\Integrations\IntegrationCapabilityRegistry;
use Sugar_Calendar\Integrations\MeetingProviderInterface;
use Sugar_Calendar\Integrations\OnlineMeeting;
use Sugar_Calendar\Options;
use Throwable;
use WP_Post;

/**
 * Product Events for everything a Lite install can do.
 *
 * Pro\UsageTracking\ProductEvents sends the Pro-only events and answers this
 * class's filters for data only Pro can read.
 *
 * @since 3.15.0
 */
class ProductEvents extends AbstractProductEvents {

	/**
	 * `created_via` for an event made in the admin.
	 *
	 * @since 3.15.0
	 */
	const VIA_ADMIN = 'admin';

	/**
	 * `created_via` for an event made through a Frontend Event Submissions form.
	 *
	 * @since 3.15.0
	 */
	const VIA_FRONTEND_SUBMISSION = 'frontend_submission';

	/**
	 * `created_via` for an event written through the abilities layer (Gia AI, MCP clients).
	 *
	 * @since 3.15.0
	 */
	const VIA_AI_ASSISTANT = 'ai_assistant';

	/**
	 * Import sources reported by `import_completed`.
	 *
	 * @since 3.15.0
	 */
	const IMPORT_SOURCES = [ 'tec', 'json', 'ics' ];

	/**
	 * Export data types reported by `export_completed`. Lite's upsell placeholders (`_venues` …) are not data types.
	 *
	 * @since 3.15.0
	 */
	const EXPORT_MODULES = [ 'events', 'custom_fields', 'calendars', 'orders', 'tags', 'venues', 'speakers', 'registration_responses' ];

	/**
	 * Providers reported by the integration events; anything else never reaches the relay.
	 *
	 * @since 3.15.0
	 */
	const INTEGRATION_PROVIDERS = [ 'zoom', 'google_maps', 'stripe' ];

	/**
	 * Stripe modes.
	 *
	 * @since 3.15.0
	 */
	const STRIPE_MODES = [ 'live', 'sandbox' ];

	/**
	 * Events created in this request, keyed by event ID, valued by `created_via`.
	 *
	 * @since 3.15.0
	 *
	 * @var string[]
	 */
	private $created_events = [];

	/**
	 * Register hooks.
	 *
	 * @since 3.15.0
	 */
	protected function hooks() {

		add_action( 'created_sc_event_category', [ $this, 'track_calendar_created' ], 999, 3 );
		add_action( 'sugar_calendar_added_event', [ $this, 'note_event_created' ], 999 );
		add_action( 'sugar_calendar_events_event_writer_after_write', [ $this, 'mark_ai_assistant' ] );
		add_action( 'post_updated', [ $this, 'on_post_updated' ], 10, 3 );
		add_action( 'elementor/document/before_save', [ $this, 'on_elementor_document_before_save' ], 10, 2 );
		add_action( 'sugar_calendar_admin_pages_smtp_install_clicked', [ $this, 'track_smtp_install_clicked' ] );
		add_action( 'sugar_calendar_importer_completed', [ $this, 'track_import_completed' ], 10, 2 );
		add_action( 'sugar_calendar_admin_pages_tools_export_tab_before_output', [ $this, 'track_export_completed' ], 10, 2 );
		add_action( 'sugar_calendar_integration_connected', [ $this, 'track_integration_connected' ], 10, 2 );
		add_action( 'sugar_calendar_integration_disconnected', [ $this, 'track_integration_disconnected' ], 10, 2 );

		// Google Maps has no code booted on every request and two writers (settings page, setup wizard).
		add_action( 'update_option_' . Options::OPTION_NAME, [ $this, 'on_options_updated' ], 10, 2 );
		add_action( 'add_option_' . Options::OPTION_NAME, [ $this, 'on_options_added' ], 10, 2 );
	}

	/**
	 * Track calendar category creation event.
	 *
	 * @since 3.11.0
	 *
	 * @param int   $term_id Term ID.
	 * @param int   $tt_id   Term taxonomy ID.
	 * @param array $args    Arguments passed to wp_insert_term().
	 */
	public function track_calendar_created( $term_id, $tt_id, $args ) {

		if ( ! $this->can_track_creation() || ! $this->is_allowed() ) {
			return;
		}

		$term = get_term( $term_id, 'sc_event_category' );

		if ( is_wp_error( $term ) || ! $term ) {
			return;
		}

		$this->track_event(
			'calendar_created',
			[
				'calendar_id' => $term_id,
				'is_default'  => $term_id === (int) sugar_calendar_get_default_calendar(),
				'has_parent'  => ! empty( $term->parent ),
			]
		);
	}

	/**
	 * Note a new event; its payload is built on shutdown, after every save.
	 *
	 * @since 3.15.0
	 *
	 * @param int $event_id Sugar Calendar event ID.
	 */
	public function note_event_created( $event_id ) {

		if ( ! $this->can_track_creation() ) {
			return;
		}

		if ( empty( $this->created_events ) ) {
			$this->defer(
				function () {
					$this->track_created_events();
				}
			);
		}

		$this->created_events[ (int) $event_id ] = self::VIA_ADMIN;
	}

	/**
	 * Mark an event noted in this request as written through the abilities layer.
	 *
	 * The hook also fires on updates; only events created in this request are marked.
	 *
	 * @since 3.15.0
	 *
	 * @param int $event_id Sugar Calendar event ID.
	 */
	public function mark_ai_assistant( $event_id ) {

		$event_id = (int) $event_id;

		if ( isset( $this->created_events[ $event_id ] ) ) {
			$this->created_events[ $event_id ] = self::VIA_AI_ASSISTANT;
		}
	}

	/**
	 * Track a WP Mail SMTP promo-page install/activate click.
	 *
	 * @since 3.12.0
	 *
	 * @param string $type Button variant: 'install' or 'activate'.
	 */
	public function track_smtp_install_clicked( $type ) {

		if ( ! $this->is_allowed() ) {
			return;
		}

		if ( ! in_array( $type, [ 'install', 'activate' ], true ) ) {
			return;
		}

		$this->track_event(
			'smtp_install_clicked',
			[
				'type' => $type,
			]
		);
	}

	/**
	 * Track a finished import.
	 *
	 * @since 3.15.0
	 *
	 * @param string $source          Import source.
	 * @param int    $events_imported Number of events the import created.
	 */
	public function track_import_completed( $source, $events_imported ) {

		$source = sanitize_key( (string) $source );

		if ( ! in_array( $source, self::IMPORT_SOURCES, true ) ) {
			return;
		}

		$this->track_event(
			'import_completed',
			[
				'source'          => $source,
				'events_imported' => absint( $events_imported ),
			]
		);
	}

	/**
	 * Track an export download.
	 *
	 * @since 3.15.0
	 *
	 * @param array  $modules Selected data-type keys.
	 * @param string $format  Export format slug.
	 */
	public function track_export_completed( $modules, $format ) {

		$modules = array_values( array_intersect( self::EXPORT_MODULES, (array) $modules ) );

		if ( empty( $modules ) ) {
			return;
		}

		$this->track_event(
			'export_completed',
			[
				'modules' => $modules,
				'format'  => in_array( $format, ExportRequest::FORMATS, true ) ? $format : ExportRequest::FORMATS[0],
			]
		);
	}

	/**
	 * Track an integration connecting.
	 *
	 * @since 3.15.0
	 *
	 * @param string $provider Provider slug.
	 * @param array  $details  Provider details.
	 */
	public function track_integration_connected( $provider, $details = [] ) {

		$this->track_integration( 'integration_connected', $provider, (array) $details );
	}

	/**
	 * Track an integration disconnecting.
	 *
	 * @since 3.15.0
	 *
	 * @param string $provider Provider slug.
	 * @param array  $details  Provider details.
	 */
	public function track_integration_disconnected( $provider, $details = [] ) {

		$this->track_integration( 'integration_disconnected', $provider, (array) $details );
	}

	/**
	 * Report a Google Maps key that went from empty to set, or set to empty.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $value     New option value.
	 */
	public function on_options_updated( $old_value, $value ) {

		$this->maybe_track_maps_key_change( (array) $old_value, (array) $value );
	}

	/**
	 * Report a Google Maps key stored with the option's first write.
	 *
	 * @since 3.15.0
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 */
	public function on_options_added( $option, $value ) {

		$this->maybe_track_maps_key_change( [], (array) $value );
	}

	/**
	 * Send an integration event for an allowlisted provider.
	 *
	 * @since 3.15.0
	 *
	 * @param string $event_name Event name: integration_connected or integration_disconnected.
	 * @param string $provider   Provider slug.
	 * @param array  $details    Provider details.
	 */
	private function track_integration( $event_name, $provider, $details ) {

		$provider = sanitize_key( (string) $provider );

		if ( ! in_array( $provider, self::INTEGRATION_PROVIDERS, true ) ) {
			return;
		}

		$properties = [
			'provider' => $provider,
		];

		if ( $provider === 'stripe' && isset( $details['mode'] ) && in_array( $details['mode'], self::STRIPE_MODES, true ) ) {
			$properties['mode'] = $details['mode'];
		}

		$this->track_event( $event_name, $properties );
	}

	/**
	 * Compare the Google Maps key before and after an option write.
	 *
	 * A key replaced by a different key is neither a connect nor a disconnect.
	 *
	 * @since 3.15.0
	 *
	 * @param array $old_value Previous settings.
	 * @param array $value     New settings.
	 */
	private function maybe_track_maps_key_change( $old_value, $value ) {

		$had_key = trim( (string) ( $old_value['maps_google_api_key'] ?? '' ) ) !== '';
		$has_key = trim( (string) ( $value['maps_google_api_key'] ?? '' ) ) !== '';

		if ( $had_key === $has_key ) {
			return;
		}

		$this->track_integration( $has_key ? 'integration_connected' : 'integration_disconnected', 'google_maps', [] );
	}

	/**
	 * Send `event_created` for every event noted in this request.
	 *
	 * @since 3.15.0
	 */
	private function track_created_events() {

		$created_events       = $this->created_events;
		$this->created_events = [];

		foreach ( $created_events as $event_id => $created_via ) {
			try {
				$this->track_event_created( $event_id, $created_via );
			} catch ( Throwable $e ) {
				unset( $e );
			}
		}
	}

	/**
	 * Track one event creation from its saved state.
	 *
	 * @since 3.11.0
	 * @since 3.15.0 Built on shutdown from saved data; Pro-only keys come from a filter.
	 *
	 * @param int    $event_id    Sugar Calendar event ID.
	 * @param string $created_via How the event was created.
	 */
	private function track_event_created( $event_id, $created_via ) {

		$sc_event = sugar_calendar_get_event( $event_id );

		if ( ! is_object( $sc_event ) || empty( $sc_event->id ) ) {
			return;
		}

		/**
		 * Filters whether to report this event creation at all.
		 *
		 * @since 3.15.0
		 *
		 * @param bool $track    Whether to report it.
		 * @param int  $event_id Sugar Calendar event ID.
		 */
		if ( ! apply_filters( 'sugar_calendar_usage_tracking_product_events_track_event_created', true, $event_id ) ) { // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			return;
		}

		/**
		 * Filters how an event created in this request was created.
		 *
		 * @since 3.15.0
		 *
		 * @param string $created_via admin, frontend_submission or ai_assistant.
		 * @param int    $event_id    Sugar Calendar event ID.
		 */
		$created_via = (string) apply_filters( 'sugar_calendar_usage_tracking_product_events_created_via', $created_via, $event_id ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		if ( ! in_array( $created_via, [ self::VIA_ADMIN, self::VIA_FRONTEND_SUBMISSION, self::VIA_AI_ASSISTANT ], true ) ) {
			$created_via = self::VIA_ADMIN;
		}

		$properties = [
			'event_id'           => $event_id,
			'calendar_id'        => $this->get_first_calendar_id( $sc_event ),
			'status'             => $sc_event->status,
			'is_recurring'       => ! empty( $sc_event->recurrence ),
			'has_venue'          => ! empty( $sc_event->venue_id ),
			'has_description'    => ! empty( $sc_event->content ),
			'has_ticketing'      => ! empty( get_event_meta( $event_id, 'tickets', true ) ),
			'has_rsvp'           => ! empty( get_event_meta( $event_id, 'rsvp_enable', true ) ),
			'has_speaker'        => false,
			'recurrence_type'    => $this->get_recurrence_type( $sc_event ),
			'online_meeting'     => $this->get_online_meeting( $event_id ),
			'has_tags'           => $this->has_tags( $sc_event ),
			'has_custom_url'     => ! empty( get_event_meta( $event_id, 'url', true ) ),
			'ticket_types_count' => 0,
			'created_via'        => $created_via,
		];

		/**
		 * Filters the `event_created` properties, for data only Pro or an add-on can read.
		 *
		 * @since 3.15.0
		 *
		 * @param array $properties Event properties.
		 * @param int   $event_id   Sugar Calendar event ID.
		 */
		$properties = (array) apply_filters( 'sugar_calendar_usage_tracking_product_events_event_created_properties', $properties, $event_id );

		// A submitter is often a guest, and their identity must not travel with the event.
		$context = $created_via === self::VIA_FRONTEND_SUBMISSION ? self::CONTEXT_SYSTEM : self::CONTEXT_USER;

		$this->track_event( 'event_created', $properties, $context );
	}

	/**
	 * First calendar assigned to the event, or '' when none.
	 *
	 * @since 3.15.0
	 *
	 * @param object $sc_event Sugar Calendar event.
	 *
	 * @return int|string
	 */
	private function get_first_calendar_id( $sc_event ) {

		if ( empty( $sc_event->object_id ) ) {
			return '';
		}

		$terms = wp_get_object_terms( $sc_event->object_id, 'sc_event_category', [ 'fields' => 'ids' ] );

		return ! is_wp_error( $terms ) && ! empty( $terms ) ? $terms[0] : '';
	}

	/**
	 * Recurrence type: none, advanced, or the frequency.
	 *
	 * @since 3.15.0
	 *
	 * @param object $sc_event Sugar Calendar event.
	 *
	 * @return string
	 */
	private function get_recurrence_type( $sc_event ) {

		if ( empty( $sc_event->recurrence ) ) {
			return 'none';
		}

		foreach ( SnapshotData::ADVANCED_RECURRENCE_META_KEYS as $meta_key ) {
			if ( ! empty( get_event_meta( $sc_event->id, $meta_key, true ) ) ) {
				return 'advanced';
			}
		}

		return sanitize_key( $sc_event->recurrence );
	}

	/**
	 * Online meeting provider slug: 'none', 'custom', a registered meeting provider, or 'other'.
	 *
	 * @since 3.15.0
	 *
	 * @param int $event_id Sugar Calendar event ID.
	 *
	 * @return string
	 */
	private function get_online_meeting( $event_id ) {

		$provider = sanitize_key( (string) get_event_meta( $event_id, 'online_provider', true ) );

		if ( $provider === '' ) {
			return 'none';
		}

		if (
			$provider === OnlineMeeting::PROVIDER_CUSTOM ||
			IntegrationCapabilityRegistry::instance()->find( MeetingProviderInterface::class, $provider ) !== null
		) {
			return $provider;
		}

		return 'other';
	}

	/**
	 * Reduce a calendars attribute to a comma-separated list of term IDs.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $calendars Comma-separated string or array.
	 *
	 * @return string
	 */
	private function sanitize_calendar_ids( $calendars ) {

		$ids = is_array( $calendars ) ? $calendars : explode( ',', (string) $calendars );

		return implode( ',', array_filter( array_map( 'absint', $ids ) ) );
	}

	/**
	 * Whether the event's post has any tag.
	 *
	 * @since 3.15.0
	 *
	 * @param object $sc_event Sugar Calendar event.
	 *
	 * @return bool
	 */
	private function has_tags( $sc_event ) {

		if ( empty( $sc_event->object_id ) ) {
			return false;
		}

		$terms = wp_get_object_terms( $sc_event->object_id, TagsHelpers::get_tags_taxonomy_id(), [ 'fields' => 'ids' ] );

		return ! is_wp_error( $terms ) && ! empty( $terms );
	}

	/**
	 * Track when calendar/list embeds are added to a post.
	 *
	 * @since 3.11.0
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  Post object after the update.
	 * @param WP_Post $post_before Post object before the update.
	 */
	public function on_post_updated( $post_id, $post_after, $post_before ) {

		if ( ! $this->is_allowed() ) {
			return;
		}

		// Validate post objects.
		if ( ! $post_after instanceof WP_Post || ! $post_before instanceof WP_Post ) {
			return;
		}

		// Only track standard post types.
		if ( ! in_array( $post_after->post_type, [ 'post', 'page' ], true ) ) {
			return;
		}

		// Skip autosaves and revisions.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$new_content = $post_after->post_content;

		// Quick bail: check if any Sugar Calendar content exists in new content.
		if (
			strpos( $new_content, 'sugarcalendar' ) === false &&
			strpos( $new_content, 'sugar-calendar' ) === false
		) {
			return;
		}

		$old_content = $post_before->post_content;

		$this->track_calendar_shortcode_embedded( $post_id, $new_content, $old_content );
		$this->track_calendar_block_embedded( $post_id, $new_content, $old_content );
		$this->track_list_shortcode_embedded( $post_id, $new_content, $old_content );
		$this->track_list_block_embedded( $post_id, $new_content, $old_content );
	}

	/**
	 * Track when Elementor calendar/list widgets are added.
	 *
	 * @since 3.11.0
	 *
	 * @param Document $document Elementor document.
	 * @param array    $data     New data being saved.
	 */
	public function on_elementor_document_before_save( $document, $data ) {

		if ( ! $this->is_allowed() ) {
			return;
		}

		// Validate document object.
		if ( ! $document instanceof Document ) {
			return;
		}

		$new_elements = $data['elements'] ?? [];

		// Quick bail: encode new elements first and check if any SC widgets exist.
		$new_elements_json = (string) wp_json_encode( $new_elements );

		if ( strpos( $new_elements_json, 'sugar-calendar' ) === false ) {
			return;
		}

		$post_id           = $document->get_main_id();
		$old_elements      = $document->get_elements_data();
		$old_elements_json = (string) wp_json_encode( $old_elements );

		$this->track_calendar_elementor_embedded( $post_id, $old_elements_json, $new_elements_json, $new_elements );
		$this->track_list_elementor_embedded( $post_id, $old_elements_json, $new_elements_json, $new_elements );
	}

	/**
	 * Track calendar shortcode if newly added.
	 *
	 * @since 3.11.0
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $new_content New post content.
	 * @param string $old_content Old post content.
	 */
	private function track_calendar_shortcode_embedded( $post_id, $new_content, $old_content ) {

		$shortcode = 'sugarcalendar_events_calendar';

		if ( ! $this->is_shortcode_newly_added( $shortcode, $new_content, $old_content ) ) {
			return;
		}

		$shortcode_data = $this->parse_shortcode( $shortcode, $new_content );
		$atts           = $shortcode_data['atts'];
		$count          = $shortcode_data['count'];

		$display = 'month';

		if ( ! empty( $atts['display'] ) && in_array( $atts['display'], [ 'month', 'week', 'day' ], true ) ) {
			$display = $atts['display'];
		}

		$this->track_event(
			'calendar_embedded',
			[
				'post_id'         => $post_id,
				'calendar_id'     => $this->sanitize_calendar_ids( $atts['calendars'] ?? '' ),
				'embed_method'    => 'shortcode',
				'display_mode'    => $display,
				'calendar_count'  => $count,
				'filters_enabled' => isset( $atts['show_filters'] ) ? filter_var( $atts['show_filters'], FILTER_VALIDATE_BOOLEAN ) : true,
				'search_enabled'  => isset( $atts['show_search'] ) ? filter_var( $atts['show_search'], FILTER_VALIDATE_BOOLEAN ) : true,
			]
		);
	}

	/**
	 * Track calendar block if newly added.
	 *
	 * @since 3.11.0
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $new_content New post content.
	 * @param string $old_content Old post content.
	 */
	private function track_calendar_block_embedded( $post_id, $new_content, $old_content ) {

		$block = 'sugar-calendar/block';

		if ( ! $this->is_block_newly_added( $block, $new_content, $old_content ) ) {
			return;
		}

		$atts  = $this->get_first_block_atts( $block, $new_content );
		$count = $this->count_block_instances( $block, $new_content );

		$display = 'month';

		if ( ! empty( $atts['display'] ) && in_array( $atts['display'], [ 'month', 'week', 'day' ], true ) ) {
			$display = $atts['display'];
		}

		$calendar_id = '';

		if ( ! empty( $atts['calendars'] ) ) {
			$calendar_id = is_array( $atts['calendars'] ) ? implode( ',', $atts['calendars'] ) : $atts['calendars'];
		}

		$this->track_event(
			'calendar_embedded',
			[
				'post_id'         => $post_id,
				'calendar_id'     => $this->sanitize_calendar_ids( $calendar_id ),
				'embed_method'    => 'block',
				'display_mode'    => $display,
				'calendar_count'  => $count,
				'filters_enabled' => filter_var( $atts['showFilters'] ?? true, FILTER_VALIDATE_BOOLEAN ),
				'search_enabled'  => filter_var( $atts['showSearch'] ?? true, FILTER_VALIDATE_BOOLEAN ),
			]
		);
	}

	/**
	 * Track events list shortcode if newly added.
	 *
	 * @since 3.11.0
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $new_content New post content.
	 * @param string $old_content Old post content.
	 */
	private function track_list_shortcode_embedded( $post_id, $new_content, $old_content ) {

		$shortcode = 'sugarcalendar_events_list';

		if ( ! $this->is_shortcode_newly_added( $shortcode, $new_content, $old_content ) ) {
			return;
		}

		$shortcode_data = $this->parse_shortcode( $shortcode, $new_content );
		$atts           = $shortcode_data['atts'];

		$this->track_event(
			'events_list_embedded',
			[
				'post_id'      => $post_id,
				'calendar_id'  => $this->sanitize_calendar_ids( $atts['calendars'] ?? '' ),
				'embed_method' => 'shortcode',
				'show_excerpt' => isset( $atts['show_descriptions'] ) ? filter_var( $atts['show_descriptions'], FILTER_VALIDATE_BOOLEAN ) : true,
				'show_date'    => isset( $atts['show_date_cards'] ) ? filter_var( $atts['show_date_cards'], FILTER_VALIDATE_BOOLEAN ) : true,
			]
		);
	}

	/**
	 * Track events list block if newly added.
	 *
	 * @since 3.11.0
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $new_content New post content.
	 * @param string $old_content Old post content.
	 */
	private function track_list_block_embedded( $post_id, $new_content, $old_content ) {

		$block = 'sugar-calendar/event-list-block';

		if ( ! $this->is_block_newly_added( $block, $new_content, $old_content ) ) {
			return;
		}

		$atts = $this->get_first_block_atts( $block, $new_content );

		$calendar_id = '';

		if ( ! empty( $atts['calendars'] ) ) {
			$calendar_id = is_array( $atts['calendars'] ) ? implode( ',', $atts['calendars'] ) : $atts['calendars'];
		}

		$this->track_event(
			'events_list_embedded',
			[
				'post_id'      => $post_id,
				'calendar_id'  => $this->sanitize_calendar_ids( $calendar_id ),
				'embed_method' => 'block',
				'show_excerpt' => filter_var( $atts['showDescriptions'] ?? true, FILTER_VALIDATE_BOOLEAN ),
				'show_date'    => filter_var( $atts['showDateCards'] ?? true, FILTER_VALIDATE_BOOLEAN ),
			]
		);
	}

	/**
	 * Track calendar Elementor widget if newly added.
	 *
	 * @since 3.11.0
	 *
	 * @param int    $post_id           Post ID.
	 * @param string $old_elements_json JSON string of old elements.
	 * @param string $new_elements_json JSON string of new elements.
	 * @param array  $new_elements      New elements array.
	 */
	private function track_calendar_elementor_embedded( $post_id, $old_elements_json, $new_elements_json, $new_elements ) {

		$widget = 'sugar-calendar-events-calendar';

		if ( ! $this->is_elementor_widget_newly_added( $widget, $old_elements_json, $new_elements_json ) ) {
			return;
		}

		$atts  = $this->find_elementor_widget_settings( $new_elements, $widget );
		$count = substr_count( $new_elements_json, '"widgetType":"' . $widget . '"' );

		$display = 'month';

		if ( ! empty( $atts['display_mode'] ) && in_array( $atts['display_mode'], [ 'month', 'week', 'day' ], true ) ) {
			$display = $atts['display_mode'];
		}

		$calendar_id = '';

		if ( ! empty( $atts['calendars'] ) ) {
			$calendar_id = is_array( $atts['calendars'] ) ? implode( ',', $atts['calendars'] ) : $atts['calendars'];
		}

		$this->track_event(
			'calendar_embedded',
			[
				'post_id'         => $post_id,
				'calendar_id'     => $this->sanitize_calendar_ids( $calendar_id ),
				'embed_method'    => 'elementor',
				'display_mode'    => $display,
				'calendar_count'  => $count,
				'filters_enabled' => isset( $atts['show_filters'] ) ? ( $atts['show_filters'] === 'yes' ) : true,
				'search_enabled'  => isset( $atts['show_search'] ) ? ( $atts['show_search'] === 'yes' ) : true,
			]
		);
	}

	/**
	 * Track events list Elementor widget if newly added.
	 *
	 * @since 3.11.0
	 *
	 * @param int    $post_id           Post ID.
	 * @param string $old_elements_json JSON string of old elements.
	 * @param string $new_elements_json JSON string of new elements.
	 * @param array  $new_elements      New elements array.
	 */
	private function track_list_elementor_embedded( $post_id, $old_elements_json, $new_elements_json, $new_elements ) {

		$widget = 'sugar-calendar-events-list';

		if ( ! $this->is_elementor_widget_newly_added( $widget, $old_elements_json, $new_elements_json ) ) {
			return;
		}

		$atts = $this->find_elementor_widget_settings( $new_elements, $widget );

		$calendar_id = '';

		if ( ! empty( $atts['calendars'] ) ) {
			$calendar_id = is_array( $atts['calendars'] ) ? implode( ',', $atts['calendars'] ) : $atts['calendars'];
		}

		$this->track_event(
			'events_list_embedded',
			[
				'post_id'      => $post_id,
				'calendar_id'  => $this->sanitize_calendar_ids( $calendar_id ),
				'embed_method' => 'elementor',
				'show_excerpt' => isset( $atts['show_descriptions'] ) ? ( $atts['show_descriptions'] === 'yes' ) : true,
				'show_date'    => isset( $atts['show_date_cards'] ) ? ( $atts['show_date_cards'] === 'yes' ) : true,
			]
		);
	}

	/**
	 * Check if an Elementor widget was newly added.
	 *
	 * @since 3.11.0
	 *
	 * @param string $widget            Widget name.
	 * @param string $old_elements_json JSON string of old elements.
	 * @param string $new_elements_json JSON string of new elements.
	 *
	 * @return bool
	 */
	private function is_elementor_widget_newly_added( $widget, $old_elements_json, $new_elements_json ) {

		// Quick check: if widget doesn't exist in new data, bail.
		if ( empty( $new_elements_json ) || strpos( $new_elements_json, $widget ) === false ) {
			return false;
		}

		// If widget existed in old data, not newly added.
		if ( ! empty( $old_elements_json ) && strpos( $old_elements_json, $widget ) !== false ) {
			return false;
		}

		return true;
	}

	/**
	 * Find widget settings in Elementor data using iterative approach.
	 *
	 * @since 3.11.0
	 *
	 * @param array  $elements Elementor elements.
	 * @param string $widget   Widget name.
	 *
	 * @return array
	 */
	private function find_elementor_widget_settings( $elements, $widget ) {

		if ( ! is_array( $elements ) || empty( $elements ) ) {
			return [];
		}

		$queue      = [ $elements ];
		$iterations = 0;
		$max        = 1000;

		while ( ! empty( $queue ) && $iterations < $max ) {
			$current_level = array_shift( $queue );

			if ( ! is_array( $current_level ) ) {
				continue;
			}

			foreach ( $current_level as $element ) {
				++$iterations;

				if ( $iterations > $max ) {
					break 2;
				}

				if ( ! is_array( $element ) ) {
					continue;
				}

				if ( isset( $element['widgetType'] ) && $element['widgetType'] === $widget ) {
					return $element['settings'] ?? [];
				}

				if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
					$queue[] = $element['elements'];
				}
			}
		}

		return [];
	}

	/**
	 * Check if a shortcode was newly added to content.
	 *
	 * @since 3.11.0
	 *
	 * @param string $shortcode   Shortcode tag.
	 * @param string $new_content New post content.
	 * @param string $old_content Old post content.
	 *
	 * @return bool
	 */
	private function is_shortcode_newly_added( $shortcode, $new_content, $old_content ) {

		// Quick check: if shortcode tag doesn't exist in new content, bail.
		if ( strpos( $new_content, $shortcode ) === false ) {
			return false;
		}

		// If shortcode existed in old content, not newly added.
		if ( strpos( $old_content, $shortcode ) !== false && has_shortcode( $old_content, $shortcode ) ) {
			return false;
		}

		// Confirm it's actually a valid shortcode in new content.
		return has_shortcode( $new_content, $shortcode );
	}

	/**
	 * Check if a block was newly added to content.
	 *
	 * @since 3.11.0
	 *
	 * @param string $block       Block name.
	 * @param string $new_content New post content.
	 * @param string $old_content Old post content.
	 *
	 * @return bool
	 */
	private function is_block_newly_added( $block, $new_content, $old_content ) {

		// Quick check: if block name doesn't exist in new content, bail.
		if ( strpos( $new_content, $block ) === false ) {
			return false;
		}

		// If block existed in old content, not newly added.
		if ( strpos( $old_content, $block ) !== false && has_block( $block, $old_content ) ) {
			return false;
		}

		// Confirm it's actually a valid block in new content.
		return has_block( $block, $new_content );
	}

	/**
	 * Parse shortcode content and return attributes and count.
	 *
	 * @since 3.11.0
	 *
	 * @param string $shortcode Shortcode tag.
	 * @param string $content   Post content.
	 *
	 * @return array Array with 'atts' and 'count' keys.
	 */
	private function parse_shortcode( $shortcode, $content ) {

		$pattern = get_shortcode_regex( [ $shortcode ] );

		preg_match_all( '/' . $pattern . '/s', $content, $matches, PREG_SET_ORDER );

		$count = count( $matches );
		$atts  = [];

		if ( ! empty( $matches[0][3] ) ) {
			$atts = shortcode_parse_atts( $matches[0][3] );

			if ( ! is_array( $atts ) ) {
				$atts = [];
			}
		}

		return [
			'atts'  => $atts,
			'count' => $count,
		];
	}

	/**
	 * Get attributes from the first instance of a block.
	 *
	 * @since 3.11.0
	 *
	 * @param string $block   Block name.
	 * @param string $content Post content.
	 *
	 * @return array
	 */
	private function get_first_block_atts( $block, $content ) {

		// Use regex to extract block attributes JSON without parsing all blocks.
		$pattern = '/<!--\s*wp:' . preg_quote( $block, '/' ) . '\s+(\{[^}]*\})/';

		if ( ! preg_match( $pattern, $content, $match ) ) {
			return [];
		}

		$atts = json_decode( $match[1], true );

		return is_array( $atts ) ? $atts : [];
	}

	/**
	 * Count block instances in content.
	 *
	 * @since 3.11.0
	 *
	 * @param string $block   Block name.
	 * @param string $content Post content.
	 *
	 * @return int
	 */
	private function count_block_instances( $block, $content ) {

		$pattern = '/<!--\s*wp:' . preg_quote( $block, '/' ) . '[\s\{]/';

		preg_match_all( $pattern, $content, $matches );

		return count( $matches[0] );
	}
}
