<?php
/**
 * Dashboard page shell.
 *
 * @since 3.18.0
 *
 * @var string $label           Page label, for the screen-reader heading.
 * @var string $stat_cards      The stat cards row above the columns.
 * @var string $main_widgets    Main column widgets.
 * @var string $sidebar_widgets Sidebar widgets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="sugar-calendar-dashboard" class="wrap sugar-calendar-admin-wrap">
	<div class="sugar-calendar-admin-content">
		<h1 class="screen-reader-text"><?php echo esc_html( $label ); ?></h1>

		<?php
		// phpcs:ignore WPForms.Comments.PHPDocHooks.RequiredHookDocumentation,WPForms.PHP.ValidateHooks.InvalidHookName
		do_action( 'sugar_calendar_admin_page_content_top' );
		?>

		<div id="sugar-calendar-dashboard-attention"></div>

		<?php echo $stat_cards; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The stat card template escapes its own output. ?>

		<div class="sugar-calendar-dashboard__columns">
			<div id="sugar-calendar-dashboard-column-main" class="sugar-calendar-dashboard__column-main">
				<?php echo $main_widgets; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The widget templates escape their own output. ?>
			</div>

			<div id="sugar-calendar-dashboard-column-sidebar" class="sugar-calendar-dashboard__column-sidebar">
				<?php echo $sidebar_widgets; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The widget templates escape their own output. ?>
			</div>
		</div>
	</div>
</div>
