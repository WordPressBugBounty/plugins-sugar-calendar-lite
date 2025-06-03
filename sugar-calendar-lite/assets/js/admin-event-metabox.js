/* globals jQuery, sugar_calendar_admin_event_meta_box */
( function ( $, settings ) {

	'use strict';

	let SugarCalendar = window.SugarCalendar || {};
	SugarCalendar.Admin = SugarCalendar.Admin || {};

	SugarCalendar.Admin.EventMetabox = {

		/**
		 * Initialize.
		 *
		 * @since 3.0.0
		 */
		init: function ( settings ) {

			this.settings = settings;
			this.$el = $( '.sugar-calendar-event-details-metabox' );
			this.$sectionButtons = $( '.sugar-calendar-metabox__navigation__button', this.$el );
			this.$sections = $( '.sugar-calendar-metabox__section', this.$el );
			this.$startDate = $( '#start_date', this.$el );
			this.$startTimeHour = $( '#start_time_hour', this.$el );
			this.$startTimeMinute = $( '#start_time_minute', this.$el );
			this.$startTimeAmPm = $( '#start_time_am_pm', this.$el );
			this.$startTz = $( '#sugar-calendar_start_tz', this.$el );
			this.$endDate = $( '#end_date', this.$el );
			this.$endTimeHour = $( '#end_time_hour', this.$el );
			this.$endTimeMinute = $( '#end_time_minute', this.$el );
			this.$endTimeAmPm = $( '#end_time_am_pm', this.$el );
			this.$endTz = $( '#sugar-calendar_end_tz', this.$el );
			this.$allDay = $( '#all_day', this.$el );
			this.$timezones = $( '.sugar-calendar-metabox__field-row--time-zone, .event-time-zone, .event-time', this.$el );
			this.$submitButton = $( '#publish' );
			this.$tagsSelect = $( '.sugar-calendar-column-tags-form select' );
			// Bind events.
			this.bindEvents();

			// Initialize ChoiceJS dropdowns.
			this.initChoicesJS();

			// Initialize date pickers.
			this.initDatepickers();
		},

		bindEvents: function () {

			this.$sectionButtons.on( 'click', this.onSectionButtonClick.bind( this ) );
			this.$allDay.on( 'change', this.toggleTimezones.bind( this ) );

			this.$submitButton.on( 'click', this.validateDates.bind( this ) );
		},

		onSectionButtonClick: function ( e ) {

			const $button = $( e.currentTarget );
			const id = $button.attr( 'data-id' );
			const $section = this.$sections.filter( `[data-id=${id}]` );

			this.$sectionButtons.removeClass( 'selected' );
			this.$sections.removeClass( 'selected' );

			$button.addClass( 'selected' );
			$section.addClass( 'selected' );
		},

		initChoicesJS: function () {

			$( '.choicesjs-select', this.$el ).each( ( i, el ) => {
				new Choices( el, {
					itemSelectText: '',
					allowHTML: true,
				} );
			} );

			this.initChoicesJSForTags();
		},

		/**
		 * Initialize ChoicesJS for tags.
		 *
		 * @since 3.7.0
		 *
		 * @return {void}
		 */
		initChoicesJSForTags: function () {

			// Create configuration.
			const
				config = this.settings.choicesjs_config,
				select = this.$tagsSelect[ 0 ];

			// Set noResultsText to indicate users can add new tags.
			config.noResultsText = this.settings.strings.add_new_tag;

			const TagsSelect = new Choices(
				select,
				config
			);

			const currentValue = TagsSelect.getValue( true );

			TagsSelect
				.clearStore()
				.setChoices(
					this.settings.all_tags_choices,
					'value',
					'label',
					true
				)
				.setChoiceByValue( currentValue );

			$( select ).data( 'choicesjs', TagsSelect );

			// Bind event for adding custom tags on keyboard input
			$( select ).siblings( 'input' ).on( 'keydown', this.addCustomTagInput.bind( this ) );
		},

		initDatepickers: function () {

			$( '[data-datepicker]', this.$el ).datepicker( {
				dateFormat: 'yy-mm-dd',
				firstDay: this.settings.start_of_week,
				beforeShow: () => {
					$( '#ui-datepicker-div' )
						.removeClass( 'ui-datepicker' )
						.addClass( 'sugar-calendar-datepicker' );
				}
			} );

			// Set the end date min date to the start date.
			// Set the end date to the start date if it is empty.
			this.$startDate.on( 'change', () => {

				const // Get start and end date time for comparison.
					startDateTime = this.getEventDateTime(
						this.$startDate,
						this.$startTimeHour,
						this.$startTimeMinute,
						this.$startTimeAmPm,
						this.$startTz
					),
					endDateTime = this.getEventDateTime(
						this.$endDate,
						this.$endTimeHour,
						this.$endTimeMinute,
						this.$endTimeAmPm,
						this.$endTz
					);

				if (
					this.$endDate.val() === ''
					||
					endDateTime.isBefore( startDateTime )
				) {
					this.$endDate.datepicker( 'setDate', this.$startDate.val() );
				}
			} );

			// Time adjustment for start and end time hour fields.
			this.$startTimeHour.on( 'change', () => this.adjustTime( this.$startTimeHour, this.$endTimeHour, 1 ) );
			this.$endTimeHour.on( 'change', () => this.adjustTime( this.$endTimeHour, this.$startTimeHour, -1 ) );

			// Set end time minute to start time minute if it is empty.
			this.$startTimeMinute.on( 'change', () => {

				if ( this.$endTimeMinute.val() !== '' ) {
					return;
				}

				this.$endTimeMinute.val( this.$startTimeMinute.val() );
			} );

			// Set end time am/pm to start time am/pm if it is empty.
			this.$startTimeAmPm.on( 'change', () => {

				if ( this.$endTimeAmPm.val() !== '' ) {
					return;
				}

				this.$endTimeAmPm.val( this.$startTimeAmPm.val() );
			} );

			// Set the start date to the end date if it is empty.
			// Set the start date max date to the end date.
			this.$endDate.on( 'change', () => {

				const // Get start and end date time for comparison.
					startDateTime = this.getEventDateTime(
						this.$startDate,
						this.$startTimeHour,
						this.$startTimeMinute,
						this.$startTimeAmPm,
						this.$startTz
					),
					endDateTime = this.getEventDateTime(
						this.$endDate,
						this.$endTimeHour,
						this.$endTimeMinute,
						this.$endTimeAmPm,
						this.$endTz
					);

				if (
					this.$startDate.val() === ''
					||
					endDateTime.isBefore( startDateTime )
				) {
					this.$startDate.datepicker( 'setDate', this.$endDate.val() );
				}
			} );

			// Set start time minute to end time minute if it is empty.
			this.$endTimeMinute.on( 'change', () => {

				if ( this.$startTimeMinute.val() !== '' ) {
					return;
				}

				this.$startTimeMinute.val( this.$endTimeMinute.val() );
			} );

			// Set start time am/pm to end time am/pm if it is empty.
			this.$endTimeAmPm.on( 'change', () => {

				if ( this.$startTimeAmPm.val() !== '' ) {
					return;
				}

				this.$startTimeAmPm.val( this.$endTimeAmPm.val() );
			} );

			// Setup start and end date range.
			this.$endDate.datepicker( 'option', 'minDate', this.getDate( this.$startDate.val() ) );

			// Validate dates for block editor. Disable the submit button if the dates are invalid.
			if ( typeof( wp.blockEditor ) === 'object' ) {

				$.each( [
					this.startDate,
					this.$startTimeHour,
					this.$startTimeMinute,
					this.$startTimeAmPm,
					this.$endDate,
					this.$endTimeHour,
					this.$endTimeMinute,
					this.$endTimeAmPm

				], function ( i, e ) {

					$( e ).on( 'change', this.blockEditorDateValidation.bind( this ) );
				}.bind( this ) );
			}
		},

		getDate: function ( date ) {
			try {
				date = $.datepicker.parseDate( 'yy-mm-dd', date );
			} catch ( error ) {
				date = null;
			}

			return date;
		},

		toggleTimezones: function () {

			const checked = this.$allDay.prop( 'checked' );

			if ( checked ) {
				this.$timezones.hide();
			} else {
				this.$timezones.show();
			}
		},

		/**
		 * Adjust the target time based on the source time and increment.
		 *
		 * @since 3.3.0
		 *
		 * @param {jQuery} sourceElement
		 * @param {jQuery} targetElement
		 * @param {int} increment
		 *
		 * @return {void}
		 */
		adjustTime( sourceElement, targetElement, increment ) {

			// If the target time hour is already set, exit.
			if ( targetElement.val() !== '' ) {
				return;
			}

			const clockType = parseInt( sugar_calendar_admin_event_meta_box.clock_type, 10 );
			const sourceHour = parseInt( sourceElement.val(), 10 );

			// Calculate the new hour, adjusting by increment and clock type.
			let newHour = ( sourceHour + increment + clockType ) % clockType;

			// Correction for 12-hour format where 0 should be 12.
			if ( clockType === 12 && newHour === 0 ) {
				newHour = 12;
			}

			targetElement.val(
				newHour.toString().padStart( 2, '0' )
			);
		},

		/**
		 * Check if start and end date is valid.
		 *
		 * @since 3.3.0
		 *
		 * @return {boolean}
		 */
		isStartEndInvalid: function () {

			// If settings timezone type is multi but value of start and end timezone is different, return false.
			if (
				this.settings.timezone_type === 'multi'
				&&
				this.$startTz.val() !== this.$endTz.val()
			) {
				return false;
			}

			const // Get start and end date time for comparison.
				startDateTime = this.getEventDateTime(
					this.$startDate,
					this.$startTimeHour,
					this.$startTimeMinute,
					this.$startTimeAmPm,
					this.$startTz
				),
				endDateTime = this.getEventDateTime(
					this.$endDate,
					this.$endTimeHour,
					this.$endTimeMinute,
					this.$endTimeAmPm,
					this.$endTz.length > 0 ? this.$endTz : this.$startTz
				);

			return endDateTime.isBefore( startDateTime ) || endDateTime.isSame( startDateTime );
		},

		/**
		 * Validate the start and end date time.
		 * If end date time is before start date time,
		 * highlight the fields and prevent submission.
		 *
		 * @since 3.3.0
		 *
		 * @param {Event} e
		 *
		 * @return {void}
		 */
		validateDates: function ( e ) {

			// If end date time is before start date time, show error.
			// If end date time is the same as start date time, show error.
			// Works only if all day is not checked.
			if (
				this.$allDay.prop( 'checked' ) === false
				&&
				this.isStartEndInvalid()
			) {

				e.preventDefault();

				// Open the duration section.
				this.$sectionButtons.filter( '[data-id=duration]' ).click();

				// Add error class to the date time fields.
				this.$sections.filter( '[data-id=duration]' ).addClass( 'sugar-calendar-field-dates-invalid' );

				// Stop submission.
				return;
			}
		},

		/**
		 * Get event date and time currently set based on provided elements, with defaults.
		 *
		 * @since 3.3.0
		 *
		 * @param {jQuery} dateElement - The jQuery element for the date input (optional).
		 * @param {jQuery} hourElement - The jQuery element for the hour input (optional).
		 * @param {jQuery} minuteElement - The jQuery element for the minute input (optional).
		 * @param {jQuery} ampmElement - The jQuery element for the AM/PM input (optional for 24-hour format).
		 * @param {jQuery} tzElement - The jQuery element for the timezone input (optional).
		 *
		 * @return {Date} - A moment.js date object.
		 */
		getEventDateTime: function ( dateElement, hourElement, minuteElement, ampmElement, tzElement ) {

			const clockType = sugar_calendar_admin_event_meta_box.clock_type;
			const defaultDate = moment().format( 'YYYY-MM-DD' );

			// Check if elements are provided and get their values, or set defaults
			const date = ( dateElement && dateElement.val() ) || defaultDate;
			const hour = ( hourElement && hourElement.val() ) || '01';
			const minute = ( minuteElement && minuteElement.val() ) || '00';
			const ampm = ( clockType === '12' && ampmElement ) ? ( ampmElement.val() || 'AM' ) : '';
			const timezone = ( tzElement && tzElement.val() ) || '';

			// Return the moment.js date object.
			return this.createMomentObject( date, hour, minute, ampm, clockType, timezone );
		},

		/**
		 * Create a moment.js date object based on provided date and time values.
		 *
		 * @param {String} date - The date string in YYYY-MM-DD format.
		 * @param {String} hour - The hour string in 00-23 format.
		 * @param {String} minute - The minute string in 00-59 format.
		 * @param {Strimg} ampm  - The AM/PM string in AM/PM format.
		 * @param {String} clockType - The clock type in 12 or 24 value.
		 * @param {String} timezone - The timezone string.
		 *
		 * @return {Date} - A moment.js date object.
		 */
		createMomentObject( date, hour, minute, ampm, clockType, timezone ) {

			let // Convert to integer for calculations.
				hourInt = parseInt( hour, 10 ),
				minuteInt = parseInt( minute, 10 );

			// If clockType is 12-hour and am/pm is provided.
			if ( clockType === '12' && ampm ) {

				const ampmLower = ampm.toLowerCase();

				if ( ampmLower === "pm" && hourInt !== 12 ) {

					// Convert PM to 24-hour format.
					hourInt += 12;

				} else if ( ampmLower === "am" && hourInt === 12 ) {

					// Convert 12 AM to 00.
					hourInt = 0;
				}
			}

			// If clockType is 24-hour, ensure hour is in the valid range.
			if ( clockType === '24' ) {
				hourInt = Math.min( Math.max( hourInt, 0 ), 23 );
			}

			// Ensure minute is in valid range
			minuteInt = Math.min( Math.max( minuteInt, 0 ), 59 );

			// Construct the time string in 24-hour format
			const timeString = `${date} ${hourInt.toString().padStart( 2, '0' )}:${minuteInt.toString().padStart( 2, '0' )}`;

			// Create and return a moment.js object
			return moment.tz( timeString, "YYYY-MM-DD HH:mm", timezone );
		},

		/**
		 * Block editor date validation.
		 *
		 * @since 3.3.0
		 *
		 * @return {void}
		 */
		blockEditorDateValidation: function () {

			// Only work if start and end time fields are not empty.
			if (
				this.$startDate.val() === ''
				||
				this.$startTimeHour.val() === ''
				||
				this.$endDate.val() === ''
				||
				this.$endTimeHour.val() === ''
			) {
				return;
			}

			const // Get start and end date time for comparison.
				startDateTime = this.getEventDateTime(
					this.$startDate,
					this.$startTimeHour,
					this.$startTimeMinute,
					this.$startTimeAmPm,
					this.$startTz
				),
				endDateTime = this.getEventDateTime(
					this.$endDate,
					this.$endTimeHour,
					this.$endTimeMinute,
					this.$endTimeAmPm,
					this.$endTz
				),
				errorLockName = 'invalid-date-error';

			if ( this.isStartEndInvalid() ) {

				// Lock the editor.
				wp.data.dispatch( 'core/editor' ).lockPostSaving( errorLockName );

				// Show error message.
				wp.data.dispatch( 'core/notices' ).createNotice(
					'error',
					wp.i18n.__( 'End date and time cannot be before the start date and time.', 'sugar-calendar-lite' ),
					{ id: errorLockName, isDismissible: true }
				);

			} else if ( endDateTime.isAfter( startDateTime ) ) {

				// Unlock the editor.
				wp.data.dispatch( 'core/editor' ).unlockPostSaving( errorLockName );

				// Remove error message.
				wp.data.dispatch( 'core/notices' ).removeNotice( errorLockName );
			}
		},

		/**
		 * Add custom tag on keyboard input
		 *
		 * @since 3.7.0
		 *
		 * @param {object} event Event object.
		 */
		addCustomTagInput: function( event ) {

			// Only process for Enter or comma key.
			if ( [ 'Enter', ',' ].indexOf( event.key ) < 0 ) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();

			const $input = $( event.target );
			const $select = $input.closest( '.choices' ).find( 'select' );
			const choicesObj = $select.data( 'choicesjs' );

			// Verify we have a Choices instance and input value.
			if ( ! choicesObj || event.target.value.length === 0 ) {
				return;
			}

			// Get the tag label and clean it - add escaping for security.
			const tagLabel = _.escape( event.target.value.trim() );

			// Skip if empty.
			if ( tagLabel === '' ) {
				choicesObj.clearInput();
				return;
			}

			// Get existing tag labels more efficiently.
			const existingLabels = _.map( choicesObj.getValue(), 'label' ).map( ( label ) => {
				return label.toLowerCase().trim();
			} );

			// Skip if already exists.
			if ( existingLabels.indexOf( tagLabel.toLowerCase() ) >= 0 ) {
				choicesObj.clearInput();
				return;
			}

			// Check if tag exists in all available tags first.
			const existingTag = _.find( this.settings.all_tags_choices || [], {
				label: tagLabel,
			} );

			if ( existingTag && existingTag.value ) {
				// Use existing tag if found.
				choicesObj.setChoiceByValue( existingTag.value );
			} else {
				// Add as new tag.
				choicesObj.setChoices(
					[ {
						value: tagLabel,
						label: tagLabel,
						selected: true,
					} ],
					'value',
					'label',
					false
				);
			}

			// Clear the input field.
			choicesObj.clearInput();
		},
	};

	SugarCalendar.Admin.EventMetabox.init( settings );

	window.SugarCalendar = SugarCalendar;

} )( jQuery, sugar_calendar_admin_event_meta_box );
