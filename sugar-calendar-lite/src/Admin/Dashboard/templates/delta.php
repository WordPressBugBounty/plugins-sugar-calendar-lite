<?php
/**
 * Change against the previous period, as a DS Labels pill.
 *
 * @since 3.18.0
 *
 * @var float $delta Percent change.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Under 1%, one decimal: on a busy site 0.3% is a real change. The arrow follows the number shown.
$tenths  = round( $delta, 1 );
$shown   = abs( $tenths ) < 1 ? $tenths : round( $delta );
$is_down = $shown < 0;
$percent = number_format_i18n( abs( $shown ), abs( $shown ) < 1 && $shown !== 0.0 ? 1 : 0 );
$summary = sprintf(
	$is_down
		/* translators: %s - percent change: a whole number, or one decimal under 1. */
		? __( 'Down %s%% on the previous period', 'sugar-calendar-lite' )
		/* translators: %s - percent change: a whole number, or one decimal under 1. */
		: __( 'Up %s%% on the previous period', 'sugar-calendar-lite' ),
	$percent
);
?>
<span class="sc-label sc-label--grey sc-reset-deep">
	<span class="sc:icon-[fa6-solid--chevron-<?php echo $is_down ? 'down' : 'up'; ?>] sc-label__icon" aria-hidden="true"></span>
	<?php // The title sits on a hidden span so screen readers don't hear the sentence twice. ?>
	<span title="<?php echo esc_attr( $summary ); ?>" aria-hidden="true"><?php /* translators: %s - percent change: a whole number, or one decimal under 1. */ echo esc_html( sprintf( __( '%s%%', 'sugar-calendar-lite' ), $percent ) ); ?></span>
	<?php // Not core's .screen-reader-text: the deep reset reverts it, so the text would show. ?>
	<span class="sc:sr-only"><?php echo esc_html( $summary ); ?></span>
</span>
