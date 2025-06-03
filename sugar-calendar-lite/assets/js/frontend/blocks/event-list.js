var SugarCalendarBlocks = window.SugarCalendarBlocks || {};

SugarCalendarBlocks.EventList = SugarCalendarBlocks.EventList || ( function( document, window, $ ) {

	const Block = function( $blockContainer ) {

		this.$blockContainer = $blockContainer;
		this.$baseContainer = $blockContainer.find( '.sugar-calendar-event-list-block__base-container' );

		this.controls = new SugarCalendarBlocks.Controls( $blockContainer );

		this.$blockContainer.on( 'block:update', ( e, args ) => {
			this.update( args );
		} );

		this.$blockContainer.on( 'block:filterDisplayedEvents', this.onFilterDisplayedEvents.bind( this ) );

		this.$blockContainer.find( '.sugar-calendar-event-list-block__footer__prev_btn' )
			.on( 'click', this.onPreviousWeekBtnClick.bind( this ) );

		this.$blockContainer.find( '.sugar-calendar-event-list-block__footer__next_btn' )
			.on( 'click', this.onNextWeekBtnClick.bind( this ) );

		/*
		 * If visitor timezone conversion is enabled, update the block
		 * on its first load.
		 */
		if ( parseInt( this.controls.$formContainer.find( 'input[name="sc_visitor_tz_convert"]' ).val() ) === 1 ) {
			this.update( {} );
		}
	}

	/**
	 * Update the block.
	 *
	 * @since 3.1.0
	 * @since 3.1.2 Convert to visitor timezone if necessary.
	 * @since 3.4.0 Add paged data support.
	 * @since 3.5.0 Add `venues` and `venuesFilter` to block data.
	 * @since 3.7.0 Add `tags` and `tagsFilter` to block data.
	 */
	Block.prototype.update = function( args ) {

		SugarCalendarBlocks.hideAllPopovers();

		const updateDisplay = ( args.update_display === undefined ) ? false : args.update_display;
		const blockAction = ( args.action === undefined ) ? '' : args.action;

		// Pagination number.
		let paged = parseInt( this.$blockContainer.data( 'paged' ), 10 ) || 1;

		// If the action is previous or next week, update the paged number.
		if ( blockAction === 'previous_week' ) {
			paged--;
		} else if ( blockAction === 'next_week' ) {
			paged++;
		} else {
			paged = 1;
		}

		let blockData = {
			attributes: this.$blockContainer.data( 'attributes' ),
			calendars: this.controls.getCalendarIds(),
			venues: this.controls.getVenueIds(),
			speakers: this.controls.getSpeakerIds(),
			tags: this.controls.getTagIds(),
			calendarsFilter: this.controls.getCalendarsFilter(),
			venuesFilter: this.controls.getVenuesFilter(),
			speakersFilter: this.controls.getSpeakersFilter(),
			tagsFilter: this.controls.getTagsFilter(),
			day: parseInt( this.controls.$formContainer.find( 'input[name="sc_day"]' ).val() ),
			month: parseInt( this.controls.$formContainer.find( 'input[name="sc_month"]' ).val() ),
			year: parseInt( this.controls.$formContainer.find( 'input[name="sc_year"]' ).val() ),
			search: this.controls.$searchContainer.val(),
			display: this.controls.getDisplayMode(),
			visitor_tz_convert: parseInt( this.controls.$formContainer.find( 'input[name="sc_visitor_tz_convert"]' ).val() ),
			visitor_tz: Intl.DateTimeFormat().resolvedOptions().timeZone,
			updateDisplay: updateDisplay,
			paged: paged,
			action: blockAction
		};

		let that = this;

		$.post(
			sc_frontend_blocks_common_obj.ajax_url,
			{
				action: 'sugar_calendar_event_list_block_update',
				block: blockData,
				nonce: sc_frontend_blocks_common_obj.nonce
			},
			function( response ) {

				if ( ! response.success ) {
					return;
				}

				that.controls.updateDate( response.data.date );

				// Update the heading.
				that.$blockContainer.find( '.sugar-calendar-block__view-heading' ).text( response.data.heading );
				that.$blockContainer.find( '.sugar-calendar-block__view-heading-mobile' ).text( response.data.heading_mobile );

				// Update the body.
				that.$baseContainer.html( response.data.body );

				that.$blockContainer.trigger( 'block:filterDisplayedEvents' );

				if ( typeof SCTimeZones !== 'undefined' ) {
					SCTimeZones.convertEventsTime();
				}

				// Update the paged attribute if it exists.
				if ( response.data.paged !== undefined ) {
					that.$blockContainer.data( 'paged', response.data.paged );
				}

				// Update next pagination button.
				if ( response.data.enable_next !== undefined ) {
					that.$blockContainer.find( '.sugar-calendar-event-list-block__footer__next_btn' ).prop( 'disabled', ! response.data.enable_next );
				}

				// Update previous pagination button.
				if ( response.data.enable_previous !== undefined ) {
					that.$blockContainer.find( '.sugar-calendar-event-list-block__footer__prev_btn' ).prop( 'disabled', ! response.data.enable_previous );
				}
			}
		);
	}

	/**
	 * Callback for the block:filterDisplayedEvents event.
	 *
	 * @since 3.1.0
	 */
	Block.prototype.onFilterDisplayedEvents = function() {
		let timeOfDay = this.controls.getTimeOfDay(),
			daysOfWeek = this.controls.getDaysOfWeek(),
			displayMode = this.controls.getDisplayMode(),
			atLeastOneEventVisible = false;

		this.$blockContainer.find( `.sugar-calendar-event-list-block__${displayMode}view__event` )
			.each( ( index, evt ) => {
				let $evt = $( evt );

				let shouldHideEvent = true;

				if (
					(
						daysOfWeek.length === 0
						||
						$( daysOfWeek ).filter( $evt.data( 'eventdays' ) ).length > 0
					)
					&&
					(
						timeOfDay.length === 0
						||
						$( timeOfDay ).filter( $evt.data( 'daydiv' ) ).length > 0
					)
				) {
					shouldHideEvent = false;
					atLeastOneEventVisible = true;
				}

				if ( shouldHideEvent ) {
					$evt.addClass( 'sugar-calendar-block-hide-element' );
				} else {
					$evt.removeClass( 'sugar-calendar-block-hide-element' );
				}
			});

		let $no_events_container = this.$baseContainer.find( '.sugar-calendar-block__base-container__no-events' );

		if ( atLeastOneEventVisible ) {

			// Display the events container.
			this.$baseContainer.find( '.sugar-calendar-block__events-display-container' ).removeClass( 'sugar-calendar-block-hide-element' );

			if ( $no_events_container.length > 0 ) {
				// Remove the no events container.
				$no_events_container.remove();
			}
		} else {

			// Hide the events container.
			this.$baseContainer.find( '.sugar-calendar-block__events-display-container' ).addClass( 'sugar-calendar-block-hide-element' );

			if ( $no_events_container.length === 0 ) {
				// @todo - Make this a template.
				this.$baseContainer.prepend(
					'<div class="sugar-calendar-block__base-container__no-events">' +
						'<div class="sugar-calendar-block__base-container__no-events__msg">' +
							SCEventListBlock.strings.no_events_criteria_based +
						'</div>' +
					'</div>'
				);
			}
		}
	}

	/**
	 * Callback for the previous week button click.
	 *
	 * @since 3.1.0
	 */
	Block.prototype.onPreviousWeekBtnClick = function () {

		this.update({action: 'previous_week'});
	}

	/**
	 * Callback for the next week button click.
	 *
	 * @since 3.1.0
	 */
	Block.prototype.onNextWeekBtnClick = function() {

		this.update( { action: 'next_week' } );
	}

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
		 */
		ready() {

			// Initialize each block.
			$( '.sugar-calendar-event-list-block' ).each( function() {
				new Block( $( this ) );
			} );
		}
	}

	return app;

} ( document, window, jQuery ) );

SugarCalendarBlocks.EventList.init();
