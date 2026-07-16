(function ($, settings) {

	'use strict';

	var SugarCalendar = window.SugarCalendar || {};
	SugarCalendar.Admin = SugarCalendar.Admin || {};

	SugarCalendar.Admin.ColumnControl = {

		// Cog checkboxes that map one-to-one to a list-table column.
		columnSelector: 'input[name="sugar-calendar-table-active-columns[columns][]"], input[name="sugar-calendar-calendars-visible-columns[]"]',

		init: function (settings) {
			this.settings = settings || {};
			this.$screenOptionsToggle = $('#sugar-calendar-table-screen-options-toggle');
			this.$screenOptionsMenu = $('.sugar-calendar-table-screen-options-menu');
			this.bindEvents();
		},

		bindEvents: function () {
			this.$screenOptionsToggle.on('click', this.onScreenOptionsToggleClick.bind(this));
			this.$screenOptionsMenu.on('change', this.columnSelector, this.onColumnToggle.bind(this));
		},

		onScreenOptionsToggleClick: function (e) {
			this.$screenOptionsToggle.toggleClass('open');
			this.$screenOptionsMenu.fadeToggle(200);
		},

		// Show/hide the matching column instantly. The choice is still persisted
		// when the form is submitted via "Save Options".
		onColumnToggle: function (e) {
			var $checkbox = $(e.target);
			var column = $checkbox.val();

			if (!column) {
				return;
			}

			var hide = !$checkbox.prop('checked');

			var $table = $('.wp-list-table');

			$table.find('.column-' + column).toggleClass('hidden', hide);

			// Keep the colspan of "no items" / full-width rows in sync, so the
			// layout does not leave a gap when the table has no data rows.
			$table.find('.colspanchange').each(function () {
				var colspan = parseInt($(this).attr('colspan'), 10) + (hide ? -1 : 1);

				$(this).attr('colspan', colspan);
			});
		}
	};

	// Replicate the initialization style of admin-events.js
	SugarCalendar.Admin.ColumnControl.init(settings);
	window.SugarCalendar = SugarCalendar;

})(jQuery, window.sugar_calendar_admin_column_control || {});
