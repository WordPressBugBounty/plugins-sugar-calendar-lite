<?php
/**
 * Shared hook wiring for event field contributors.
 *
 * @since 3.15.0
 */

namespace Sugar_Calendar\Integrations\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * AbstractEventFieldContributor — registers the contributor hooks once.
 *
 * @since 3.15.0
 */
abstract class AbstractEventFieldContributor implements EventFieldContributorInterface {

	/**
	 * Priority for the input schema and snapshot filters.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	protected const SCHEMA_PRIORITY = 10;

	/**
	 * Priority for the after-write action.
	 *
	 * @since 3.15.0
	 *
	 * @var int
	 */
	protected const APPLY_PRIORITY = 10;

	/**
	 * Register the hooks.
	 *
	 * The snapshot filter takes 3 args so a subclass can read the resolved event; a
	 * 2-param `snapshot()` ignores the extra one.
	 *
	 * @since 3.15.0
	 *
	 * @return void
	 */
	public static function hooks() {

		add_filter( 'sugar_calendar_integrations_abilities_event_input_schema', [ static::class, 'add_schema' ], static::SCHEMA_PRIORITY, 2 );
		add_filter( 'sugar_calendar_integrations_abilities_event_snapshot', [ static::class, 'snapshot' ], static::SCHEMA_PRIORITY, 3 );
		add_action( 'sugar_calendar_events_event_writer_after_write', [ static::class, 'apply' ], static::APPLY_PRIORITY, 3 );
		add_filter( 'sugar_calendar_ai_field_labels', [ static::class, 'field_labels' ] );
	}

	/**
	 * Leave the labels as they are, for a field core already labels.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $labels Write property name => label.
	 *
	 * @return mixed
	 */
	public static function field_labels( $labels ) {

		return $labels;
	}

	/**
	 * Leave the snapshot as it is, for a field with nothing worth diffing.
	 *
	 * @since 3.15.0
	 *
	 * @param mixed $snapshot Snapshot so far.
	 * @param mixed $event_id Sugar Calendar event id.
	 *
	 * @return mixed
	 */
	public static function snapshot( $snapshot, $event_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		return $snapshot;
	}
}
