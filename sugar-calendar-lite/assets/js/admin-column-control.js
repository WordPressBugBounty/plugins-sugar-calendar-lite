(function ($, settings) {

	'use strict';

	var SugarCalendar = window.SugarCalendar || {};
	SugarCalendar.Admin = SugarCalendar.Admin || {};

	SugarCalendar.Admin.ColumnControl = {
		init: function (settings) {
			this.settings = settings || {};
			this.$screenOptionsToggle = $('#sugar-calendar-table-screen-options-toggle');
			this.$screenOptionsMenu = $('.sugar-calendar-table-screen-options-menu');
			this.bindEvents();
		},

		bindEvents: function () {
			this.$screenOptionsToggle.on('click', this.onScreenOptionsToggleClick.bind(this));
		},

		onScreenOptionsToggleClick: function (e) {
			this.$screenOptionsToggle.toggleClass('open');
			this.$screenOptionsMenu.fadeToggle(200);
		}
	};

	// Replicate the initialization style of admin-events.js
	SugarCalendar.Admin.ColumnControl.init(settings);
	window.SugarCalendar = SugarCalendar;

})(jQuery, window.sugar_calendar_admin_column_control || {});
