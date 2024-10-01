/* globals jQuery, sugar_calendar_admin_education */
( function ( $, settings ) {

	'use strict';

	let SugarCalendar = window.SugarCalendar || {};
	SugarCalendar.Admin = SugarCalendar.Admin || {};

	SugarCalendar.Admin.Education = {

		/**
		 * Initialize.
		 *
		 * @since 3.0.0
		 */
		init: function ( settings ) {

			this.settings = settings;
			this.$notices = $( '.sugar-calendar-education-notice' );
			this.$dismissButtons = $( '.sugar-calendar-dismiss-notice' );

			this.bindEvents();
		},

		bindEvents: function () {

			this.$dismissButtons.on( 'click', this.dismissNotice.bind( this ) );
		},

		dismissNotice: function ( e ) {

			const noticeId = $( e.target ).attr( 'data-notice' );
			const $notice = this.$notices.filter( `[data-notice="${noticeId}"]` )

			$.post( this.settings.ajax_url, {
				task: 'education_notice_dismiss',
				notice_id: noticeId,
			} );

			if ( noticeId === 'notice_bar' ) {
				$notice.slideUp( 250, () => $notice.remove() );
			} else {
				$notice.remove();
			}
		},
	};

	SugarCalendar.Admin.Education.init( settings );

	window.SugarCalendar = SugarCalendar;

} )( jQuery, sugar_calendar_admin_education );
