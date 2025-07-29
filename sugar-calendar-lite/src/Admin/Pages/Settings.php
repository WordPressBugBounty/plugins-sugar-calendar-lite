<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Area;
use Sugar_Calendar\Admin\Pages\Settings\EmailsConfigTab;
use Sugar_Calendar\Admin\PageTabAbstract;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Plugin;
use function Sugar_Calendar\Admin\Settings\get_sections;
use function Sugar_Calendar\Admin\Settings\get_subsection;
use function Sugar_Calendar\Admin\Settings\get_subsections;

/**
 * Settings tab. Handles any settings screen
 * that's not handled by a dedicated tab class.
 *
 * @since 3.0.0
 */
class Settings extends PageTabAbstract {

	/**
	 * Nonce for dismissing WP Mail SMTP notice.
	 *
	 * @since 3.8.0
	 *
	 * @var string
	 */
	const DISMISS_NONCE_EMAIL_WP_MAIL_SMTP_NOTICE = 'sugar_calendar_email_wp_mail_smtp_notice_dismiss';

	/**
	 * Page slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_slug() {

		return 'sugarcalendar-settings';
	}

	/**
	 * Page tab slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_tab_slug() {

		if ( ! isset( $_GET['section'] ) ) {
			return null;
		}

		return sanitize_key( $_GET['section'] );
	}

	/**
	 * Page label.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Settings', 'sugar-calendar-lite' );
	}

	/**
	 * Page menu priority.
	 *
	 * @since 3.0.0
	 *
	 * @return int
	 */
	public static function get_priority() {

		return 10;
	}

	/**
	 * Register page hooks.
	 *
	 * @since 3.0.0
	 */
	public function hooks() {

		add_action( 'admin_init', [ $this, 'init' ] );
		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'enqueue_assets' ] );
		add_action( 'sc_et_settings_emails_section_top', [ $this, 'maybe_display_email_wp_mail_smtp_notice' ] );
	}

	/**
	 * Display WP Mail SMTP notice if possible.
	 *
	 * @since 3.8.0
	 */
	public function maybe_display_email_wp_mail_smtp_notice() {

		// Check if notice was already been dismissed.
		if ( (bool) get_option( self::DISMISS_NONCE_EMAIL_WP_MAIL_SMTP_NOTICE, false ) ) {
			return;
		}

		// Check if existing mail smtp plugins.
		$smtp_plugins = [
			'wp-mail-smtp/wp_mail_smtp.php',
			'wp-mail-smtp-pro/wp_mail_smtp.php',
			'fluent-smtp/fluent-smtp.php',
			'post-smtp/postman-smtp.php',
			'easy-wp-smtp/easy-wp-smtp.php',
		];

		$active_smtp_plugins = [];

		foreach ( $smtp_plugins as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				$active_smtp_plugins[] = $plugin;
			}
		}

		if ( ! empty( $active_smtp_plugins ) ) {
			return;
		}
		?>
		<div class="sugar-calendar-settings__emails__wpmailsmtp__notice">
			<button data-nonce="<?php echo esc_attr( wp_create_nonce( self::DISMISS_NONCE_EMAIL_WP_MAIL_SMTP_NOTICE ) ); ?>" type="button" class="sugar-calendar-settings__emails__wpmailsmtp__notice__close">
			</button>
			<div class="sugar-calendar-settings__emails__wpmailsmtp__notice__logo">
			<svg width="32" height="33" viewBox="0 0 32 33" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path fill-rule="evenodd" clip-rule="evenodd" d="M12.2163 4.84143C11.1638 5.38813 10.2419 6.15586 9.51377 7.09204C8.78563 8.02823 8.26845 9.11074 7.99765 10.2654C6.01116 11.9114 4.58073 14.1301 3.90145 16.6189C3.22217 19.1076 3.32709 21.7454 4.20192 24.1723C5.07675 26.5993 6.6789 28.6973 8.78986 30.1803C10.9008 31.6633 13.4178 32.4591 15.9977 32.4591C18.5775 32.4591 21.0945 31.6633 23.2054 30.1803C25.3164 28.6973 26.9186 26.5993 27.7934 24.1723C28.6682 21.7454 28.7731 19.1076 28.0939 16.6189C27.4146 14.1301 25.9841 11.9114 23.9977 10.2654C23.6944 8.9701 23.0812 7.76774 22.2107 6.76166C21.3403 5.75558 20.2386 4.97584 19.0003 4.48943C18.9576 4.17396 18.8326 3.8753 18.6379 3.62346C18.4432 3.37162 18.1856 3.17548 17.891 3.05477C17.8645 2.49912 17.6511 1.96871 17.2855 1.54947C16.9199 1.13022 16.4234 0.84671 15.8765 0.744866C15.3297 0.643022 14.7644 0.728813 14.2724 0.988337C13.7804 1.24786 13.3904 1.66592 13.1657 2.17477C12.7532 3.02651 12.4349 3.92068 12.2163 4.84143Z" fill="#395360"/>
				<path d="M19.3277 14.3672H12.9277V21.8339H19.3277V14.3672Z" fill="#FBAA6F"/>
				<path fill-rule="evenodd" clip-rule="evenodd" d="M17.1282 18.196L17.0642 18.1533C16.9015 18.0352 16.7924 17.8573 16.7609 17.6588C16.7294 17.4603 16.778 17.2573 16.8962 17.0947C17.0143 16.932 17.1922 16.8229 17.3907 16.7914C17.5892 16.7599 17.7922 16.8086 17.9548 16.9267C17.8229 16.9512 17.6972 17.0021 17.5854 17.0763C17.4736 17.1506 17.378 17.2466 17.3042 17.3587C17.2139 17.4761 17.1511 17.6123 17.1207 17.7573C17.0902 17.9022 17.0928 18.0522 17.1282 18.196ZM14.7388 18.196C14.7693 18.0523 14.7664 17.9035 14.7303 17.7611C14.6943 17.6188 14.626 17.4865 14.5308 17.3747C14.4555 17.2628 14.3587 17.1671 14.2461 17.0929C14.1335 17.0187 14.0073 16.9677 13.8748 16.9427C14.0375 16.8246 14.2404 16.7759 14.4389 16.8074C14.6375 16.8389 14.8154 16.948 14.9335 17.1107C15.0516 17.2733 15.1003 17.4763 15.0687 17.6748C15.0372 17.8733 14.9282 18.0512 14.7655 18.1693C14.7572 18.1788 14.7483 18.1877 14.7388 18.196ZM18.5895 16.5H18.8722L18.3015 19.0707L17.1602 22.5H14.8722L13.1602 19.6413L13.7308 17.9293C14.3015 18.692 14.6855 19.1667 14.8722 19.3587C15.1602 19.6413 16.3015 19.6413 16.8722 19.0707C17.538 18.28 18.114 17.4178 18.5895 16.5Z" fill="#DC7F3C"/>
				<path fill-rule="evenodd" clip-rule="evenodd" d="M8.53542 15.9669H11.3408V11.1669H9.17542C9.34029 10.0134 9.79321 8.92008 10.4923 7.98791C11.1915 7.05575 12.1142 6.31481 13.1754 5.83356C13.5808 4.05578 14.0625 2.85578 14.6208 2.23356L14.7168 2.14289L14.7754 2.08956C14.9418 1.95634 15.1406 1.86982 15.3514 1.83889C15.5996 1.79968 15.8539 1.83696 16.0804 1.94578C16.3069 2.05461 16.4949 2.22981 16.6194 2.4481C16.7438 2.66638 16.7989 2.91739 16.7773 3.16774C16.7556 3.41809 16.6582 3.65591 16.4981 3.84956H16.4661C16.4196 3.90772 16.3658 3.95969 16.3061 4.00423C16.0544 4.26712 15.821 4.54688 15.6074 4.84156C16.0327 4.39863 16.5979 4.11605 17.2074 4.04156C17.33 4.04068 17.4509 4.06999 17.5594 4.12689C17.7206 4.21882 17.8394 4.37025 17.8902 4.54872C17.9411 4.72719 17.92 4.91848 17.8314 5.08156C17.7601 5.2133 17.6478 5.31816 17.5114 5.38023C18.9073 5.68206 20.1759 6.4076 21.1439 7.45767C22.1119 8.50773 22.732 9.8311 22.9194 11.2469L22.9834 11.7109H20.9408V15.9776H23.5168L24.4714 23.1402C22.2208 24.5376 19.403 25.2362 16.0181 25.2362C12.6332 25.2362 9.82964 24.5358 7.60742 23.1349L8.53542 15.9669ZM16.6048 20.9749C17.9808 18.7029 18.6688 17.35 18.6688 16.9162C18.6688 15.7269 16.9354 14.7509 16.0714 14.7509C15.2074 14.7509 13.4741 15.7216 13.4741 16.9162C13.4741 17.35 14.1514 18.7047 15.5061 20.9802C15.5668 21.0747 15.6505 21.1522 15.7494 21.2054C15.8483 21.2586 15.9591 21.2857 16.0714 21.2842C16.1794 21.2895 16.2867 21.2643 16.381 21.2115C16.4753 21.1587 16.5528 21.0804 16.6048 20.9856V20.9749Z" fill="#BDCFC8"/>
				<path fill-rule="evenodd" clip-rule="evenodd" d="M24.5658 26.538C23.5732 27.872 22.2825 28.9554 20.7967 29.7017C19.3109 30.4481 17.6712 30.8368 16.0085 30.8368C14.3458 30.8368 12.7061 30.4481 11.2203 29.7017C9.73452 28.9554 8.4438 27.872 7.45117 26.538L7.89917 23.2794C8.06884 23.3469 8.24989 23.3813 8.43251 23.3807C8.79662 23.3818 9.14885 23.2512 9.42426 23.013C9.69968 22.7749 9.87971 22.4452 9.93117 22.0847V22.7354C9.93117 23.1371 10.0908 23.5224 10.3748 23.8064C10.6589 24.0905 11.0441 24.25 11.4458 24.25C11.8476 24.25 12.2328 24.0905 12.5169 23.8064C12.8009 23.5224 12.9605 23.1371 12.9605 22.7354V23.5994C12.9835 23.9863 13.1533 24.3497 13.4353 24.6155C13.7174 24.8814 14.0903 25.0294 14.4778 25.0294C14.8654 25.0294 15.2383 24.8814 15.5203 24.6155C15.8024 24.3497 15.9722 23.9863 15.9952 23.5994C15.9952 24.0011 16.1548 24.3864 16.4388 24.6704C16.7229 24.9545 17.1081 25.114 17.5098 25.114C17.9116 25.114 18.2968 24.9545 18.5809 24.6704C18.8649 24.3864 19.0245 24.0011 19.0245 23.5994V22.7621C19.0245 22.961 19.0637 23.1579 19.1398 23.3417C19.2159 23.5255 19.3275 23.6924 19.4681 23.8331C19.6088 23.9737 19.7758 24.0853 19.9595 24.1614C20.1433 24.2375 20.3403 24.2767 20.5392 24.2767C20.7381 24.2767 20.935 24.2375 21.1188 24.1614C21.3026 24.0853 21.4696 23.9737 21.6102 23.8331C21.7509 23.6924 21.8624 23.5255 21.9385 23.3417C22.0147 23.1579 22.0538 22.961 22.0538 22.7621V22.1114C22.1014 22.4845 22.2861 22.8267 22.572 23.0712C22.8578 23.3157 23.2245 23.445 23.6005 23.434C23.7826 23.434 23.9632 23.4015 24.1338 23.338L24.5658 26.538Z" fill="#809EB0"/>
				<path fill-rule="evenodd" clip-rule="evenodd" d="M7.75977 24.3133L7.89843 23.3053C8.0681 23.3728 8.24915 23.4072 8.43177 23.4066C8.79588 23.4077 9.14811 23.2771 9.42352 23.0389C9.69893 22.8008 9.87897 22.4711 9.93043 22.1106V22.7613C9.93043 23.163 10.09 23.5482 10.3741 23.8323C10.6581 24.1163 11.0434 24.2759 11.4451 24.2759C11.8468 24.2759 12.2321 24.1163 12.5161 23.8323C12.8002 23.5482 12.9598 23.163 12.9598 22.7613V23.6253C12.9827 24.0121 13.1526 24.3756 13.4346 24.6414C13.7166 24.9072 14.0895 25.0553 14.4771 25.0553C14.8646 25.0553 15.2376 24.9072 15.5196 24.6414C15.8016 24.3756 15.9715 24.0121 15.9944 23.6253C15.9944 24.027 16.154 24.4122 16.4381 24.6963C16.7221 24.9803 17.1074 25.1399 17.5091 25.1399C17.9108 25.1399 18.2961 24.9803 18.5801 24.6963C18.8642 24.4122 19.0238 24.027 19.0238 23.6253V22.7613C19.0238 23.163 19.1833 23.5482 19.4674 23.8323C19.7515 24.1163 20.1367 24.2759 20.5384 24.2759C20.9401 24.2759 21.3254 24.1163 21.6095 23.8323C21.8935 23.5482 22.0531 23.163 22.0531 22.7613V22.1106C22.1006 22.4838 22.2853 22.8259 22.5712 23.0704C22.8571 23.3149 23.2237 23.4442 23.5998 23.4333C23.7818 23.4332 23.9624 23.4007 24.1331 23.3373L24.2664 24.3453C24.0518 24.4501 23.8155 24.5029 23.5766 24.4994C23.3378 24.4959 23.1031 24.4361 22.8917 24.3249C22.6803 24.2138 22.498 24.0543 22.3598 23.8596C22.2215 23.6648 22.131 23.4402 22.0958 23.2039V23.8546C22.0958 24.2563 21.9362 24.6416 21.6521 24.9256C21.3681 25.2097 20.9828 25.3693 20.5811 25.3693C20.1794 25.3693 19.7941 25.2097 19.5101 24.9256C19.226 24.6416 19.0664 24.2563 19.0664 23.8546V24.7186C19.0664 25.1203 18.9068 25.5056 18.6228 25.7896C18.3387 26.0737 17.9535 26.2333 17.5518 26.2333C17.15 26.2333 16.7648 26.0737 16.4807 25.7896C16.1967 25.5056 16.0371 25.1203 16.0371 24.7186C16.0141 25.1055 15.8443 25.469 15.5623 25.7348C15.2802 26.0006 14.9073 26.1486 14.5198 26.1486C14.1322 26.1486 13.7593 26.0006 13.4773 25.7348C13.1952 25.469 13.0254 25.1055 13.0024 24.7186V23.8279C13.0024 24.2296 12.8428 24.6149 12.5588 24.899C12.2747 25.183 11.8895 25.3426 11.4878 25.3426C11.086 25.3426 10.7008 25.183 10.4167 24.899C10.1327 24.6149 9.9731 24.2296 9.9731 23.8279V23.1773C9.92697 23.5481 9.74491 23.8886 9.46215 24.133C9.17939 24.3773 8.81604 24.5081 8.44243 24.4999C8.20563 24.4996 7.97207 24.4448 7.75977 24.3399V24.3133Z" fill="#738E9E"/>
				<path fill-rule="evenodd" clip-rule="evenodd" d="M23.4827 12.4409C22.9494 10.9102 21.8827 9.93958 20.9654 10.0356C19.7921 10.1582 19.5041 12.0409 19.7441 14.3022C19.9841 16.5636 20.6507 18.3342 21.8241 18.2116C22.9974 18.0889 23.9574 16.1369 23.7867 13.9449C23.7441 14.6009 23.5094 15.1609 23.0454 15.1982C22.4481 15.2516 22.2774 14.5636 22.1974 13.6942C22.1174 12.8249 22.1227 12.0942 22.7307 12.0516C22.8799 12.0402 23.0292 12.0707 23.162 12.1394C23.2948 12.2082 23.4059 12.3126 23.4827 12.4409Z" fill="#86A196"/>
				<path fill-rule="evenodd" clip-rule="evenodd" d="M23.0662 12.0996C22.7302 11.4543 22.2662 11.0596 21.7915 11.113C20.9862 11.193 20.7942 12.4836 20.9542 14.0303C21.1142 15.577 21.5782 16.793 22.3782 16.697C22.9702 16.633 23.4075 15.897 23.5302 14.8943C23.4808 14.9789 23.412 15.0506 23.3295 15.1035C23.2469 15.1563 23.153 15.1888 23.0555 15.1983C22.4582 15.2516 22.2875 14.5636 22.2075 13.6943C22.1275 12.825 22.1328 12.0943 22.7408 12.0516C22.8513 12.0465 22.9618 12.0628 23.0662 12.0996Z" fill="white"/>
				<path fill-rule="evenodd" clip-rule="evenodd" d="M8.44873 12.4409C8.98207 10.9102 10.0487 9.93958 10.9661 10.0356C12.1394 10.1582 12.4274 12.0409 12.1874 14.3022C11.9474 16.5636 11.2807 18.3342 10.1074 18.2116C8.93407 18.0889 7.97407 16.1369 8.14473 13.9449C8.1874 14.6009 8.41673 15.1609 8.88607 15.1982C9.4834 15.2516 9.65407 14.5636 9.72873 13.6942C9.8034 12.8249 9.80873 12.0942 9.1954 12.0516C9.04559 12.0371 8.89473 12.0649 8.75993 12.1319C8.62513 12.1988 8.51178 12.3022 8.43273 12.4302L8.44873 12.4409Z" fill="#86A196"/>
				<path fill-rule="evenodd" clip-rule="evenodd" d="M8.85658 12.0996C9.19258 11.4543 9.66191 11.0596 10.1366 11.113C10.9366 11.193 11.1339 12.4836 10.9686 14.0303C10.8032 15.577 10.3499 16.793 9.54458 16.697C8.95258 16.633 8.51524 15.897 8.39258 14.8943C8.44314 14.9791 8.51294 15.0507 8.59632 15.1035C8.6797 15.1564 8.77434 15.1888 8.87258 15.1983C9.46458 15.2516 9.64058 14.5636 9.71524 13.6943C9.78991 12.825 9.78991 12.0943 9.18191 12.0516C9.06679 12.0419 8.95089 12.0546 8.84058 12.089L8.85658 12.0996Z" fill="white"/>
				<path fill-rule="evenodd" clip-rule="evenodd" d="M13.4649 15.7699C13.457 15.6474 13.457 15.5245 13.4649 15.4019C13.4649 14.0846 14.1103 12.5859 16.0623 12.5859C18.0143 12.5859 18.6596 14.0846 18.6596 15.4019C18.657 15.5818 18.6337 15.7607 18.5903 15.9353C18.1636 15.2153 17.3636 14.7726 16.0303 14.7726C14.7663 14.7886 13.9449 15.1566 13.4649 15.7699Z" fill="#F4F8FF"/>
				<path fill-rule="evenodd" clip-rule="evenodd" d="M17.47 5.38522L15.774 5.29989L17.87 4.68122C17.8816 4.82514 17.8497 4.96923 17.7783 5.09477C17.707 5.22032 17.5996 5.32151 17.47 5.38522ZM15.5927 4.84122L14.8887 5.37455C15.3264 4.76081 15.6848 4.0942 15.9553 3.39055C16.1099 2.93276 16.1681 2.44791 16.126 1.96655C16.3374 2.08354 16.5107 2.25889 16.6251 2.47168C16.7395 2.68447 16.7903 2.92569 16.7713 3.16655C16.7384 3.47413 16.5965 3.75978 16.3713 3.97189C16.091 4.24232 15.8307 4.53287 15.5927 4.84122Z" fill="#86A196"/>
				</svg>
			</div>
			<div class="sugar-calendar-settings__emails__wpmailsmtp__notice__content">
				<div class="sugar-calendar-settings__emails__wpmailsmtp__notice__content__title">
					<span><?php esc_html_e( 'Make Sure Important Emails Reach Your Customers', 'sugar-calendar-lite' ); ?></span>
				</div>
				<p>
					<?php
					echo wp_kses(
						sprintf(
							/*
							 * translators: 1. WP Mail SMTP URL.
							 */
							__( 'Solve common email deliverability issues for good. <a target="_blank" href="%1$s">Get WP Mail SMTP!</a>', 'sugar-calendar-lite' ),
							esc_url( 'https://wordpress.org/plugins/wp-mail-smtp/' )
						),
						[
							'a' => [
								'target' => [],
								'href'   => [],
							],
						]
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Initialize page.
	 *
	 * @since 3.0.0
	 * @since 3.7.0 Added Emails configuration section.
	 *
	 * @return void
	 */
	public function init() {

		$section_id = static::get_tab_slug();
		$sections   = array_keys( $this->get_tabs() );

		if ( ! in_array( $section_id, $sections ) ) {
			wp_safe_redirect( Plugin::instance()->get_admin()->get_page_url( 'settings_general' ) );
			exit;
		}

		if ( $section_id === 'emails' ) {
			( new EmailsConfigTab() )->hooks();
		}
	}

	/**
	 * Display page.
	 *
	 * @since 3.0.0
	 */
	public function display() {
		?>
		<div id="sugar-calendar-settings" class="wrap sugar-calendar-admin-wrap">

			<?php UI::tabs( $this->get_tabs(), static::get_tab_slug() ); ?>

			<div class="sugar-calendar-admin-content">
				<h1 class="screen-reader-text"><?php esc_html_e( 'Settings', 'sugar-calendar-lite' ); ?></h1>
				<form class="sugar-calendar-admin-content__settings-form" method="post" action="">

					<?php $this->display_tab( static::get_tab_slug() ); ?>

					<p class="submit">
						<?php
						UI::button(
							[
								'text' => apply_filters(
									'sugar_calendar_admin_settings_save_btn_label',
									esc_html__( 'Save Settings', 'sugar-calendar-lite' ),
									static::get_slug()
								),
							]
						);
						?>
					</p>

					<?php wp_nonce_field( Area::SLUG ); ?>

				</form>

				<?php
				// phpcs:ignore WPForms.Comments.PHPDocHooks.RequiredHookDocumentation,WPForms.PHP.ValidateHooks.InvalidHookName
				do_action( 'sugar_calendar_admin_page_after' );
				?>

			</div>
		</div>
		<?php
	}

	/**
	 * Return the list of tabs for this page.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	protected function get_tabs() {

		static $sections = null;

		if ( $sections === null ) {
			$tabs = [
				'settings_general',
				'settings_feeds',
				'settings_maps',
				'settings_misc',
			];

			/**
			 * Filter Settings page tabs.
			 *
			 * @since 3.0.0
			 *
			 * @param array $tabs Array of tabs.
			 */
			$tabs = apply_filters( 'sugar_calendar_admin_pages_settings_get_tabs', $tabs );

			// Map tab ids to their classes.
			$tabs = array_map( fn( $tab ) => Plugin::instance()->get_admin()->get_page( $tab ), $tabs );

			// Convert tabs to a format legacy navigation understands.
			$tabs = array_reduce(
				$tabs,
				function ( $tabs, $tab ) {

					$tabs[ $tab::get_tab_slug() ] = [
						'name'     => $tab::get_label(),
						'url'      => $tab::get_url(),
						'priority' => $tab::get_priority(),
					];

					return $tabs;
				},
				[]
			);

			// Add priority to legacy tabs.
			$legacy_tabs = get_sections();

			foreach ( $legacy_tabs as $tab_id => $tab_data ) {
				$legacy_tabs[ $tab_id ]['priority'] = 20;
			}

			// Append "new" sections to legacy ones.
			$sections = array_merge( $tabs, $legacy_tabs );

			if ( ! empty( $sections['zapier'] ) ) {
				$sections['zapier']['priority'] = 60;
			}

			// Sort tabs by priority.
			uasort( $sections, fn( $a, $b ) => $a['priority'] <= $b['priority'] ? -1 : 1 );
		}

		return $sections;
	}

	/**
	 * Display a tab's content.
	 *
	 * @since 3.0.0
	 *
	 * @param string $section The tab's slug.
	 */
	protected function display_tab( $section = '' ) {

		$subsections = get_subsections( $section );

		foreach ( $subsections as $subsection_id => $subsection ) {
			$subsection = get_subsection( $section, $subsection_id );
			$func       = $subsection['func'] ?? '';

			if ( is_callable( $func ) || function_exists( $func ) ) {
				call_user_func( $func );
			}
		}
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style( 'sugar-calendar-admin-settings' );
		wp_enqueue_script( 'sugar-calendar-admin-settings' );

		wp_localize_script(
			'sugar-calendar-admin-settings',
			'sugar_calendar_admin_settings',
			[
				'ajax_url' => Plugin::instance()->get_admin()->ajax_url(),
			]
		);
	}
}
