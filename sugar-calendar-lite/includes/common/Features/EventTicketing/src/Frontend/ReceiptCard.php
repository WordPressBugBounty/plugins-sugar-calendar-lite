<?php

namespace Sugar_Calendar\AddOn\Ticketing\Frontend;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\format_event_when;

/**
 * The ticket purchase receipt card.
 *
 * Replaces the legacy `<table>` receipt. The legacy markup is still reachable
 * through the `use_legacy_template` setting, so both must keep working.
 *
 * Renders a `ReceiptCardData` and nothing else: no lookups, no filters, so a
 * preview can feed it a hand-filled one directly.
 *
 * Built from the frontend Tailwind utilities (`scf:` prefix), which is why the
 * class strings are spelled out literally — Tailwind scans this file's source
 * text, so a class assembled by concatenation compiles to nothing.
 *
 * @since 3.14.0
 */
class ReceiptCard {

	/**
	 * The value under a label.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const VALUE = 'scf:self-stretch scf:text-[var(--sc-ticket-text,#1a1a1a)] scf:text-sm scf:font-medium scf:leading-[19.6px] sc-reset';

	/**
	 * A label + value pair. Half width, or full width for `w-full` callers.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const ITEM = 'scf:flex scf:flex-col scf:items-start scf:gap-2 sc-reset';

	/**
	 * Render the receipt card.
	 *
	 * @since 3.14.0
	 *
	 * @param ReceiptCardData $data Prepared card data.
	 *
	 * @return string
	 */
	public static function render( ReceiptCardData $data ) {

		return TicketCard::shell(
			$data->classes,
			function () use ( $data ) {
				self::header( $data->status );
				self::details( $data );
				self::tickets( $data->tickets, $data->read_only );
			}
		);
	}

	/**
	 * The status ring, its icon and the card title.
	 *
	 * @since 3.14.0
	 *
	 * @param string $status Order status.
	 */
	private static function header( $status ) {

		switch ( $status ) {
			case 'refunded':
				$ring  = 'scf:outline-[var(--sc-ticket-accent,#fa6400)]';
				$icon  = 'scf:icon-[fa7-solid--rotate-left] scf:text-[var(--sc-ticket-accent,#fa6400)]';
				$title = esc_html__( 'Your Ticket Was Refunded', 'sugar-calendar-lite' );
				break;

			case 'trash':
				$ring  = 'scf:outline-red-600';
				$icon  = 'scf:icon-[fa7-solid--triangle-exclamation] scf:text-red-600';
				$title = esc_html__( 'Ticket Has Been Canceled', 'sugar-calendar-lite' );
				break;

			default:
				$ring  = 'scf:outline-green-600';
				$icon  = 'scf:icon-[fa7-solid--check] scf:text-green-600';
				$title = esc_html__( 'Your Ticket Purchase Receipt', 'sugar-calendar-lite' );
		}

		?>
		<div class="scf:self-stretch scf:p-6 scf:flex scf:flex-col scf:items-start scf:gap-3 sc-reset">
			<div class="scf:self-stretch scf:flex scf:flex-col scf:justify-center scf:items-center scf:gap-3 sc-reset">
				<div class="scf:size-16 scf:bg-[var(--sc-ticket-body,#ffffff)] scf:rounded-full scf:outline scf:outline-[3px] scf:outline-offset-[-3px] <?php echo esc_attr( $ring ); ?> scf:flex scf:items-center scf:justify-center sc-reset">
					<i aria-hidden="true" class="<?php echo esc_attr( $icon ); ?> scf:text-[32px] sc-reset"></i>
				</div>
				<div class="scf:self-stretch scf:text-center scf:text-[var(--sc-ticket-text,#1a1a1a)] scf:text-2xl scf:font-bold scf:leading-[33.6px] sc-reset">
					<?php echo $title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * The order detail pairs.
	 *
	 * @since 3.14.0
	 *
	 * @param ReceiptCardData $data Prepared card data.
	 */
	private static function details( ReceiptCardData $data ) {
		?>
		<div class="scf:self-stretch scf:p-6 sc-ticket-divider scf:flex scf:items-start scf:gap-x-3 scf:gap-y-6 scf:flex-wrap sc-reset">
			<?php
			self::item( esc_html__( 'Order #', 'sugar-calendar-lite' ), esc_html( $data->order_id ) );
			self::item( esc_html__( 'Purchaser', 'sugar-calendar-lite' ), esc_html( $data->purchaser ) );
			self::item( esc_html__( 'Email', 'sugar-calendar-lite' ), esc_html( $data->email ) );
			self::item( esc_html__( 'Purchase Date', 'sugar-calendar-lite' ), esc_html( $data->purchase_date ) );
			self::item( esc_html__( 'Amount Paid', 'sugar-calendar-lite' ), esc_html( $data->amount ) );
			self::item( esc_html__( 'Transaction ID', 'sugar-calendar-lite' ), esc_html( $data->transaction_id ) );
			// No title means the event is gone; a bare separator and "at" would be worse than no line.
			if ( $data->event_title !== '' ) {
				self::item( esc_html__( 'Event', 'sugar-calendar-lite' ), self::event_value( $data ), true );
			}

			if ( $data->has_meeting ) {
				self::item( esc_html__( 'Online Meeting', 'sugar-calendar-lite' ), self::meeting_value( $data ), true );
			}
			?>
		</div>
		<?php
	}

	/**
	 * One label + value pair.
	 *
	 * @since 3.14.0
	 *
	 * @param string $label Escaped label.
	 * @param string $value Escaped value HTML.
	 * @param bool   $full  Whether the pair takes the whole row.
	 */
	private static function item( $label, $value, $full = false ) {

		// A full-width pair is `w-full` rather than `flex-1` so two of them in a
		// row (Event plus Online Meeting) each get their own line.
		$width = $full ? 'scf:w-full' : 'scf:w-[245px]';
		?>
		<div class="<?php echo esc_attr( $width . ' ' . self::ITEM ); ?>">
			<div class="<?php echo esc_attr( TicketCard::LABEL ); ?>"><?php echo $label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="<?php echo esc_attr( self::VALUE ); ?>"><?php echo $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
		<?php
	}

	/**
	 * Linked event title followed by its start date and time.
	 *
	 * @since 3.14.0
	 *
	 * @param ReceiptCardData $data Prepared card data.
	 *
	 * @return string Escaped HTML.
	 */
	private static function event_value( ReceiptCardData $data ) {

		$when = esc_html( format_event_when( $data->event_date, $data->event_time ) );

		return '<a class="scf:text-[var(--sc-ticket-accent,#fa6400)] scf:underline scf:underline-offset-2 sc-reset" href="' . esc_url( $data->event_url ) . '">'
			. esc_html( $data->event_title )
			. '</a> <span class="sc-ticket-sep" aria-hidden="true"></span> ' . $when;
	}

	/**
	 * Join link for the event's online meeting.
	 *
	 * The presenter is deliberately not reused: it supplies its own "Online:"
	 * label, which would double up under this card's own label.
	 *
	 * @since 3.14.0
	 *
	 * @param ReceiptCardData $data Prepared card data.
	 *
	 * @return string Escaped HTML.
	 */
	private static function meeting_value( ReceiptCardData $data ) {

		if ( $data->meeting_provider_name !== '' ) {
			/* translators: %s - online meeting provider name (e.g. Zoom). */
			$label = sprintf( esc_html__( 'Join %s meeting', 'sugar-calendar-lite' ), esc_html( $data->meeting_provider_name ) );
		} else {
			$label = esc_html__( 'Join meeting', 'sugar-calendar-lite' );
		}

		$out = '<a class="scf:text-[var(--sc-ticket-accent,#fa6400)] scf:underline scf:underline-offset-2 sc-reset" href="'
			. esc_url( $data->meeting_join_url ) . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';

		if ( $data->meeting_password !== '' ) {
			/* translators: %s - meeting password. */
			$out .= ' ' . sprintf( esc_html__( '(Password: %s)', 'sugar-calendar-lite' ), esc_html( $data->meeting_password ) );
		}

		return $out;
	}

	/**
	 * The ticket rows.
	 *
	 * @since 3.14.0
	 *
	 * @param array[] $tickets   Ticket rows, see ReceiptCardData::ticket_defaults().
	 * @param bool    $read_only Whether to omit the per-ticket action links.
	 */
	private static function tickets( array $tickets, $read_only = false ) {
		?>
		<div class="scf:self-stretch scf:p-6 sc-ticket-divider scf:flex scf:flex-col scf:items-start scf:gap-2 sc-reset">
			<div class="<?php echo esc_attr( TicketCard::LABEL ); ?>"><?php esc_html_e( 'Tickets', 'sugar-calendar-lite' ); ?></div>
			<div class="scf:self-stretch scf:flex scf:flex-col scf:items-start sc-reset">
				<?php
				foreach ( $tickets as $ticket ) {
					self::ticket_row( wp_parse_args( (array) $ticket, ReceiptCardData::ticket_defaults() ), $read_only );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * One ticket row: id, attendee, and the Print / Email Ticket / Details links.
	 *
	 * @since 3.14.0
	 *
	 * @param array $ticket    Keys: id, name, email, code, print_url, email_url, email_nonce, details_url.
	 * @param bool  $read_only Whether to omit the action links.
	 */
	private static function ticket_row( array $ticket, $read_only = false ) {
		?>
		<div class="scf:self-stretch scf:py-3 scf:border-b scf:last:border-b-0 scf:border-[var(--sc-ticket-border,rgba(141,141,141,0.15))] scf:flex scf:items-center scf:gap-x-4 scf:gap-y-2 scf:flex-wrap sc-reset">
			<?php // The id and name keep their width; the email takes what is left and clips, so a wider action label never pushes it to a second line. ?>
			<div class="scf:flex-1 scf:min-w-[200px] scf:flex scf:items-center scf:gap-2 scf:flex-nowrap sc-reset">
				<div class="scf:shrink-0 scf:text-[#888888] scf:text-xs scf:font-semibold scf:leading-[19.2px] sc-reset">
					<?php echo esc_html( '#' . $ticket['id'] ); ?>
				</div>
				<?php if ( $ticket['name'] !== '' ) : ?>
					<div class="scf:shrink-0 scf:text-[var(--sc-ticket-text,#1e1e1e)] scf:text-sm scf:font-semibold scf:leading-[22.4px] sc-reset">
						<?php echo esc_html( $ticket['name'] ); ?>
					</div>
				<?php endif; ?>
				<?php if ( $ticket['email'] !== '' ) : ?>
					<div class="scf:min-w-0 scf:truncate scf:text-[#888888] scf:text-xs scf:font-normal scf:leading-[19.2px] sc-reset" title="<?php echo esc_attr( $ticket['email'] ); ?>">
						<?php echo esc_html( $ticket['email'] ); ?>
					</div>
				<?php endif; ?>
				<?php if ( $ticket['name'] === '' && $ticket['email'] === '' ) : ?>
					<div class="scf:text-[#888888] scf:text-xs scf:font-normal scf:italic scf:leading-[19.2px] sc-reset">
						<?php esc_html_e( 'No details provided', 'sugar-calendar-lite' ); ?>
					</div>
				<?php endif; ?>
			</div>
			<?php if ( ! $read_only ) : ?>
			<div class="scf:flex scf:items-center scf:gap-4 sc-reset">
				<?php if ( $ticket['print_url'] !== '' ) : ?>
					<a class="scf:text-[var(--sc-color-neutral-20,#a7aaad)] scf:text-xs scf:font-medium scf:underline scf:underline-offset-2 scf:leading-[19.2px] sc-reset"
					   target="_blank"
					   href="<?php echo esc_url( $ticket['print_url'] ); ?>">
						<?php esc_html_e( 'Print', 'sugar-calendar-lite' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $ticket['email_url'] !== '' ) : ?>
					<?php
					// The href stays a working link: with no JS it goes through the same
					// send and lands on the Email Sent page, exactly as it does today.
					// The nonce is repeated as a data attribute so the script does not
					// have to pick it back out of the URL.
					?>
					<a class="sc-ticket-email scf:text-[var(--sc-color-neutral-20,#a7aaad)] scf:text-xs scf:font-medium scf:leading-[19.2px] sc-reset"
					   data-sc-ticket="<?php echo esc_attr( $ticket['code'] ); ?>"
					   data-sc-nonce="<?php echo esc_attr( $ticket['email_nonce'] ); ?>"
					   href="<?php echo esc_url( $ticket['email_url'] ); ?>">
						<?php esc_html_e( 'Email Ticket', 'sugar-calendar-lite' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $ticket['details_url'] !== '' ) : ?>
					<a class="scf:text-[var(--sc-ticket-accent,#fa6400)] scf:text-xs scf:font-medium scf:underline scf:underline-offset-2 scf:leading-[19.2px] sc-reset"
					   href="<?php echo esc_url( $ticket['details_url'] ); ?>">
						<?php esc_html_e( 'Details', 'sugar-calendar-lite' ); ?>
					</a>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
