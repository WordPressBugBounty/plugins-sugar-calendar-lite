<?php

namespace Sugar_Calendar\Admin;

use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Plugin;

/**
 * Admin Flyout Menu.
 *
 * @since 3.9.0
 */
class FlyoutMenu {

	/**
	 * Hooks.
	 *
	 * @since 3.9.0
	 */
	public function hooks() {

		/**
		 * Filter for enabling/disabling the quick links (flyout menu).
		 *
		 * @since 3.9.0
		 *
		 * @param bool $enabled Whether quick links are enabled.
		 */
		if ( apply_filters( 'sugar_calendar_admin_flyout_menu', true ) ) {
			add_action( 'admin_footer', [ $this, 'output' ] );
		}
	}

	/**
	 * Output menu.
	 *
	 * @since 3.9.0
	 */
	public function output() {

		// Bail if we're not on a Sugar Calendar admin page.
		if ( ! sugar_calendar()->get_admin()->is_admin_page() ) {
			return;
		}

		printf(
			'<div id="sugar-calendar-flyout">
				<div id="sugar-calendar-flyout-items">%1$s</div>
				<a href="#" class="sc-flyout-button sc-flyout-head">
					<div class="sc-flyout-label">%2$s</div>
					<figure><img src="%3$s" alt="%2$s"/></figure>
				</a>
			</div>',
			$this->get_items_html(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html__( 'See Quick Links', 'sugar-calendar-lite' ),
			esc_url( SC_PLUGIN_ASSETS_URL . 'images/flyout-menu/mascot.svg' )
		);
	}

	/**
	 * Generate menu items HTML.
	 *
	 * @since 3.9.0
	 *
	 * @return string Menu items HTML.
	 */
	private function get_items_html() {

		$items      = array_reverse( $this->menu_items() );
		$items_html = '';

		foreach ( $items as $item_key => $item ) {
			$items_html .= sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer" class="sc-flyout-button sc-flyout-item sc-flyout-item-%2$d"%5$s%6$s>
					<div class="sc-flyout-label">%3$s</div>
					<img src="%4$s" alt="%3$s">
				</a>',
				esc_url( $item['url'] ),
				(int) $item_key,
				esc_html( $item['title'] ),
				esc_url( $item['icon'] ),
				! empty( $item['bgcolor'] ) ? ' style="background-color: ' . esc_attr( $item['bgcolor'] ) . '"' : '',
				! empty( $item['hover_bgcolor'] ) ? ' onMouseOver="this.style.backgroundColor=\'' . esc_attr( $item['hover_bgcolor'] ) . '\'" onMouseOut="this.style.backgroundColor=\'' . esc_attr( $item['bgcolor'] ) . '\'"' : ''
			);
		}

		return $items_html;
	}

	/**
	 * Menu items data.
	 *
	 * @since 3.9.0
	 *
	 * @return array Menu items data.
	 */
	private function menu_items() {

		$icons_url = SC_PLUGIN_ASSETS_URL . 'images/flyout-menu';
		$is_pro    = sugar_calendar()->is_pro();

		$items = [];

		// Add upgrade item for Lite users only.
		if ( ! $is_pro ) {
			$items[] = [
				'title'         => esc_html__( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ),
				'url'           => $this->get_upgrade_url(),
				'icon'          => $icons_url . '/star.svg',
				'bgcolor'       => '#FF8845',
				'hover_bgcolor' => '#FF6712',
			];
		}

		// Support & Docs.
		$items[] = [
			'title' => esc_html__( 'Support & Docs', 'sugar-calendar-lite' ),
			'url'   => $this->get_utm_url( 'https://sugarcalendar.com/docs/', 'Support & Docs' ),
			'icon'  => $icons_url . '/life-ring.svg',
		];

		// Join Community.
		$items[] = [
			'title' => esc_html__( 'Join Community', 'sugar-calendar-lite' ),
			'url'   => 'https://www.facebook.com/groups/1346464176419960',
			'icon'  => $icons_url . '/comments.svg',
		];

        // Suggest a Feature.
		$items[] = [
			'title' => esc_html__( 'Suggest a Feature', 'sugar-calendar-lite' ),
			'url'   => $this->get_utm_url( 'https://sugarcalendar.com/feedback/', 'Suggest a Feature' ),
			'icon'  => $icons_url . '/lightbulb.svg',
		];

		/**
		 * Filters quick links items.
		 *
		 * @since 3.9.0
		 *
		 * @param array $items {
		 *     Quick links items.
		 *
		 *     @type string $title         Item title.
		 *     @type string $url           Item link.
		 *     @type string $icon          Item icon url.
		 *     @type string $bgcolor       Item background color (optional).
		 *     @type string $hover_bgcolor Item background color on hover (optional).
		 * }
		 */
		return apply_filters( 'sugar_calendar_admin_flyout_menu_menu_items', $items );
	}

	/**
	 * Generate UTM URL for Sugar Calendar destinations.
	 *
	 * @since 3.9.0
	 *
	 * @param string $url     Base URL.
	 * @param string $content UTM content parameter.
	 *
	 * @return string URL with UTM parameters.
	 */
	private function get_utm_url( $url, $content ) {

		// Only apply UTM parameters to sugarcalendar.com URLs.
		if ( strpos( $url, 'sugarcalendar.com' ) === false ) {
			return $url;
		}

		$utm_params = [
			'source'   => 'WordPress',
			'medium'   => 'quick-link-menu',
			'campaign' => sugar_calendar()->is_pro() ? 'plugin' : 'pluginlite',
			'content'  => $content,
		];

		return Helpers::get_utm_url( $url, $utm_params );
	}

	/**
	 * Get upgrade URL.
	 *
	 * @since 3.9.0
	 *
	 * @return string Upgrade URL.
	 */
	private function get_upgrade_url() {

		// Use Sugar Calendar's upgrade URL with UTM parameters.
		return $this->get_utm_url( 'https://sugarcalendar.com/pricing/', 'Upgrade to Sugar Calendar Pro' );
	}
}
