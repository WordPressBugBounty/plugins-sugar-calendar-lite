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
				tickets: null,
				orders: null,
				attendees: null,
			},

			/**
			 * The number of successful imports per context.
			 *
			 * @type {number}
			 */
			number_of_success_import: {
				events: 0,
				tickets: 0,
				orders: 0,
				attendees: 0,
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
				 * jQuery DOM of the Importer button.
				 *
				 * @since 3.3.0
				 *
				 * @type {jQuery}
				 */
				$import_sc_btn: null,

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
		 * Bind events.
		 *
		 * @since 3.3.0
		 */
		bindEvents() {

			// Listen to migrate button click.
			$( '#sc-admin-tools-import-btn' ).on( 'click', function( e ) {
				e.preventDefault();

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
		 *
		 * @param {string} icon The icon name from /assets/ to be used in modal.
		 *
		 * @returns {string} Modal icon HTML.
		 */
		getIcon( icon ) {
			return '"></i><img src="' + sc_admin_importers.assets_url + 'images/icons/' + icon + '.svg" style="width: 40px; height: 40px;" alt="Icon"><i class="';
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

					app.showLogs( response.data.importer.process, response.data.importer.progress, response.data.importer.total_number_to_import );

					if ( response.data.importer.attendees_count ) {
						app.showLogs( 'attendees', response.data.importer.attendees_count, response.data.importer.attendees_total_count );
					}

					app.runImporter();
				}
			).fail( function( res ) {
				app.retryAttempt();
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
		 */
		showLogs( process_context, progress_count, context_total_number_to_import ) {

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
			if ( successful_import_count >= app.runtime_vars.total_number_to_import[ process_context ]  ) {
				const $status = $( `#sc-admin-importer-tec-logs__process-${process_context}` )
					.find( '.sc-admin-tools-migrate-context__status' );

				$status.html( '<div class="sc-admin-tools-migrate-context__status__complete"></div>' );
			}
		},

		/**
		 * Retry the migration.
		 *
		 * @since 3.3.0
		 */
		retryAttempt() {

			if ( app.runtime_vars.number_of_retries >= 5 ) {
				alert( sc_admin_importers.strings['migration_failed'] );
				return;
			}

			++app.runtime_vars.number_of_retries;
			app.runImporter();
		}
	};

	return app;
}( document, window, jQuery ) );

SCAdminImporters.init();
