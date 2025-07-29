<?php

namespace Sugar_Calendar\AddOn\Ticketing\Admin;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Output admin notices.
 *
 * @since 1.0.0
 * @since 3.8.0 Added event-missing notice.
 */
function notices() {

	// Bail if no notice to show.
	if ( empty( $_GET['sc-notice-id'] ) ) {
		return;
	}

	// Bail if user cannot manage options.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Defaults.
	$notice_text = false;
	$notice_type = ! empty( $_GET['sc-notice-type'] )
		? sanitize_key( $_GET['sc-notice-type'] )
		: '';

	// Notices are displayed when &sc-notice-id=% is present in the URL.
	switch ( $_GET['sc-notice-id'] ) {
		case 'order-refund':
			$notice_text = $notice_type === 'updated'
				? esc_html__( 'Order successfully refunded.', 'sugar-calendar-lite' )
				: esc_html__( 'Order not refunded.', 'sugar-calendar-lite' );
			break;

		case 'order-update':
			$notice_text = $notice_type === 'updated'
				? esc_html__( 'Order successfully updated.', 'sugar-calendar-lite' )
				: esc_html__( 'Order not updated.', 'sugar-calendar-lite' );
			break;

		case 'order-delete':
			$notice_text = $notice_type === 'updated'
				? esc_html__( 'Order successfully deleted.', 'sugar-calendar-lite' )
				: esc_html__( 'Order not deleted.', 'sugar-calendar-lite' );
			break;

		case 'email-send':
			$notice_text = $notice_type === 'updated'
				? esc_html__( 'Ticket successfully emailed.', 'sugar-calendar-lite' )
				: esc_html__( 'Ticket not emailed.', 'sugar-calendar-lite' );
			break;

		case 'email-resend':
			$notice_text = $notice_type === 'updated'
				? esc_html__( 'Receipt successfully resent.', 'sugar-calendar-lite' )
				: esc_html__( 'Receipt not resent.', 'sugar-calendar-lite' );
			break;

		case 'event-missing':
			$notice_text = esc_html__( 'Cannot send ticket email. Event not found.', 'sugar-calendar-lite' );
			break;
	}

	// Bail if no notice.
	if ( empty( $notice_text ) ) {
		return;
	}

	// Output the notice HTML.
	?>

	<div class="<?php echo esc_attr( $notice_type ); ?> notice is-dismissible sugar-calendar-notice">
		<p>
			<?php echo esc_html( $notice_text ); ?>
		</p>
	</div>

	<?php
}
