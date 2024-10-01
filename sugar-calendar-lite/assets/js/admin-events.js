/* globals jQuery, tippy, sugar_calendar_admin_events */
( function ( $, tippy, settings ) {

	'use strict';

	let SugarCalendar = window.SugarCalendar || {};
	SugarCalendar.Admin = SugarCalendar.Admin || {};

	SugarCalendar.Admin.Events = {

		/**
		 * Initialize.
		 *
		 * @since 3.0.0
		 */
		init: function ( settings ) {

			this.settings = settings;
			this.$calendarDropdown = $( '#sc_event_category' );
			this.$screenOptionsToggle = $( '#sugar-calendar-screen-options-toggle' );
			this.$screenOptionsMenu = $( '.sugar-calendar-screen-options-menu' );
			this.$columnFields = $( '[name="sugar-calendar[columns][]"]', this.$screenOptionsMenu );
			this.$gridColumnLayout = $( '#sugar-calendar-table-grid-column-layout' );

			this.bindEvents();
			this.initializeTooltips();
		},

		bindEvents: function () {

			this.$calendarDropdown.on( 'change', ( e ) => $( e.target ).parents( 'form' ).submit() );
			this.$screenOptionsToggle.on( 'click', this.onScreenOptionsToggleClick.bind( this ) );
			this.$columnFields.on( 'click', this.onColumnsChange.bind( this ) );
		},

		onScreenOptionsToggleClick: function ( e ) {
			this.$screenOptionsToggle.toggleClass( 'open' );
			this.$screenOptionsMenu.fadeToggle( 200 );
		},

		onColumnsChange: function ( e ) {

			const columns = this.$columnFields.map( function () {
				const $this = $( this );

				return {
					id: $this.val(),
					visible: $this.is( ':checked' )
				}
			} ).toArray();

			this.hideColumns( columns );
			this.hideEventSpans( columns );
			this.updateGridColumnLayout( columns );
			this.updateHiddenColumns( columns );
		},

		hideColumns: function ( columns ) {

			const hiddenColumns = columns
				.filter( column => ! column.visible )
				.map( column => `.column-${column.id}` ).join( ',' );

			// Handle both grid and table modes.
			$( '.column, th, td', '.sugar-calendar-table' ).removeClass( 'hidden' );
			$( hiddenColumns, '.sugar-calendar-table' ).addClass( 'hidden' );
		},

		hideEventSpans: function ( columns ) {

			const hiddenColumns = columns
				.filter( column => ! column.visible )
				.map( column => column.id );

			$( '.event-span', '.sugar-calendar-table-events' ).removeClass( 'hidden' );

			$( '.event-span', '.sugar-calendar-table-events' ).each( function () {
				const $this = $( this );
				const days = $this.attr( 'data-days' )
					.split( ',' )
					.filter( day => ! hiddenColumns.includes( day ) );

				if ( days.length === 0 ) {
					$this.addClass( 'hidden' );
				}
			} );
		},

		updateGridColumnLayout: function ( columns ) {

			let template = columns.map( ( column ) => {
				const max = column.id === columns[0].id ? '120px' : '1fr';
				const size = column.visible ? `minmax(0, ${max})` : '0fr';

				return `[${column.id}] ${size}`;
			} ).join( ' ' );

			const style = `
				.sugar-calendar-table-events {
					--grid-template-columns: ${template};
				}
			`;

			this.$gridColumnLayout.html( style );
		},

		updateHiddenColumns: function ( columns ) {

			const columnValues = columns
				.filter( column => ! column.visible )
				.map( column => column.id );

			$.post( this.settings.ajax_url, {
				'columns': columnValues,
				'mode': $( '[name=mode]', this.$screenOptionsMenu ).val(),
				'cd': $( '[name=cd]', this.$screenOptionsMenu ).val(),
				'cm': $( '[name=cm]', this.$screenOptionsMenu ).val(),
				'cy': $( '[name=cy]', this.$screenOptionsMenu ).val(),
				'task': 'update_hidden_columns',
			} );
		},

		initializeTooltips: function () {

			const $links = $( '.sugar-calendar-event-entry' );
			const $targets = $( '.sugar-calendar-event-entry span' );

			$links.on( 'click', e => e.preventDefault() );

			$targets.each( function () {
				tippy( $( this ).get( 0 ), {
					trigger: 'click',
					allowHTML: true,
					interactive: true,
					triggerTarget: $( this ).parent( 'a' ).get( 0 ),
					offset: [0, 12],

					content( el ) {
						const id = el.parentElement.getAttribute( 'data-id' );

						return $( `#sugar-calendar-tooltip-${id}` ).html();
					},
				} );
			} );
		}
	};

	SugarCalendar.Admin.Events.init( settings );

	window.SugarCalendar = SugarCalendar;

} )( jQuery, tippy, sugar_calendar_admin_events );
