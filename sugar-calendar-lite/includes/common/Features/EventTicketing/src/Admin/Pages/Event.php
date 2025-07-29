<?php

namespace Sugar_Calendar\AddOn\Ticketing\Admin\Pages;

use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Admin\Pages\Addons;
use Sugar_Calendar\Common\Features\EventTicketing\Feature;

/**
 * Event page.
 *
 * @since 3.8.0
 */
class Event {

	/**
	 * Feature.
	 *
	 * @since 3.8.0
	 *
	 * @var Feature
	 */
	public $feature;

	/**
	 * Hooks.
	 *
	 * @since 3.8.0
	 *
	 * @return void
	 */
	public function hooks() {

		add_action( 'admin_init', [ $this, 'admin_init' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'sc_et_metabox_bottom', [ $this, 'metabox_extra' ] );
	}

	/**
	 * Init.
	 *
	 * @since 3.8.0
	 */
	public function admin_init() {

		$this->feature = wp_parse_args(
			sugar_calendar()->get_addons()->get_addon( 'sc-event-ticketing' ),
			[
				'plugin_allow' => false,
				'status'       => 'missing',
			]
		);
	}

	/**
	 * Enqueue scripts.
	 *
	 * @since 3.8.0
	 */
	public function admin_enqueue_scripts() {

		wp_register_script(
			'sugar-calendar-event-ticketing-admin',
			SC_PLUGIN_ASSETS_URL . 'js/features/event-ticketing/admin' . WP::asset_min() . '.js',
			[
				'jquery',
			],
			BaseHelpers::get_asset_version()
		);

		wp_enqueue_script( 'sugar-calendar-event-ticketing-admin' );
	}
	/**
	 * Metabox bottom.
	 *
	 * @since 3.8.0
	 *
	 * @param Event $event The event object.
	 *
	 * @return void
	 */
	public function metabox_extra( $event ) {

		// If addon is allowed and active, show extra ticket features.
		if (
			$this->feature['plugin_allow']
			&&
			$this->feature['status'] === 'active'
		) {

			/**
			 * Extra ticket features.
			 *
			 * @since 3.8.0
			 *
			 * @param Event $event The event object.
			 *
			 * @return void
			 */
			do_action( 'sugar_calendar_add_on_ticketing_admin_pages_event', $event );

			return;
		}

		// If we're here, addon is either not allowed or not active.
		$this->education_ui_button();
		$this->education_cta();
	}

	/**
	 * Education UI button.
	 *
	 * @since 3.8.0
	 *
	 * @return void
	 */
	private function education_ui_button() {
		?>
		<div class="sugar-calendar-metabox__field-row">
			<button class="button button-secondary button-secondary__education">
				<?php esc_html_e( 'Add Another Ticket', 'sugar-calendar-lite' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Education CTA.
	 *
	 * @since 3.8.0
	 *
	 * @return void
	 */
	private function education_cta() {

		if ( // Addon is allowed but not active.
			$this->feature['plugin_allow']
			&&
			in_array(
				$this->feature['status'],
				[
					'missing',
					'installed',
				],
				true
			)
		) {

			// Recommend to install the addon.
			$this->education_cta_pro();

		} else {

			// Addon is not allowed. Recommend to upgrade.
			$this->education_cta_basic();
		}
	}

	/**
	 * Education CTA pro.
	 *
	 * @since 3.8.0
	 *
	 * @return void
	 */
	private function education_cta_pro() {

		$cta = wp_sprintf(
			'Install the Event Ticketing Addon to enjoy multiple ticket benefits! <a href="%1$s">Click here</a>',
			Addons::get_url()
		);

		$kses_args = [
			'a' => [
				'href' => [],
			],
		];
		?>
		<div class="sugar-calendar-metabox__notice sugar-calendar-metabox__notice-addon-install">
			<span class="dashicons dashicons-sc-et-ticketing"></span>
			<p><?php echo wp_kses( $cta, $kses_args ); ?></p>
		</div>
		<?php
	}

	/**
	 * Education CTA basic.
	 *
	 * @since 3.8.0
	 *
	 * @return void
	 */
	private function education_cta_basic() {

		$upgrade_url = Helpers::get_upgrade_link(
			[
				'medium'  => 'lite-event-ticketing',
				'content' => 'Upgrade to Sugar Calendar Plus Pro or Elite',
			]
		);

		if ( // Addon is installed but license is basic.
			isset( $this->feature['status'] )
			&&
			in_array( $this->feature['status'], [ 'installed', 'incompatible' ], true )
		) {

			$upgrade_url = Helpers::get_utm_url(
				'https://sugarcalendar.com/account/licenses/',
				[
					'medium'  => 'pro-event-ticketing',
					'content' => 'Upgrade to Sugar Calendar Plus Pro or Elite',
				]
			);
		}

		?>
		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--upgrade">
			<p class="desc">
				<?php
				echo wp_kses(
					sprintf( /* translators: %1$s - SugarCalendar.com documentation URL; %2$s - link text; %2$3 - paragraph text. */
						'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a> %3$s',
						$upgrade_url,
						esc_html__( 'Upgrade to Sugar Calendar Plus, Pro or Elite', 'sugar-calendar-lite' ),
						esc_html__( 'to get access to Event Ticketing add-on + more!', 'sugar-calendar-lite' )
					),
					[
						'a' => [
							'href'   => [],
							'rel'    => [],
							'target' => [],
						],
					]
				);
				?>
			</p>
		</div>
		<?php
	}
}
