<?php

namespace Sugar_Calendar\Frontend\StructuredData;

use Sugar_Calendar\Event;

/**
 * Aggregates registered schema providers into one JSON-LD <script> in the
 * single-event page <head>.
 *
 * @since 3.12.0
 */
class StructuredData {

	/**
	 * Request-scoped guard against double emission.
	 *
	 * @since 3.12.0
	 *
	 * @var bool
	 */
	private $printed = false;

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function hooks() {

		add_action( 'wp_head', [ $this, 'render' ] );
	}

	/**
	 * Print the JSON-LD @graph for the current single event.
	 *
	 * @since 3.12.0
	 *
	 * @return void
	 */
	public function render() {

		if ( $this->printed ) {
			return;
		}

		$event = $this->get_renderable_event();

		if ( $event === null ) {
			return;
		}

		/**
		 * Master kill-switch for all structured-data output.
		 *
		 * @since 3.12.0
		 *
		 * @param bool  $enabled Whether to emit JSON-LD. Default true.
		 * @param Event $event   The event being rendered.
		 */
		if ( ! (bool) apply_filters( 'sugar_calendar_structured_data_enabled', true, $event ) ) { // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			return;
		}

		$nodes = $this->collect_nodes( $event );

		if ( empty( $nodes ) ) {
			return;
		}

		$graph = [
			'@context' => 'https://schema.org',
			'@graph'   => $nodes,
		];

		$this->printed = true;

		// JSON_HEX_TAG/AMP/APOS/QUOT escape <, >, &, ', " to \u00XX so no value
		// can break out of the <script> tag (or an inline-handler context),
		// independent of the default "/" => "\/" escaping. This encoding IS the
		// escaping — do not concatenate raw values.
		$json = wp_json_encode( $graph, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );

		echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Resolve the event for the current request, or null when the page is not
	 * a public single-event page.
	 *
	 * Never leaks details from a non-public post into crawlable markup.
	 *
	 * @since 3.12.0
	 *
	 * @return Event|null
	 */
	private function get_renderable_event() {

		if ( ! is_singular( sugar_calendar_get_event_post_type_id() ) ) {
			return null;
		}

		$post_id = get_queried_object_id();

		if (
			$post_id === 0
			|| get_post_status( $post_id ) !== 'publish'
			|| post_password_required( $post_id )
		) {
			return null;
		}

		$event = sugar_calendar_get_event_by_object( $post_id, 'post' );

		return empty( $event->object_id ) ? null : $event;
	}

	/**
	 * Collect every registered provider's schema node for the event.
	 *
	 * @since 3.12.0
	 *
	 * @param Event $event The event being rendered.
	 *
	 * @return array<int, array> Zero or more schema.org nodes.
	 */
	private function collect_nodes( $event ) {

		/**
		 * Registered schema providers.
		 *
		 * @since 3.12.0
		 *
		 * @param SchemaProviderInterface[] $providers Provider instances. Default [].
		 */
		$providers = apply_filters( 'sugar_calendar_structured_data_providers', [] ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		$nodes = [];

		foreach ( (array) $providers as $provider ) {

			if ( ! $provider instanceof SchemaProviderInterface ) {
				continue;
			}

			$node = $provider->get_schema( $event );

			if ( is_array( $node ) && ! empty( $node ) ) {
				$nodes[] = $node;
			}
		}

		return $nodes;
	}
}
