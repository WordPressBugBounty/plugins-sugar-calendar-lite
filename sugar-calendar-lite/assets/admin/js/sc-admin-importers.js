/** global sc_admin_importers */

'use strict';

const SCAdminImporters = window.SCAdminImporters || ( function( document, window, $ ) {

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
			 * The importer slug.
			 *
			 * @since 3.3.0
			 *
			 * @type {string}
			 */
			importer_slug: '',

			/**
			 * The number of times the importer has been retried.
			 *
			 * @since 3.3.0
			 *
			 * @type {number}
			 */
			number_of_retries: 0,

			/**
			 * The total number to import per context.
			 *
			 * @type {number}
			 */
			total_number_to_import: {
				events: null,
				venues: null,
				speakers: null,
				tickets: null,
				orders: null,
				attendees: null,
				categories: null,
				tags: null,
			},

			/**
			 * The number of successful imports per context.
			 *
			 * @type {number}
			 */
			number_of_success_import: {
				events: 0,
				venues: 0,
				speakers: 0,
				tickets: 0,
				orders: 0,
				attendees: 0,
				categories: 0,
				tags: 0,
			},

			/**
			 * The last migrated context.
			 *
			 * @since 3.3.0
			 *
			 * @type {string}
			 */
			last_migrated_context: null,

			/**
			 * The ICS URL.
			 *
			 * @since 3.6.0
			 *
			 * @type {string}
			 */
			ics_url: null,

			/**
			 * The assets URL.
			 *
			 * @since 3.6.0
			 *
			 * @type {string}
			 */
			assets_url: null,

			/**
			 * DOM elements cache.
			 *
			 * @since 3.3.0
			 *
			 * @type {object}
			 */
			doms: {
				/**
				 * jQuery DOM of the importer file field.
				 *
				 * @since 3.3.0
				 *
				 * @type {jQuery}
				 */
				$import_file_field: null,

				/**
				 * jQuery DOM of the Importer file info span.
				 *
				 * @since 3.3.0
				 *
				 * @type {jQuery}
				 */
				$import_file_info_span: null,

				/**
				 * jQuery DOM of the ICS Importer field.
				 *
				 * @since 3.6.0
				 *
				 * @type {jQuery}
				 */
				$import_ics_url_field: null,

				/**
				 * jQuery DOM of the Importer button.
				 *
				 * @since 3.3.0
				 *
				 * @type {jQuery}
				 */
				$import_sc_btn: null,

				/**
				 * jQuery DOM of the ICS Importer button.
				 *
				 * @since 3.6.0
				 *
				 * @type {jQuery}
				 */
				$import_ics_btn: null,

				/**
				 * jQuery DOM of the importer logs.
				 *
				 * @since 3.3.0
				 *
				 * @type {jQuery}
				 */
				$importer_logs: null,

				/**
				 * jQuery DOM of the importer status.
				 *
				 * @since 3.3.0
				 *
				 * @type {jQuery}
				 */
				$importer_logs_status: null,

				/**
				 * jQuery DOM of the ICS Importer form.
				 *
				 * @since 3.6.0
				 *
				 * @type {jQuery}
				 */
				$import_ics_form: null,

				/**
				 * jQuery DOM of the ICS Importer summary.
				 *
				 * @since 3.6.0
				 *
				 * @type {jQuery}
				 */
				$import_ics_summary: null,
			},

			/**
			 * Strings.
			 *
			 * @since 3.6.0
			 *
			 * @type {object}
			 */
			strings: {},
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
			app.setDefaults();
		},

		/**
		 * Cache DOM elements.
		 *
		 * @since 3.3.0
		 */
		cacheDom() {

			// Importer DOMS.
			app.runtime_vars.doms.$import_file_info_span = $( '#sc-admin-tools-form-import-file-info' );
			app.runtime_vars.doms.$import_file_field = $( '#sc-admin-tools-form-import' );
			app.runtime_vars.doms.$import_sc_btn = $( '#sc-admin-tools-sc-import-btn' );

			// Migration DOMS.
			app.runtime_vars.doms.$importer_logs = $( '#sc-admin-importer-tec-logs' );
			app.runtime_vars.doms.$importer_logs_status = $( '#sc-admin-importer-tec-logs__status' );
		},

		/**
		 * Set defaults.
		 *
		 * @since 3.6.0
		 */
		setDefaults: function () {

			jconfirm.defaults = {
				typeAnimated: false,
				draggable: false,
				animateFromElement: false,
				boxWidth: '400px',
				useBootstrap: false,
			};
		},

		/**
		 * Bind events.
		 *
		 * @since 3.3.0
		 * @since 3.6.0 Modified where to get the assets URL.
		 */
		bindEvents() {

			// Listen to migrate button click.
			$( '#sc-admin-tools-import-btn' ).on( 'click', function( e ) {
				e.preventDefault();

				// Set the assets URL.
				app.runtime_vars.assets_url = sc_admin_importers.assets_url;

				// Set the strings.
				app.runtime_vars.strings = sc_admin_importers.strings;

				const $this = $( this );
				const warning = $this.data( 'warning' );

				if ( warning && warning.toString() === '1' ) {
					$.confirm( {
						backgroundDismiss: false,
						escapeKey: true,
						animationBounce: 1,
						type: 'orange',
						icon: app.getIcon( 'exclamation-circle-solid-orange' ),
						title: sc_admin_importers.strings.heads_up,
						content: sc_admin_importers.strings.recurring_events_warning,
						buttons: {
							confirm: {
								text: sc_admin_importers.strings.yes,
								btnClass: 'btn-confirm',
								keys: [ 'enter' ],
								action: function() {
									app.performImport( $this );
								}
							},
							cancel: {
								text: sc_admin_importers.strings.cancel,
								btnClass: 'btn-cancel',
							}
						}
					} );

					return;
				}

				app.performImport( $this );
			} );

			// Listen to import button click.
			$( '#sc-admin-tools-sc-import-btn' ).on( 'click', function( e ) {
				const $this = $( this );

				// Hide the text.
				$this.find( '.sc-admin-tools-sc-import-btn__text' ).addClass( 'sc-admin-tools__invisible' );

				// Add the spinner.
				$this.append( '<span class="sc-admin-tools-loading-spinner"></span>' );

				$this.blur();
			} );

			// Listen to file field change.
			app.runtime_vars.doms.$import_file_field.on( 'change', function( ev ) {

				if ( ev.target.value ) {
					app.runtime_vars.doms.$import_file_info_span.text( ev.target.value.split( '\\' ).pop() );
					app.runtime_vars.doms.$import_sc_btn.removeClass( 'sc-admin-tools-disabled' );
				}
			} );

			// Listen to ics import form submit.
			$( '#sc-admin-tools-import-form-ics' ).on( 'submit', function( e ) {

				e.preventDefault();

				// Set elements.
				app.runtime_vars.doms.$import_ics_form = $( 'form#sc-admin-tools-import-form-ics' );
				app.runtime_vars.doms.$import_ics_url_field = $( this ).find( '#sugar-calendar-setting-sc-admin-tools-ics-import-url' );
				app.runtime_vars.doms.$import_ics_btn = $( this ).find( '#sc-admin-tools-sc-import-ics-btn' );
				app.runtime_vars.doms.$import_ics_summary = $( '.sc-admin-tools-import-summary-ics' );

				// Set strings.
				app.runtime_vars.strings = sc_admin_ics_importers.strings;

				// Set the assets URL.
				app.runtime_vars.assets_url = sc_admin_ics_importers.assets_url;

				// ICS URL is set and not empty.
				if (
					app.runtime_vars.doms.$import_ics_url_field.val().length > 0
				) {

					// Set the ICS URL.
					app.runtime_vars.ics_url = app.runtime_vars.doms.$import_ics_url_field.val();

					// Setup the importer slug.
					app.runtime_vars.importer_slug = 'sugar-calendar-ics';

					// Disable the button.
					app.toggleEnabledIcsButtonState( false );

					// Run the importer.
					app.runIcsImporter();
				}
			} );
		},

		/**
		 * Toggle ICS button state.
		 *
		 * @since 3.6.0
		 *
		 * @param {boolean} is_enabled The state to set.
		 */
		toggleEnabledIcsButtonState( is_enabled ) {

			if ( is_enabled ) {
				app.runtime_vars.doms.$import_ics_btn.removeClass( 'sc-admin-tools__invisible' );
				app.runtime_vars.doms.$import_ics_btn.find( '.sc-admin-tools-loading-spinner' ).remove();
				app.runtime_vars.doms.$import_ics_btn.prop( 'disabled', false );
			} else {
				app.runtime_vars.doms.$import_ics_btn.addClass( 'sc-admin-tools__invisible' );
				app.runtime_vars.doms.$import_ics_btn.append( '<span class="sc-admin-tools-loading-spinner"></span>' );
				app.runtime_vars.doms.$import_ics_btn.blur();
				app.runtime_vars.doms.$import_ics_btn.prop( 'disabled', true );
			}
		},

		/**
		 * Toggle ICS summary state.
		 *
		 * @since 3.6.0
		 *
		 * @param {number} eventsImported The number of events imported.
		 */
		showIcsSummaryState( eventsImported ) {

			// Hide the form.
			app.runtime_vars.doms.$import_ics_form.addClass( 'hidden' );

			// Show the summary.
			app.runtime_vars.doms.$import_ics_summary.addClass( 'visible' );

			// If the events imported are more than 0, show the summary.
			// Show the item.
			app.runtime_vars.doms.$import_ics_summary
				.find( '.sc-admin-tools-import-summary__item-events-created' )
				.removeClass( 'hidden' );

			// Update number of events imported.
			app.runtime_vars.doms.$import_ics_summary
				.find( '.sc-admin-tools-import-summary__item-events-created .sc-admin-tools-import-summary-ics__item__value' )
				.text( eventsImported.created );

			// If the events updated are more than 0, show the summary.
			// Show the item.
			app.runtime_vars.doms.$import_ics_summary
				.find( '.sc-admin-tools-import-summary__item-events-updated' )
				.removeClass( 'hidden' );

			// Update number of events imported.
			app.runtime_vars.doms.$import_ics_summary
				.find( '.sc-admin-tools-import-summary__item-events-updated .sc-admin-tools-import-summary-ics__item__value' )
				.text( eventsImported.updated );
		},

		/**
		 * Perform the import.
		 *
		 * @since 3.3.0
		 *
		 * @param {jQuery} $btn The button that triggered the import.
		 */
		performImport( $btn ) {
			if ( typeof $btn.data( 'importer' ) !== 'undefined' ) {
				app.runtime_vars.importer_slug = $btn.data( 'importer' );
			}

			// Display the status container.
			$( '#sc-admin-importer-tec-status' )
				.text( sc_admin_importers.strings.migration_in_progress ).show();

			app.runImporter();

			$btn.prop( 'disabled', true );
			$btn.hide();
		},

		/**
		 * Returns prepared modal icon.
		 *
		 * @since 3.3.0
		 * @since 3.6.0 Modified where to get the URL.
		 *
		 * @param {string} icon The icon name from /assets/ to be used in modal.
		 *
		 * @returns {string} Modal icon HTML.
		 */
		getIcon( icon ) {

			return '"></i><img src="' + app.runtime_vars.assets_url + 'images/icons/' + icon + '.svg" style="width: 40px; height: 40px;" alt="Icon"><i class="';
		},

		/**
		 * Run the importer.
		 *
		 * @since 3.3.0
		 */
		runImporter() {

			$.post(
				sc_admin_importers.ajax_url,
				{
					nonce: sc_admin_importers.nonce,
					action: 'sc_admin_importer',
					importer_slug: app.runtime_vars.importer_slug,
					total_number_to_import: app.runtime_vars.total_number_to_import
				},
				function ( response ) {

					if ( ! response.success ) {
						app.retryAttempt();
						return;
					}

					if ( ! app.runtime_vars.last_migrated_context ) {
						app.runtime_vars.last_migrated_context = response.data.importer.process;
					}

					// Update the status dom.
					app.runtime_vars.doms.$importer_logs_status.text( sc_admin_importers.strings[ response.data.importer.status ] );

					if ( response.data.importer.status === 'complete' ) {

						$( '#sc-admin-importer-tec-status' )
							.text( sc_admin_importers.strings.migration_completed );

						if ( response.data.importer.error_html && response.data.importer.error_html.length > 0 ) {
							$( '#sc-admin-importer-tec-logs' ).after( response.data.importer.error_html );
						}

						return;
					}

					// These are import process that we don't have to show any UI to the users.
					if ( response.data.importer.process === 'hidden' ) {
						app.runImporter();
						return;
					}

					app.showLogs( response.data.importer.process, response.data.importer.progress, response.data.importer.total_number_to_import, response.data.importer.process_status );

					if ( response.data.importer.attendees_count ) {
						app.showLogs( 'attendees', response.data.importer.attendees_count, response.data.importer.attendees_total_count, response.data.importer.process_status );
					}

					app.runImporter();
				}
			).fail( function( res ) {
				app.retryAttempt();
			});
		},

		/**
		 * Run the ics importer.
		 *
		 * @since 3.6.0
		 *
		 * @param {boolean} clear_cache Whether to clear the cache.
		 */
		runIcsImporter( clear_cache = false ) {

			$.post(
				sc_admin_ics_importers.ajax_url,
				{
					nonce: sc_admin_ics_importers.nonce,
					action: 'sc_admin_importer',
					importer_slug: app.runtime_vars.importer_slug,
					total_number_to_import: app.runtime_vars.total_number_to_import,
					ics_url: app.runtime_vars.ics_url,
					clear_cache: clear_cache,
				},
				function ( response ) {

					if ( ! response.success ) {
						app.retryAttempt(
							function() {
								app.runIcsImporter( true );
							},
							function() {
								app.toggleEnabledIcsButtonState( true );
							}
						);

						return;
					}

					const // Response data.
						responseStatus = response.data.importer.status,
						responseTotalNumberToImport = response.data.importer.total_number_to_import,
						responseProgress = response.data.importer.progress,
						responseMessage = response.data.importer.message;

					switch ( responseStatus ) {

						case 'completed':
							app.showIcsSummaryState( responseProgress );
							break;

						case 'error':
							$.alert( {
								title: false,
								content: responseMessage,
								titleClass: 'sc-ics-importer-error-title',
								icon: app.getIcon( 'exclamation-circle-solid-orange' ),
								type: 'red',
								boxWidth: '400px',
								buttons: {
									confirm: {
										text: sc_admin_ics_importers.strings.ok,
										btnClass: 'sugar-calendar-btn sugar-calendar-btn-lg sugar-calendar-btn-primary',
										keys: ['enter'],
									},
								},
							} );
							break;

						case 'in_progress':
							app.runtime_vars.total_number_to_import.events = responseTotalNumberToImport;
							app.runIcsImporter();
							return;
					}

					// Enable the button.
					app.toggleEnabledIcsButtonState( true );
				}
			).fail( function( res ) {

				// Retry with ICS importer.
				app.retryAttempt( function() {
					app.runIcsImporter( true );
				},
				function() {
					app.toggleEnabledIcsButtonState( true );
				} );
			});
		},

		/**
		 * Show the logs of the import for the given context.
		 *
		 * @since 3.3.0
		 *
		 * @param {string} process_context                The import context.
		 * @param {number} progress_count                 The progress of the import.
		 * @param {mixed}  context_total_number_to_import The total number of items to import for the context
		 * @param {string} process_status                 The process status.
		 */
		showLogs( process_context, progress_count, context_total_number_to_import, process_status ) {

			// Update the number of success imports.
			app.runtime_vars.number_of_success_import[ process_context ] += progress_count;

			// First let's check if the importer process is already in the DOM.
			const importer_progress_dom_id = 'sc-admin-importer-tec-logs__progress-' + process_context;
			const importer_process_dom_id = 'sc-admin-importer-tec-logs__process-' + process_context;

			const successful_import_count = app.runtime_vars.number_of_success_import[ process_context ];

			if ( $( '#' + importer_process_dom_id ).length > 0 ) {
				// DOM is already created, just update the context.
				$( '#' + importer_progress_dom_id ).text( successful_import_count );
			} else {
				// Save the total number of items to import for the context.
				if ( ! app.runtime_vars.total_number_to_import[ process_context ] ) {
					app.runtime_vars.total_number_to_import[ process_context ] = context_total_number_to_import;
				}

				let total_count_string = '';

				if ( context_total_number_to_import !== undefined ) {
					total_count_string = '/' + app.runtime_vars.total_number_to_import[ process_context ];
				}

				/*
				 * This block should only run once per context.
				 */
				app.runtime_vars.doms.$importer_logs.append(
					'<div id="' + importer_process_dom_id + '" class="sc-admin-tools-migrate-context">' +
					'<div class="sc-admin-tools-migrate-context__status"><div class="sc-admin-tools-migrate-context__status__in-progress"></div></div>' +
					'<div class="sc-admin-tools-migrate-context__info">'
					+ sc_admin_importers.strings['migrated_' + process_context] + ' ' +
					'<span id="' + importer_progress_dom_id + '">' + successful_import_count + '</span>'
					+ total_count_string + '</div>' +
					'</div>'
				);
			}

			// Check if the migration of the context is complete.
			if ( successful_import_count >= app.runtime_vars.total_number_to_import[ process_context ] || process_status === 'complete' ) {
				const $status = $( `#sc-admin-importer-tec-logs__process-${process_context}` )
					.find( '.sc-admin-tools-migrate-context__status' );

				$status.html( '<div class="sc-admin-tools-migrate-context__status__complete"></div>' );
			}
		},

		/**
		 * Retry the migration.
		 *
		 * @since 3.3.0
		 * @since 3.6.0 Modified to use the custom and final callback.
		 *
		 * @param {function} customCallback      The custom callback.
		 * @param {function} customFinalCallback The custom final callback.
		 */
		retryAttempt( customCallback, customFinalCallback ) {

			if ( app.runtime_vars.number_of_retries >= 5 ) {

				alert( app.runtime_vars.strings.migration_failed );

				if ( customFinalCallback ) {
					customFinalCallback();
				}

				return;
			}

			++app.runtime_vars.number_of_retries;

			if ( customCallback ) {
				customCallback();
			} else {
				app.runImporter();
			}
		},
	};

	return app;
}( document, window, jQuery ) );

SCAdminImporters.init();
