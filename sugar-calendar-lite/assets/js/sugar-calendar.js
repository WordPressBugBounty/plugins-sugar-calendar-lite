/* global sugar_calendar_obj, FloatingUIDOM */

'use strict';

var sugar_calendar = window.sugar_calendar || ( function ( document, window, $ ) {

	/**
	 * Add method to JS Array object that only adds unique items.
	 *
	 * @since 3.5.0
	 *
	 * @param {array} arr The array to add the item to.
	 * @param {mixed} item The item to add in the array.
	 *
	 * @return {Array}
	 */
	function pushUnique( arr, item ) {
		if ( ! arr.includes( item ) ) {
			arr.push( item );
		}

		return arr;
	}

	/**
	 * Hide all popovers inside a block.
	 *
	 * @since 3.0.0
	 *
	 * @param {jQuery} $mainContainer The main container of the calendar block.
	 */
	function hideAllPopovers( $mainContainer ) {

		// Hide all popovers.
		$mainContainer.find( '.sugar-calendar-block__popover' )
			.removeClass( 'sugar-calendar-block__controls__settings__btn_active' )
			.hide();

		// De-active all buttons.
		$mainContainer.find( '.sugar-calendar-block__controls__settings__btn' )
			.removeClass( 'sugar-calendar-block__controls__settings__btn_active' );

		$mainContainer.find( '.sugar-calendar-block__controls__left__date' )
			.removeClass( 'sugar-calendar-block__controls__settings__btn_active' );

		$( 'body' ).removeClass( 'sugar-calendar-block__popovers__active' );
	}

	/**
	 * FloatingUIDOM object cache.
	 *
	 * @since 3.0.0
	 *
	 * @type {FloatingUIDOM}
	 */
	let FloatingUIDOM = null;

	/**
	 * Constructor: Calendar events popover.
	 *
	 * @param {jQuery} $popover       The popover element.
	 * @param {jQuery} $mainContainer The main container of the calendar block.
	 */
	let CalendarPopoverEvents = function( $popover, $mainContainer ) {
		this.$popover = $popover;
		this.$mainContainer = $mainContainer;
	}

	/**
	 * Show the event popover.
	 *
	 * @since 3.0.0
	 *
	 * @param {Event} e The event object.
	 */
	CalendarPopoverEvents.prototype.show = function( e ) {

		let $target = $( e.target );
		let $eventDataContainer;

		if ( $target.hasClass( 'sugar-calendar-block__event-cell' ) ) {
			$eventDataContainer = $target;
		} else {
			$eventDataContainer = $( e.target ).parents( '.sugar-calendar-block__event-cell' );
		}

		let eventObjId = $eventDataContainer.data( 'eventobjid' );

		let $popoverImageContainer = this.$popover.find( '.sugar-calendar-block__popover__event__container__image' );
		let $popoverDescContainer = this.$popover.find( '.sugar-calendar-block__popover__event__container__content__description' );

		// Clear the current popover.
		$popoverImageContainer.hide();
		$popoverImageContainer.css( 'background-image', '' );
		$popoverDescContainer.text( '' );

		if ( eventObjId !== undefined ) {

			$popoverDescContainer.prepend( '<div class="sugar-calendar-block__loading sugar-calendar-block__loading--no-overlay"></div>' );

			$.post(
				sugar_calendar_obj.ajax_url,
				{
					action: 'sugar_calendar_event_popover',
					event_object_id: eventObjId,
					nonce: sugar_calendar_obj.nonce
				},
				function ( response ) {

					if ( response.success && response.data ) {

						if ( response.data.image ) {
							$popoverImageContainer.css( 'background-image', `url(${response.data.image})` );
							$popoverImageContainer.show();
						}

						let textContent = [];

						if ( response.data.description ) {
							// We parse the HTML to get the decoded text instead of HTML entities for commas, etc.
							let parsed = $.parseHTML( response.data.description.trim() );

							$.each( parsed, function( i, el ) {
								textContent.push( el.textContent );
							} );
						}

						$popoverDescContainer.html( '' );
						$popoverDescContainer.text( textContent.join( '' ) );
					}
				}
			);
		}

		// Get the event information.
		let title = $eventDataContainer.find( '.sugar-calendar-block__event-cell__title' ).text().trim();
		let eventTime = $eventDataContainer.find( '.sugar-calendar-block__event-cell__time' ).text().trim();

		// Setup the popover.
		let eventLink = this.$popover.find( '.sugar-calendar-block__popover__event__container__content__title__link' );
		eventLink.attr( 'href', $eventDataContainer.data( 'eventurl' ) );
		eventLink.text( title );

		// Handle the date.
		let visitorTZ = Intl.DateTimeFormat().resolvedOptions().timeZone,
			eventDate = '',
			eventDateObj = $eventDataContainer.data( 'daydate' );

		if ( typeof SCTimeZones !== 'undefined' && visitorTZ.length ) {
			eventDate = wp.date.dateI18n( SCTimezoneConvert.date_format, eventDateObj.start_date.datetime, visitorTZ );

			if ( eventDateObj.end_date ) {
				eventDate += ' - ' + wp.date.dateI18n( SCTimezoneConvert.date_format, eventDateObj.end_date.datetime, visitorTZ );
			}
		} else {
			eventDate = eventDateObj.start_date.value;

			if ( eventDateObj.end_date ) {
				eventDate += ' - ' + eventDateObj.end_date.value;
			}
		}

		this.$popover.find( '.sugar-calendar-block__popover__event__container__content__date' ).text( eventDate );
		this.$popover.find( '.sugar-calendar-block__popover__event__container__content__time' ).text( eventTime );

		// Setup the calendar information if available.
		let $calendarInfoContainer = this.$popover.find( '.sugar-calendar-block__popover__event__container__content__calendar' );
		// Clear the calendar info container.
		$calendarInfoContainer.html( '' );

		let calendarsInfo = $eventDataContainer.data( 'calendarsinfo' );

		if ( calendarsInfo !== undefined && calendarsInfo.calendars !== undefined ) {
			let calItems = [];

			calendarsInfo.calendars.forEach( ( cal ) => {
				calItems.push( `<div style="border-left: 2px solid ${cal.color ? cal.color : calendarsInfo.primary_event_color};" class="sugar-calendar-block__popover__event__container__content__calendar__item">${cal.name}</div>` );
			});

			$calendarInfoContainer.html( calItems.join( '' ) );
		}

		// Computer for the popover position.
		FloatingUIDOM.computePosition(
			$eventDataContainer[0],
			this.$popover[0],
			{
				placement: 'bottom-start',
				middleware: [
					FloatingUIDOM.offset( 10 ),
					FloatingUIDOM.flip(),
					FloatingUIDOM.shift()
				]
			}
		).
		then( ({x, y} ) => {

			Object.assign(
				this.$popover[0].style,
				{
					left: `${x}px`,
					top: `${y}px`,
				}
			)
		});

		hideAllPopovers( this.$mainContainer );
		this.$popover.show();

		$( 'body' ).addClass( 'sugar-calendar-block__popovers__active' );
	}

	/**
	 * Constructor: Settings popover events.
	 *
	 * @since 3.0.0
	 *
	 * @param {jQuery} $mainContainer The main container of the calendar block.
	 */
	let SettingsPopoverEvents = function ( $mainContainer ) {
		this.$mainContainer = $mainContainer;

		let settingPopovers = [
			{
				key: 'month_selector',
				popover_selector: '.sugar-calendar-block__popover__month_selector',
				button_selector: '.sugar-calendar-block__controls__left__date'
			},
			{
				key: 'calendar_selector',
				popover_selector: '.sugar-calendar-block__popover__calendar_selector',
				button_selector: '.sugar-calendar-block__controls__right__settings__btn'
			},
			{
				key: 'display_selector',
				popover_selector: '.sugar-calendar-block__popover__display_selector',
				button_selector: '.sugar-calendar-block__controls__right__view__btn'
			}
		];

		let that = this;

		settingPopovers.forEach( ( settingPopover ) => {

			let $button = $mainContainer.find( settingPopover.button_selector );

			$button.on( 'click', that.toggle.bind( that, $button, settingPopover.key, settingPopovers ) );
		});
	}

	/**
	 * Event callback for handling the toggle of the popover.
	 *
	 * @since 3.0.0
	 *
	 * @param {jQuery} $button         The button that toggles the popover.
	 * @param {string} key             The key of the popover.
	 * @param {Array}  settingPopovers Array containing all info of setting popovers.
	 */
	SettingsPopoverEvents.prototype.toggle = function( $button, key, settingPopovers ) {

		let settingPopover = settingPopovers.find( ( setting ) => setting.key === key );
		let $popover = this.$mainContainer.find( settingPopover.popover_selector );

		// We invoke `this.hideAll` on both because `$popover` will also be hidden.
		if ( $popover.is( ':visible' ) ) {
			hideAllPopovers( this.$mainContainer );
		} else {
			hideAllPopovers( this.$mainContainer );
			this.show( $button, $popover,  key );
		}
	}

	/**
	 * Show the popover.
	 *
	 * @since 3.0.0
	 *
	 * @param {jQuery} $button  The button element.
	 * @param {jQuery} $popover The popover to toggle.
	 * @param {string} key      The key of the popover.
	 */
	SettingsPopoverEvents.prototype.show = function ( $button, $popover, key ) {

		const isMobile = window.innerWidth < 768;

		let middlewares = [
			FloatingUIDOM.offset( 10 ),
			FloatingUIDOM.shift(),
		];

		// If the popover is happening on mobile,
		// scroll the button into view.
		// Otherwise, do nothing but flip the popover.
		if ( isMobile ) {

			$button[0].scrollIntoView( {
				behavior: 'smooth',
			} );
		} else {
			middlewares.push( FloatingUIDOM.flip() );
		}

		// Computer for the popover position.
		FloatingUIDOM.computePosition(
			$button[0],
			$popover[0],
			{
				placement: key === 'calendar_selector' ? 'bottom-end' : 'bottom-start',
				middleware: middlewares,
			}
		).
		then( ({x, y} ) => {

			Object.assign(
				$popover[0].style,
				{
					left: `${x}px`,
					top: `${y}px`,
				}
			)
		});

		$button.addClass( 'sugar-calendar-block__controls__settings__btn_active' );
		$popover.show();
		$( 'body' ).addClass( 'sugar-calendar-block__popovers__active' );
	}

	/**
	 * Constructor: Control events.
	 *
	 * @since 3.0.0
	 *
	 * @param {CalendarBlock} calendarBlock The calendar block object.
	 */
	let ControlEvents = function( calendarBlock ) {
		this.calendarBlock = calendarBlock;
	}

	/**
	 * Event callback for searching events.
	 *
	 * @since 3.0.0
	 *
	 * @param {Event} e The event object.
	 */
	ControlEvents.prototype.onSearch = function( e ) {

		if ( e.keyCode === 13 ) {
			this.calendarBlock.update();
			return;
		}

		if ( e.target.value.length > 0 ) {
			this.calendarBlock.$searchClear.show();
		} else {
			this.calendarBlock.$searchClear.hide();
		}
	}

	/**
	 * Event callback for clicking the search icon.
	 *
	 * @since 3.0.0
	 *
	 * @param {Event} e The event object.
	 */
	ControlEvents.prototype.onSearchClick = function( e ) {
		this.calendarBlock.update();
	}

	/**
	 * Event callback for clearing the search field.
	 *
	 * @since 3.0.0
	 *
	 * @param {Event} e
	 */
	ControlEvents.prototype.onClearSearch = function( e ) {
		this.calendarBlock.$searchContainer.val( '' );
		this.calendarBlock.$searchClear.hide();
		this.calendarBlock.update();
	}

	/**
	 * Event callback for navigating to a specific month and year.
	 *
	 * @since 3.0.0
	 *
	 * @param {Event} e The event object.
	 */
	ControlEvents.prototype.goToMonth = function( e ) {

		this.calendarBlock.$formContainer.find( 'input[name="sc_month"]' ).val( parseInt( e.target.dataset.month ) );

		this.calendarBlock.update();
	}

	/**
	 * Event callback when the previous button is clicked.
	 *
	 * This event will navigate to the previous day, week, or month depending on the current display.
	 *
	 * @since 3.0.0
	 */
	ControlEvents.prototype.goToPrevious = function() {

		switch ( this.calendarBlock.getDisplay() ) {
			case 'day':
				this.calendarBlock.update(
					false,
					'previous_day'
				);
				break;

			case 'week':
				this.calendarBlock.update(
					false,
					'previous_week'
				);
				break;

			case 'month':
				this.calendarBlock.update(
					false,
					'previous_month'
				);
				break;
		}
	}

	/**
	 * Event callback when the next button is clicked.
	 *
	 * This event will navigate to the next day, week, or month depending on the current display.
	 *
	 * @since 3.0.0
	 */
	ControlEvents.prototype.goToNext = function() {
		switch ( this.calendarBlock.getDisplay() ) {
			case 'day':
				this.calendarBlock.update(
					false,
					'next_day'
				);
				break;

			case 'week':
				this.calendarBlock.update(
					false,
					'next_week'
				);
				break;

			case 'month':
				this.calendarBlock.update(
					false,
					'next_month'
				);
				break;
		}
	}

	/**
	 * Event callback for selecting a calendar to display.
	 *
	 * @since 3.0.0
	 */
	ControlEvents.prototype.onSelectCalendar = function() {

		this.calendarBlock.update();
	}

	/**
	 * Event callback for selecting a tag to display.
	 *
	 * @since 3.7.0
	 */
	ControlEvents.prototype.onSelectTag = function() {

		this.calendarBlock.update();
	}

	/**
	 * Event callback for selecting a venue to display.
	 *
	 * @since 3.7.0
	 */
	ControlEvents.prototype.onSelectVenue = function() {

		this.calendarBlock.update();
	}

	/**
	 * Event callback for selecting a speaker to display.
	 *
	 * @since 3.7.0
	 */
	ControlEvents.prototype.onSelectSpeaker = function() {

		this.calendarBlock.update();
	}

	/**
	 * Event callback for "This Month", "This Week", or "Today" button.
	 *
	 * @since 3.0.0
	 */
	ControlEvents.prototype.onSelectCurrent = function() {

		this.calendarBlock.$formContainer.find( 'input[name="sc_month"]' ).val( this.calendarBlock.$mainContainer.data( 'ogmonth' ) );
		this.calendarBlock.$formContainer.find( 'input[name="sc_year"]' ).val( this.calendarBlock.$mainContainer.data( 'ogyear' ) );
		this.calendarBlock.$formContainer.find( 'input[name="sc_day"]' ).val( this.calendarBlock.$mainContainer.data( 'ogday' ) );

		this.calendarBlock.update();
	}

	/**
	 * Event callback for changing the display mode of the calendar block.
	 *
	 * @since 3.0.0
	 *
	 * @param {Event} e The event object.
	 */
	ControlEvents.prototype.onChangeDisplay = function( e ) {

		let $el = $( e.target ),
			display = $el.text().trim(),
			displayMode = $el.data( 'mode' );

		if ( displayMode === this.calendarBlock.getDisplay() ) {
			return;
		}

		this.calendarBlock.$mainContainer.removeClass( `sugar-calendar-block__${this.calendarBlock.getDisplay()}-view` );
		this.calendarBlock.$mainContainer.addClass( `sugar-calendar-block__${displayMode}-view` );

		this.calendarBlock.$formContainer.find( 'input[name="sc_display"]' ).val( displayMode );
		this.calendarBlock.update( true );
		this.calendarBlock.$mainContainer.find( '.sugar-calendar-block__controls__right__view__btn span' ).text( display );
	}

	/**
	 * Constructor: Calendar block.
	 *
	 * @since 3.0.0
	 *
	 * @param {jQuery} $mainContainer The main container of the calendar block.
	 */
	let CalendarBlock = function( $mainContainer ) {

		this.$mainContainer = $mainContainer;
		this.$formContainer = $mainContainer.find( '.sugar-calendar-block-settings' );
		this.$mobileListContainer = $mainContainer.find( '.sugar-calendar-block__mobile_event_list' );
		this.id = this.$formContainer.find( 'input[name="sc_calendar_id"]' ).val();
		this.$searchContainer = $mainContainer.find( '.sugar-calendar-block__controls__right__search__field' );
		this.$searchClear = $mainContainer.find( '.sugar-calendar-block__controls__right__search__clear' );
		this.$timeOfDayContainer = $mainContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__time' );
		this.$daysOfWeekContainer = $mainContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__days' );
		this.$datePicker = $mainContainer.find( '.sugar-calendar-block__controls__datepicker' );

		if ( this.id !== undefined && this.id.length > 0 ) {
			this.initPopovers();
			this.initControls();
			this.initDatePicker();
		}

		if ( parseInt( this.$formContainer.find( 'input[name="sc_visitor_tz_convert"]' ).val() ) === 1 ) {
			this.update();
		}
	}

	/**
	 * Initialize the Datepicker.
	 *
	 * @since 3.0.0
	 * @since 3.7.0 Added datepicker i18n.
	 */
	CalendarBlock.prototype.initDatePicker = function() {

		if ( this.$datePicker !== undefined ) {
			this.$datePicker.scbootdatepicker( 'destroy' );
		}

		let minViewMode = 0;

		if ( this.getDisplay() === 'month' ) {
			minViewMode = 1;
		}

		// Setup scbootdatepicker i18n.
		this.setupDatepickerI18n();

		this.$datePicker.scbootdatepicker({
			minViewMode: minViewMode,
			maxViewMode: 2,
			templates: {
				leftArrow: '<svg width="6" height="11" viewBox="0 0 6 11" fill="none" xmlns="http://www.w3.org/2000/svg">' +
					'<path d="M5.41406 10.6094C5.29688 10.7266 5.13281 10.7266 5.01562 10.6094L0.09375 5.71094C0 5.59375 0 5.42969 0.09375 5.3125L5.01562 0.414062C5.13281 0.296875 5.29688 0.296875 5.41406 0.414062L5.88281 0.859375C5.97656 0.976562 5.97656 1.16406 5.88281 1.25781L1.64062 5.5L5.88281 9.76562C5.97656 9.85938 5.97656 10.0469 5.88281 10.1641L5.41406 10.6094Z" fill="currentColor"/>' +
					'</svg>',
				rightArrow: '<svg width="6" height="11" viewBox="0 0 6 11" fill="none" xmlns="http://www.w3.org/2000/svg">' +
					'<path d="M0.5625 0.414062C0.679688 0.296875 0.84375 0.296875 0.960938 0.414062L5.88281 5.3125C5.97656 5.42969 5.97656 5.59375 5.88281 5.71094L0.960938 10.6094C0.84375 10.7266 0.679688 10.7266 0.5625 10.6094L0.09375 10.1641C0 10.0469 0 9.85938 0.09375 9.76562L4.33594 5.5L0.09375 1.25781C0 1.16406 0 0.976562 0.09375 0.859375L0.5625 0.414062Z" fill="currentColor"/>' +
					'</svg>'
			},
			weekStart: sugar_calendar_obj.settings.sow,
			language: sugar_calendar_obj.settings.locale
		});

		let $year = this.$formContainer.find( 'input[name="sc_year"]' ),
			$month = this.$formContainer.find( 'input[name="sc_month"]' ),
			$day = this.$formContainer.find( 'input[name="sc_day"]' );

		this.$datePicker.scbootdatepicker( 'update', new Date( $year.val(), $month.val() - 1, $day.val() ) );

		this.$datePicker.on( 'changeDate', ( e ) => {

			$year.val( e.date.getFullYear() );
			$month.val( e.date.getMonth() + 1 );

			if ( this.getDisplay() !== 'month' ) {
				$day.val( e.date.getDate() );
			}

			this.update();
		} );
	}

	/**
	 * Initialize the popovers.
	 *
	 * @since 3.0.0
	 */
	CalendarBlock.prototype.initPopovers = function() {

		let $mainContainer = this.$mainContainer;

		new SettingsPopoverEvents( $mainContainer );

		// Setup the event popover.
		let $calendarPopover = $mainContainer.find( '.sugar-calendar-block__popover__event' );

		let calPop = new CalendarPopoverEvents( $calendarPopover, $mainContainer );

		if ( window.innerWidth >= 768 ) {
			$mainContainer
				.on( 'click', '.sugar-calendar-block__event-cell', calPop.show.bind( calPop ) );
		} else {
			// Week view header click on mobile.
			$mainContainer
				.on( 'click', '.sugar-calendar-block__calendar-week__header__cell', function( e ) {
					let $target = $( e.target );

					if ( ! $target.hasClass( 'sugar-calendar-block__calendar-week__header__cell' ) ) {
						$target = $target.parents( '.sugar-calendar-block__calendar-week__header__cell' );
					}

					if (
						$target.hasClass( 'sugar-calendar-block__calendar-week__header__cell--active' )
						|| $target.data( 'weekdaynum' ) === undefined
					) {
						return;
					}

					// De-active the current active day.
					$mainContainer.find( '.sugar-calendar-block__calendar-week__header__cell--active' )
						.removeClass( 'sugar-calendar-block__calendar-week__header__cell--active' );
					$mainContainer.find( '.sugar-calendar-block__calendar-week__time-grid__day-col--active' )
						.removeClass( 'sugar-calendar-block__calendar-week__time-grid__day-col--active' );
					$mainContainer.find( '.sugar-calendar-block__calendar-week__event-slot--all-day--active')
						.removeClass( 'sugar-calendar-block__calendar-week__event-slot--all-day--active' );

					// Make the new clicked day active.
					$target.addClass( 'sugar-calendar-block__calendar-week__header__cell--active' );
					$mainContainer
						.find( `.sugar-calendar-block__calendar-week__event-slot--all-day--${$target.data( 'weekdaynum' )}` )
						.addClass( 'sugar-calendar-block__calendar-week__event-slot--all-day--active' );
					$mainContainer
						.find( `.sugar-calendar-block__calendar-week__time-grid__day-col-${$target.data( 'weekdaynum' )}` )
						.addClass( 'sugar-calendar-block__calendar-week__time-grid__day-col--active' );
				} );
		}
	}

	/**
	 * Initialize the controls.
	 *
	 * @since 3.0.0
	 * @since 3.5.0 Add venue filter trigger.
	 * @since 3.7.0 Add tag filter trigger.
	 */
	CalendarBlock.prototype.initControls = function() {

		this.controlEvents = new ControlEvents( this );

		// Search.
		this.$searchContainer
			.on( 'keyup', this.controlEvents.onSearch.bind( this.controlEvents ) );

		// Clear search.
		this.$searchClear
			.on( 'click', this.controlEvents.onClearSearch.bind( this.controlEvents ) );

		this.$mainContainer.find( '.sugar-calendar-block__controls__right__search__icon' )
			.on( 'click', this.controlEvents.onSearchClick.bind( this.controlEvents ) );

		// Specific month navigation.
		this.$mainContainer.find( '.sugar-calendar-block__popover__month_selector__container__body__month' )
			.on( 'click', this.controlEvents.goToMonth.bind( this.controlEvents ) );

		// Prev/Next navigation.
		this.$mainContainer.find( '.sugar-calendar-block__controls__left__pagination__prev' )
			.on( 'click', this.controlEvents.goToPrevious.bind( this.controlEvents ) );

		this.$mainContainer.find( '.sugar-calendar-block__controls__left__pagination__next' )
			.on( 'click', this.controlEvents.goToNext.bind( this.controlEvents ) );

		// Current navigation.
		this.$mainContainer.find( '.sugar-calendar-block__controls__left__pagination__current' )
			.on( 'click', this.controlEvents.onSelectCurrent.bind( this.controlEvents ) );

		// Calendars selector.
		this.$mainContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__cal' )
			.on( 'change', this.controlEvents.onSelectCalendar.bind( this.controlEvents ) );

		// Venues selector.
		this.$mainContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__venue' )
			.on( 'change', this.controlEvents.onSelectVenue.bind( this.controlEvents ) );

		// Speaker selector.
		this.$mainContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__speaker' )
			.on( 'click', this.controlEvents.onSelectSpeaker.bind( this.controlEvents ) );

		// Tags selector.
		this.$mainContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__tag' )
			.on( 'change', this.controlEvents.onSelectTag.bind( this.controlEvents ) );

		// Day selector.
		this.$mainContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__day' )
			.on( 'change', this.displayEvents.bind( this ) );

		// Time selector.
		this.$mainContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__time' )
			.on( 'change', this.displayEvents.bind( this ) );

		// View selector.
		this.$mainContainer.find( '.sugar-calendar-block__popover__display_selector__container__body__option' )
			.on( 'click', this.controlEvents.onChangeDisplay.bind( this.controlEvents ) );

		if ( window.innerWidth < 768 ) {
			this.$mainContainer
				.on( 'click', '.sugar-calendar-block__calendar-month__body__day', this.showMobileEvents.bind( this ) );

			this.$mainContainer
				.on( 'click', '.sugar-calendar-block__mobile_event_list .sugar-calendar-block__event-cell', this.onMobileEventCellClicked.bind( this ) );

			this.$mainContainer
				.on( 'click', '.sugar-calendar-block__calendar-week__event-slot .sugar-calendar-block__event-cell', this.onMobileEventCellClicked.bind( this ) );

			this.$mainContainer
				.on( 'click', '.sugar-calendar-block__calendar-day .sugar-calendar-block__event-cell', this.onMobileEventCellClicked.bind( this ) );
		}
	}

	/**
	 * Event fired when event cell is clicked on mobile.
	 *
	 * This redirects the user to the event URL.
	 *
	 * @since 3.0.0
	 *
	 * @param {Event} e The event object.
	 */
	CalendarBlock.prototype.onMobileEventCellClicked = function( e ) {
		let $target = $( e.target );

		if ( ! $target.hasClass( 'sugar-calendar-block__event-cell' ) ) {
			$target = $target.parents( '.sugar-calendar-block__event-cell' );
		}

		if ( ! $target.data( 'eventurl' ) ) {
			return;
		}

		window.location.href = $target.data( 'eventurl' );
	}

	/**
	 * Get the calendar IDs that are checked.
	 *
	 * @since 3.0.0
	 *
	 * @return {string[]}
	 */
	CalendarBlock.prototype.getCalendarIds = function() {
		let calendarIds = [];

		this.$mainContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__cal:checked' )
			.each( function() {
				calendarIds.push( $( this ).val() );
			});

		return calendarIds;
	}

	/**
	 * Get the venue IDs that are checked.
	 *
	 * @since 3.5.0
	 *
	 * @return {string[]}
	 */
	CalendarBlock.prototype.getVenuesIds = function() {
		let venueIds = [];

		this.$mainContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__venue:checked' )
			.each( function() {
				venueIds.push( $( this ).val() );
			});

		return venueIds;
	}

	/**
	 * Get the speaker IDs that are checked.
	 *
	 * @since 3.7.0
	 *
	 * @return {string[]}
	 */
	CalendarBlock.prototype.getSpeakerIds = function() {
		let speakerIds = [];

		this.$mainContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__speaker:checked' )
			.each( function() {
				speakerIds.push( $( this ).val() );
			});

		return speakerIds;
	}

	/**
	 * Get the calendar IDs that are filtered from block settings.
	 *
	 * @since 3.2.0
	 *
	 * @return {string[]}
	 */
	CalendarBlock.prototype.getCalendarsFilter = function() {
		const $calendarFilters = this.$formContainer.find( 'input[name="sc_calendars_filter"]' );

		if ( $calendarFilters.length <= 0 ) {
			return [];
		}

		const calendarFilters = $calendarFilters.val();

		if ( calendarFilters.length <= 0 ) {
			return [];
		}

		return calendarFilters.split( ',' );
	}

	/**
	 * Get the venue IDs that are filtered from block settings.
	 *
	 * @since 3.5.0
	 *
	 * @return {string[]}
	 */
	CalendarBlock.prototype.getVenuesFilter = function() {
		const $venueFilters = this.$formContainer.find( 'input[name="sc_venues_filter"]' );

		if ( $venueFilters.length <= 0 ) {
			return [];
		}

		const venueFilters = $venueFilters.val();

		if ( venueFilters.length <= 0 ) {
			return [];
		}

		return venueFilters.split( ',' );
	}

	/**
	 * Get the speaker IDs that are filtered from block settings.
	 *
	 * @since 3.7.0
	 *
	 * @return {string[]}
	 */
	CalendarBlock.prototype.getSpeakersFilter = function() {
		const $speakerFilters = this.$formContainer.find( 'input[name="sc_speakers_filter"]' );

		if ( $speakerFilters.length <= 0 ) {
			return [];
		}

		const speakerFilters = $speakerFilters.val();

		if ( speakerFilters.length <= 0 ) {
			return [];
		}

		return speakerFilters.split( ',' );
	}

	/**
	 * Get the tag IDs that are checked.
	 *
	 * @since 3.7.0
	 *
	 * @return {string[]}
	 */
	CalendarBlock.prototype.getTagIds = function() {
		let tagIds = [];

		this.$mainContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__tag:checked' )
			.each( function() {
				tagIds.push( $( this ).val() );
			});

		return tagIds;
	}

	/**
	 * Get the tag IDs that are filtered from block settings.
	 *
	 * @since 3.7.0
	 *
	 * @return {string[]}
	 */
	CalendarBlock.prototype.getTagsFilter = function() {
		const $tagFilters = this.$formContainer.find( 'input[name="sc_tags_filter"]' );

		if ( $tagFilters.length <= 0 ) {
			return [];
		}

		const tagFilters = $tagFilters.val();

		if ( tagFilters.length <= 0 ) {
			return [];
		}

		return tagFilters.split( ',' );
	}

	/**
	 * Get the display mode.
	 *
	 * @since 3.0.0
	 *
	 * @return {string}
	 */
	CalendarBlock.prototype.getDisplay = function() {
		return this.$formContainer.find( 'input[name="sc_display"]' ).val();
	}

	/**
	 * Update the calendar block.
	 *
	 * @since 3.0.0
	 * @since 3.5.0 Add accessibility label for previous and next left control buttons.
	 * @since 3.5.0 Add `venues` and `venuesFilter` parameters.
	 * @since 3.7.0 Add `tags` and `tagsFilter` parameters.
	 *
	 * @param {boolean} updateDisplay Whether the request is updating the display mode.
	 * @param {string} action         The action to perform, e.g., next_day, previous_day, etc.
	 */
	CalendarBlock.prototype.update = function( updateDisplay = false, action = '' ) {

		hideAllPopovers( this.$mainContainer );

		// Add loading state.
		let $containerToPutBody = this.$mainContainer.find( '.sugar-calendar-block__base-container' );

		$containerToPutBody.addClass( 'sugar-calendar-block__loading-state' );
		$containerToPutBody.prepend( '<div class="sugar-calendar-block__base-container__overlay"><div class="sugar-calendar-block__loading"></div></div>' );

		let that = this;
		let calendarBlock = {
			id: this.id,
			attributes: this.$mainContainer.data( 'attributes' ),
			calendars: this.getCalendarIds(),
			venues: this.getVenuesIds(),
			speakers: this.getSpeakerIds(),
			calendarsFilter: this.getCalendarsFilter(),
			venuesFilter: this.getVenuesFilter(),
			speakersFilter: this.getSpeakersFilter(),
			tags: this.getTagIds(),
			tagsFilter: this.getTagsFilter(),
			day: parseInt( this.$formContainer.find( 'input[name="sc_day"]' ).val() ),
			month: parseInt( this.$formContainer.find( 'input[name="sc_month"]' ).val() ),
			year: parseInt( this.$formContainer.find( 'input[name="sc_year"]' ).val() ),
			search: this.$searchContainer.val(),
			accentColor: this.$mainContainer.data( 'accentcolor' ) ? this.$mainContainer.data( 'accentcolor' ) : '',
			display: this.getDisplay(),
			visitor_tz_convert: parseInt( this.$formContainer.find( 'input[name="sc_visitor_tz_convert"]' ).val() ),
			visitor_tz: Intl.DateTimeFormat().resolvedOptions().timeZone,
			updateDisplay: updateDisplay,
			action: action
		};

		$.post(
			sugar_calendar_obj.ajax_url,
			{
				action: 'sugar_calendar_block_update',
				calendar_block: calendarBlock,
				nonce: sugar_calendar_obj.nonce
			},
			function( response ) {

				if ( response.success ) {

					// Update the calendar info.
					that.$formContainer.find( 'input[name="sc_day"]' ).val( response.data.date.day );
					that.$formContainer.find( 'input[name="sc_month"]' ).val( response.data.date.month );
					that.$formContainer.find( 'input[name="sc_year"]' ).val( response.data.date.year );

					// Update the left control labels.
					that.$mainContainer
						.find( '.sugar-calendar-block__controls__left__pagination__prev' )
						.attr( 'aria-label', response.data.control_labels.prev );
					that.$mainContainer
						.find( '.sugar-calendar-block__controls__left__pagination__next' )
						.attr( 'aria-label', response.data.control_labels.next );

					let navToCurrentLabel = '';

					switch ( that.getDisplay() ) {
						case 'day':
							that.$mainContainer.find( '.sugar-calendar-block__view-heading' ).text( response.data.heading );
							that.$mainContainer.find( '.sugar-calendar-block__view-heading-mobile' ).text( response.data.heading_mobile );
							that.$mainContainer.find( '.sugar-calendar-block__view-heading--year' ).hide();

							if ( response.data.is_update_display ) {
								that.$mainContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__days' ).hide();
								navToCurrentLabel = sugar_calendar_obj.strings.today;
							}
							break;
						case 'week':
							that.$mainContainer.find( '.sugar-calendar-block__view-heading' ).text( response.data.heading );
							that.$mainContainer.find( '.sugar-calendar-block__view-heading-mobile' ).text( response.data.heading_mobile );
							that.$mainContainer.find( '.sugar-calendar-block__view-heading--year' ).hide();

							if ( response.data.is_update_display ) {
								that.$mainContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__days' ).show();
								that.$mobileListContainer.hide();
								navToCurrentLabel = sugar_calendar_obj.strings.this_week;
							}
							break;
						default:
							that.$mainContainer.find( '.sugar-calendar-block__view-heading' ).text( response.data.heading );
							that.$mainContainer.find( '.sugar-calendar-block__view-heading-mobile' ).text( response.data.heading_mobile );
							that.$mainContainer.find( '.sugar-calendar-block__view-heading--year' ).text( response.data.date.year );
							that.$mainContainer.find( '.sugar-calendar-block__view-heading--year' ).show();

							if ( response.data.is_update_display ) {
								that.$mainContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__days' ).show();
								that.$mobileListContainer.show();
								navToCurrentLabel = sugar_calendar_obj.strings.this_month;
							} else {
								// In this case, we have to find and delete the overlay element.
								that.$mainContainer.find( '.sugar-calendar-block__base-container__overlay' ).remove();

								// Remove the loading state first before overwriting `$containerToPutBody`.
								$containerToPutBody.removeClass( 'sugar-calendar-block__loading-state' );

								$containerToPutBody = that.$mainContainer.find( '.sugar-calendar-block__calendar-month__body' );
							}
							break;
					}

					// Update the "This Month" label depending on the display mode.
					if ( navToCurrentLabel !== '' ) {
						that.$mainContainer.find( '.sugar-calendar-block__controls__left__pagination__current' ).text( navToCurrentLabel );
					}

					// Replace the calendar block content.
					$containerToPutBody.html( response.data.body );

					$containerToPutBody.removeClass( 'sugar-calendar-block__loading-state' );

					that.displayEvents();

					// Re-init the datepicker if we updated the display mode.
					if ( response.data.is_update_display ) {
						that.initDatePicker();
					} else {
						that.$datePicker.scbootdatepicker( 'update', new Date( response.data.date.year, response.data.date.month - 1, response.data.date.day ) );
					}

					if ( typeof SCTimeZones !== 'undefined' ) {
						SCTimeZones.convertEventsTime();
					}
				}
			}
		);
	}

	/**
	 * Get the checked time of day.
	 *
	 * @since 3.0.0
	 *
	 * @return {Array}
	 */
	CalendarBlock.prototype.getTimeOfDay = function() {
		return this.$timeOfDayContainer
			.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__time:checked' )
			.map( ( index, el ) => el.value ).get();
	}

	/**
	 * Get the checked days of the week.
	 *
	 * @since 3.0.0
	 *
	 * @return {Array}
	 */
	CalendarBlock.prototype.getDaysOfWeek = function() {
		return this.$daysOfWeekContainer
			.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__day:checked' )
			.map( ( index, el ) => el.value ).get();
	}

	/**
	 * Event callback for showing the events on mobile.
	 *
	 * @since 3.0.0
	 *
	 * @param {Event} e The event object.
	 */
	CalendarBlock.prototype.showMobileEvents = function( e ) {

		// Find the mobile events container.
		let $mobileEventsDateContainer = this.$mobileListContainer.find( '.sugar-calendar-block__mobile_event_list__date' );
		let $mobileEventsListContainer = this.$mobileListContainer.find( '.sugar-calendar-block__mobile_event_list__events_container' );

		$mobileEventsDateContainer.html( '' );
		$mobileEventsListContainer.html( '' );

		let $target = $( e.target );
		let $cellContainer;

		if ( $target.hasClass( 'sugar-calendar-block__calendar-month__body__day' ) ) {
			$cellContainer = $target;
		} else {
			$cellContainer = $( e.target ).parents( '.sugar-calendar-block__calendar-month__body__day' );
		}

		// Find the events container.
		let $eventsContainer = $cellContainer.find( '.sugar-calendar-block__calendar-month__body__day__events-container' );

		let $eventMonth = $cellContainer.data( 'offsetmonth' );

		if ( $eventMonth === undefined || $eventMonth.length <= 0 ) {
			$eventMonth = this.$mainContainer.find( '.sugar-calendar-block__view-heading' ).text();
		}

		let eventsDate = sugar_calendar_obj.strings.events_on;
		let dayNumber = $cellContainer.find( '.sugar-calendar-block__calendar-month__body__day__number' ).text().trim();
		let monthDate = eventsDate.replace( '[Month Date]', $eventMonth );

		if ( dayNumber ) {
			monthDate = `${monthDate} ${dayNumber}`;
		}

		$mobileEventsDateContainer.text( monthDate );
		$mobileEventsListContainer.html( $eventsContainer.clone() );

		this.$mobileListContainer.show();
	}

	/**
	 * Event callback for displaying the events.
	 *
	 * This method will filter the events that should be displayed on the calendar block.
	 *
	 * @since 3.0.0
	 */
	CalendarBlock.prototype.displayEvents = function() {

		if ( this.getDisplay() === 'week' ) {
			// Loop through each of the week day cells.
			this.displayEventsOnWeekDisplay();
		} else if ( this.getDisplay() === 'day' ) {
			this.displayEventsOnDayDisplay();
		} else {
			let time_of_day = this.getTimeOfDay();
			let days_of_week = this.getDaysOfWeek();
			// Contains the event IDs that overflows to a checked day of the week.
			let overflowEventIds = [];

			let $calendarMonth = this.$mainContainer.find( '.sugar-calendar-block__calendar-month' );

			// Loop through each of the day cells.
			$calendarMonth.find( '.sugar-calendar-block__calendar-month__body__day__events-container' )
				.each( ( index, cell ) => {
					let $cell = $( cell );

					// Let's check all the events inside the `$cell` and filter them by time of day.
					$cell.find( '.sugar-calendar-block__event-cell' )
						.each( ( idx, evt ) => {
							let $evt = $( evt );

							if (
								(
									days_of_week.length === 0 // If nothing is checked, then we'll show all.
									||
									$( days_of_week ).filter( [ $cell.data( 'weekday' ).toString() ] ).length > 0
								)
								&&
								(
									time_of_day.length === 0 // If nothing is checked, then we'll show all.
									||
									$( time_of_day ).filter( $evt.data( 'daydiv' ) ).length > 0
								)
							) {
								$evt.removeClass( 'sugar-calendar-block__calendar-month__cell-hide' );
								overflowEventIds.push( $evt.data( 'eventid' ) );
							} else {
								$evt.addClass( 'sugar-calendar-block__calendar-month__cell-hide' );

								// Hide spacer as well.
								$calendarMonth.find( `.sugar-calendar-block__calendar-month__spacer-eventid-${$evt.data( 'eventid' )}` )
									.addClass( 'sugar-calendar-block__calendar-month__cell-hide' );
							}
						} );
				} );

			overflowEventIds.forEach( ( eventId ) => {
				// Display the multi-day events
				$calendarMonth
					.find( `.sugar-calendar-block__calendar-month__body__day__events-container__event-id-${eventId}` )
					.removeClass( 'sugar-calendar-block__calendar-month__cell-hide' );

				// Display the spacer as well.
				$calendarMonth
					.find( `.sugar-calendar-block__calendar-month__spacer-eventid-${eventId}` )
					.removeClass( 'sugar-calendar-block__calendar-month__cell-hide' );
			} );
		}
	}

	CalendarBlock.prototype.filterDisplayWeekView = function(
		col_class,
		event_class,
		days_of_week,
		time_of_day,
		should_return_events_ids = false
	) {

		let event_ids_to_display = [];

		this.$mainContainer.find( col_class ).each( ( index, col ) => {
			let $col = $( col );

			// Let's get the checked days to display
			if (
				days_of_week.length === 0
				||
				$( days_of_week ).filter( [ $col.data( 'weekday' ).toString() ] ).length > 0
			) {
				// If we're here then it means that we are weekday cell that needs to be displayed.
				$col.find( event_class ).each( ( idx, evt ) => {
					let $evt = $( evt );

					if (
						time_of_day.length === 0
						||
						$( time_of_day ).filter( $evt.data( 'daydiv' ) ).length > 0
					) {
						if ( should_return_events_ids ) {
							event_ids_to_display = pushUnique( event_ids_to_display, $evt.data( 'eventid' ) );
						} else {
							$evt.removeClass( 'sugar-calendar-block__calendar-month__cell-hide' );
						}
					} else {
						$evt.addClass( 'sugar-calendar-block__calendar-month__cell-hide' );
					}
				});
			} else {
				$col.find( event_class ).addClass( 'sugar-calendar-block__calendar-month__cell-hide' );
			}
		})

		return event_ids_to_display;
	}

	/**
	 * Display the events on the week view.
	 *
	 * This method filters the events that should be displayed on the week view.
	 *
	 * @since 3.0.0
	 */
	CalendarBlock.prototype.displayEventsOnWeekDisplay = function() {

		let days_of_week = this.getDaysOfWeek(),
			time_of_day = this.getTimeOfDay();

		// Filter the All day and Multi-day events.
		this.filterDisplayWeekView(
			'.sugar-calendar-block__calendar-week__event-slot--all-day',
			'.sugar-calendar-block__calendar-week__event-cell--all-day',
			days_of_week,
			time_of_day,
			true
		).forEach( ( eventId ) => {
			this.$mainContainer.find( `.sugar-calendar-block__calendar-week__event-cell--id-${eventId}` ).removeClass( 'sugar-calendar-block__calendar-month__cell-hide' );
		});

		// Filter the normal events.
		this.filterDisplayWeekView(
			'.sugar-calendar-block__calendar-week__time-grid__day-col',
			'.sugar-calendar-block__calendar-week__event-cell',
			days_of_week,
			time_of_day
		);
	}

	/**
	 * Display the events on the day display.
	 *
	 * @since 3.0.0
	 */
	CalendarBlock.prototype.displayEventsOnDayDisplay = function() {
		let time_of_day = this.getTimeOfDay();

		if ( time_of_day.length === 0 ) {
			// Display all the events.
			this.$mainContainer.find( '.sugar-calendar-block__event-cell' ).removeClass( 'sugar-calendar-block__calendar-month__cell-hide' );
			return;
		}

		this.$mainContainer.find( '.sugar-calendar-block__event-cell' ).each( ( index, evt ) => {
			let $evt = $( evt );

			if ( $( time_of_day ).filter( $evt.data( 'daydiv' ) ).length > 0 ) {
				$evt.removeClass( 'sugar-calendar-block__calendar-month__cell-hide' );
			} else {
				$evt.addClass( 'sugar-calendar-block__calendar-month__cell-hide' );
			}
		} );
	}

	/**
	 * Setup the datepicker i18n.
	 *
	 * @since 3.7.0
	 */
	CalendarBlock.prototype.setupDatepickerI18n = function() {

		// Get the current locale.
		let locale = sugar_calendar_obj.settings.locale;

		$.fn.scbootdatepicker.dates[ locale ] = {
			days: sugar_calendar_obj.settings.i18n.days,
			daysShort: sugar_calendar_obj.settings.i18n.daysShort,
			daysMin: sugar_calendar_obj.settings.i18n.daysMin,
			months: sugar_calendar_obj.settings.i18n.months,
			monthsShort: sugar_calendar_obj.settings.i18n.monthsShort,
		}
	}

	let app = {

		/**
		 * Start the engine.
		 *
		 * @since 3.0.0
		 */
		init: function() {

			// Page load.
			$( window ).on( 'load', function() {

				app.load();
			} );
		},

		/**
		 * Page load.
		 *
		 * @since 3.0.0
		 */
		load: function() {

			if ( 'undefined' !== typeof window.FloatingUIDOM ) {
				FloatingUIDOM = window.FloatingUIDOM;
				app.initCalendars();

				$( 'body' ).on( 'click', app.closePopoversOnBodyClick );
			}
		},

		/**
		 * Initialize the calendars.
		 *
		 * @since 3.0.0
		 */
		initCalendars: function() {

			$( '.sugar-calendar-block' ).each( function() {

				new CalendarBlock( $( this ) );
			} );
		},

		/**
		 * Close all popovers when user clicked outside of context.
		 *
		 * @since 3.0.0
		 *
		 * @param {Event} e The event object.
		 */
		closePopoversOnBodyClick: function( e ) {

			let $body = $( this );

			if ( ! $body.hasClass( 'sugar-calendar-block__popovers__active' ) ) {
				return;
			}

			let $target = $( e.target );

			// If the terget is supposed to open the popover, then we don't hide the popover.
			if (
				$target.hasClass( 'sugar-calendar-block__controls__left__date' ) ||
				$target.hasClass( 'sugar-calendar-block__controls__right__settings__btn' ) ||
				$target.hasClass( 'sugar-calendar-block__controls__right__view__btn' ) ||
				$target.hasClass( 'sugar-calendar-block__event-cell' ) ||
				$target.hasClass( 'sugar-calendar-block__popover' ) ||
				$target.parents( '.sugar-calendar-block__controls__left__date' ).length > 0 ||
				$target.parents( '.sugar-calendar-block__controls__right__settings__btn' ).length > 0 ||
				$target.parents( '.sugar-calendar-block__controls__right__view__btn' ).length > 0 ||
				$target.parents( '.sugar-calendar-block__event-cell' ).length > 0 ||
				$target.parents( '.sugar-calendar-block__popover' ).length > 0
			) {
				return;
			}

			hideAllPopovers( $body );
		}
	};

	return app;

} ( document, window, jQuery ) );

sugar_calendar.init();
