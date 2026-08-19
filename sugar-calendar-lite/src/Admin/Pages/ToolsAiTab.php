<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Tools\WpVibeInstaller;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\Integrations\Abilities\Abilities;

/**
 * AI Tools tab: 1-click installs the WPVibe.ai MCP plugin and surfaces the
 * read-only Abilities API (`Sugar_Calendar\Integrations\Abilities\Abilities`).
 *
 * Only registered on WordPress 6.9+ — see `Abilities::is_available()`.
 *
 * @since 3.13.0
 */
class ToolsAiTab extends Tools {

	/**
	 * Docs URL for the "View Abilities API Documentation" link.
	 *
	 * @since 3.13.0
	 */
	const DOCS_URL = 'https://sugarcalendar.com/docs/events/using-sugarcalendar-events-with-ai-assistants';

	/**
	 * Register AI tab hooks.
	 *
	 * @since 3.13.0
	 */
	public function hooks() {

		parent::hooks();

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	// phpcs:disable WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks
	/**
	 * Register the early init hooks (AJAX handlers for install/activate).
	 *
	 * Runs before `admin_init`, including on AJAX requests. The JS side sends
	 * `page_id: 'tools_ai'` so `Area::get_current_page_id()` resolves this
	 * class during the AJAX request and calls this method.
	 *
	 * @since 3.13.0
	 */
	public function early_init() {

		if ( ! wp_doing_ajax() ) {
			return;
		}

		// This tab, and everything it offers, only exists when the Abilities
		// API is available. Without this guard, a crafted AJAX request could
		// still install/activate WPVibe on WP < 6.9, even though the tab that
		// is supposed to be the only way to reach this action is hidden there.
		if ( ! Abilities::is_available() ) {
			return;
		}

		add_action( 'sugar_calendar_ajax_sce_install_vibe_ai', [ $this, 'ajax_install_vibe_ai' ] );
		add_action( 'sugar_calendar_ajax_sce_activate_vibe_ai', [ $this, 'ajax_activate_vibe_ai' ] );
	}
	// phpcs:enable WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

	/**
	 * Ajax handler: install (and activate, if permitted) WPVibe.
	 *
	 * @since 3.13.0
	 */
	public function ajax_install_vibe_ai() {

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( __( 'You do not have permission.', 'sugar-calendar-lite' ) );

			return;
		}

		$credentials_url = esc_url_raw(
			add_query_arg(
				[
					'page'    => self::get_slug(),
					'section' => self::get_tab_slug(),
				],
				admin_url( 'admin.php' )
			)
		);

		$this->send_wpvibe_result( ( new WpVibeInstaller() )->install( $credentials_url ) );
	}

	/**
	 * Ajax handler: activate an already-installed WPVibe.
	 *
	 * @since 3.13.0
	 */
	public function ajax_activate_vibe_ai() {

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( __( 'Plugin activation is disabled for you on this site.', 'sugar-calendar-lite' ) );

			return;
		}

		$this->send_wpvibe_result( ( new WpVibeInstaller() )->activate() );
	}

	/**
	 * Send a `WpVibeInstaller::install()`/`::activate()` result as the AJAX
	 * response.
	 *
	 * @since 3.13.0
	 *
	 * @param array $result Result array — see `WpVibeInstaller::install()`.
	 */
	private function send_wpvibe_result( $result ) {

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( $result['message'] );

			return;
		}

		wp_send_json_success(
			[
				'is_activated' => $result['is_activated'],
				'basename'     => $result['basename'],
				'msg'          => $result['message'],
			]
		);
	}

	/**
	 * Enqueue scripts for the AI tab's install/activate flow.
	 *
	 * @since 3.13.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_style(
			'sugar-calendar-admin-tools-ai',
			SC_PLUGIN_ASSETS_URL . 'css/admin-tools-ai' . WP::asset_min() . '.css',
			[ 'sugar-calendar-admin-tools' ],
			BaseHelpers::get_asset_version()
		);

		wp_enqueue_script(
			'sugar-calendar-admin-tools-ai',
			SC_PLUGIN_ASSETS_URL . 'admin/js/sc-admin-tools-ai' . WP::asset_min() . '.js',
			[ 'jquery' ],
			BaseHelpers::get_asset_version(),
			true
		);

		wp_localize_script(
			'sugar-calendar-admin-tools-ai',
			'sugar_calendar_admin_tools_ai',
			$this->get_js_strings()
		);
	}

	/**
	 * JS strings for the install/activate flow.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	protected function get_js_strings() {

		return [
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'sugar-calendar' ),
			'page_id'    => 'tools_ai',
			'installing' => esc_html__( 'Installing...', 'sugar-calendar-lite' ),
			'activating' => esc_html__( 'Activating...', 'sugar-calendar-lite' ),
			'error_text' => esc_html__( 'Something went wrong. Please try again.', 'sugar-calendar-lite' ),
		];
	}

	/**
	 * Filter the help URL in the Tools page -> AI tab.
	 *
	 * @since 3.13.0
	 *
	 * @param string $help_url The help URL.
	 *
	 * @return string
	 */
	public function help_url( $help_url ) {

		return BaseHelpers\Helpers::get_utm_url(
			self::DOCS_URL,
			[
				'content' => 'Help',
				'medium'  => 'tools-ai',
			]
		);
	}

	/**
	 * Page tab slug.
	 *
	 * @since 3.13.0
	 *
	 * @return string
	 */
	public static function get_tab_slug() {

		return 'ai';
	}

	/**
	 * Page label.
	 *
	 * @since 3.13.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'AI MCP', 'sugar-calendar-lite' );
	}

	/**
	 * Render the state-dependent WPVibe CTA button (or link).
	 *
	 * @since 3.13.0
	 *
	 * @param string $state WPVibe state, from WpVibeInstaller::get_state().
	 */
	protected function display_cta( $state ) {

		$can_install  = current_user_can( 'install_plugins' );
		$can_activate = current_user_can( 'activate_plugins' );

		if ( $state === 'active' ) {
			// Figma's "Setup AI MCP" button (node 14010:177951) is
			// background #f7f7f7 / border #8c8f94 / text #50575e — an exact
			// match to this component's `tertiary` style
			// ($color-neutral-30/$color-neutral-60), not `secondary` (which
			// is a solid-fill button in this shared component, not the
			// light outlined look Figma specifies here).
			UI::button(
				[
					'text' => esc_html__( 'Setup AI MCP', 'sugar-calendar-lite' ),
					'link' => add_query_arg( [ 'page' => WpVibeInstaller::PAGE_SLUG ], admin_url( 'admin.php' ) ),
					'type' => 'tertiary',
					'size' => 'lg',
				]
			);

			return;
		}

		if ( $state === 'installed_inactive' ) {

			if ( ! $can_activate ) {
				echo '<p class="sugar-calendar-tools-ai-note">' .
					esc_html__( 'Your site is configured to disallow plugin activation from the dashboard.', 'sugar-calendar-lite' ) .
					'</p>';

				return;
			}

			UI::button(
				[
					'text'   => esc_html__( 'Activate AI MCP', 'sugar-calendar-lite' ),
					'submit' => false,
					'size'   => 'lg',
					'data'   => [
						'action' => 'activate',
					],
				]
			);

			return;
		}

		// $state === 'not_installed'.

		if ( $can_install ) {
			UI::button(
				[
					'text'   => esc_html__( 'Connect AI MCP', 'sugar-calendar-lite' ),
					'submit' => false,
					'size'   => 'lg',
					'data'   => [
						'action' => 'install',
					],
				]
			);

			return;
		}

		UI::button(
			[
				'text'   => esc_html__( 'Install from WordPress.org', 'sugar-calendar-lite' ),
				'link'   => WpVibeInstaller::WPORG_URL,
				'target' => '_blank',
				'size'   => 'lg',
			]
		);

		echo '<p class="sugar-calendar-tools-ai-note">' .
			esc_html__( 'Your site is configured to disallow plugin installation from the dashboard.', 'sugar-calendar-lite' ) .
			'</p>';
	}

	/**
	 * Capability cards mapping the sc-events/* abilities (see
	 * Sugar_Calendar\Integrations\Abilities\Abilities and its
	 * sugar_calendar_abilities_register filter contributors) to
	 * plain-language examples. Static curated content — not dynamically
	 * enumerated from the ability registry (issue #618 explicit
	 * out-of-scope note). Always-available cards (Events, Calendars, Event
	 * Stats) render first; Tickets/Venues/Speakers/RSVP trail in a fixed
	 * order, locked when their license/add-on isn't currently active.
	 *
	 * @since 3.13.0
	 *
	 * @return array[]
	 */
	protected function get_capability_cards() {

		$cards = [
			[
				'icon'      => 'tools-ai-icon-events',
				'color'     => 'yellow',
				'title'     => __( 'Events', 'sugar-calendar-lite' ),
				'bullets'   => [
					__( 'Browse and filter your events by status, calendar, and date range', 'sugar-calendar-lite' ),
					__( "Get a single event's full details", 'sugar-calendar-lite' ),
				],
				'is_locked' => false,
			],
			[
				'icon'      => 'tools-ai-icon-calendars',
				'color'     => 'blue',
				'title'     => __( 'Calendars', 'sugar-calendar-lite' ),
				'bullets'   => [
					__( 'List all your calendars', 'sugar-calendar-lite' ),
					__( 'Look up a single calendar by id or slug', 'sugar-calendar-lite' ),
				],
				'is_locked' => false,
			],
			[
				'icon'      => 'tools-ai-icon-event-stats',
				'color'     => 'red',
				'title'     => __( 'Event Stats', 'sugar-calendar-lite' ),
				'bullets'   => [
					__( 'See aggregate stats for any date range, grouped by calendar and by month', 'sugar-calendar-lite' ),
				],
				'is_locked' => false,
			],
			[
				'icon_class'   => 'sc:icon-[fa6-solid--ticket]',
				'color'        => 'green',
				'title'        => __( 'Tickets', 'sugar-calendar-lite' ),
				'bullets'      => [
					__( "See who's registered for an event", 'sugar-calendar-lite' ),
					__( 'Check ticket sales and availability', 'sugar-calendar-lite' ),
				],
				'is_locked'    => ! $this->is_ticketing_active(),
				'lock_label'   => __( 'Pro', 'sugar-calendar-lite' ),
				'lock_cta'     => __( 'Get Event Ticketing', 'sugar-calendar-lite' ),
				'lock_cta_url' => BaseHelpers\Helpers::get_utm_url(
					'https://sugarcalendar.com/downloads/event-tickets/',
					[ 'medium' => 'tools-ai-tickets', 'content' => 'Get Event Ticketing' ]
				),
			],
			[
				'icon_class'   => 'sc:icon-[fa6-solid--location-dot]',
				'color'        => 'indigo',
				'title'        => __( 'Venues', 'sugar-calendar-lite' ),
				'bullets'      => [
					__( 'List your venues and look up a single venue', 'sugar-calendar-lite' ),
				],
				'is_locked'    => ! $this->is_venues_active(),
				'lock_label'   => __( 'Pro', 'sugar-calendar-lite' ),
				'lock_cta'     => __( 'Upgrade to Pro', 'sugar-calendar-lite' ),
				'lock_cta_url' => BaseHelpers\Helpers::get_upgrade_link( [ 'medium' => 'tools-ai-venues', 'content' => 'Upgrade to Sugar Calendar Pro' ] ),
			],
			[
				'icon_class'   => 'sc:icon-[fa6-solid--microphone]',
				'color'        => 'indigo',
				'title'        => __( 'Speakers', 'sugar-calendar-lite' ),
				'bullets'      => [
					__( 'List your speakers and look up a single speaker', 'sugar-calendar-lite' ),
				],
				'is_locked'    => ! $this->is_speakers_active(),
				'lock_label'   => __( 'Pro', 'sugar-calendar-lite' ),
				'lock_cta'     => __( 'Upgrade to Pro', 'sugar-calendar-lite' ),
				'lock_cta_url' => BaseHelpers\Helpers::get_upgrade_link( [ 'medium' => 'tools-ai-speakers', 'content' => 'Upgrade to Sugar Calendar Pro' ] ),
			],
			[
				'icon_class'   => 'sc:icon-[fa6-solid--users]',
				'color'        => 'green',
				'title'        => __( 'RSVP', 'sugar-calendar-lite' ),
				'bullets'      => [
					__( "See who's RSVP'd to an event and check attendee capacity", 'sugar-calendar-lite' ),
				],
				'is_locked'    => ! $this->is_rsvp_active(),
				'lock_label'   => __( 'Pro', 'sugar-calendar-lite' ),
				'lock_cta'     => __( 'Get RSVP', 'sugar-calendar-lite' ),
				'lock_cta_url' => BaseHelpers\Helpers::get_utm_url(
					'https://sugarcalendar.com/events/features/rsvp-addon/',
					[ 'medium' => 'tools-ai-rsvp', 'content' => 'Get RSVP' ]
				),
			],
		];

		return $cards;
	}

	/**
	 * Whether the Tickets abilities are actually registered — display signal
	 * for the Tickets capability card. Resolved via the
	 * `sugar_calendar_tools_ai_capability_active` filter rather than naming
	 * the add-on's class directly — core stays free of add-on references,
	 * matching the same boundary `sugar_calendar_abilities_register` already
	 * enforces for ability registration itself. The add-on hooks this filter
	 * from its own boot and answers only once `Requirements::should_load()`
	 * passes, which also requires a qualifying license tier — a bare
	 * `class_exists()` + `is_plugin_active()` check would show the card
	 * unlocked on a site where the abilities never actually register.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	protected function is_ticketing_active() {

		return (bool) apply_filters( 'sugar_calendar_tools_ai_capability_active', false, 'tickets' );
	}

	/**
	 * Whether the RSVP abilities are actually registered — display signal
	 * for the RSVP capability card. Same filter-based check as
	 * is_ticketing_active(), passing the `rsvp` domain.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	protected function is_rsvp_active() {

		return (bool) apply_filters( 'sugar_calendar_tools_ai_capability_active', false, 'rsvp' );
	}

	/**
	 * Whether the Venues abilities are actually registered — display signal
	 * for the Venues capability card. Same filter-based check as
	 * is_ticketing_active()/is_rsvp_active(), passing the `venues` domain,
	 * rather than a bare `sugar_calendar()->is_pro()` check — Pro being
	 * present doesn't guarantee the Venues feature's own requirements passed
	 * and actually registered its abilities.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	protected function is_venues_active() {

		return (bool) apply_filters( 'sugar_calendar_tools_ai_capability_active', false, 'venues' );
	}

	/**
	 * Whether the Speakers abilities are actually registered — display
	 * signal for the Speakers capability card. Same filter-based check as
	 * is_venues_active(), passing the `speakers` domain.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	protected function is_speakers_active() {

		return (bool) apply_filters( 'sugar_calendar_tools_ai_capability_active', false, 'speakers' );
	}

	/**
	 * Render the hero eyebrow line.
	 *
	 * @since 3.13.0
	 */
	protected function display_eyebrow() {

		?>
		<p class="sugar-calendar-tools-ai-eyebrow">
			<?php esc_html_e( 'WordPress Abilities API + Sugar Calendar', 'sugar-calendar-lite' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the hero title. Plain single color per Figma — no accent
	 * highlight on "AI" (that treatment belonged to an earlier, superseded
	 * design pass).
	 *
	 * @since 3.13.0
	 */
	protected function display_title() {

		?>
		<h2 class="sugar-calendar-tools-ai-title">
			<?php esc_html_e( 'Use Sugar Calendar With Your Favorite AI', 'sugar-calendar-lite' ); ?>
		</h2>
		<?php
	}

	/**
	 * Render the hero lede paragraph.
	 *
	 * @since 3.13.0
	 *
	 * @param string $docs_url UTM-tagged Abilities API docs URL.
	 */
	protected function display_lede( $docs_url ) {

		?>
		<p class="sugar-calendar-tools-ai-lede">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s - "Learn more about Abilities API" inline link. */
					__( 'Connect your WordPress site and Sugar Calendar to AI assistants like Claude, ChatGPT, Cursor, and more. Ask it about your events, calendars, and stats in your plain language. %s', 'sugar-calendar-lite' ),
					sprintf(
						'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
						esc_url( $docs_url ),
						esc_html__( 'Learn more about Abilities API', 'sugar-calendar-lite' )
					)
				),
				[
					'a' => [
						'href'   => [],
						'target' => [],
						'rel'    => [],
					],
				]
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render the small disclosure line below the CTA button.
	 *
	 * @since 3.13.0
	 */
	protected function display_wpvibe_caption() {

		?>
		<p class="sugar-calendar-tools-ai-wpvibe-caption">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s - WPVibe.ai inline link. */
					__( 'Connecting AI MCP will install and activate our free sister plugin %s', 'sugar-calendar-lite' ),
					sprintf(
						'<a class="sugar-calendar-tools-ai-wpvibe-link" href="%s" target="_blank" rel="noopener noreferrer">WPVibe.ai</a>',
						esc_url(
							add_query_arg(
								[
									'utm_source'   => 'sugarcalendar',
									'utm_medium'   => 'link',
									'utm_campaign' => 'ai-mcp-page',
								],
								'https://wpvibe.ai/'
							)
						)
					)
				),
				[
					'a' => [
						'class'  => [],
						'href'   => [],
						'target' => [],
						'rel'    => [],
					],
				]
			);
			?>
		</p>
		<?php
	}

	/**
	 * Banner AI-client icon positions/sizes.
	 *
	 * Each SVG is the icon's own "Icon Container" Figma node
	 * (14007:63630/63649/63653/63656), hand-isolated from a raw
	 * `download_assets` export.
	 *
	 * `download_assets` on these node IDs does NOT return an isolated
	 * tile — Figma's SVG exporter includes the full ancestor page
	 * mockup's own paint layers (a dark frame background, a near-white
	 * "Content Area" panel this component was originally duplicated
	 * from — see the layer names in the raw export: Mockup > Body >
	 * Content Area > ... > Icon Container). Those ancestor fills sit on
	 * top of the actual white rotated tile and, within a small crop,
	 * visually dominate it — rendering as a plain white card with a
	 * faint border instead of the tile. A naive same-node SVG swap
	 * shipped this exact bug once already (issue #618 review) and was
	 * only caught by actually screenshotting the live page, not by
	 * checking the file existed / had the right dimensions / served
	 * with a 200.
	 *
	 * Fix: loaded the raw export in a headless browser, called
	 * `getBBox()` on the `#Icon Container` element (this — not the
	 * SVG's own declared viewBox — is what correctly bounds just that
	 * node's own rotated-rect + masked-glyph content, matching
	 * `get_metadata`'s reported node size), then rebuilt a minimal SVG
	 * containing only that subtree plus the `<defs>` entries it
	 * actually references (walked via `url(#...)` attribute refs, so
	 * unrelated filters from sibling icons aren't dragged in). No
	 * ancestor content, no unused defs.
	 *
	 * `width`/`height` include the shadow's bounding region, not just the
	 * icon's own bbox — offset downward by Figma's shadow, hence the
	 * non-round `center_y` values below.
	 *
	 * `center_x`/`center_y` themselves are NOT derived from the node's
	 * `left`/`bottom` layout properties — that approach (tried first)
	 * was off by 3.6-7px vertically, because the Tailwind export's
	 * `bottom` values are lossy for tiles clipped by the banner's own
	 * edge. Instead these are measured empirically: rendered a 1:1
	 * 700x120 screenshot of the whole banner (14007:63607) as ground
	 * truth, rendered each icon node in isolation, then template-matched
	 * (ImageMagick `compare -subimage-search`) each isolated render
	 * against the ground truth to get its exact top-left pixel offset.
	 * Verified visually — each match crop shows the correct icon shape,
	 * not a neighboring one.
	 *
	 * Array order = paint order (same z-index on every tile, so later
	 * DOM wins) — and it must match Figma's own layer stack, confirmed
	 * via get_metadata on node 14007:63604: within the left pair,
	 * 63630 (Gemini) is listed before 63649 (Claude), i.e. Gemini sits
	 * behind Claude; within the right pair, 63653 (Cursor — the node
	 * with the "cursor logo" child) is listed before 63656 (ChatGPT),
	 * i.e. Cursor sits behind ChatGPT. Cursor must render before
	 * ChatGPT here even though it sits further right, to match that.
	 *
	 * @since 3.13.0
	 *
	 * @return array[]
	 */
	protected function get_banner_clients() {

		return [
			[
				'file'      => 'tools-ai-banner-icon-gemini.svg',
				'center_x'  => 204.5,
				'center_y'  => 99.85,
				'width'     => 89,
				'height'    => 89,
			],
			[
				'file'      => 'tools-ai-banner-icon-claude.svg',
				'center_x'  => 258.5,
				'center_y'  => 70.55,
				'width'     => 86,
				'height'    => 86,
			],
			[
				'file'      => 'tools-ai-banner-icon-cursor.svg',
				'center_x'  => 492,
				'center_y'  => 98.85,
				'width'     => 91,
				'height'    => 91,
			],
			[
				'file'      => 'tools-ai-banner-icon-chatgpt.svg',
				'center_x'  => 438.5,
				'center_y'  => 70.55,
				'width'     => 86,
				'height'    => 86,
			],
		];
	}

	/**
	 * Render the decorative banner above the card: the Sugar Calendar mascot
	 * flanked by the supported AI client logos.
	 *
	 * @since 3.13.0
	 */
	protected function display_banner() {
		?>
		<div class="sugar-calendar-tools-ai-banner">
			<div class="sugar-calendar-tools-ai-banner-blob" aria-hidden="true"></div>
			<div class="sugar-calendar-tools-ai-banner-ring" aria-hidden="true"></div>

			<?php foreach ( $this->get_banner_clients() as $client ) : ?>
				<img
					class="sugar-calendar-tools-ai-banner-client"
					style="left:<?php echo esc_attr( $client['center_x'] ); ?>px;top:<?php echo esc_attr( $client['center_y'] ); ?>px;width:<?php echo esc_attr( $client['width'] ); ?>px;height:<?php echo esc_attr( $client['height'] ); ?>px;"
					src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/icons/' . $client['file'] ); ?>"
					alt=""
					role="presentation"
				>
			<?php endforeach; ?>

			<div class="sugar-calendar-tools-ai-banner-shadow-ground" aria-hidden="true"></div>

			<img
				class="sugar-calendar-tools-ai-banner-heart"
				src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/icons/tools-ai-banner-heart.svg' ); ?>"
				alt=""
				role="presentation"
			>

			<span class="sugar-calendar-tools-ai-banner-mascot-wrap">
				<img
					class="sugar-calendar-tools-ai-banner-mascot"
					src="<?php echo esc_url( SC_PLUGIN_URL . 'assets/images/tools-ai-banner-mascot.svg' ); ?>"
					alt=""
					role="presentation"
				>
			</span>
		</div>
		<?php
	}

	/**
	 * Output the tab.
	 *
	 * @since 3.13.0
	 */
	protected function display_tab() {

		$state    = ( new WpVibeInstaller() )->get_state();
		$docs_url = BaseHelpers\Helpers::get_utm_url(
			self::DOCS_URL,
			[
				'content' => 'Learn More Link',
				'medium'  => 'tools-ai',
			]
		);
		?>
		<div class="sugar-calendar-tools-ai">
			<?php $this->display_banner(); ?>

			<div class="sugar-calendar-tools-ai-panel">
				<section class="sugar-calendar-tools-ai-hero">
					<?php
					$this->display_eyebrow();
					$this->display_title();
					$this->display_lede( $docs_url );
					?>
					<div class="sugar-calendar-tools-ai-cta-row">
						<?php $this->display_cta( $state ); ?>
					</div>
					<?php $this->display_wpvibe_caption(); ?>
				</section>

				<section class="sugar-calendar-tools-ai-capabilities">
					<h3 class="sugar-calendar-tools-ai-capabilities-title">
						<?php esc_html_e( 'Everything Sugar Calendar Can Do With AI', 'sugar-calendar-lite' ); ?>
					</h3>

					<div class="sugar-calendar-tools-ai-cards">
						<?php foreach ( $this->get_capability_cards() as $card ) : ?>
							<?php $this->display_capability_card( $card ); ?>
						<?php endforeach; ?>
					</div>

					<p class="sugar-calendar-tools-ai-more-note">
						<?php esc_html_e( '...And many more abilities coming soon!', 'sugar-calendar-lite' ); ?>
					</p>
				</section>
			</div>
		</div>
		<?php
	}

	/**
	 * Output one capability card.
	 *
	 * @since 3.13.0
	 *
	 * @param array $card One entry of get_capability_cards().
	 */
	private function display_capability_card( array $card ) {

		?>
				<article class="sugar-calendar-tools-ai-card sugar-calendar-tools-ai-card-<?php echo esc_attr( $card['color'] ); ?><?php echo ! empty( $card['is_locked'] ) ? ' sugar-calendar-tools-ai-card-locked' : ''; ?>">
					<header class="sugar-calendar-tools-ai-card-head">
						<div class="sugar-calendar-tools-ai-card-head-label">
							<h4 class="sugar-calendar-tools-ai-card-title"><?php echo esc_html( $card['title'] ); ?></h4>
							<?php if ( ! empty( $card['is_locked'] ) ) : ?>
								<span class="sc-badge sc-badge-sm sc-badge--platinum" data-size="sm"><?php echo esc_html( $card['lock_label'] ); ?></span>
							<?php endif; ?>
						</div>
						<span class="sugar-calendar-tools-ai-card-icon" aria-hidden="true">
							<?php if ( ! empty( $card['icon_class'] ) ) : ?>
								<?php
								// The class is a literal in get_capability_cards(), never
								// composed: the Tailwind build finds icon utilities by
								// scanning source text, so a sprintf()-built class name
								// would compile to nothing.
								?>
								<span class="<?php echo esc_attr( $card['icon_class'] ); ?>"></span>
							<?php else : ?>
								<?php
								// Events/Calendars/Event Stats ship the Figma icon as an
								// extracted SVG (nodes 14007:63674/63688/63702); the four
								// gated cards, which Figma never drew, use the same
								// Font Awesome 6 Solid set through the icon utility above.
								// `aria-hidden` on the wrapper span (above) covers this
								// SVG too — `UI::svg_icon()` doesn't inject its own.
								UI::svg_icon( $card['icon'] );
								?>
							<?php endif; ?>
						</span>
					</header>
					<ul class="sugar-calendar-tools-ai-card-bullets">
						<?php foreach ( $card['bullets'] as $bullet ) : ?>
							<li>
								<span class="sugar-calendar-tools-ai-card-bullet-check">
									<?php
									// Figma's checkmark is the Font Awesome 6 "check" glyph, not
									// a WP dashicon — visually a different (thicker, rounder)
									// shape. Path extracted directly from the Figma node itself
									// (download_assets on node 14007:63680) rather than
									// approximated with a dashicon. `svg_icon()`'s default
									// currentColor fill keeps it green on every card, gated or
									// not — the PRO badge states the gate, so a card is never
									// greyed out to say it.
									UI::svg_icon( 'tools-ai-checkmark' );
									?>
								</span>
								<span class="sugar-calendar-tools-ai-card-bullet-text"><?php echo esc_html( $bullet ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php if ( ! empty( $card['is_locked'] ) ) : ?>
						<div class="sugar-calendar-tools-ai-card-lock">
							<a class="sc-button sc-button--text" data-size="sm" href="<?php echo esc_url( $card['lock_cta_url'] ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $card['lock_cta'] ); ?>
							</a>
						</div>
					<?php endif; ?>
				</article>
		<?php
	}
}
