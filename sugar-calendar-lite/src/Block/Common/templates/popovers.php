<?php
/**
 * @var Sugar_Calendar\Block\Calendar\CalendarView\Block $context
 */
?>
<div class="sugar-calendar-block__popover__month_selector sugar-calendar-block__popover" role="popover">
	<div class="sugar-calendar-block__popover__month_selector__container">
		<div class="sugar-calendar-block__controls__datepicker"
			data-date="<?php echo esc_attr( $context->get_datetime()->format( 'm/d/Y' ) ); ?>">
		</div>
	</div>
</div>

<div class="sugar-calendar-block__popover__calendar_selector sugar-calendar-block__popover" role="popover">

	<div class="sugar-calendar-block__popover__calendar_selector__container">

		<?php
		$calendars = $context->get_calendars_for_filter();

		if ( ! empty( $calendars ) ) {
			?>
			<div class="sugar-calendar-block__popover__calendar_selector__container__calendars">
				<div class="sugar-calendar-block__popover__calendar_selector__container__heading">
					<?php esc_html_e( 'Calendars', 'sugar-calendar-lite' ); ?>
				</div>
				<div class="sugar-calendar-block__popover__calendar_selector__container__options">
				<?php
				foreach ( $calendars as $calendar ) {
					$cal_checkbox_id = sprintf(
						'sc-cal-%1$s-%2$d',
						esc_attr( $context->get_block_id() ),
						esc_attr( $calendar->term_id )
					);
					?>
					<div class="sugar-calendar-block__popover__calendar_selector__container__options__val">
						<label for="<?php echo esc_attr( $cal_checkbox_id ); ?>">
							<input
								type="checkbox"
								id="<?php echo esc_attr( $cal_checkbox_id ); ?>"
								class="sugar-calendar-block__popover__calendar_selector__container__options__val__cal"
								name="calendar-<?php echo esc_attr( $calendar->term_id ); ?>"
								value="<?php echo esc_attr( $calendar->term_id ); ?>"
							/>
							<?php echo esc_html( $calendar->name ); ?>
						</label>
					</div>
					<?php
				}
				?>
				</div>
			</div>
			<?php
		}

		/**
		 * Fires after the calendars filter in the calendar selector popover.
		 *
		 * @since 3.5.0
		 *
		 * @param Sugar_Calendar\Block\Calendar\CalendarView\Block $context The block context.
		 */
		do_action( 'sugar_calendar_block_popover_additional_filters', $context );

		$container_days_style = '';

		if ( $context->get_display_mode() === 'day' ) {
			$container_days_style = 'display: none;';
		}
		?>

		<div style="<?php echo esc_attr( $container_days_style ); ?>" class="sugar-calendar-block__popover__calendar_selector__container__days">
			<div class="sugar-calendar-block__popover__calendar_selector__container__heading">
				<?php esc_html_e( 'Days of the Week', 'sugar-calendar-lite' ); ?>
			</div>
			<div class="sugar-calendar-block__popover__calendar_selector__container__options">
				<?php
				global $wp_locale;

				foreach ( $wp_locale->weekday as $weekday_number => $weekday_name ) {
					$input_attr = "sc-day-{$context->get_block_id()}-{$weekday_name}";
					?>
					<div class="sugar-calendar-block__popover__calendar_selector__container__options__val">
						<input
							class="sugar-calendar-block__popover__calendar_selector__container__options__val__day"
							type="checkbox"
							id="<?php echo esc_attr( $input_attr ); ?>"
							name="<?php echo esc_attr( $input_attr ); ?>"
							value="<?php echo esc_attr( $weekday_number ); ?>"
						/>
						<label for="<?php echo esc_attr( $input_attr ); ?>">
							<?php echo esc_html( $weekday_name ); ?>
						</label>
					</div>
					<?php
				}
				?>
			</div>
		</div>

		<div class="sugar-calendar-block__popover__calendar_selector__container__time">
			<div class="sugar-calendar-block__popover__calendar_selector__container__heading">
				<?php esc_html_e( 'Time of Day', 'sugar-calendar-lite' ); ?>
			</div>
			<div class="sugar-calendar-block__popover__calendar_selector__container__options">
				<?php
				$time_of_day = [
					'all_day'   => esc_html__( 'All Day', 'sugar-calendar-lite' ),
					'morning'   => esc_html__( 'Morning', 'sugar-calendar-lite' ),
					'afternoon' => esc_html__( 'Afternoon', 'sugar-calendar-lite' ),
					'evening'   => esc_html__( 'Evening', 'sugar-calendar-lite' ),
					'night'     => esc_html__( 'Night', 'sugar-calendar-lite' ),
				];

				foreach ( $time_of_day as $tod_key => $tod_val ) {
					$tod_attr = "tod-{$context->get_block_id()}-{$tod_key}";
					?>
					<div class="sugar-calendar-block__popover__calendar_selector__container__options__val">
						<input
							class="sugar-calendar-block__popover__calendar_selector__container__options__val__time"
							type="checkbox"
							id="<?php echo esc_attr( $tod_attr ); ?>"
							name="<?php echo esc_attr( $tod_attr ); ?>"
							value="<?php echo esc_attr( $tod_key ); ?>"
						/>
						<label for="<?php echo esc_attr( $tod_attr ); ?>">
							<?php echo esc_html( $tod_val ); ?>
						</label>
					</div>
					<?php
				}
				?>
			</div>
		</div>
	</div>
</div>

<div class="sugar-calendar-block__popover__display_selector sugar-calendar-block__popover" role="popover">
	<div class="sugar-calendar-block__popover__display_selector__container">
		<div class="sugar-calendar-block__popover__display_selector__container__body">
			<?php
			foreach ( $context->get_display_options() as $display_key => $display_option ) {
				?>
				<div data-mode="<?php echo esc_attr( $display_key ); ?>" class="sugar-calendar-block__popover__display_selector__container__body__option">
					<?php echo esc_html( $display_option ); ?>
				</div>
				<?php
			}
			?>
		</div>
	</div>
</div>
