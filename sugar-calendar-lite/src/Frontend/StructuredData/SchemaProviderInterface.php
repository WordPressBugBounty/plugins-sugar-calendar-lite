<?php

namespace Sugar_Calendar\Frontend\StructuredData;

use Sugar_Calendar\Event;

/**
 * Contract for a schema.org JSON-LD node provider.
 *
 * Implementations are PURE: given an event, return one schema.org node
 * (associative array) or null. No output, no side effects — this is the
 * unit of behavior the aggregator collects and renders.
 *
 * @since 3.12.0
 */
interface SchemaProviderInterface {

	/**
	 * Build a schema.org node for this event, or null to emit nothing.
	 *
	 * @since 3.12.0
	 *
	 * @param Event $event The event being rendered.
	 *
	 * @return array|null A single schema.org node, or null.
	 */
	public function get_schema( $event );
}
