<?php

namespace Sugar_Calendar\AddOn\Ticketing\Frontend;

use Sugar_Calendar\AddOn\Ticketing\Settings;

/**
 * Renders the two ticket pages as standalone documents.
 *
 * A receipt or a ticket is a destination reached from an email, not a page of
 * the site, so the designs give them their own background rather than the
 * theme's chrome. Follows the print view's approach: take over on
 * `template_redirect`, emit a full document, exit.
 *
 * `the_content()` still runs, so the shortcode owns every branch (the card, the
 * expired-link page, the invalid-link error) and any blocks the site owner added
 * to the page keep rendering.
 *
 * @since 3.14.0
 */
class TicketPageTemplate {

	/**
	 * Hook the takeover.
	 *
	 * Priority 20 leaves the print view (priority 10) to exit first if both
	 * gates somehow match on one request.
	 *
	 * @since 3.14.0
	 */
	public static function hooks() {

		add_action( 'template_redirect', [ self::class, 'maybe_render' ], 20 );
	}

	/**
	 * Emit the standalone document and stop, when this is a ticket page.
	 *
	 * @since 3.14.0
	 */
	public static function maybe_render() {

		if ( ! self::is_enabled() ) {
			return;
		}

		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<?php
			// A theme's own head normally emits this; wp_head() only does so when the
			// theme declares title-tag support, and Twenty Twenty-Five does not.
			if ( ! current_theme_supports( 'title-tag' ) ) {
				echo '<title>' . esc_html( wp_get_document_title() ) . '</title>';
			}

			wp_head();
			?>
		</head>
		<?php // The background repeats on body so the strip behind the admin bar is not left white. ?>
		<body <?php body_class( [ 'sc-ticket-page', 'sugar-calendar-scope' ] ); ?>
			  style="<?php echo esc_attr( TicketCard::css_vars() . 'background-color:var(--sc-ticket-bg,#e9eaec);' ); ?>">
			<?php wp_body_open(); ?>
			<?php // Block, not flex: a flex item sizes to its content, which let a page builder's
			// nested containers resolve the card down to its text width instead of 550px.
			// TicketCard::shell() centres the card itself. ?>
			<div class="scf:min-h-screen scf:bg-[var(--sc-ticket-bg,#e9eaec)] scf:py-20 scf:px-4 sc-reset">
				<?php
				while ( have_posts() ) :
					the_post();
					the_content();
				endwhile;
				?>
			</div>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php

		exit;
	}

	/**
	 * Whether this request should take the document over.
	 *
	 * A preview answers from the page being previewed rather than from what is
	 * stored, so it shows what saving that choice would actually produce.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	private static function is_standalone_page() {

		if ( EditorPreview::is_preview_request() ) {
			return self::holds_only_shortcode( get_queried_object_id() );
		}

		return ( self::is_receipt_page() && self::holds_only_shortcode( Settings\get_setting( 'receipt_page' ) ) )
			|| ( self::is_ticket_page() && self::holds_only_shortcode( Settings\get_setting( 'ticket_page' ) ) );
	}

	/**
	 * Whether a page holds nothing but one of the ticket shortcodes.
	 *
	 * The standalone document replaces the theme's entirely, so a page carrying
	 * its own content would lose the theme around it. Such a page keeps the theme
	 * and renders the card inline instead: the card's own wrapper carries the
	 * `sugar-calendar-scope` its utilities need, so it works either way.
	 *
	 * @since 3.14.0
	 *
	 * @param int $page_id Page to inspect.
	 *
	 * @return bool
	 */
	private static function holds_only_shortcode( $page_id ) {

		$content = get_post_field( 'post_content', (int) $page_id );

		// The block editor wraps it in delimiters; the classic editor does not.
		$content = trim( preg_replace( '/<!--\s*\/?wp:[^>]*-->/', '', (string) $content ) );

		return $content === '[sc_event_tickets_receipt]'
			|| $content === '[sc_event_tickets_details]';
	}

	/**
	 * Whether this request is the configured Receipt page.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public static function is_receipt_page() {

		$page = (int) Settings\get_setting( 'receipt_page' );

		return ! empty( $page ) && is_page( $page );
	}

	/**
	 * Whether this request is the configured Ticket Details page.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public static function is_ticket_page() {

		$page = (int) Settings\get_setting( 'ticket_page' );

		return ! empty( $page ) && is_page( $page );
	}

	/**
	 * Whether this request should render standalone.
	 *
	 * Legacy-template sites keep the theme's page: the standalone document is
	 * part of the new design, not a change to the old one. Covers both the
	 * receipt and the ticket page, which share the Color Scheme settings.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	private static function is_enabled() {

		$enabled = self::is_standalone_page()
			&& ! is_admin()
			&& ! is_feed()
			&& ! Settings\use_legacy_template();

		/**
		 * Filters whether a ticket page renders as a standalone document.
		 *
		 * Return false to keep the theme's header, footer and page title.
		 *
		 * @since 3.14.0
		 *
		 * @param bool $enabled Whether to take the template over.
		 */
		return (bool) apply_filters( 'sugar_calendar_ticketing_standalone_page', $enabled ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}
}
