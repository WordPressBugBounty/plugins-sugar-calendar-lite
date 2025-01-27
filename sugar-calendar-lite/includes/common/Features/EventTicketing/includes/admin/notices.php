<?php

namespace Sugar_Calendar\AddOn\Ticketing\Admin;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Output admin notices
 *
 * @since 1.0.0
 */
function notices() {

	// Bail if no notice to show
	if ( empty( $_GET['sc-notice-id'] ) ) {
		return;
	}

	// Bail if user cannot manage options
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Defaults
	$notice_text = false;
	$notice_type = ! empty( $_GET['sc-notice-type'] )
		? sanitize_key( $_GET['sc-notice-type'] )
		: '';

	// Notices are displayed when &sc-notice-id=% is present in the URL
	switch ( $_GET['sc-notice-id'] ) {

		// Refund
		case 'order-refund' :
			$notice_text = ( 'updated' === $notice_type )
				? esc_html__( 'Order successfully refunded.', 'sugar-calendar-lite' )
				: esc_html__( 'Order not refunded.', 'sugar-calendar-lite' );
			break;

		// Update
		case 'order-update' :
			$notice_text = ( 'updated' === $notice_type )
				? esc_html__( 'Order successfully updated.', 'sugar-calendar-lite' )
				: esc_html__( 'Order not updated.', 'sugar-calendar-lite' );
			break;

		// Delete
		case 'order-delete' :
			$notice_text = ( 'updated' === $notice_type )
				? esc_html__( 'Order successfully deleted.', 'sugar-calendar-lite' )
				: esc_html__( 'Order not deleted.', 'sugar-calendar-lite' );
			break;

		// Email
		case 'email-send' :
			$notice_text = ( 'updated' === $notice_type )
				? esc_html__( 'Ticket successfully emailed.', 'sugar-calendar-lite' )
				: esc_html__( 'Ticket not emailed.', 'sugar-calendar-lite' );
			break;

		// Resend email
		case 'email-resend' :
			$notice_text = ( 'updated' === $notice_type )
				? esc_html__( 'Receipt successfully resent.', 'sugar-calendar-lite' )
				: esc_html__( 'Receipt not resent.', 'sugar-calendar-lite' );
			break;
	}

	// Bail if no notice
	if ( empty( $notice_text ) ) {
		return;
	}

	// Output the notice HTML
	?>

    <div class="<?php echo esc_attr( $notice_type ); ?> notice is-dismissible sugar-calendar-notice">
        <p>
			<?php echo esc_html( $notice_text ); ?>
        </p>
    </div>

	<?php
}
