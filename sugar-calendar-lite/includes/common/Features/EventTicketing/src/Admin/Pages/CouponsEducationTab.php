<?php

namespace Sugar_Calendar\AddOn\Ticketing\Admin\Pages;

use Sugar_Calendar\Admin\Pages\EducationContent;
use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\WP;
use function Sugar_Calendar\AddOn\Ticketing\Common\Assets\get_url;

/**
 * Coupons tab preview shown when the Pro coupons feature is not running.
 *
 * @since 3.17.0
 */
class CouponsEducationTab extends Tickets {

	/**
	 * Page tab slug.
	 *
	 * @since 3.17.0
	 *
	 * @return string
	 */
	public static function get_tab_slug() {

		return 'coupons';
	}

	/**
	 * Page title.
	 *
	 * @since 3.17.0
	 *
	 * @return string
	 */
	public static function get_title() {

		return esc_html__( 'Coupons', 'sugar-calendar-lite' );
	}

	/**
	 * Register page hooks.
	 *
	 * @since 3.17.0
	 */
	public function hooks() {

		parent::hooks();

		add_filter( 'sc_event_tickets_admin_nav', [ $this, 'filter_nav' ] );
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );
		add_filter( 'sugar_calendar_helpers_ui_help_url', [ $this, 'help_url' ] );
	}

	/**
	 * Remove the Export List and column options items, which have nothing to act on here.
	 *
	 * @since 3.17.0
	 *
	 * @param array $tabs Navigation items.
	 *
	 * @return array
	 */
	public function filter_nav( $tabs ) {

		unset( $tabs['export'], $tabs['column_options'] );

		return $tabs;
	}

	/**
	 * Filter the help URL.
	 *
	 * @since 3.17.0
	 *
	 * @param string $help_url The help URL.
	 *
	 * @return string
	 */
	public function help_url( $help_url ) {

		return Helpers::get_utm_url(
			'https://sugarcalendar.com/docs/events/using-coupon-codes/',
			[
				'content' => 'Help',
				'medium'  => 'coupons-education',
			]
		);
	}

	/**
	 * Enqueue the page chrome, the preview styles and the screenshot zoom.
	 *
	 * @since 3.17.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style(
			'sugar-calendar-ticketing-admin-coupons-education',
			get_url( 'css' ) . '/admin-coupons-education' . WP::asset_min() . '.css',
			[ 'sugar-calendar-vendor-lity' ],
			BaseHelpers::get_asset_version()
		);

		wp_enqueue_script( 'sugar-calendar-vendor-lity' );
	}

	/**
	 * Display the preview.
	 *
	 * @since 3.17.0
	 */
	protected function display_tab() {

		?>
		<div id="sugar-calendar-coupons-education" class="sugar-calendar-admin-page-education">
			<div class="sugar-calendar-admin-page-education__content">
				<?php EducationContent::display( $this->get_education() ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Preview copy, images and UTM medium.
	 *
	 * @since 3.17.0
	 *
	 * @return array
	 */
	private function get_education() {

		return [
			'title'          => static::get_title(),
			'heading_id'     => 'sugar-calendar-coupons-heading',
			'header_class'   => 'sugar-calendar-admin-page-education__content__header',
			'button_class'   => 'sugar-calendar-admin-page-education__content__header__buy-pro-btn',
			'medium'         => 'coupons',
			'description'    => esc_html__( 'Create coupon codes for gift vouchers, schools, members and early birds. Buyers enter the code at checkout and the total updates right away. You choose the amount, the events, the dates and how many times each coupon can be used.', 'sugar-calendar-lite' ),
			'screenshots'    => $this->get_screenshots(),
			'features_title' => esc_html__( 'Unlock These Awesome Features!', 'sugar-calendar-lite' ),
			'features'       => $this->get_features(),
		];
	}

	/**
	 * Screenshots with their full size and thumbnail images.
	 *
	 * @since 3.17.0
	 *
	 * @return array
	 */
	private function get_screenshots() {

		$images_url = SC_PLUGIN_ASSETS_URL . 'images/features/coupons/';

		return [
			[
				'url'           => $images_url . 'coupons-list.png',
				'url_thumbnail' => $images_url . 'coupons-list-thumbnail.png',
				'title'         => esc_html__( 'Managing Coupons', 'sugar-calendar-lite' ),
			],
			[
				'url'           => $images_url . 'coupons-add.png',
				'url_thumbnail' => $images_url . 'coupons-add-thumbnail.png',
				'title'         => esc_html__( 'Adding a Coupon', 'sugar-calendar-lite' ),
			],
		];
	}

	/**
	 * Feature list.
	 *
	 * @since 3.17.0
	 *
	 * @return array
	 */
	private function get_features() {

		return [
			esc_html__( 'Set a percentage or flat amount off tickets', 'sugar-calendar-lite' ),
			esc_html__( 'Set coupon start and expiry dates', 'sugar-calendar-lite' ),
			esc_html__( 'Set free orders with 100% off coupons', 'sugar-calendar-lite' ),
			esc_html__( 'Limit a coupon to chosen events', 'sugar-calendar-lite' ),
			esc_html__( 'Limit usage per coupon', 'sugar-calendar-lite' ),
			esc_html__( 'Discount shown on receipts and emails', 'sugar-calendar-lite' ),
		];
	}
}
