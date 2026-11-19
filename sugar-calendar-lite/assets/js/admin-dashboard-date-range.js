/* global flatpickr */

/**
 * Dashboard date range control.
 *
 * A preset resolves to explicit bounds; `custom` opens flatpickr's range picker. Either way
 * app.updater fetches.
 *
 * @since 3.18.0
 *
 * @param {Object} document Document object.
 * @param {Object} window   Window object.
 * @param {jQuery} $        jQuery object.
 * @param {Object} app      Dashboard page app.
 *
 * @return {Object} Module API.
 */
export default function( document, window, $, app ) { // eslint-disable-line no-unused-vars
	const local = {
		$select: $(),
		$input: $(),
	};

	const dateRange = {

		/**
		 * Longest Custom range, in days, both ends counted: DateRange::MAX_RANGE_DAYS.
		 *
		 * @since 3.18.0
		 *
		 * @type {number}
		 */
		maxRangeDays: Number( window.sugar_calendar_admin_dashboard.max_range_days ),

		/**
		 * Text between the two dates of a range: DateRange::RANGE_SEPARATOR.
		 *
		 * @since 3.18.0
		 *
		 * @type {string}
		 */
		separator: window.sugar_calendar_admin_dashboard.range_separator,

		/**
		 * How the picker shows a date: DateRange::DISPLAY_FORMAT, which the server prints the
		 * loaded range in. Requests are built from the picked dates, never from this text.
		 *
		 * @since 3.18.0
		 *
		 * @type {string}
		 */
		displayFormat: window.sugar_calendar_admin_dashboard.display_format,

		/**
		 * CSS selectors.
		 *
		 * @since 3.18.0
		 *
		 * @type {Object}
		 */
		selectors: {
			root: '.sugar-calendar-dashboard-date-range',
			select: '.sugar-calendar-dashboard-date-range__select',
			input: '.sugar-calendar-dashboard-date-range__input',
		},

		/**
		 * Bind the control.
		 *
		 * @since 3.18.0
		 */
		init() {
			const $root = $( dateRange.selectors.root );

			if ( ! $root.length ) {
				return;
			}

			local.$select = $root.find( dateRange.selectors.select );
			local.$input = $root.find( dateRange.selectors.input );

			local.$select.on( 'change', dateRange.onSelectChange );

			// The input carries a value only when a custom range is already in effect.
			if ( local.$input.val() ) {
				dateRange.createFlatpickr( app.updater.getCurrentRange() );
			}
		},

		/**
		 * A preset fetches at once; `custom` reveals the picker and waits for two dates.
		 *
		 * @since 3.18.0
		 */
		onSelectChange() {
			const value = local.$select.val();

			if ( value === 'custom' ) {
				dateRange.setInputHidden( false );
				dateRange.createFlatpickr();
				local.$input.get( 0 )?._flatpickr?.open();

				return;
			}

			dateRange.setInputHidden( true );
			dateRange.clearPicker();

			// A keyboard change of the select leaves the calendar open, where two clicks would load a range the select doesn't name.
			local.$input.get( 0 )?._flatpickr?.close();

			app.updater.fetchStats( dateRange.presetRange( Number( value ) ) );
		},

		/**
		 * Show a range in the control without fetching it, for app.updater after the server
		 * rejects a newer one, and when the picker closes without a whole range.
		 *
		 * @since 3.18.0
		 *
		 * @param {string} range `"Y-m-d - Y-m-d"`.
		 */
		restore( range ) {
			if ( ! range || ! local.$select.length ) {
				return;
			}

			const preset = local.$select.find( 'option' ).toArray()
				.map( ( option ) => option.value )
				.find( ( value ) => value !== 'custom' && dateRange.presetRange( Number( value ) ) === range );

			if ( preset ) {
				local.$select.val( preset );
				dateRange.setInputHidden( true );
				dateRange.clearPicker();

				return;
			}

			local.$select.val( 'custom' );
			dateRange.setInputHidden( false );
			dateRange.createFlatpickr();

			const picker = local.$input.get( 0 )?._flatpickr;

			if ( ! picker ) {
				local.$input.val( range );

				return;
			}

			const [ from, to ] = range.split( dateRange.separator );

			// Without triggering onChange, so the span limit is reset here instead.
			picker.setDate( [ dateRange.parseDate( from ), dateRange.parseDate( to ) ], false );
			dateRange.limitSpan( picker.selectedDates, picker );
		},

		/**
		 * N days ending today on the site's calendar, matching DateRange::get_preset_range().
		 *
		 * @since 3.18.0
		 *
		 * @param {number} days Preset day count.
		 *
		 * @return {string} `"Y-m-d - Y-m-d"`.
		 */
		presetRange( days ) {
			const to = dateRange.parseDate( window.sugar_calendar_admin_dashboard.today );
			const from = new Date( to );

			from.setDate( from.getDate() - ( days - 1 ) );

			return [ dateRange.formatDate( from ), dateRange.formatDate( to ) ].join( dateRange.separator );
		},

		/**
		 * A local Date from `Y-m-d`, avoiding Date's UTC parse of ISO date strings.
		 *
		 * @since 3.18.0
		 *
		 * @param {string} ymd Date.
		 *
		 * @return {Date} Date at local midnight.
		 */
		parseDate( ymd ) {
			const [ year, month, day ] = ymd.split( '-' ).map( Number );

			return new Date( year, month - 1, day );
		},

		/**
		 * Format a Date as `Y-m-d` in local time.
		 *
		 * @since 3.18.0
		 *
		 * @param {Date} date Date.
		 *
		 * @return {string} `Y-m-d`.
		 */
		formatDate( date ) {
			const month = String( date.getMonth() + 1 ).padStart( 2, '0' );
			const day = String( date.getDate() ).padStart( 2, '0' );

			return `${ date.getFullYear() }-${ month }-${ day }`;
		},

		/**
		 * Show or hide the range input.
		 *
		 * @since 3.18.0
		 *
		 * @param {boolean} hidden Whether to hide it.
		 */
		setInputHidden( hidden ) {
			local.$input.prop( 'hidden', hidden );
		},

		/**
		 * Empty the picker, so Custom opened later starts from no dates, not the last custom range.
		 *
		 * @since 3.18.0
		 */
		clearPicker() {
			const picker = local.$input.get( 0 )?._flatpickr;

			if ( ! picker ) {
				return;
			}

			picker.clear( false );

			// The clear skips onChange, so the first date's span limit would outlive it.
			dateRange.limitSpan( [], picker );
		},

		/**
		 * Attach flatpickr once.
		 *
		 * The server already wrote the loaded range into the input in displayFormat, so the picker
		 * takes its dates from `range` instead of parsing that text, which may hold translated
		 * month names.
		 *
		 * @since 3.18.0
		 *
		 * @param {string} range `"Y-m-d - Y-m-d"` to start from; empty for none.
		 */
		createFlatpickr( range = '' ) {
			const input = local.$input.get( 0 );

			if ( ! input || input._flatpickr || typeof flatpickr === 'undefined' ) {
				return;
			}

			flatpickr( input, {
				mode: 'range',
				dateFormat: dateRange.displayFormat,
				defaultDate: range ? range.split( dateRange.separator ).map( dateRange.parseDate ) : null,
				maxDate: dateRange.parseDate( window.sugar_calendar_admin_dashboard.today ),
				locale: { ...window.sugar_calendar_admin_dashboard.picker_locale, rangeSeparator: dateRange.separator },
				onChange( selectedDates, dateStr, instance ) {
					dateRange.limitSpan( selectedDates, instance );
				},
				onClose( selectedDates ) {
					dateRange.onPickerClose( selectedDates );
				},
			} );
		},

		/**
		 * A whole new range fetches; anything less puts back the range the numbers come from.
		 *
		 * @since 3.18.0
		 *
		 * @param {Array} selectedDates Picked dates.
		 */
		onPickerClose( selectedDates ) {
			// Closed because a preset was chosen: its own fetch is under way, and a restore would undo the choice.
			if ( local.$select.val() !== 'custom' ) {
				return;
			}

			if ( selectedDates.length !== 2 ) {
				// Closed without a whole range: show the range the numbers still come from.
				dateRange.clearPicker();
				dateRange.restore( app.updater.getCurrentRange() );

				return;
			}

			// Built from the dates, not the input: flatpickr writes a same-day range as one date, which the server rejects.
			const range = selectedDates.map( ( date ) => dateRange.formatDate( date ) ).join( dateRange.separator );

			if ( app.updater.isCurrentRange( range ) ) {
				return;
			}

			app.updater.fetchStats( range );
		},

		/**
		 * Once the first date is picked, allow only dates that keep the range within maxRangeDays
		 * and not after today, so the picker cannot build a range DateRange::parse_range() rejects.
		 *
		 * @since 3.18.0
		 *
		 * @param {Array}  selectedDates Picked dates.
		 * @param {Object} instance      flatpickr instance.
		 */
		limitSpan( selectedDates, instance ) {
			const today = dateRange.parseDate( window.sugar_calendar_admin_dashboard.today );

			if ( selectedDates.length !== 1 ) {
				instance.set( 'minDate', null );
				instance.set( 'maxDate', today );

				return;
			}

			// The first date is one of the days, so the other end is at most maxRangeDays - 1 away.
			const reach = dateRange.maxRangeDays - 1;
			const min = new Date( selectedDates[ 0 ] );
			const max = new Date( selectedDates[ 0 ] );

			min.setDate( min.getDate() - reach );
			max.setDate( max.getDate() + reach );

			instance.set( 'minDate', min );
			instance.set( 'maxDate', max < today ? max : today );
		},
	};

	return dateRange;
}
