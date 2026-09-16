<?php

namespace Sugar_Calendar\AddOn\Ticketing\Frontend;

use Sugar_Calendar\AddOn\Ticketing\Settings;

/**
 * The card shell the Receipt and Details pages share.
 *
 * Both designs draw the same 550px card, so the wrapper, the Color Scheme
 * custom properties and the uppercase label style live here rather than being
 * spelled twice.
 *
 * Class strings are written out literally because Tailwind scans this file's
 * source text; a class assembled by concatenation compiles to nothing.
 *
 * @since 3.14.0
 */
class TicketCard {

	/**
	 * Uppercase label above a value. Identical in both designs.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const LABEL = 'scf:self-stretch scf:text-[#aaaaaa] scf:text-xs scf:font-semibold scf:uppercase scf:leading-[14.52px] scf:tracking-[0.8px] sc-reset';

	/**
	 * The Color Scheme settings as custom properties.
	 *
	 * Inline because the values are per-site options, not stylesheet constants.
	 * The standalone page template paints its background from the same settings.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	public static function css_vars() {

		$out = '';

		foreach ( Settings\get_color_scheme_fields() as $key => $field ) {
			$color = sanitize_hex_color( Settings\get_setting( $key, $field['default'] ) );
			$out  .= $field['css_var'] . ':' . ( empty( $color ) ? $field['default'] : $color ) . ';';
		}

		return $out;
	}

	/**
	 * Render the wrapper and card, calling $body to fill it.
	 *
	 * The id is kept for both pages: the legacy markup used it on each, and E2E
	 * specs assert on it.
	 *
	 * @since 3.14.0
	 *
	 * @param string[]      $classes Extra classes for the outer wrapper.
	 * @param callable      $body    Echoes the card's sections.
	 * @param callable|null $footer  Echoes anything sitting below the card, at card width.
	 *
	 * @return string
	 */
	public static function shell( array $classes, callable $body, $footer = null ) {

		$classes = implode( ' ', array_map( 'sanitize_html_class', $classes ) );

		ob_start();
		?>
		<?php
		// Three notes on the wrapper below:
		// - Every scf: utility is scoped to a `.sugar-calendar-scope` ANCESTOR, so that
		//   element cannot style itself; the utilities start on the child.
		// - `tracking-normal` on the card resets an inherited theme letter-spacing
		//   (Twenty Twenty-Five ships `body { letter-spacing: -0.1px }`); the designs
		//   specify 0 everywhere except the labels, which set their own.
		// - `break-words` inherits to every value, so an unbroken token (a long
		//   ticket code, transaction id or registration answer) wraps instead of
		//   being clipped by the overflow-hidden above.
		?>
		<div id="sc-event-ticketing-ticket-details"
			 class="sugar-calendar-scope <?php echo esc_attr( $classes ); ?>"
			 style="<?php echo esc_attr( self::css_vars() ); ?>">
			<?php // A column, so a footer stacks under the card; `> div > div` still reaches the card (see ticket-card-print.scss). ?>
			<div class="scf:flex scf:flex-col scf:items-center sc-reset">
				<div class="scf:w-full scf:max-w-[550px] scf:bg-[var(--sc-ticket-body,#ffffff)] scf:rounded-2xl scf:shadow-[0px_2px_12px_0px_rgba(0,0,0,0.06)] scf:outline scf:outline-1 scf:outline-offset-[-1px] scf:outline-[var(--sc-color-neutral-5,#dcdcde)] scf:flex scf:flex-col scf:overflow-hidden scf:tracking-normal scf:break-words sc-reset">
					<?php $body(); ?>
				</div>
				<?php
				if ( is_callable( $footer ) ) {
					$footer();
				}
				?>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}
}
