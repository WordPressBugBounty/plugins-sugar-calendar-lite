<?php
/**
 * Dashboard widget card shell.
 *
 * @since 3.18.0
 *
 * @var string $id            Widget id, e.g. `upcoming_events`.
 * @var string $variant       Variant, added as the `--{id}-{variant}` state class.
 * @var array  $extra_classes Extra classes for the card root.
 * @var array  $attributes    Extra attributes for the card root, as `name => value`.
 * @var string $title         Widget title.
 * @var string $head          Custom head HTML. Empty renders $title.
 * @var string $head_aside    HTML at the trailing edge of the head.
 * @var string $body          Body HTML.
 * @var bool   $flush         Whether the body drops its padding.
 * @var string $footer        Footer HTML. Empty renders none.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$slug    = str_replace( '_', '-', $id );
$classes = [ 'sc-dashboard-widget', 'sc-reset', 'sugar-calendar-dashboard-widget--' . $slug ];

if ( $variant !== '' ) {
	$classes[] = 'sugar-calendar-dashboard-widget--' . $slug . '-' . $variant;
}

$classes = array_merge( $classes, array_map( 'sanitize_html_class', $extra_classes ) );

$root_attributes = '';

foreach ( $attributes as $name => $value ) {
	$root_attributes .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
}

// Titleless cards (the license strip, the setup notice) have no head row.
$has_head = $head !== '' || $title !== '' || $head_aside !== '';
?>
<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-widget="<?php echo esc_attr( $id ); ?>"<?php echo $root_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>>
	<?php if ( $has_head ) : ?>
		<header class="sc-dashboard-widget__heading sc-reset">
			<?php if ( $head !== '' ) : ?>
				<?php echo $head; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered and escaped by the widget. ?>
			<?php else : ?>
				<h2 class="sc-dashboard-widget__title sc-section-title sc-reset"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>

			<?php echo $head_aside; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered and escaped by the widget. ?>
		</header>
	<?php endif; ?>

	<?php if ( $body !== '' ) : ?>
		<div class="sc-dashboard-widget__body sc-reset"<?php echo $flush ? ' data-flush' : ''; ?>>
			<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered and escaped by the widget. ?>
		</div>
	<?php endif; ?>

	<?php echo $footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered and escaped by the widget. ?>
</section>
