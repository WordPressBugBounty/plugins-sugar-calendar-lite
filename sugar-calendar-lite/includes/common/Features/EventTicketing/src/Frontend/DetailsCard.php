<?php

namespace Sugar_Calendar\AddOn\Ticketing\Frontend;

use Sugar_Calendar\Event;

/**
 * The single ticket details card.
 *
 * Replaces the legacy `<table>` ticket view.
 *
 * Renders a `DetailsCardData` and nothing else: no lookups, no filters, so a
 * preview can feed it a hand-filled one directly.
 *
 * The QR block the Figma shows is deliberately absent, deferred with the
 * check-in feature.
 *
 * Class strings are written out literally because Tailwind scans this file's
 * source text; a class assembled by concatenation compiles to nothing.
 *
 * @since 3.14.0
 */
class DetailsCard {

	/**
	 * Value under a label in the icon rows.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const ROW_VALUE = 'scf:self-stretch scf:text-[var(--sc-ticket-text,#1a1a1a)] scf:text-lg scf:font-medium scf:leading-[21.78px] sc-reset';

	/**
	 * Render the card.
	 *
	 * @since 3.14.0
	 *
	 * @param DetailsCardData $data Prepared card data.
	 *
	 * @return string
	 */
	public static function render( DetailsCardData $data ) {

		return TicketCard::shell(
			$data->classes,
			function () use ( $data ) {
				self::image( $data->image, $data->event_title );

				// No title means the event is gone; an empty date badge would be worse than no block.
				if ( $data->event_title !== '' ) {
					self::event_block( $data );
				}

				self::rows( $data );
				self::registration( $data->registration );
			}
		);
	}

	/**
	 * The event's featured image. Skipped entirely when there is none.
	 *
	 * @since 3.14.0
	 *
	 * @param string $url Image URL, or '' for none.
	 * @param string $alt Alt text.
	 */
	private static function image( $url, $alt ) {

		if ( empty( $url ) ) {
			return;
		}
		?>
		<div class="scf:self-stretch scf:pt-6 scf:px-6 sc-reset">
			<img src="<?php echo esc_url( $url ); ?>"
				 alt="<?php echo esc_attr( $alt ); ?>"
				 class="scf:w-full scf:h-[260px] scf:object-cover scf:rounded-xl scf:block sc-reset">
		</div>
		<?php
	}

	/**
	 * Date badge, title, venue line, and the two links.
	 *
	 * @since 3.14.0
	 *
	 * @param DetailsCardData $data Prepared card data.
	 */
	private static function event_block( DetailsCardData $data ) {
		?>
		<div class="scf:self-stretch scf:p-6 scf:flex scf:flex-col scf:gap-6 sc-reset">
			<div class="scf:self-stretch scf:flex scf:items-start scf:gap-3 sc-reset">
				<div class="scf:w-16 scf:shrink-0 scf:py-1 scf:rounded-[10px] scf:border-[1.5px] scf:border-[var(--sc-ticket-border,rgba(141,141,141,0.15))] scf:flex scf:flex-col scf:items-center scf:justify-center scf:gap-1 sc-reset">
					<div class="scf:text-center scf:text-[var(--sc-ticket-accent,#fa6400)] scf:text-xs scf:font-semibold scf:uppercase scf:leading-[14.52px] scf:tracking-[2.16px] sc-reset">
						<?php echo esc_html( $data->event_month ); ?>
					</div>
					<div class="scf:text-center scf:text-[var(--sc-ticket-text,#1a1a1a)] scf:text-2xl scf:font-semibold scf:leading-6 scf:tracking-[-0.48px] sc-reset">
						<?php echo esc_html( $data->event_day ); ?>
					</div>
					<?php if ( $data->event_time !== '' ) : ?>
						<div class="scf:text-center scf:text-[#7a7a7a] scf:text-xs scf:font-semibold scf:leading-[14.52px] sc-reset">
							<?php echo esc_html( $data->event_time ); ?>
						</div>
					<?php endif; ?>
				</div>
				<div class="scf:flex-1 scf:min-w-0 scf:pt-[10px] scf:flex scf:flex-col scf:gap-[3px] sc-reset">
					<div class="scf:self-stretch scf:text-[var(--sc-ticket-text,#1a1a1a)] scf:text-2xl scf:font-bold scf:leading-[28.8px] sc-reset">
						<?php echo esc_html( $data->event_title ); ?>
					</div>
					<?php if ( $data->location_name !== '' || $data->location_address !== '' ) : ?>
						<div class="scf:self-stretch scf:text-[#7a7a7a] scf:text-sm scf:font-normal scf:leading-[19.6px] sc-reset">
							<?php if ( $data->location_name !== '' ) : ?>
								<?php if ( $data->location_url !== '' ) : ?>
									<a href="<?php echo esc_url( $data->location_url ); ?>"
									   class="scf:text-[var(--sc-ticket-accent,#fa6400)] scf:font-semibold scf:underline scf:underline-offset-2 sc-reset"><?php echo esc_html( $data->location_name ); ?></a>
								<?php else : ?>
									<span class="scf:text-[var(--sc-ticket-accent,#fa6400)] scf:font-semibold sc-reset"><?php echo esc_html( $data->location_name ); ?></span>
								<?php endif; ?>
								<?php echo $data->location_address === '' ? '' : ' &middot; '; ?>
							<?php endif; ?>
							<?php echo esc_html( $data->location_address ); ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php
			// No url means the event is gone; a button to nowhere is worse than no row.
			if ( ! $data->printing && $data->event_url !== '' ) {
				self::links( $data->event_url, $data->event );
			}
			?>
		</div>
		<?php
	}

	/**
	 * View Event Details, plus Add to Calendar when Pro supplies it.
	 *
	 * @since 3.14.0
	 *
	 * @param string     $url   Event URL.
	 * @param Event|null $event The event, for the actions hook. The hook is skipped when null.
	 */
	private static function links( $url, $event ) {
		?>
		<?php
		// Grid, not flex: `flex-basis: 0` sizes the content box, so the primary's
		// horizontal padding was added on top of its share and it rendered wider
		// than a sibling that pads an inner element instead. Equal tracks size by
		// the border box, and auto-fit still stacks them when the card narrows.
		?>
		<div class="sc-ticket-card__actions scf:self-stretch scf:grid scf:grid-cols-[repeat(auto-fit,minmax(180px,1fr))] scf:gap-3 sc-reset">
			<a href="<?php echo esc_url( $url ); ?>"
			   class="scf:py-2.5 scf:px-4 scf:bg-[var(--sc-ticket-accent,#fa6400)] scf:rounded-lg scf:flex scf:items-center scf:justify-center scf:text-white scf:text-sm scf:font-semibold scf:leading-[16.94px] scf:no-underline sc-reset">
				<?php esc_html_e( 'View Event Details', 'sugar-calendar-lite' ); ?>
			</a>
			<?php
			if ( $event instanceof Event ) {
				/**
				 * Fires in the ticket card's action row, after View Event Details.
				 *
				 * Pro renders its Add to Calendar control here; nothing draws on Lite.
				 *
				 * @since 3.14.0
				 *
				 * @param Event $event The event.
				 */
				do_action( 'sugar_calendar_ticketing_details_card_actions', $event ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			}
			?>
		</div>
		<?php
	}

	/**
	 * Attendee, Ticket Type, Ticket Code and Ticket Cost.
	 *
	 * @since 3.14.0
	 *
	 * @param DetailsCardData $data Prepared card data.
	 */
	private static function rows( DetailsCardData $data ) {
		?>
		<div class="scf:self-stretch scf:p-6 scf:flex scf:flex-col scf:gap-6 sc-ticket-divider sc-reset">
			<?php
			if ( $data->attendee_name !== '' ) {
				self::row( 'user', esc_html__( 'Attendee', 'sugar-calendar-lite' ), $data->attendee_name, true );
			}

			if ( $data->ticket_type !== '' ) {
				self::row( 'ticket', esc_html__( 'Ticket Type', 'sugar-calendar-lite' ), $data->ticket_type );
			}

			self::row( 'qrcode', esc_html__( 'Ticket Code', 'sugar-calendar-lite' ), $data->code );

			if ( $data->cost !== '' ) {
				self::row( 'money-bill', esc_html__( 'Ticket Cost', 'sugar-calendar-lite' ), $data->cost );
			}
			?>
		</div>
		<?php
	}

	/**
	 * One icon row.
	 *
	 * @since 3.14.0
	 *
	 * @param string $icon  Glyph name from the fa7-solid set.
	 * @param string $label Escaped label.
	 * @param string $value Raw value; escaped here.
	 * @param bool   $tall  Whether to use the Attendee row's taller line height.
	 */
	private static function row( $icon, $label, $value, $tall = false ) {

		$icons = [
			'user'       => 'scf:icon-[fa7-solid--user]',
			'ticket'     => 'scf:icon-[fa7-solid--ticket]',
			'qrcode'     => 'scf:icon-[fa7-solid--qrcode]',
			'money-bill' => 'scf:icon-[fa7-solid--money-bill]',
		];

		// The row top-aligns and the text block carries a fixed 10px top pad, so every
		// label sits at the same offset from its tile no matter how many lines the
		// value wraps to. 10px is where the design's centred single-line row put it.
		$value_class = $tall
			? 'scf:self-stretch scf:text-[var(--sc-ticket-text,#1a1a1a)] scf:text-lg scf:font-medium scf:leading-[26.4px] sc-reset'
			: self::ROW_VALUE;
		?>
		<div class="scf:self-stretch scf:flex scf:items-start scf:gap-3 sc-reset">
			<div class="scf:size-16 scf:shrink-0 scf:bg-[var(--sc-ticket-body,#ffffff)] scf:rounded-xl scf:border scf:border-[var(--sc-ticket-border,rgba(141,141,141,0.15))] scf:flex scf:items-center scf:justify-center sc-reset">
				<i aria-hidden="true" class="<?php echo esc_attr( $icons[ $icon ] ); ?> scf:text-[var(--sc-ticket-accent,#fa6400)] scf:text-2xl sc-reset"></i>
			</div>
			<div class="scf:flex-1 scf:min-w-0 scf:pt-[10px] scf:flex scf:flex-col scf:gap-2 sc-reset">
				<div class="<?php echo esc_attr( TicketCard::LABEL ); ?>"><?php echo $label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div class="<?php echo esc_attr( $value_class ); ?>"><?php echo esc_html( $value ); ?></div>
			</div>
		</div>
		<?php
	}

	/**
	 * The Registration Details block, when this ticket's attendee answered.
	 *
	 * @since 3.14.0
	 *
	 * @param array[] $rows Answered questions, see DetailsCardData::answer_defaults().
	 */
	private static function registration( array $rows ) {

		if ( empty( $rows ) ) {
			return;
		}
		?>
		<div class="scf:self-stretch scf:p-6 scf:flex scf:flex-col scf:gap-2 sc-ticket-divider sc-reset">
			<div class="<?php echo esc_attr( TicketCard::LABEL ); ?>">
				<?php esc_html_e( 'Registration Details', 'sugar-calendar-lite' ); ?>
			</div>
			<div class="scf:self-stretch scf:flex scf:flex-col sc-reset">
				<?php foreach ( $rows as $row ) : ?>
					<?php $row = wp_parse_args( (array) $row, DetailsCardData::answer_defaults() ); ?>
					<div class="sc-ticket-qa scf:self-stretch scf:py-3 scf:flex scf:flex-col scf:gap-1 sc-reset">
						<div class="scf:self-stretch scf:text-[var(--sc-ticket-text,#000000)] scf:text-sm scf:font-normal scf:leading-[19.6px] sc-reset">
							<?php echo esc_html( $row['label'] ); ?>
						</div>
						<div class="scf:self-stretch scf:text-[var(--sc-ticket-text,#000000)] scf:text-sm scf:font-medium scf:leading-[19.6px] sc-reset">
							<?php echo esc_html( $row['answer'] ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
