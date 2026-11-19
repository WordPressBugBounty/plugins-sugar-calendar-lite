<?php
/**
 * Activity widget body: the tabs and the two tab panels (the Registrations & Orders recipe).
 *
 * Each tab keeps its own state, so the recipe root carries no `data-tab` or `data-state`.
 *
 * @since 3.18.0
 *
 * @var array  $series    Graph rows for the selected range.
 * @var array  $tabs      Tab labels keyed by id.
 * @var string $active    Selected tab id.
 * @var array  $attendees Attendees panel view.
 * @var array  $orders    Orders panel view.
 */

use Sugar_Calendar\Admin\Dashboard\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$panels = [
	'attendees' => Templates::get_html( 'widgets/activity-attendees', $attendees ),
	'orders'    => Templates::get_html( 'widgets/activity-orders', $orders ),
];
?>
<div class="sc-dashboard-registrations-orders sugar-calendar-dashboard-activity sc-reset-deep" data-series="<?php echo esc_attr( wp_json_encode( $series ) ); ?>">
	<?php
	$tabs_html = Templates::get_html(
		'widgets/activity-tabs',
		[
			'tabs'   => $tabs,
			'active' => $active,
		]
	);

	echo $tabs_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes its own output.
	?>

	<?php foreach ( $panels as $panel_id => $panel_html ) : ?>
		<?php // The recipe's `display: flex` outranks the reset's `[hidden]` rule, so the utility restores it. ?>
		<div id="sugar-calendar-dashboard-panel-<?php echo esc_attr( $panel_id ); ?>"
				class="sc-dashboard-registrations-orders__content sugar-calendar-dashboard-activity__panel sc:[&[hidden]]:hidden"
				role="tabpanel"
				aria-labelledby="sugar-calendar-dashboard-tab-<?php echo esc_attr( $panel_id ); ?>"
				data-panel="<?php echo esc_attr( $panel_id ); ?>"
				<?php echo $panel_id === $active ? '' : 'hidden'; ?>>
			<?php echo $panel_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The panel templates escape their own output. ?>
		</div>
	<?php endforeach; ?>
</div>
