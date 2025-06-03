<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\PageAbstract;
use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Plugin;

/**
 * Addons page.
 *
 * @since 3.7.0
 */
class Addons extends PageAbstract {

	/**
	 * Page slug.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		return 'sugar-calendar-addons';
	}

	/**
	 * Page label.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Addons', 'sugar-calendar-lite' );
	}

	/**
	 * Register page hooks.
	 *
	 * @since 3.7.0
	 */
	public function hooks() {

		add_action( 'admin_notices', [ $this, 'display_upgrade_notice' ] );
		add_action( 'in_admin_header', [ $this, 'display_admin_subheader' ] );
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Display the subheader.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function display_admin_subheader() {

		?>
        <div class="sugar-calendar-admin-subheader">
            <h4><?php echo esc_html( $this->get_label() ); ?></h4>
        </div>
		<?php
	}

	/**
	 * Display page.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function display() {

		$addons = Plugin::instance()->get_addons()->get_all();
		?>
        <div id="sugar-calendar-addons" class="wrap sugar-calendar-admin-wrap">
            <div class="sugar-calendar-admin-content">
                <h1 class="screen-reader-text"><?php echo esc_html( $this->get_label() ); ?></h1>
                <div id="sugar-calendar-addons-list-section-all">
                    <div class="list sugar-calendar-addons-list">

						<?php foreach ( $addons as $addon ) : ?>

							<?php
							$addon['title']   = ! empty( $addon['title'] ) ? $addon['title'] : __( 'Unknown Addon', 'sugar-calendar-lite' );
							$addon['title']   = str_replace( ' Addon', '', $addon['title'] );
							$addon['excerpt'] = ! empty( $addon['excerpt'] ) ? $addon['excerpt'] : '';

							$upgrade_link = Helpers::get_upgrade_link(
								[
									'medium'  => 'addons',
									'content' => $addon['title'],
								]
							);

							$licenses                 = [ 'basic', 'plus', 'pro', 'elite', 'agency', 'ultimate' ];
							$addon_licenses           = $addon['license'];
							$common_licenses          = array_intersect( $licenses, $addon_licenses );
							$minimum_required_license = reset( $common_licenses );
							$image_alt                = sprintf( /* translators: %s - addon title. */
								__( '%s logo', 'sugar-calendar-lite' ),
								$addon['title']
							);

							$badge = UI::get_addon_badge( $addon );

							$item_class = ! empty( $badge ) ? 'has-badge' : '';
							?>

                            <div class="sugar-calendar-addons-list-item addon-item <?php echo sanitize_key( $item_class ); ?>">
                                <div class="sugar-calendar-addons-list-item-header">
									<?php if ( ! empty( $addon['icon'] ) ) : ?>
                                        <div class="sugar-calendar-addons-list-item-header-icon">
                                            <img src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/addon-icons/' . $addon['icon'] ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>">
                                        </div>
									<?php endif; ?>
                                    <div class="sugar-calendar-addons-list-item-header-meta">
                                        <div class="sugar-calendar-addons-list-item-header-meta-title">
											<?php
											printf(
												'<a href="%1$s" title="%2$s" target="_blank" rel="noopener noreferrer" class="addon-link">%3$s</a>',
												esc_url( $upgrade_link ),
												esc_attr__( 'Learn more', 'sugar-calendar-lite' ),
												esc_html( $addon['title'] )
											);
											?>

											<?php
											echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
											?>
                                        </div>

                                        <div class="sugar-calendar-addons-list-item-header-meta-excerpt">
											<?php echo esc_html( $addon['excerpt'] ); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="sugar-calendar-addons-list-item-footer">
									<?php UI::print_badge( $minimum_required_license, 'lg' ); ?>

                                    <a href="<?php echo esc_url( $upgrade_link ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary sugar-calendar-upgrade-modal">
										<?php esc_html_e( 'Upgrade Now', 'sugar-calendar-lite' ); ?>
                                    </a>
                                </div>
                            </div>

						<?php endforeach; ?>

                    </div>
                </div>
            </div>
        </div>
		<?php
	}

	/**
	 * Display the ugprade notice.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function display_upgrade_notice() {

		$upgrade_link = Helpers::get_upgrade_link(
			[
				'medium'  => 'addons',
				'content' => 'All Addons',
			]
		);
		?>
        <div class="notice notice-info sugar-calendar-notice">
            <h4><?php esc_html_e( 'Upgrade to Unlock Sugar Calendar Addons', 'sugar-calendar-lite' ); ?></h4>
            <p><?php esc_html_e( 'Access powerful marketing and payment integrations, advanced form fields, and more when you purchase our Plus, Pro, or Elite plans.', 'sugar-calendar-lite' ); ?></p>
            <p>
                <a class="sugar-calendar-btn sugar-calendar-btn-primary sugar-calendar-btn-md"
                   href="<?php echo esc_url( $upgrade_link ); ?>"
                   target="_blank"><?php esc_html_e( 'Upgrade Now', 'sugar-calendar-lite' ); ?></a>
            </p>
        </div>
		<?php
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style(
			'sugar-calendar-admin-addons',
			SC_PLUGIN_ASSETS_URL . 'css/admin-addons' . WP::asset_min() . '.css',
			[],
			BaseHelpers::get_asset_version()
		);
	}
}
