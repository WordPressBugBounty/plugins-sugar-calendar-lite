/* global FloatingUIDOM */

var SugarCalendarBlocks = window.SugarCalendarBlocks || ( function( document, window, $ ) {

	/**
	 * Contains the shared functionality for all blocks.
	 *
	 * @since 3.1.0
	 */
	const app = {
		/**
		 * Start the engine.
		 *
		 * @since 3.1.0
		 */
		init() {
			// Document ready.
			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 3.1.0
		 * @since 3.7.0 Added datepicker i18n.
		 */
		ready() {

			app.setupDatepickerI18n();

			$( 'body' ).on( 'click', app.events.closePopoversOnBodyClick );
		},

		/**
		 * Events.
		 *
		 * @since 3.1.0
		 */
		events: {

			/**
			 * Close all popovers when user clicked outside of context.
			 *
			 * @since 3.1.0
			 *
			 * @param {Event} e The event object.
			 */
			closePopoversOnBodyClick( e ) {

				if ( ! $( this ).hasClass( 'sugar-calendar-block__popovers__active' ) ) {
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

				app.hideAllPopovers();
			}
		},

		/**
		 * Hide all popovers in the page.
		 *
		 * @since 3.1.0
		 */
		hideAllPopovers() {

			const $body = $( 'body' );

			// Hide all popovers.
			$body.find( '.sugar-calendar-block__popover' )
				.removeClass( 'sugar-calendar-block__controls__settings__btn_active' )
				.hide();

			// De-active all buttons.
			$body.find( '.sugar-calendar-block__controls__settings__btn' )
				.removeClass( 'sugar-calendar-block__controls__settings__btn_active' );

			$body.find( '.sugar-calendar-block__controls__left__date' )
				.removeClass( 'sugar-calendar-block__controls__settings__btn_active' );

			$body.removeClass( 'sugar-calendar-block__popovers__active' );
		},

		/**
		 * Setup the datepicker i18n.
		 *
		 * @since 3.7.0
		 */
		setupDatepickerI18n() {

			// Get the current locale.
			let locale = sc_frontend_blocks_common_obj.settings.locale;

			$.fn.scbootdatepicker.dates[ locale ] = {
				days: sc_frontend_blocks_common_obj.settings.i18n.days,
				daysShort: sc_frontend_blocks_common_obj.settings.i18n.daysShort,
				daysMin: sc_frontend_blocks_common_obj.settings.i18n.daysMin,
				months: sc_frontend_blocks_common_obj.settings.i18n.months,
				monthsShort: sc_frontend_blocks_common_obj.settings.i18n.monthsShort,
			}
		}
	}

	return app;

}( document, window, jQuery ) );

SugarCalendarBlocks.init();

/**
 * Block Controls.
 *
 * @since 3.1.0
 * @since 3.5.0 Added the trigger for filter by venue.
 * @since 3.7.0 Added the trigger for filter by tag.
 */
SugarCalendarBlocks.Controls = SugarCalendarBlocks.Controls || function( $blockContainer ) {

	this.$blockContainer = $blockContainer;
	this.$baseContainer  = $blockContainer.find( '.sugar-calendar-block__base-container' );
	this.$datePicker     = $blockContainer.find( '.sugar-calendar-block__controls__datepicker' );
	this.$formContainer  = $blockContainer.find( '.sugar-calendar-block-settings' );
	this.$searchContainer = $blockContainer.find( '.sugar-calendar-block__controls__right__search__field' );
	this.$searchClear = $blockContainer.find( '.sugar-calendar-block__controls__right__search__clear' );
	this.$timeOfDayContainer = $blockContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__time' );
	this.$daysOfWeekContainer = $blockContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__days' );

	this.initDatePicker();

	// Event listeners.
	this.$blockContainer.find( '.sugar-calendar-block__popover__display_selector__container__body__option' )
		.on( 'click', this.onChangeDisplay.bind( this ) );

	// Pagination.
	this.$blockContainer.find( '.sugar-calendar-block__controls__left__pagination__prev' )
		.on( 'click', this.goToPrevious.bind( this ) );

	this.$blockContainer.find( '.sugar-calendar-block__controls__left__pagination__next' )
		.on( 'click', this.goToNext.bind( this ) );

	this.$blockContainer.find( '.sugar-calendar-block__controls__left__pagination__current' )
		.on( 'click', this.goToCurrent.bind( this ) );

	// Calendar Selector.
	this.$blockContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__cal' )
		.on( 'click', this.onSelectCalendar.bind( this ) );

	// Venue Selector.
	this.$blockContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__venue' )
		.on( 'click', this.onSelectVenue.bind( this ) );

	// Speaker Selector.
	this.$blockContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__speaker' )
		.on( 'click', this.onSelectSpeaker.bind( this ) );

	// Tag Selector.
	this.$blockContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__tag' )
		.on( 'click', this.onSelectTag.bind( this ) );

	// Day Selector.
	this.$blockContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__day' )
			.on( 'change', this.filterDisplayedEvents.bind( this ) );

	// Time Selector.
	this.$blockContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__time' )
			.on( 'change', this.filterDisplayedEvents.bind( this ) );

	// Search.
	this.$searchContainer
			.on( 'keyup', this.onSearch.bind( this ) );

	// Search Focus.
	this.$searchContainer
		.on( 'focus', this.onSearchFieldFocus.bind( this ) );

	// Search Focusout.
	this.$searchContainer
		.on( 'focusout', this.onSearchFieldFocusOut.bind( this ) );

	// Search Icon click.
	this.$blockContainer.find( '.sugar-calendar-block__controls__right__search__icon' )
			.on( 'click', this.onSearchClick.bind( this ) );

	// Clear search.
	this.$searchClear
			.on( 'click', this.onClearSearch.bind( this ) );

	new SugarCalendarBlocks.Controls.Popovers( $blockContainer );

	this.$blockContainer.on( 'block:preupdate', this.onPreUpdate.bind( this ) );
	this.$blockContainer.on( 'block:postupdate', this.onPostUpdate.bind( this ) );
}

/**
 * Event callback for pre-update.
 *
 * @since 3.1.0
 *
 * @param {Event}  e    The event object.
 * @param {object} args The args.
 */
SugarCalendarBlocks.Controls.prototype.onPreUpdate = function( e, args ) {

	// Add the loading state.
	this.$baseContainer.addClass( 'sugar-calendar-block__loading-state' );
	this.$baseContainer.prepend( '<div class="sugar-calendar-block__base-container__overlay"><div class="sugar-calendar-block__loading"></div></div>' );
}

/**
 * Event callback for post-update.
 *
 * @since 3.1.0
 *
 * @param {Event}  e    The event object.
 * @param {object} args The args.
 */
SugarCalendarBlocks.Controls.prototype.onPostUpdate = function( e, args ) {

	// Remove the loading state.
	this.$baseContainer.removeClass( 'sugar-calendar-block__loading-state' );
	this.$baseContainer.find( '.sugar-calendar-block__base-container__overlay' ).remove();
}

/**
 * Get the display mode.
 *
 * @since 3.1.0
 *
 * @return {string}
 */
SugarCalendarBlocks.Controls.prototype.getDisplayMode = function() {

	return this.$formContainer.find( 'input[name="sc_display"]' ).val();
}

/**
 * Get the calendar IDs that are checked.
 *
 * @since 3.1.0
 *
 * @return {string[]}
 */
SugarCalendarBlocks.Controls.prototype.getCalendarIds = function() {
	let calendarIds = [];

	this.$blockContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__cal:checked' )
		.each( function() {
			calendarIds.push( jQuery( this ).val() );
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
SugarCalendarBlocks.Controls.prototype.getVenueIds = function() {
	let venueIds = [];

	this.$blockContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__venue:checked' )
		.each( function() {
			venueIds.push( jQuery( this ).val() );
		});

	return venueIds;
}

/**
 * Get the calendar IDs that are filtered from block settings.
 *
 * @since 3.2.0
 *
 * @return {string[]}
 */
SugarCalendarBlocks.Controls.prototype.getCalendarsFilter = function() {
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
SugarCalendarBlocks.Controls.prototype.getVenuesFilter = function() {
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
 * Event callback for changing the display mode of the block.
 *
 * @since 3.1.0
 *
 * @param {Event} e The event object.
 */
SugarCalendarBlocks.Controls.prototype.onChangeDisplay = function( e ) {

	let $el = jQuery( e.target ),
		display = $el.text().trim(),
		displayMode = $el.data( 'mode' );

	if ( displayMode === this.getDisplayMode() ) {
		return;
	}

	// Update the form value.
	this.$formContainer.find( 'input[name="sc_display"]' ).val( displayMode );
	this.$blockContainer.find( '.sugar-calendar-block__controls__right__view__btn span' ).text( display );

	this.update( { update_display: true } );
}

SugarCalendarBlocks.Controls.prototype.update = function( args ) {

	// Pre-update.
	this.$blockContainer.trigger( 'block:preupdate', [ args ] );

	// Update.
	this.$blockContainer.trigger( 'block:update', [ args ] );

	// Post-update
	this.$blockContainer.trigger( 'block:postupdate', [ args ] );
}

/**
 * Event call back when the prev button is clicked.
 *
 * @since 3.1.0
 */
SugarCalendarBlocks.Controls.prototype.goToPrevious = function() {

	// Trigger the update block.
	this.update( { action: 'previous_week' } );
};

/**
 * Event call back when the next button is clicked.
 *
 * @since 3.1.0
 */
SugarCalendarBlocks.Controls.prototype.goToNext = function() {

	// Trigger the update block.
	this.update( { action: 'next_week' } );
};

/**
 * Event callback for "This Month", "This Week", or "Today" button.
 *
 * @since 3.1.0
 */
SugarCalendarBlocks.Controls.prototype.goToCurrent = function() {

	const blockData = this.$blockContainer.data();

	this.$formContainer.find( 'input[name="sc_year"]' ).val( blockData.ogyear );
	this.$formContainer.find( 'input[name="sc_month"]' ).val( blockData.ogmonth );
	this.$formContainer.find( 'input[name="sc_day"]' ).val( blockData.ogday );

	// Trigger the update block.
	this.update( {} );
}

/**
 * Event callback for selecting a calendar to display.
 *
 * @since 3.1.0
 */
SugarCalendarBlocks.Controls.prototype.onSelectCalendar = function() {

	// Trigger the update block.
	this.update( {} );
}

/**
 * Event callback for selecting a venue to display.
 *
 * @since 3.5.0
 */
SugarCalendarBlocks.Controls.prototype.onSelectVenue = function() {

	// Trigger the update block.
	this.update( {} );
}

/**
 * Get the speaker IDs that are checked.
 *
 * @since 3.7.0
 *
 * @return {string[]}
 */
SugarCalendarBlocks.Controls.prototype.getSpeakerIds = function() {
	const $checkedSpeakers = this.$blockContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__speaker:checked' );

	if ( $checkedSpeakers.length <= 0 ) {
		return [];
	}

	return $checkedSpeakers.map( function() {
		return this.value;
	} ).get();
}

/**
 * Get the speaker IDs that are filtered from block settings.
 *
 * @since 3.7.0
 *
 * @return {string[]}
 */
SugarCalendarBlocks.Controls.prototype.getSpeakersFilter = function() {
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
 * Event callback for selecting a speaker to display.
 *
 * @since 3.7.0
 */
SugarCalendarBlocks.Controls.prototype.onSelectSpeaker = function() {
	this.update( {} );
}

/**
 * Event callback for searching events.
 *
 * @since 3.1.0
 *
 * @param {Event} e The event object.
 */
SugarCalendarBlocks.Controls.prototype.onSearch = function( e ) {

	if ( e.keyCode === 13 ) {
		// Trigger the update block.
		this.update( {} );
		return;
	}

	if ( e.target.value.length > 0 ) {
		this.$searchClear.show();
	} else {
		this.$searchClear.hide();
	}
}

/**
 * Get the tag IDs that are checked.
 *
 * @since 3.7.0
 *
 * @return {string[]}
 */
SugarCalendarBlocks.Controls.prototype.getTagIds = function() {
	const $checkedTags = this.$blockContainer.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__tag:checked' );

	if ( $checkedTags.length <= 0 ) {
		return [];
	}

	return $checkedTags.map( function() {
		return this.value;
	} ).get();
}

/**
 * Get the tag IDs that are filtered from block settings.
 *
 * @since 3.7.0
 *
 * @return {string[]}
 */
SugarCalendarBlocks.Controls.prototype.getTagsFilter = function() {
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
 * Event callback for selecting a tag to display.
 *
 * @since 3.7.0
 */
SugarCalendarBlocks.Controls.prototype.onSelectTag = function() {
	this.update( {} );
}

/**
 * Event callback for focusing on the search field.
 *
 * @since 3.1.0
 *
 * @param {Event} e The event object.
 */
SugarCalendarBlocks.Controls.prototype.onSearchFieldFocus = function( e ) {

	jQuery( e.target ).parent( '.sugar-calendar-block__controls__right__search' )
		.addClass( 'sugar-calendar-block__controls__right__search--active' );
}

/**
 * Event callback for focusing out on the search field.
 *
 * @since 3.1.0
 *
 * @param {Event} e The event object.
 */
SugarCalendarBlocks.Controls.prototype.onSearchFieldFocusOut = function( e ) {

	jQuery( e.target ).parent( '.sugar-calendar-block__controls__right__search' )
		.removeClass( 'sugar-calendar-block__controls__right__search--active' );
}

/**
 * Event callback for clicking the search icon.
 *
 * @since 3.1.0
 *
 * @param {Event} e The event object.
 */
SugarCalendarBlocks.Controls.prototype.onSearchClick = function( e ) {

	// Trigger the update block.
	this.update( {} );
}

/**
 * Event callback for clearing the search field.
 *
 * @since 3.1.0
 *
 * @param {Event} e The event object.
 */
SugarCalendarBlocks.Controls.prototype.onClearSearch = function( e ) {
	this.$searchContainer.val( '' );
	this.$searchClear.hide();

	// Trigger the update block.
	this.update( {} );
}

/**
 * Event callback for selecting a calendar to display.
 *
 * @since 3.1.0
 */
SugarCalendarBlocks.Controls.prototype.filterDisplayedEvents = function() {

	// Trigger the update block.
	this.$blockContainer.trigger( 'block:filterDisplayedEvents', [ {} ] );
}

/**
 * Initialize the Datepicker.
 *
 * @since 3.1.0
 */
SugarCalendarBlocks.Controls.prototype.initDatePicker = function() {

	if ( this.$datePicker !== undefined ) {
		this.$datePicker.scbootdatepicker( 'destroy' );
	}

	this.$datePicker.scbootdatepicker({
		minViewMode: 0,
		maxViewMode: 2,
		templates: {
			leftArrow: '<svg width="6" height="11" viewBox="0 0 6 11" fill="none" xmlns="http://www.w3.org/2000/svg">' +
				'<path d="M5.41406 10.6094C5.29688 10.7266 5.13281 10.7266 5.01562 10.6094L0.09375 5.71094C0 5.59375 0 5.42969 0.09375 5.3125L5.01562 0.414062C5.13281 0.296875 5.29688 0.296875 5.41406 0.414062L5.88281 0.859375C5.97656 0.976562 5.97656 1.16406 5.88281 1.25781L1.64062 5.5L5.88281 9.76562C5.97656 9.85938 5.97656 10.0469 5.88281 10.1641L5.41406 10.6094Z" fill="currentColor"/>' +
				'</svg>',
			rightArrow: '<svg width="6" height="11" viewBox="0 0 6 11" fill="none" xmlns="http://www.w3.org/2000/svg">' +
				'<path d="M0.5625 0.414062C0.679688 0.296875 0.84375 0.296875 0.960938 0.414062L5.88281 5.3125C5.97656 5.42969 5.97656 5.59375 5.88281 5.71094L0.960938 10.6094C0.84375 10.7266 0.679688 10.7266 0.5625 10.6094L0.09375 10.1641C0 10.0469 0 9.85938 0.09375 9.76562L4.33594 5.5L0.09375 1.25781C0 1.16406 0 0.976562 0.09375 0.859375L0.5625 0.414062Z" fill="currentColor"/>' +
				'</svg>'
		},
		weekStart: sc_frontend_blocks_common_obj.settings.sow,
		language: sc_frontend_blocks_common_obj.settings.locale,
	});

	let $year = this.$formContainer.find( 'input[name="sc_year"]' ),
		$month = this.$formContainer.find( 'input[name="sc_month"]' ),
		$day = this.$formContainer.find( 'input[name="sc_day"]' ),
		that = this;

	this.$datePicker.scbootdatepicker( 'update', new Date( $year.val(), $month.val() - 1, $day.val() ) );

	this.$datePicker.on( 'changeDate', ( e ) => {

		$year.val( e.date.getFullYear() );
		$month.val( e.date.getMonth() + 1 );
		$day.val( e.date.getDate() );

		that.update( {} );
	} );
}

/**
 * Update the Date of the block controls.
 *
 * @since 3.1.0
 *
 * @param {Object} newDate Object containing `year`, `month`, and `day`.
 */
SugarCalendarBlocks.Controls.prototype.updateDate = function( newDate ) {

	this.$formContainer.find( 'input[name="sc_year"]' ).val( newDate.year );
	this.$formContainer.find( 'input[name="sc_month"]' ).val( newDate.month );
	this.$formContainer.find( 'input[name="sc_day"]' ).val( newDate.day );

	this.$datePicker.scbootdatepicker( 'update', new Date( newDate.year, newDate.month - 1, newDate.day ) );
}

/**
 * Get the checked time of day.
 *
 * @since 3.1.0
 *
 * @return {Array}
 */
SugarCalendarBlocks.Controls.prototype.getTimeOfDay = function() {

	return this.$timeOfDayContainer
		.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__time:checked' )
		.map( ( index, el ) => el.value ).get();
}

/**
 * Get the checked days of the week.
 *
 * @since 3.1.0
 *
 * @return {Array}
 */
SugarCalendarBlocks.Controls.prototype.getDaysOfWeek = function() {

	return this.$daysOfWeekContainer
		.find( '.sugar-calendar-block__popover__calendar_selector__container__options__val__day:checked' )
		.map( ( index, el ) => el.value ).get();
}

/**
 * Control Popovers.
 *
 * @since 3.1.0
 */
SugarCalendarBlocks.Controls.Popovers = SugarCalendarBlocks.Controls.Popovers || function( $blockContainer ) {

	this.$blockContainer = $blockContainer;

	let popovers = [
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

	const that = this;

	popovers.forEach( ( popover ) => {

		let $button = this.$blockContainer.find( popover.button_selector );

		$button.on( 'click', that.toggle.bind( that, $button, popover.key, popovers ) );
	} );
}

/**
 * Event callback for handling the toggle of the controls popover.
 *
 * @since 3.1.0
 *
 * @param {jQuery} $button  The button that toggles the popover.
 * @param {string} key      The key of the popover.
 * @param {Array}  popovers Array containing all info of setting popovers.
 */
SugarCalendarBlocks.Controls.Popovers.prototype.toggle = function( $button, key, popovers ) {

	let popover = popovers.find( ( setting ) => setting.key === key );
	let $popover = this.$blockContainer.find( popover.popover_selector );

	// Hide all other popovers before showing a new one.
	SugarCalendarBlocks.hideAllPopovers();

	if ( ! $popover.is( ':visible' ) ) {
		this.show( $button, $popover,  key );
	}
}

/**
 * Show the popover.
 *
 * @since 3.1.0
 *
 * @param {jQuery} $button  The button element.
 * @param {jQuery} $popover The popover to toggle.
 * @param {string} key      The key of the popover.
 */
SugarCalendarBlocks.Controls.Popovers.prototype.show = function ( $button, $popover, key ) {

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

	// Compute the popover position.
	FloatingUIDOM.computePosition(
		$button[0],
		$popover[0],
		{
			placement: key === 'calendar_selector' ? 'bottom-end' : 'bottom-start',
			middleware: middlewares,
		}
	).then( ({x, y} ) => {

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

	this.$blockContainer.parents( 'body').addClass( 'sugar-calendar-block__popovers__active' );
}
