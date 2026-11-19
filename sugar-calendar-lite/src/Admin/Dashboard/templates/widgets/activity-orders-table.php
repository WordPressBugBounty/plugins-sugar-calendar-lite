<?php
/**
 * Latest orders table, shared by the Orders panel and its teaser.
 *
 * Each column takes its Figma share of the 781px table (170/190/221/100/100). From a 740px table, Order
 * and Event stay on one line and end in an ellipsis, as Figma's rows do; the link, not the cell, is cut, so
 * its focus ring is not clipped. Below it, ordinary names would be cut, so a long value wraps in its own
 * cell and the wrapper scrolls sideways when it must. Date is hidden below 600px so Total and Status fit
 * a phone without a sideways scroll. Status has no left padding: Figma starts its labels at the column's edge.
 *
 * @since 3.18.0
 *
 * @var array $rows Orders: `label`, `url`, `event`, `event_url` (empty for a deleted event), `date`, `total`, `status_label`, `status_color`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php // The side padding, given back by the margin, keeps the edge links' focus rings inside the scroll box's clip. ?>
<div class="sc:overflow-x-auto sc:@container sc:px-[4px] sc:-mx-[4px]">
	<table class="sc-dashboard-registrations-orders__table">
		<thead>
			<tr class="sc-dashboard-registrations-orders__row">
				<th class="sc-dashboard-registrations-orders__th sc-field-label sc:w-[21.77%]" scope="col"><?php esc_html_e( 'Order', 'sugar-calendar-lite' ); ?></th>
				<th class="sc-dashboard-registrations-orders__th sc-field-label sc:w-[24.33%]" scope="col"><?php esc_html_e( 'Event', 'sugar-calendar-lite' ); ?></th>
				<th class="sc-dashboard-registrations-orders__th sc-field-label sc:w-[28.3%] sc:max-phone:hidden" scope="col"><?php esc_html_e( 'Date', 'sugar-calendar-lite' ); ?></th>
				<th class="sc-dashboard-registrations-orders__th sc-field-label sc:w-[12.8%]" scope="col"><?php esc_html_e( 'Total', 'sugar-calendar-lite' ); ?></th>
				<th class="sc-dashboard-registrations-orders__th sc-field-label sc:w-[12.8%] sc:pl-0" scope="col"><?php esc_html_e( 'Status', 'sugar-calendar-lite' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr class="sc-dashboard-registrations-orders__row">
					<td class="sc-dashboard-registrations-orders__td sc-field-text sc:@min-[740px]:max-w-0" title="<?php echo esc_attr( $row['label'] ); ?>"><a class="sc-dashboard-registrations-orders__link sc-field-label sc:@min-[740px]:inline-block sc:@min-[740px]:max-w-full sc:@min-[740px]:truncate sc:@min-[740px]:align-top" href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['label'] ); ?></a></td>
					<td class="sc-dashboard-registrations-orders__td sc-field-text sc:@min-[740px]:max-w-0" title="<?php echo esc_attr( $row['event'] ); ?>">
						<?php if ( $row['event_url'] !== '' ) : ?>
							<a class="sc-dashboard-registrations-orders__link sc-field-text sc:@min-[740px]:inline-block sc:@min-[740px]:max-w-full sc:@min-[740px]:truncate sc:@min-[740px]:align-top" href="<?php echo esc_url( $row['event_url'] ); ?>"><?php echo esc_html( $row['event'] ); ?></a>
						<?php else : ?>
							<span class="sc:@min-[740px]:inline-block sc:@min-[740px]:max-w-full sc:@min-[740px]:truncate sc:@min-[740px]:align-top"><?php echo esc_html( $row['event'] ); ?></span>
						<?php endif; ?>
					</td>
					<td class="sc-dashboard-registrations-orders__td sc-field-text sc:max-phone:hidden"><?php echo esc_html( $row['date'] ); ?></td>
					<td class="sc-dashboard-registrations-orders__td sc-field-text"><?php echo wp_kses_post( $row['total'] ); ?></td>
					<td class="sc-dashboard-registrations-orders__td sc-field-text sc:pl-0"><span class="sc-label sc-label--<?php echo esc_attr( $row['status_color'] ); ?>" title="<?php echo esc_attr( $row['status_label'] ); ?>"><?php echo esc_html( $row['status_label'] ); ?></span></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
