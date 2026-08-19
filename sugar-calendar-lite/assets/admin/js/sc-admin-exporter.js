/* globals jQuery, flatpickr, SugarCalendar */
'use strict';

const SCAdminExporter = window.SCAdminExporter || ( function( document, window, $ ) {

	const app = {

		/**
		 * Runtime variables.
		 *
		 * @since 3.3.0
		 *
		 * @type {object}
		 */
		runtime_vars: {

			/**
			 * DOM elements cache.
			 *
			 * @since 3.3.0
			 *
			 * @type {object}
			 */
			doms: {
				/**
				 * jQuery DOM of the Events checkbox.
				 *
				 * @since 3.3.0
				 *
				 * @type {jQuery}
				 */
				$events_checkbox: null,

				/**
				 * jQuery DOM of the Custom Fields checkbox.
				 *
				 * @since 3.3.0
				 *
				 * @type {jQuery}
				 */
				$custom_fields_checkbox: null,

				/**
				 * jQuery DOM of the Custom Fields list.
				 *
				 * @since 3.3.0
				 *
				 * @type {jQuery}
				 */
				$custom_fields_list: null,
			}
		},

		/**
		 * Start the engine.
		 *
		 * @since 3.3.0
		 */
		init() {
			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 3.3.0
		 */
		ready() {

			app.cacheDom();
			app.bindEvents();
			app.initDateRange();
			app.bindProEducation();
		},

		/**
		 * Cache DOM elements.
		 *
		 * @since 3.3.0
		 */
		cacheDom() {

			app.runtime_vars.doms.$events_checkbox = $( '#sc-admin-tools-export-checkbox-events' );
			app.runtime_vars.doms.$custom_fields_checkbox = $( '#sc-admin-tools-export-checkbox-custom_fields' );
			app.runtime_vars.doms.$custom_fields_list = $( '#sc-admin-tools-export-context-custom_fields' );
		},

		/**
		 * Bind events.
		 *
		 * @since 3.3.0
		 */
		bindEvents() {

			app.runtime_vars.doms.$events_checkbox.on( 'change', function() {
				if ( this.checked ) {
					app.runtime_vars.doms.$custom_fields_list.removeClass( 'sc-admin-tools-disabled' );
				} else {
					app.runtime_vars.doms.$custom_fields_checkbox.prop( 'checked', false );
					app.runtime_vars.doms.$custom_fields_list.addClass( 'sc-admin-tools-disabled' );
				}
			} );
		},

		/**
		 * On Lite, open the upgrade modal when a Pro-only data type is clicked.
		 *
		 * The rows carry `pointer-events: none` on their label so the checkbox
		 * cannot be ticked, which means the click lands on the <li> — that is what
		 * is bound here. Copy comes from this screen's own localized object rather
		 * than the shared education global, so nothing depends on which page
		 * localized that global last.
		 *
		 * Absent on Pro: the localized object is only enqueued for Lite, so there
		 * is nothing to bind and no Pro-only rows to bind it to.
		 *
		 * @since 3.13.0
		 */
		bindProEducation() {

			if ( typeof window.sc_admin_exporter === 'undefined' || ! window.sc_admin_exporter.education ) {
				return;
			}

			const education = window.sc_admin_exporter.education;

			$( '#sc-admin-tools-export-form' ).on( 'click', 'li.need-pro', function() {

				const $row = $( this );
				const featId = $row.data( 'featId' );
				const featName = $row.data( 'featName' );

				if ( ! featId || ! featName ) {
					return;
				}

				SugarCalendar.Admin.Education.openUpgradeModal( {
					title: featName + ' ' + education.upgrade_title,
					content: education.upgrade_content.replace( '[feat-name]', featName + ' ' + education.feature_name ),
					bonus: education.upgrade_bonus,
					upgradeButton: education.upgrade_button,
					alreadyPurchased: education.already_purchased,
					utmLocale: education.utm_locale,
					utmMedium: 'tools-export-' + featId,
					thankYou: education.thank_you,
				} );
			} );
		},

		/**
		 * Initialize every flatpickr date-range control on the page.
		 *
		 * Binds by the `sugar-calendar-date-range` class (rendered by
		 * UI::date_range_control) and resolves each control's hidden start / end
		 * fields from its `data-start-field` / `data-end-field` attributes, so the
		 * control works wherever it is rendered — not just on this screen.
		 *
		 * @since 3.13.0
		 */
		initDateRange() {

			if ( typeof flatpickr === 'undefined' ) {
				return;
			}

			$( '.sugar-calendar-date-range' ).each( function() {

				const $input = $( this );
				const startField = $input.data( 'startField' );
				const endField = $input.data( 'endField' );

				// UI::date_range_control leaves these empty when rendered without an
				// id. Skip rather than build `$('#')`, which throws and would abort
				// the loop, leaving any other control on the page uninitialized.
				if ( ! startField || ! endField ) {
					return;
				}

				const $start = $( '#' + startField );
				const $end = $( '#' + endField );

				flatpickr( this, {
					altInput: true,
					altFormat: 'M j, Y',
					dateFormat: 'Y-m-d',
					mode: 'range',
					defaultDate: [ $start.val(), $end.val() ].filter( Boolean ),
					onReady( selectedDates, dateStr, instance ) {

						// flatpickr swaps in a generated altInput; carry the label over.
						const label = $input.attr( 'aria-label' );

						if ( label && instance.altInput ) {
							instance.altInput.setAttribute( 'aria-label', label );
						}
					},
					onChange( selectedDates, dateStr, instance ) {

						$start.val( selectedDates[ 0 ] ? instance.formatDate( selectedDates[ 0 ], 'Y-m-d' ) : '' );
						$end.val( selectedDates[ 1 ] ? instance.formatDate( selectedDates[ 1 ], 'Y-m-d' ) : '' );
					},
				} );
			} );
		},
	};

	return app;
}( document, window, jQuery ) );

SCAdminExporter.init();
