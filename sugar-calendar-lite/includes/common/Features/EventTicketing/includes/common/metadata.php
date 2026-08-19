<?php
namespace Sugar_Calendar\AddOn\Ticketing\Metadata;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Event;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\sanitize_amount;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\ticketing_provider_available_for_admin;

/**
 * Meta schema.
 *
 * @since 3.4.0
 *
 * @return array
 */
function schema() {

	$schema = [
		'tickets' => [
			'type'              => 'boolean',
			'description'       => '',
			'single'            => true,
			'sanitize_callback' => 'Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\sanitize_boolean',
			'auth_callback'     => null,
			'show_in_rest'      => false,
		],
		'ticket_price' => [
			'type'              => 'number',
			'description'       => '',
			'single'            => true,
			'sanitize_callback' => 'Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\sanitize_amount',
			'auth_callback'     => null,
			'show_in_rest'      => true,
		],
		'ticket_quantity' => [
			'type'              => 'integer',
			'description'       => '',
			'single'            => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => null,
			'show_in_rest'      => false,
		],
		'ticket_limit_capacity' => [
			'type'              => 'boolean',
			'description'       => '',
			'single'            => true,
			'sanitize_callback' => 'Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\sanitize_boolean',
			'auth_callback'     => null,
			'show_in_rest'      => false,
		],
	];

	/**
	 * Filter the metadata schema.
	 *
	 * @since 3.8.0
	 *
	 * @param array $schema The metadata schema.
	 */
	return apply_filters( 'sugar_calendar_event_ticketing_metadata_schema', $schema );
}

/**
 * Register meta data keys & sanitization callbacks.
 *
 * @since 1.0.0
 * @since 3.4.0 Use schema() to define meta keys.
 *
 * @param array $schema
 *
 * @return array
 */
function register_meta_data( $schema = [] ) {

	return array_merge( $schema, schema() );
}

/**
 * Save ticket meta data.
 *
 * @since 1.0.0
 * @since 3.8.0 Add limit capacity meta data.
 * @since 3.13.0 Accept the already-fetched `$event` (2nd `sugar_calendar_event_to_save`
 *                   filter arg) instead of re-querying it for the price-preservation branch.
 *
 * @param array      $event_data Array of event data.
 * @param Event|null $event      The existing event, or an empty Event for a brand-new one.
 *
 * @return array
 */
function save_meta_data( $event_data = [], $event = null ) {

	// Enable.
	$event_data['tickets'] = ! empty( $_POST['enable_tickets'] )
		? 1
		: 0;

	// Price.
	$event_data['ticket_price'] = isset( $_POST['ticket_price'] ) && $_POST['ticket_price'] !== ''
		? sanitize_amount( wp_unslash( $_POST['ticket_price'] ) )
		: '';

	// Free events: when tickets are enabled but no payment provider is connected,
	// a newly-submitted non-zero price can never be trusted (there's no gateway to
	// charge against), so it's always clamped to 0. The rendered Price field is
	// disabled in this state, though, so an *omitted* $_POST value doesn't mean
	// "the admin cleared it" -- it means the field couldn't be touched. Preserve
	// whatever price was already stored (e.g. set while a provider WAS connected)
	// instead of silently wiping it on the next unrelated save; the checkout-time
	// gates (Functions\is_ticket_price_stale(), the create_payment_intent() guard)
	// are what actually stop a stale price from ever being charged, not this clamp.
	if ( ! empty( $event_data['tickets'] ) && ! ticketing_provider_available_for_admin() ) {

		if ( ! isset( $_POST['ticket_price'] ) && $event instanceof Event && ! empty( $event->id ) ) {
			$event_data['ticket_price'] = get_event_meta( $event->id, 'ticket_price', true );
		} else {
			$event_data['ticket_price'] = 0;
		}
	}

	// Limit capacity.
	$event_data['ticket_limit_capacity'] = ! empty( $_POST['ticket_limit_capacity'] )
		? 1
		: 0;

	// Quantity.
	$event_data['ticket_quantity'] = $event_data['ticket_limit_capacity'] && ! empty( $_POST['ticket_quantity'] )
		? absint( $_POST['ticket_quantity'] )
		: '';

	// Return event metadata array.
	return $event_data;
}

/**
 * Maybe migrate ticket limit capacity.
 *
 * @since 3.8.0
 */
function maybe_migrate_ticket_limit_capacity() {

	/**
	 * Filter to skip the ticket limit capacity migration.
	 *
	 * @since 3.8.0
	 *
	 * @param bool $skip_migration Whether to skip the migration. Default false.
	 */
	if ( apply_filters( 'sugar_calendar_skip_ticket_limit_capacity_migration', false ) ) {
		return;
	}

	// Check if migration has already run.
	if ( get_option( 'sc_ticket_limit_capacity_migrated', false ) ) {
		return;
	}

	// Run migration.
	$migrated = migrate_ticket_limit_capacity_meta();

	// Mark as completed only if migration actually ran.
	if ( $migrated ) {
		update_option( 'sc_ticket_limit_capacity_migrated', true );

		/**
		 * Action fired after the complete ticket limit capacity migration.
		 *
		 * @since 3.8.0
		 */
		do_action( 'sugar_calendar_ticket_limit_capacity_migration_completed' );
	}
}

/**
 * Migrate ticket limit capacity meta.
 *
 * @since 3.8.0
 *
 * @return bool True if migration ran and completed, false if no records to migrate.
 */
function migrate_ticket_limit_capacity_meta() {

	global $wpdb;

	// Get all events that need migration in one optimized query.
	$ticket_quantities = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT tq.sc_event_id, tq.meta_value
			FROM {$wpdb->prefix}sc_eventmeta tq
			LEFT JOIN {$wpdb->prefix}sc_eventmeta tlc
				ON tq.sc_event_id = tlc.sc_event_id
				AND tlc.meta_key = %s
			WHERE tq.meta_key = %s
				AND tlc.meta_id IS NULL",
			'ticket_limit_capacity',
			'ticket_quantity'
		)
	);

	if ( empty( $ticket_quantities ) ) {
		return true; // No records to migrate means migration is complete.
	}

	/**
	 * Filter the batch size for ticket limit capacity migration.
	 *
	 * @since 3.8.0
	 *
	 * @param int $batch_size Maximum number of records to process in a single batch. Default 1000.
	 */
	$batch_size = apply_filters( 'sugar_calendar_ticket_limit_capacity_migration_batch_size', 1000 );

	// Get total quantity to migrate.
	$quantity_to_migrate_total = count( $ticket_quantities );

	// Limit the number of records to process in this batch.
	$ticket_quantities = array_slice( $ticket_quantities, 0, $batch_size );

	// Prepare all values for bulk insert.
	$values = [];

	foreach ( $ticket_quantities as $ticket_quantity ) {
		$quantity       = absint( $ticket_quantity->meta_value );
		$limit_capacity = $quantity > 0 ? 1 : 0;

		$values[] = $wpdb->prepare(
			'(%d, %s, %s)',
			$ticket_quantity->sc_event_id,
			'ticket_limit_capacity',
			$limit_capacity
		);
	}

	// Single bulk insert query.
	$query = "INSERT INTO {$wpdb->prefix}sc_eventmeta (sc_event_id, meta_key, meta_value) VALUES " . implode( ', ', $values );

	$quantity_migrated = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

	// If no result, return false.
	if ( $quantity_migrated === false ) {
		return false;
	}

	// Return the number of records migrated based on total quantity to migrate.
	return $quantity_migrated === $quantity_to_migrate_total;
}
