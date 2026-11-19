<?php
/**
 * Activity widget tabs: Attendees and Orders.
 *
 * @since 3.18.0
 *
 * @var array  $tabs   Tab labels keyed by id.
 * @var string $active Selected tab id.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<nav class="sc-dashboard-registrations-orders__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Activity', 'sugar-calendar-lite' ); ?>">
	<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
		<?php $is_active = $tab_id === $active; ?>
		<button type="button"
				role="tab"
				id="sugar-calendar-dashboard-tab-<?php echo esc_attr( $tab_id ); ?>"
				class="sc-dashboard-registrations-orders__tab sc-nav sugar-calendar-dashboard-activity__tab<?php echo $is_active ? ' is-selected' : ''; ?>"
				aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
				aria-controls="sugar-calendar-dashboard-panel-<?php echo esc_attr( $tab_id ); ?>"
				tabindex="<?php echo $is_active ? '0' : '-1'; ?>"
				data-tab="<?php echo esc_attr( $tab_id ); ?>"><?php echo esc_html( $tab_label ); ?></button>
	<?php endforeach; ?>
</nav>
