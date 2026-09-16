<?php

namespace Sugar_Calendar\Features\FeaturedEvents\Common;

/**
 * Markup and labels for the Featured Events feature.
 *
 * Consumed by both admin and front-end surfaces, so nothing here may assume one.
 * Callers decide whether an event is featured; see Featured::is_featured().
 *
 * @since 3.14.0
 */
class Markup {

	/**
	 * Class on every featured star.
	 *
	 * The Calendar block's popover clones the star by this name, so it is a contract.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const STAR_CLASS = 'sugar-calendar-block__featured-star';

	/**
	 * Get the label used wherever an event is shown as featured.
	 *
	 * Covers the star tooltip, the admin status link, the single-event badge and the
	 * pop-out badge. The settings labels that talk *about* the feature are separate.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	public static function get_label() {

		/**
		 * Filter the label used wherever an event is shown as featured.
		 *
		 * Lets a site rename "Featured" to anything else via a snippet.
		 *
		 * @since 3.14.0
		 *
		 * @param string $label The featured label.
		 */
		return (string) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_featured_events_label',
			esc_html__( 'Featured', 'sugar-calendar-lite' )
		);
	}

	/**
	 * Get the featured star markup.
	 *
	 * Colour follows the event's calendar, falling back to the link colour.
	 *
	 * @since 3.14.0
	 *
	 * @param string $context Surface key from self::get_context_classes().
	 * @param string $color   Accent color of the surface, when it has one.
	 *
	 * @return string
	 */
	public static function get_star( $context = '', $color = '' ) {

		$label = self::get_label();

		// Painted from star.svg via a CSS mask rather than an icon font, so the star
		// renders on the front end too, where the Tailwind bundle is not enqueued.
		$style = empty( $color )
			? ''
			: sprintf( ' style="--sc-featured-star-color: %s;"', esc_attr( $color ) );

		return sprintf(
			'<i class="%1$s %2$s"%3$s role="img" aria-label="%4$s" title="%4$s"></i>',
			esc_attr( self::STAR_CLASS ),
			esc_attr( self::get_context_classes( $context ) ),
			$style,
			esc_attr( $label )
		);
	}

	/**
	 * Per-surface modifier class for the star.
	 *
	 * @since 3.14.0
	 *
	 * @param string $context Surface key.
	 *
	 * @return string Modifier class, or an empty string when the surface needs none.
	 */
	private static function get_context_classes( $context ) {

		// Modifier classes only; the sizes themselves live with each surface's stylesheet.
		$classes = [
			'admin-list'     => self::STAR_CLASS . '--admin-list',
			'calendar-chip'  => self::STAR_CLASS . '--calendar-chip',
			'pointer-header' => self::STAR_CLASS . '--pointer-header',
			'block'          => '',
		];

		return isset( $classes[ $context ] ) ? $classes[ $context ] : '';
	}
}
