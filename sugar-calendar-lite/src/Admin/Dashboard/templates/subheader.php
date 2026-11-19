<?php
/**
 * Dashboard title bar with the date range control.
 *
 * @since 3.18.0
 *
 * @var string $label   Page title.
 * @var array  $options Preset labels keyed by value: a day count, or `custom`.
 * @var string $active  The option in effect.
 * @var string $custom  The active custom range as the picker writes it; empty on a preset.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="sugar-calendar-admin-subheader sugar-calendar-dashboard-subheader">
	<h4><?php echo esc_html( $label ); ?></h4>

	<div class="sugar-calendar-dashboard-date-range">
		<span class="spinner sugar-calendar-dashboard-date-range__spinner"></span>

		<label class="screen-reader-text" for="sugar-calendar-dashboard-date-range-preset"><?php esc_html_e( 'Date range', 'sugar-calendar-lite' ); ?></label>

		<span class="sugar-calendar-dashboard-date-range__select-wrap">
			<select id="sugar-calendar-dashboard-date-range-preset" class="sc-select sugar-calendar-dashboard-date-range__select">
				<?php foreach ( $options as $value => $option_label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) $value, $active ); ?>><?php echo esc_html( $option_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="sc-select__chevron sc:icon-[fa6-solid--chevron-down]" aria-hidden="true"></span>
		</span>

		<input type="text"
				class="sc-input sugar-calendar-dashboard-date-range__input"
				value="<?php echo esc_attr( $custom ); ?>"
				placeholder="<?php esc_attr_e( 'Select a date range', 'sugar-calendar-lite' ); ?>"
				aria-label="<?php esc_attr_e( 'Custom date range', 'sugar-calendar-lite' ); ?>"
				readonly
				<?php echo $custom === '' ? 'hidden' : ''; ?>>
	</div>
</div>
