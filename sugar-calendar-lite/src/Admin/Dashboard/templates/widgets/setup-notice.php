<?php
/**
 * Setup notice.
 *
 * @since 3.18.0
 *
 * @var string $wizard_url Setup Wizard start URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="sugar-calendar-dashboard-setup-notice__title">
	<span class="sugar-calendar-dashboard-setup-notice__icon sc:icon-[fa6-solid--circle-exclamation]" aria-hidden="true"></span>
	<h2><?php esc_html_e( 'Finish Setting Up Sugar Calendar', 'sugar-calendar-lite' ); ?></h2>
</div>

<p class="sugar-calendar-dashboard-setup-notice__text">
	<?php esc_html_e( 'Use the Setup Wizard to configure your first event, payments, and emails in just a few clicks.', 'sugar-calendar-lite' ); ?>
</p>

<a class="sc-button sc-button--primary" href="<?php echo esc_url( $wizard_url ); ?>">
	<?php esc_html_e( 'Launch Setup Wizard', 'sugar-calendar-lite' ); ?>
	<span class="sc:icon-[fa6-solid--arrow-right] sc-button__icon" aria-hidden="true"></span>
</a>
