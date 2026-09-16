<?php

namespace Sugar_Calendar\AddOn\Ticketing\Frontend;

/**
 * The dead-end message card: an expired receipt link, or a link that resolves
 * to nothing.
 *
 * Both ticket pages reach a state where there is no receipt and no ticket to
 * draw. Those states share this card so they read as part of the same family
 * as the receipt and details cards rather than as a stray alert.
 *
 * Class strings are written out literally because Tailwind scans this file's
 * source text; a class assembled by concatenation compiles to nothing.
 *
 * @since 3.14.0
 */
class MessageCard {

	/**
	 * The tones, keyed by name.
	 *
	 * `notice` uses the site's accent so it reads as brand rather than as
	 * success; the receipt's green tick already means "you bought a ticket".
	 * `success` does reuse that tick, for the one card that confirms an action
	 * the visitor just took rather than describing a state.
	 *
	 * @since 3.14.0
	 *
	 * @var array
	 */
	const TONES = [
		'notice'  => [
			'ring' => 'scf:outline-[var(--sc-ticket-accent,#fa6400)]',
			'icon' => 'scf:icon-[fa7-solid--envelope] scf:text-[var(--sc-ticket-accent,#fa6400)]',
		],
		'success' => [
			'ring' => 'scf:outline-green-600',
			'icon' => 'scf:icon-[fa7-solid--check] scf:text-green-600',
		],
		'error'   => [
			'ring' => 'scf:outline-red-600',
			'icon' => 'scf:icon-[fa7-solid--triangle-exclamation] scf:text-red-600',
		],
	];

	/**
	 * Render the message card.
	 *
	 * @since 3.14.0
	 *
	 * @param string   $tone  A key of self::TONES.
	 * @param string   $title Escaped title.
	 * @param string[] $body  Escaped body lines.
	 * @param array    $args  Optional. `pill`: escaped emphasis line, a masked
	 *                        email. `footer`: callable echoing anything below
	 *                        the card, at card width.
	 *
	 * @return string
	 */
	public static function render( $tone, $title, array $body = [], array $args = [] ) {

		$args = wp_parse_args(
			$args,
			[
				'pill'   => '',
				'footer' => null,
			]
		);

		$tone = isset( self::TONES[ $tone ] ) ? $tone : 'error';
		$pill = $args['pill'];

		return TicketCard::shell(
			[ 'sc-ticket-card', 'sc-ticket-card--message' ],
			function () use ( $tone, $title, $body, $pill ) {
				self::header( $tone, $title );
				self::body( $body, $pill );
			},
			$args['footer']
		);
	}

	/**
	 * The tone ring, its icon and the title.
	 *
	 * Mirrors ReceiptCard::header() so the three cards share one silhouette.
	 *
	 * @since 3.14.0
	 *
	 * @param string $tone  A key of self::TONES.
	 * @param string $title Escaped title.
	 */
	private static function header( $tone, $title ) {
		?>
		<div class="scf:self-stretch scf:p-6 scf:flex scf:flex-col scf:justify-center scf:items-center scf:gap-3 sc-reset">
			<div class="scf:size-16 scf:bg-[var(--sc-ticket-body,#ffffff)] scf:rounded-full scf:outline scf:outline-[3px] scf:outline-offset-[-3px] <?php echo esc_attr( self::TONES[ $tone ]['ring'] ); ?> scf:flex scf:items-center scf:justify-center sc-reset">
				<i aria-hidden="true" class="<?php echo esc_attr( self::TONES[ $tone ]['icon'] ); ?> scf:text-[32px] sc-reset"></i>
			</div>
			<h1 class="scf:self-stretch scf:text-center scf:text-[var(--sc-ticket-text,#1a1a1a)] scf:text-2xl scf:font-bold scf:leading-[33.6px] sc-reset">
				<?php echo $title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the caller. ?>
			</h1>
		</div>
		<?php
	}

	/**
	 * The explanatory lines and the emphasis pill.
	 *
	 * Skipped entirely when there is nothing to say, so a title-only card keeps
	 * the dashed rule off.
	 *
	 * @since 3.14.0
	 *
	 * @param string[] $body Escaped body lines.
	 * @param string   $pill Escaped emphasis line, or ''.
	 */
	private static function body( array $body, $pill ) {

		if ( empty( $body ) && ( $pill === '' ) ) {
			return;
		}
		?>
		<div class="scf:self-stretch scf:p-6 sc-ticket-divider scf:flex scf:flex-col scf:items-center scf:gap-3 sc-reset">
			<?php foreach ( $body as $line ) : ?>
				<p class="scf:text-center scf:text-[#7a7a7a] scf:text-sm scf:font-medium scf:leading-[19.6px] scf:max-w-[38ch] sc-reset">
					<?php echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the caller. ?>
				</p>
			<?php endforeach; ?>

			<?php if ( $pill !== '' ) : ?>
				<div class="scf:px-4 scf:py-2 scf:rounded-full scf:border scf:border-[var(--sc-ticket-border,rgba(141,141,141,0.15))] scf:bg-[#fafafa] scf:text-[var(--sc-ticket-text,#1a1a1a)] scf:text-sm scf:font-semibold scf:leading-[19.6px] scf:break-all sc-reset">
					<?php echo $pill; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the caller. ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
