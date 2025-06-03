<?php

namespace Sugar_Calendar\AddOn\Ticketing\Admin\Pages;

use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\AddOn\Ticketing\Common\Functions;
use Sugar_Calendar\AddOn\Ticketing\Helpers\UI;
use Sugar_Calendar\AddOn\Ticketing\Settings;
use function Sugar_Calendar\AddOn\Ticketing\Common\Assets\get_url;
use Sugar_Calendar\Helpers as BaseHelpers;

/**
 * Description
 *
 * @since 1.2.0
 */
class OrderEdit {

	private $order;

	/**
	 * Page slug.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		return 'sc-event-ticketing-edit';
	}

	/**
	 * Whether the page appears in menus.
	 *
	 * @since 1.2.0
	 *
	 * @return bool
	 */
	public static function has_menu_item() {

		return false;
	}

	/**
	 * Which menu item to highlight
	 * if the page doesn't appear in dashboard menu.
	 *
	 * @since 1.2.0
	 *
	 * @return null|string;
	 */
	public static function highlight_menu_item() {

		return null;
	}

	/**
	 * Register page hooks.
	 *
	 * @since 1.2.0
	 */
	public function hooks() {

		add_action( 'admin_init', [ $this, 'init' ] );
		add_action( 'in_admin_header', [ $this, 'display_admin_subheader' ] );
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Initialize the page.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function init() {

		$order_id = absint( $_GET['order_id'] ?? 0 );

		// Order
		$order = Functions\get_order( $order_id );

		// Bail if no order
		if ( empty( $order ) ) {
			wp_die( esc_html__( 'You attempted to edit an item that does not exist. Perhaps it was deleted?', 'sugar-calendar-lite' ) );
		}

		$this->order = $order;
	}

	/**
	 * Display the subheader.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function display_admin_subheader() {

		?>
        <div class="sugar-calendar-admin-subheader">
            <h4><?php esc_html_e( 'Tickets', 'sugar-calendar-lite' ); ?></h4>

			<?php
			UI::button(
				[
					'text'  => esc_html__( 'Back to All Orders', 'sugar-calendar-lite' ),
					'size'  => 'sm',
					'class' => 'sugar-calendar-btn-new-item',
					'link'  => OrdersTab::get_url(),
				]
			);
			?>
        </div>

		<?php
		/**
		 * Runs before the page content is displayed.
		 *
		 * @since 1.2.0
		 */
		do_action( 'sugar_calendar_admin_page_before' ); //phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		?>
		<?php
	}

	/**
	 * Display page.
	 *
	 * @since 1.2.0
	 */
	public function display() {

		// Transactions may be empty
		$transaction = ! empty( $this->order->transaction_id )
			? $this->order->transaction_id
			: '&mdash;';

		// Stripe URL
		$sandbox     = (bool) Settings\get_setting( 'sandbox' );
		$test        = $sandbox ? 'test/' : '';
		$payment_url = 'https://dashboard.stripe.com/' . $test . 'payments/' . $this->order->transaction_id;

		// Form action
		$form_action = add_query_arg(
			[
				'page'     => 'sc-event-ticketing',
				'order_id' => $this->order->id,
			],
			admin_url( 'admin.php' )
		);

		// Event
		$event  = sugar_calendar_get_event( $this->order->event_id );
		$format = sc_get_date_format() . ' \a\t ' . sc_get_time_format();

		// Tickets
		$tickets = Functions\get_order_tickets( $this->order->id ); ?>

        <div id="sugar-calendar-order" class="wrap sugar-calendar-admin-wrap">

            <div class="sugar-calendar-admin-content">

                <h1 class="screen-reader-text"><?php echo esc_html_e( 'Edit Ticket', 'sugar-calendar-lite' ); ?></h1>

                <h2>
					<?php
					echo esc_html(
						sprintf(
							'#%1$s - (%2$s %3$s)',
							$this->order->id,
							$this->order->first_name,
							$this->order->last_name
						)
					);
					?>
                </h2>

                <form id="edit-item-info" method="post" action="<?php echo esc_url( $form_action ); ?>">
                    <div class="sugar-calendar-order-metabox">
                        <div class="sugar-calendar-order-metabox__header">
                            <h4><?php esc_html_e( 'Order Details', 'sugar-calendar-lite' ); ?></h4>
                        </div>
                        <div class="sugar-calendar-order-metabox__body">

							<?php do_action( 'sc_et_admin_order_top', $this->order ); ?>

                            <div class="sugar-calendar-metabox__field-row">
                                <label><?php esc_html_e( 'Order ID', 'sugar-calendar-lite' ); ?></label>
                                <div class="sugar-calendar-metabox__field"><?php echo esc_html( $this->order->id ); ?></div>
                            </div>
                            <div class="sugar-calendar-metabox__field-row">
                                <label><?php esc_html_e( 'Transaction ID', 'sugar-calendar-lite' ); ?></label>
                                <div class="sugar-calendar-metabox__field">
                                    <a href="<?php echo esc_url( $payment_url ); ?>" target="_blank"><?php echo esc_html( $transaction ); ?></a>
                                </div>
                            </div>
                            <div class="sugar-calendar-metabox__field-row">
                                <label><?php esc_html_e( 'Purchase Date', 'sugar-calendar-lite' ); ?></label>
                                <div class="sugar-calendar-metabox__field">
									<?php echo esc_html( $this->order->date_created ); ?>
                                </div>
                            </div>
                            <div class="sugar-calendar-metabox__field-row">
                                <label><?php esc_html_e( 'Customer', 'sugar-calendar-lite' ); ?></label>
                                <div class="sugar-calendar-metabox__field">
									<?php echo esc_html( $this->order->first_name . ' ' . $this->order->last_name ) . ' (' . make_clickable( $this->order->email ) . ')'; ?>
                                </div>
                            </div>
                            <div class="sugar-calendar-metabox__field-row">
                                <label><?php esc_html_e( 'Total', 'sugar-calendar-lite' ); ?></label>
                                <div class="sugar-calendar-metabox__field">
									<?php echo Functions\currency_filter( $this->order->total ); ?>
                                </div>
                            </div>
                            <div class="sugar-calendar-metabox__field-row">
                                <label for="sc-et-status"><?php esc_html_e( 'Status', 'sugar-calendar-lite' ); ?></label>
                                <div class="sugar-calendar-metabox__field">
                                    <select name="status" id="sc-et-status">
                                        <option value="pending"<?php selected( $this->order->status, 'pending' ); ?>><?php echo Functions\order_status_label( 'pending' ); ?></option>
                                        <option value="paid"<?php selected( $this->order->status, 'paid' ); ?>><?php echo Functions\order_status_label( 'paid' ); ?></option>
                                        <option value="refunded"<?php selected( $this->order->status, 'refunded' ); ?>><?php echo Functions\order_status_label( 'refunded' ); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="sugar-calendar-metabox__field-row">
                                <label><?php esc_html_e( 'Event', 'sugar-calendar-lite' ); ?></label>
                                <div class="sugar-calendar-metabox__field">
									<?php if ( ! empty( $event ) ) : ?>

                                        <a href="<?php echo esc_url( admin_url( 'post.php?action=edit&post=' . $event->object_id ) ); ?>">
											<?php echo esc_html( $event->title ); ?>
                                        </a>

                                        &mdash;

										<?php echo esc_html( $event->format_date( $format, strtotime( $this->order->event_date ) ) ); ?>

									<?php else : ?>

                                        &mdash;

									<?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="sugar-calendar-order-actions">
						<?php wp_nonce_field( 'sc_event_tickets', 'sc_event_tickets_nonce', false, true ); ?>

                        <input type="hidden" name="order_id" value="<?php echo absint( $this->order->id ); ?>"/>

						<?php if ( current_user_can( 'manage_options' ) ) : ?>

                            <input type="submit"
                                   name="sc_et_update_order"
                                   class="sugar-calendar-btn sugar-calendar-btn-md sugar-calendar-btn-secondary"
                                   value="<?php esc_attr_e( 'Update Order', 'sugar-calendar-lite' ); ?>"/>

						<?php endif; ?>

                        <input type="submit"
                               name="sc_et_resend_receipt"
                               class="sugar-calendar-btn sugar-calendar-btn-md sugar-calendar-btn-tertiary"
                               value="<?php esc_attr_e( 'Resend Email Receipt', 'sugar-calendar-lite' ); ?>"/>

						<?php if ( current_user_can( 'manage_options' ) ) : ?>

                            <input type="submit"
                                   name="sc_et_delete_order"
                                   class="sugar-calendar-btn sugar-calendar-btn-md sugar-calendar-btn-delete-order"
                                   value="<?php esc_attr_e( 'Delete Order', 'sugar-calendar-lite' ); ?>"/>

						<?php endif; ?>
                    </div>
                </form>

                <div class="sugar-calendar-order-metabox">
                    <div class="sugar-calendar-order-metabox__header">
                        <h4><?php esc_html_e( 'Tickets', 'sugar-calendar-lite' ); ?></h4>
                    </div>
                    <div class="sugar-calendar-order-metabox__body">

						<?php do_action( 'sc_et_admin_order_before_tickets', $this->order ); ?>

                        <table class="widefat striped">
                            <thead>
                            <tr>
                                <th><?php esc_html_e( 'ID', 'sugar-calendar-lite' ); ?></th>
                                <th><?php esc_html_e( 'Code', 'sugar-calendar-lite' ); ?></th>
                                <th><?php esc_html_e( 'Attendee', 'sugar-calendar-lite' ); ?></th>
                            </tr>
                            </thead>

                            <tbody>

							<?php

							foreach ( $tickets as $ticket ) :

								// Get the attendee
								$attendee = Functions\get_attendee( $ticket->attendee_id );

								// Try to put the name together
								$fname = ! empty( $attendee->first_name )
									? $attendee->first_name
									: '';
								$lname = ! empty( $attendee->last_name )
									? $attendee->last_name
									: '';
								$name  = ! empty( $fname . $lname )
									? $fname . ' ' . $lname
									: '&mdash;';

								$print_url = wp_nonce_url(
									add_query_arg(
										[
											'sc_et_action' => 'print',
											'ticket_code'  => $ticket->code,
										],
										home_url()
									),
									$ticket->code
								);

								$email_url = wp_nonce_url(
									add_query_arg(
										[
											'sc_et_action' => 'email_ticket',
											'ticket_code'  => $ticket->code,
										]
									),
									$ticket->code
								);

								?>

                                <tr>
                                    <td>
                                        <span class="row-title"><?php echo absint( $ticket->id ); ?></span>

                                        <div class="row-actions">
										<span class="print">
											<a href="<?php echo esc_url( $print_url ); ?>" target="_blank"><?php esc_html_e( 'Print', 'sugar-calendar-lite' ); ?></a>
										</span>

											<?php if ( ! empty( $attendee->email ) ) : ?>

                                                |

                                                <span class="email">
												<a href="<?php echo esc_url( $email_url ); ?>"><?php esc_html_e( 'Resend Email', 'sugar-calendar-lite' ); ?></a>
											</span>

											<?php endif; ?>

                                        </div>
                                    </td>

                                    <td>
                                        <code><?php echo esc_html( $ticket->code ); ?></code>
                                    </td>

                                    <td>
										<?php echo esc_html( $name ); ?>

										<?php

										if ( ! empty( $attendee->email ) ) :
											echo '<br>' . make_clickable( $attendee->email );
										endif;

										?>
                                    </td>
                                </tr>

							<?php endforeach; ?>

							<?php do_action( 'sc_et_admin_order_ticket_list', $this->order ); ?>

                            </tbody>
                        </table>

						<?php do_action( 'sc_et_admin_order_after_tickets', $this->order ); ?>

						<?php do_action( 'sc_et_admin_order_bottom', $this->order ); ?>

                    </div>
                </div>
            </div>
        </div>

		<?php
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style(
			'sugar-calendar-ticketing-admin-order',
			get_url( 'css' ) . '/admin-order' . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);
	}
}
