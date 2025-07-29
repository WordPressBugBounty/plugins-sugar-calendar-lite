<?php

namespace Sugar_Calendar\AddOn\Ticketing\Admin\Metabox;

// Exit if accessed directly.
use Sugar_Calendar\AddOn\Ticketing\Helpers\UI;
use Sugar_Calendar\Event;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_ticket_data;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_ticket_data_temporary;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\stripe_is_connected;

defined( 'ABSPATH' ) || exit;

/**
 * Register our Tickets metabox.
 *
 * @since 1.0.0
 *
 * @param object $metabox The metabox object.
 */
function metabox( $metabox ) {

	$metabox->add_section(
		[
			'id'       => 'tickets',
			'label'    => esc_html__( 'Tickets', 'sugar-calendar-lite' ),
			'icon'     => 'tickets-alt',
			'order'    => 80,
			'callback' => __NAMESPACE__ . '\\metabox_section',
		]
	);
}

/**
 * Render the Tickets metabox section.
 *
 * @since 1.0.0
 * @since 3.8.0 Add toggle limit meta data.
 *
 * @param Event $event The event object.
 */
function metabox_section( $event = null ) {

	$enabled = get_event_meta( $event->ID, 'tickets', true );

	?>

	<div class="sugar-calendar-metabox__field-row">
		<p class="desc">
			<?php esc_html_e( 'Configure the setting below to enable and sell tickets for this event.', 'sugar-calendar-lite' ); ?>
		</p>
	</div>

	<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--enable_tickets">
		<label for="enable_tickets"><?php esc_html_e( 'Ticket Sales', 'sugar-calendar-lite' ); ?></label>
		<div class="sugar-calendar-metabox__field">
			<?php
			UI::toggle_control(
				/**
				 * Filter the enable tickets toggle args.
				 *
				 * @since 3.7.0
				 *
				 * @param array $args  Args for the UI toggle.
				 * @param Event $event The Event object.
				 */
				apply_filters(
					'sc_et_enable_tickets_toggle_args',
					[
						'id'            => 'enable_tickets',
						'name'          => 'enable_tickets',
						'value'         => $enabled,
						'toggle_labels' => [
							esc_html__( 'ON', 'sugar-calendar-lite' ),
							esc_html__( 'OFF', 'sugar-calendar-lite' ),
						],
					],
					$event
				),
				true
			);
			?>
		</div>
	</div>

	<?php
	/**
	 * Fires after the ticket toggle field.
	 *
	 * @since 3.6.0
	 *
	 * @param Event $event The event object.
	 */
	do_action( 'sc_et_metabox_after_toggle', $event );

	/**
	 * Render the general ticket fields.
	 *
	 * @since 3.8.0
	 *
	 * @param Event $event The event object.
	 */
	render_ticket_fields( $event );

	/**
	 * Fires after the ticket price field.
	 *
	 * @since 1.0.0
	 *
	 * @param Event $event The event object.
	 */
	do_action( 'sc_et_metabox_bottom', $event );
}

/**
 * Render the ticket fields section.
 *
 * @since 3.8.0
 *
 * @param Event $event          The event object.
 * @param int   $ticket_type_id The ticket type ID.
 * @param bool  $is_temporary   Whether this is a temporary ticket.
 */
function render_ticket_fields( $event, $ticket_type_id = 0, $is_temporary = false ) {

	// Get ticket data based on whether this is a temporary ticket.
	if ( $is_temporary ) {
		$ticket_data = get_ticket_data_temporary();
	} else {
		$ticket_data = get_ticket_data( $event, $ticket_type_id );
	}

	// Check if this is a general ticket (ticket_type_id = 0).
	$is_general_ticket = $ticket_type_id === 0;

	// Set ticket type to blank if lower than 0.
	$ticket_type_id = $ticket_type_id < 0 ? '' : $ticket_type_id;

	// Define field names with or without array notation based on ticket type.
	$price_field_name    = $is_general_ticket ? 'ticket_price' : apply_filters( 'sugar_calendar_event_ticketing_admin_area_ticket_price_field_name', 'ticket_price', $ticket_type_id );
	$capacity_field_name = $is_general_ticket ? 'ticket_limit_capacity' : apply_filters( 'sugar_calendar_event_ticketing_admin_area_ticket_limit_capacity_field_name', 'ticket_limit_capacity', $ticket_type_id );
	$quantity_field_name = $is_general_ticket ? 'ticket_quantity' : apply_filters( 'sugar_calendar_event_ticketing_admin_area_ticket_quantity_field_name', 'ticket_quantity', $ticket_type_id );

	// Define toggle ID based on ticket type.
	$capacity_toggle_id = $is_general_ticket ? 'ticket_limit_capacity' : "ticket_limit_capacity_{$ticket_type_id}";

	// Extra toggle class for limit capacity field row.
	$field_row_class_ticket_limit_capacity_enabled = $ticket_data['ticket_limit_capacity']
		? 'sugar-calendar-metabox__field-row--ticket_limit_capacity-enabled'
		: 'sugar-calendar-metabox__field-row--ticket_limit_capacity-disabled';

	$disabled = ! stripe_is_connected();

	/**
	 * Fires before the ticket fields.
	 *
	 * @since 3.8.0
	 *
	 * @param Event  $event        The event object.
	 * @param string $ticket_type  The ticket type.
	 * @param bool   $is_temporary Whether this is a temporary ticket.
	 */
	do_action( 'sc_et_metabox_before_ticket_fields', $event, $ticket_type_id, $is_temporary );
	?>

	<!-- Ticket Price -->
	<div
		class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--ticket_price"
		ticket-type-id="<?php echo esc_attr( $ticket_type_id ); ?>"
	>
		<label for="ticket_price"><?php esc_html_e( 'Price', 'sugar-calendar-lite' ); ?></label>
		<div class="sugar-calendar-metabox__field">
			<input
				name="<?php echo esc_attr( $price_field_name ); ?>"
				type="text"
				inputmode="numeric"
				autocomplete="off"
				placeholder="0.00"
				pattern="^[0-9]{1,18}([,.][0-9]{1,9})?$"
				data-lpignore="true" value="<?php echo $ticket_data['ticket_price'] ? esc_attr( $ticket_data['ticket_price'] ) : ''; ?>"
				<?php echo $disabled ? 'disabled' : ''; ?>
			/>
		</div>
	</div>

	<!-- Limit Capacity -->
	<div
		class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--ticket_limit_capacity <?php echo esc_attr( $field_row_class_ticket_limit_capacity_enabled ); ?>"
		ticket-type-id="<?php echo esc_attr( $ticket_type_id ); ?>"
	>
		<label for="<?php echo esc_attr( $capacity_toggle_id ); ?>"><?php esc_html_e( 'Limit Capacity', 'sugar-calendar-lite' ); ?></label>
		<div class="sugar-calendar-metabox__field">
			<?php
			UI::toggle_control(
				/**
				 * Filter the limit capacity toggle args.
				 *
				 * @since 3.7.0
				 * @since 3.8.0 Add ticket type ID to filter.
				 *
				 * @param array $args           Args for the UI toggle.
				 * @param Event $event          The Event object.
				 * @param int   $ticket_type_id The ticket type ID.
				 */
				apply_filters(
					'sc_et_limit_capacity_toggle_args',
					[
						'id'            => $capacity_toggle_id,
						'name'          => $capacity_field_name,
						'value'         => $ticket_data['ticket_limit_capacity'],
						'toggle_labels' => [
							esc_html__( 'ON', 'sugar-calendar-lite' ),
							esc_html__( 'OFF', 'sugar-calendar-lite' ),
						],
						'is_multiple'   => true,
					],
					$event,
					$ticket_type_id
				),
				true
			);
			?>
		</div>
	</div>

	<!-- Capacity -->
	<div
		class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--ticket_quantity sugar-calendar-metabox__field-row--ticket_quantity-general"
		ticket-type-id="<?php echo esc_attr( $ticket_type_id ); ?>"
	>
		<label for="ticket_quantity"><?php esc_html_e( 'Capacity', 'sugar-calendar-lite' ); ?></label>
		<div class="sugar-calendar-metabox__field">
			<input
				name="<?php echo esc_attr( $quantity_field_name ); ?>"
				type="number"
				inputmode="numeric"
				autocomplete="off"
				min="1"
				step="1"
				placeholder="0"
				pattern="[0-9]"
				data-lpignore="true"
				value="<?php echo esc_attr( $ticket_data['ticket_quantity'] ); ?>"
				<?php echo $disabled ? 'disabled' : ''; ?>
			/>
		</div>
	</div>

	<?php
	/**
	 * Fires after the ticket fields.
	 *
	 * @since 3.8.0
	 *
	 * @param Event $event          The event object.
	 * @param int   $ticket_type_id The ticket type ID.
	 */
	do_action( 'sc_et_metabox_after_ticket_fields', $event, $ticket_type_id );
	?>
	<div
		class="sugar-calendar-metabox__field-row__sep sugar-calendar-metabox__field-row__sep"
		ticket-type-id="<?php echo esc_attr( $ticket_type_id ); ?>"
	></div>
	<?php
}

/**
 * Filter enable tickets toggle args to disable when Stripe is not connected.
 *
 * @since 3.8.0
 *
 * @param array $args  Args for the UI toggle.
 * @param Event $event The Event object.
 *
 * @return array
 */
function filter_enable_tickets_args_for_stripe( $args, $event ) {

	if ( ! stripe_is_connected() ) {
		$args['disabled'] = true;
	}

	return $args;
}

/**
 * Render Stripe connection notice after the enable tickets toggle.
 *
 * @since 3.8.0
 *
 * @param Event $event Event object.
 */
function render_stripe_connection_notice( $event ) {

	$hide_class = '';

	if ( stripe_is_connected() ) {
		$hide_class = 'sugar-calendar-metabox__notice__hide';
	}

	$settings_url = admin_url( 'admin.php?page=sugarcalendar-settings&section=payments#sugar-calendar-setting-stripe-connection' );
	?>
	<div id="sugar-calendar-metabox__stripe-connection-notice" class="sugar-calendar-metabox__notice <?php echo esc_attr( $hide_class ); ?>">
		<span class="dashicons dashicons-info-outline"></span>
		<p>
			<?php
			printf(
				/* translators: %s - URL to payment settings page. */
				esc_html__( 'Ticket Sales cannot be used when %s.', 'sugar-calendar-lite' ),
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( $settings_url ),
					esc_html__( 'Stripe is not connected', 'sugar-calendar-lite' )
				)
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Filter limit capacity toggle args to disable when Stripe is not connected.
 *
 * @since 3.8.0
 *
 * @param array $args           Args for the UI toggle.
 * @param Event $event          The Event object.
 * @param int   $ticket_type_id The ticket type ID.
 *
 * @return array
 */
function filter_limit_capacity_toggle_args_for_stripe( $args, $event, $ticket_type_id ) {

	if ( ! stripe_is_connected() ) {
		$args['disabled'] = true;
	}

	return $args;
}
