<?php

namespace Sugar_Calendar\AddOn\Ticketing\Admin\Pages;

use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\AddOn\Ticketing\Common\Functions;
use Sugar_Calendar\AddOn\Ticketing\Helpers\UI;
use Sugar_Calendar\AddOn\Ticketing\Settings;
use function Sugar_Calendar\AddOn\Ticketing\Common\Assets\get_url;
use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\Features\RegistrationForm\RespondentNaming;

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

		// Order.
		$order = Functions\get_order( $order_id );

		// Bail if no order.
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
	 * Two columns sharing WordPress core's #poststuff/.postbox chrome: the record
	 * and tickets on the left, Details and Actions on the right, one <form> wrapping both.
	 *
	 * @since 1.2.0
	 */
	public function display() {
		/*
		 * The Tickets submenu's cap maps to edit_posts, so any Contributor can
		 * reach this page by URL. Gate reads at manage_options too, matching every
		 * action handler below, since the panels now carry editable attendee PII
		 * and free-text registration answers.
		 */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have the necessary capabilities to view orders.', 'sugar-calendar-lite' ),
				esc_html__( 'Error', 'sugar-calendar-lite' ),
				[ 'response' => 403 ]
			);
		}

		// Form action.
		$form_action = add_query_arg(
			[
				'page'     => 'sc-event-ticketing',
				'order_id' => $this->order->id,
			],
			admin_url( 'admin.php' )
		);

		// Event.
		$event   = sugar_calendar_get_event( $this->order->event_id );
		$tickets = Functions\get_order_tickets( $this->order->id );
		?>

		<div id="sugar-calendar-order" class="wrap sugar-calendar-admin-wrap">

			<div class="sugar-calendar-admin-content">

				<h1 class="screen-reader-text"><?php esc_html_e( 'Edit Ticket', 'sugar-calendar-lite' ); ?></h1>

				<form id="edit-item-info" method="post" action="<?php echo esc_url( $form_action ); ?>">

					<?php wp_nonce_field( 'sc_event_tickets', 'sc_event_tickets_nonce', false, true ); ?>
					<input type="hidden" name="order_id" value="<?php echo absint( $this->order->id ); ?>"/>

					<?php
					// postboxes.js posts this back so collapsed-panel state survives a reload.
					wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
					?>

					<?php
					/*
					 * Must stay the form's first submit control and be Update. Pressing Enter
					 * in a field activates the first submit button in tree order, and the side
					 * column (rendered first) opens with Delete, which would otherwise delete
					 * the order irreversibly. Hidden by clipping, not display:none, so it stays
					 * the default button; tabindex="-1" and aria-hidden keep it inaccessible.
					 */
					?>
					<input
						type="submit"
						name="sc_et_update_order"
						class="sc-et-default-submit"
						tabindex="-1"
						aria-hidden="true"
						value="<?php esc_attr_e( 'Update', 'sugar-calendar-lite' ); ?>"/>

					<div id="poststuff">
						<div id="post-body" class="metabox-holder columns-2">

							<div id="postbox-container-1" class="postbox-container">
								<?php $this->render_side_column( $event ); ?>
							</div>

							<div id="postbox-container-2" class="postbox-container">

								<?php
								$this->render_summary_panel( $event );

								do_action( 'sc_et_admin_order_before_tickets', $this->order );

								$this->render_tickets_panel( $tickets, $event );

								do_action( 'sc_et_admin_order_after_tickets', $this->order );

								/**
								 * Renders panels below the tickets table, inside the page's form.
								 *
								 * Unlike the four sibling sc_et_admin_order_* actions, this one fires
								 * inside the <form>, so editable inputs added here actually submit.
								 *
								 * @since 3.13.0
								 *
								 * @param object $order The order object.
								 */
								do_action( 'sc_et_admin_order_panels', $this->order ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- Named for the four sibling sc_et_admin_order_* hooks on this page; a lone differently-prefixed name would be the odd one out. A standalone ignore on the preceding line would detach the docblock above and trip the missing-PHPDoc sniff instead.
								?>

							</div>

						</div>

						<br class="clear"/>
					</div>

					<?php do_action( 'sc_et_admin_order_bottom', $this->order ); ?>

				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * The record panel: who bought what, and the editable status.
	 *
	 * @since 3.13.0
	 *
	 * @param object|false $event The event, when it still exists.
	 */
	private function render_summary_panel( $event ) {

		// Transactions may be empty.
		$transaction = ! empty( $this->order->transaction_id )
			? $this->order->transaction_id
			: '&mdash;';

		$format = sc_get_date_format() . ' \a\t ' . sc_get_time_format();

		$title = sprintf(
			/* translators: %1$d - order id, %2$s - customer name. */
			__( '#%1$d - Ticket for %2$s', 'sugar-calendar-lite' ),
			absint( $this->order->id ),
			RespondentNaming::purchaser( $this->order )
		);

		UI::postbox_open( 'sc-et-order-summary', $title );

		do_action( 'sc_et_admin_order_top', $this->order );

		UI::form_table_open();

		UI::form_table_row_open( __( 'Transaction ID', 'sugar-calendar-lite' ) );
		printf(
			'<a href="%1$s" target="_blank">%2$s</a>',
			esc_url( $this->payment_url() ),
			esc_html( $transaction )
		);
		UI::form_table_row_close();

		UI::form_table_row_open( __( 'Customer', 'sugar-calendar-lite' ) );

		/*
		 * esc_html on the email too: the orders schema has no sanitize_email
		 * validator, and make_clickable() passes non-matches through verbatim.
		 */
		printf(
			'%1$s (<a href="%2$s">%3$s</a>)',
			esc_html( RespondentNaming::purchaser( $this->order ) ),
			esc_url( 'mailto:' . $this->order->email ),
			esc_html( $this->order->email )
		);
		UI::form_table_row_close();

		UI::form_table_row_open( __( 'Event', 'sugar-calendar-lite' ) );

		if ( empty( $event ) ) {
			echo '&mdash;';
		} else {
			printf(
				'<a href="%1$s">%2$s</a> &mdash; %3$s',
				esc_url( admin_url( 'post.php?action=edit&post=' . $event->object_id ) ),
				esc_html( $event->title ),
				esc_html( $event->format_date( $format, strtotime( $this->order->event_date ) ) )
			);
		}

		UI::form_table_row_close();

		UI::form_table_row_open( __( 'Status', 'sugar-calendar-lite' ), 'sc-et-status' );
		?>
		<select name="status" id="sc-et-status">
			<?php foreach ( [ 'pending', 'paid', 'refunded', 'trash' ] as $status ) : ?>
				<option value="<?php echo esc_attr( $status ); ?>"<?php selected( $this->order->status, $status ); ?>>
					<?php echo esc_html( Functions\order_status_label( $status ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
		UI::form_table_row_close();

		UI::form_table_row_open( __( 'Total', 'sugar-calendar-lite' ) );
		echo esc_html( Functions\display_price( $this->order->total ) );
		UI::form_table_row_close();

		UI::form_table_close();
		UI::postbox_close();
	}

	/**
	 * The tickets table panel.
	 *
	 * @since 3.13.0
	 *
	 * @param array        $tickets The order's tickets.
	 * @param object|false $event   The event, when it still exists.
	 */
	private function render_tickets_panel( $tickets, $event ) {

		UI::postbox_open( 'sugar-calendar-order__tickets', __( 'Tickets', 'sugar-calendar-lite' ) );
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th class="sugar-calendar-order__tickets__table-row__first-cell"><?php esc_html_e( 'ID', 'sugar-calendar-lite' ); ?></th>
					<th><?php esc_html_e( 'Code', 'sugar-calendar-lite' ); ?></th>
					<th class="sugar-calendar-order__tickets__table-row__last-cell"><?php esc_html_e( 'Attendee', 'sugar-calendar-lite' ); ?></th>

					<?php
					/**
					 * Action for extra columns.
					 *
					 * @since 3.8.0
					 *
					 * @param Order $order   The order object.
					 * @param array $tickets The tickets array.
					 * @param Event $event   The event object.
					 */
					do_action( 'sugar_calendar_add_on_ticketing_admin_pages_order_edit_tickets_table_header', $this->order, $tickets, $event );
					?>
				</tr>
			</thead>

			<tbody>
				<?php
				foreach ( $tickets as $ticket ) {
					$this->render_ticket_row( $ticket, $event );
				}

				do_action( 'sc_et_admin_order_ticket_list', $this->order );
				?>
			</tbody>
		</table>
		<?php
		UI::postbox_close();
	}

	/**
	 * One ticket row.
	 *
	 * Extracted from display() unchanged when the page moved to two columns.
	 *
	 * @since 3.13.0
	 *
	 * @param object       $ticket The ticket.
	 * @param object|false $event  The event, when it still exists.
	 */
	private function render_ticket_row( $ticket, $event ) {

		// Get the attendee.
		$attendee = Functions\get_attendee( $ticket->attendee_id );

		// Try to put the name together.
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
			<td class="sugar-calendar-order__tickets__table-row__first-cell">
				<span class="row-title"><?php echo absint( $ticket->id ); ?></span>
				<div class="row-actions">
					<?php
					$actions = [];

					$actions['print'] = sprintf(
						'<a href="%1$s" target="_blank">%2$s</a>',
						esc_url( $print_url ),
						esc_html__( 'Print', 'sugar-calendar-lite' )
					);

					if ( ! empty( $attendee->email ) && ! empty( $event ) ) {
						$actions['email'] = sprintf(
							'<a href="%1$s">%2$s</a>',
							esc_url( $email_url ),
							esc_html__( 'Resend Email', 'sugar-calendar-lite' )
						);
					}

					/**
					 * Filters one ticket row's hover actions.
					 *
					 * Values are ready-to-print HTML keyed by action slug, matching
					 * WP_List_Table's row-actions shape.
					 *
					 * @since 3.13.0
					 *
					 * @param array  $actions Action slug => HTML.
					 * @param object $ticket  The ticket.
					 * @param object $order   The order.
					 */
					$actions = (array) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- Matches the sugar_calendar_add_on_ticketing_admin_pages_order_edit_* family already on this page.
						'sugar_calendar_add_on_ticketing_admin_pages_order_edit_ticket_row_actions',
						$actions,
						$ticket,
						$this->order
					);

					// Already escaped above and by each filter consumer; the separator is
					// what core's row actions use between links.
					echo wp_kses_post( implode( ' | ', array_filter( $actions ) ) );
					?>
				</div>
			</td>

			<td>
				<span class="sc-et-ticket-code">
					<code><?php echo esc_html( $ticket->code ); ?></code>
				</span>
			</td>

			<td class="sugar-calendar-order__tickets__table-row__last-cell">
				<?php
				echo esc_html( $name );

				if ( ! empty( $attendee->email ) ) :
					printf(
						'<br><a href="mailto:%1$s">%2$s</a>',
						esc_attr( $attendee->email ),
						esc_html( $attendee->email )
					);
				endif;
				?>

				<?php
				/**
				 * Action for extra columns.
				 *
				 * @since 3.8.0
				 *
				 * @param Ticket   $ticket   The ticket object.
				 * @param Attendee $attendee The attendee object.
				 * @param Order    $order    The order object.
				 * @param Event    $event    The event object.
				 */
				do_action( 'sugar_calendar_add_on_ticketing_admin_pages_order_edit_tickets_table_row', $ticket, $attendee, $this->order, $event );
				?>
			</td>
		</tr>
		<?php
	}

	/**
	 * The right column: Details (with the submit pair) and Actions.
	 *
	 * The submit pair lives here because the <form> wraps both columns.
	 *
	 * @since 3.13.0
	 *
	 * @param object|false $event The event, when it still exists.
	 */
	private function render_side_column( $event ) {

		UI::postbox_open( 'sc-et-order-details', __( 'Details', 'sugar-calendar-lite' ) );

		/*
		 * Core's Publish-box fact list, not a .form-table: a form table stacks
		 * label above value, which reads poorly in this narrow 280px rail.
		 */
		echo '<div id="misc-publishing-actions">';

		UI::misc_pub_row_open( __( 'Order ID', 'sugar-calendar-lite' ), 'admin-network' );
		echo esc_html( '#' . $this->order->id );
		UI::misc_pub_row_close();

		UI::misc_pub_row_open( __( 'Purchase Date', 'sugar-calendar-lite' ), 'calendar-alt', true );
		echo esc_html( $this->order->date_created );
		UI::misc_pub_row_close();

		UI::misc_pub_row_open( __( 'Processor', 'sugar-calendar-lite' ), 'store' );
		printf(
			'<a href="%1$s" target="_blank">%2$s</a>',
			esc_url( $this->payment_url() ),
			esc_html__( 'Stripe', 'sugar-calendar-lite' )
		);
		UI::misc_pub_row_close();

		echo '</div>';

		/*
		 * Core's own submit footer, styled like the post editor's Publish box.
		 * Both inputs keep their POST names, so the admin_init:30 handlers in
		 * includes/admin/view.php still fire.
		 */
		if ( current_user_can( 'manage_options' ) ) {
			?>
			<div class="submitbox">
				<div id="major-publishing-actions">
					<div id="delete-action">
						<?php
						/*
						 * .submitbox .submitdelete (core's common.css) is what colours this
						 * red; not .button-link, which would repaint it theme blue. Confirmed
						 * on click since delete() removes the order and every ticket and
						 * registration response on it, with nothing to restore from.
						 */
						?>
						<input
							type="submit"
							name="sc_et_delete_order"
							class="submitdelete"
							data-sc-confirm="delete"
							value="<?php esc_attr_e( 'Delete', 'sugar-calendar-lite' ); ?>"/>
					</div>
					<div id="publishing-action">
						<input
							type="submit"
							name="sc_et_update_order"
							class="button button-primary button-large"
							value="<?php esc_attr_e( 'Update', 'sugar-calendar-lite' ); ?>"/>
					</div>
					<div class="clear"></div>
				</div>
			</div>
			<?php
		}

		UI::postbox_close();

		UI::postbox_open( 'sc-et-order-actions', __( 'Actions', 'sugar-calendar-lite' ) );

		// Decorative dashicons, hidden from assistive tech since each action names itself.
		if ( ! empty( $event ) ) {
			printf(
				'<p><span class="dashicons dashicons-media-text" aria-hidden="true"></span><input type="submit" name="sc_et_resend_receipt" data-sc-confirm="resend" value="%1$s"/></p>',
				esc_attr__( 'Resend Email Receipt', 'sugar-calendar-lite' )
			);
		}

		printf(
			'<p><span class="dashicons dashicons-external" aria-hidden="true"></span><a href="%1$s" target="_blank">%2$s</a></p>',
			esc_url( $this->payment_url() ),
			esc_html__( 'View in Stripe', 'sugar-calendar-lite' )
		);

		UI::postbox_close();
	}

	/**
	 * The Stripe dashboard URL for this order's payment.
	 *
	 * @since 3.13.0
	 *
	 * @return string
	 */
	private function payment_url() {

		$test = (bool) Settings\get_setting( 'sandbox' ) ? 'test/' : '';

		return 'https://dashboard.stripe.com/' . $test . 'payments/' . $this->order->transaction_id;
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

		/*
		 * Core's metabox script, for the panel collapse toggles. add_postbox_toggles()
		 * also wires sortables, but only for .meta-box-sortables, which this page has none of.
		 */
		wp_enqueue_script( 'postbox' );
		wp_add_inline_script(
			'postbox',
			'jQuery( function( $ ) { postboxes.add_postbox_toggles( pagenow ); } );'
		);

		// The self-contained jQuery-Confirm theme, needed since this page loads no
		// other Sugar Calendar admin stylesheet that would carry .sugar-calendar-btn.
		wp_enqueue_style( 'sugar-calendar-admin-confirm' );

		wp_enqueue_script(
			'sugar-calendar-ticketing-admin-order',
			SC_PLUGIN_ASSETS_URL . 'js/features/event-ticketing/admin-order' . WP::asset_min() . '.js',
			[ 'jquery', 'sugar-calendar-vendor-jquery-confirm' ],
			BaseHelpers::get_asset_version(),
			true
		);

		wp_localize_script(
			'sugar-calendar-ticketing-admin-order',
			'sugar_calendar_ticketing_admin_order',
			[ 'dialogs' => $this->confirm_dialogs() ]
		);
	}

	/**
	 * Copy for the page's confirmation dialogs, keyed by data-sc-confirm value.
	 *
	 * Kept here, not in the script, so every string is translatable and the
	 * resend message can name the recipient's actual address.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	private function confirm_dialogs() {

		$icons = SC_PLUGIN_ASSETS_URL . 'images/icons/';

		$dialogs = [
			'delete' => [
				'type'     => 'red',
				'icon_url' => $icons . 'exclamation-circle.svg',
				'title'    => esc_html__( 'Delete this order?', 'sugar-calendar-lite' ),
				'message'  => esc_html__( 'This removes the order, every ticket on it, and every registration answer collected for those tickets. There is no trash to restore from.', 'sugar-calendar-lite' ),
				'confirm'  => esc_html__( 'Delete', 'sugar-calendar-lite' ),
				'cancel'   => esc_html__( 'Cancel', 'sugar-calendar-lite' ),
			],
		];

		$email = empty( $this->order->email ) ? '' : $this->order->email;

		$dialogs['resend'] = [
			/*
			 * Orange, not blue, to match .sugar-calendar-btn-primary's brand-orange
			 * token; the delete dialog pairs red the same way with its red button.
			 */
			'type'     => 'orange',
			'icon_url' => $icons . 'envelope.svg',
			'title'    => esc_html__( 'Resend the email receipt?', 'sugar-calendar-lite' ),
			'message'  => $email === ''
				? esc_html__( 'The customer will be sent another copy of the receipt for this order.', 'sugar-calendar-lite' )
				: sprintf(
					/* translators: %s - the customer's email address. */
					esc_html__( 'Another copy of the receipt for this order will be sent to %s.', 'sugar-calendar-lite' ),
					esc_html( $email )
				),
			'confirm'  => esc_html__( 'Resend', 'sugar-calendar-lite' ),
			'cancel'   => esc_html__( 'Cancel', 'sugar-calendar-lite' ),
		];

		return $dialogs;
	}
}
