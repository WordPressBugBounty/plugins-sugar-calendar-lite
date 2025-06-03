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
		 * @since 3.7.0 Add tags functionality.
		 */
		init: function ( settings ) {

			this.settings = settings;
			this.$calendarDropdown = $( '#sc_event_category' );
			this.$screenOptionsToggle = $( '#sugar-calendar-screen-options-toggle' );
			this.$screenOptionsMenu = $( '.sugar-calendar-screen-options-menu' );
			this.$columnFields = $( '[name="sugar-calendar[columns][]"]', this.$screenOptionsMenu );
			this.$gridColumnLayout = $( '#sugar-calendar-table-grid-column-layout' );
			this.$tableEventsList = $( '.sugar-calendar-table-events--list' );
			this.$listTableRows = $( '.sugar-calendar-table-events--list #the-list' );
			this.$tagsFilterSelect = $( '#sugar-calendar-tags-filter' );
			this.$tagsFilterButton = $( '#sugar-calendar-tags-filter-button' );
			this.$bulkEditTagsRow = $( '.sugar-calendar-bulk-edit-tags-row' );
			this.$bulkActionSelector = $( '#bulk-action-selector-top' );
			this.$tableNavActionButton1 = $( '.sugar-calendar-tablenav #doaction' );
			this.$tableNavActionButton2 = $( '.sugar-calendar-tablenav #doaction2' );
			this.$bulkEditTagsEventsWrapper = $( '.sugar-calendar-bulk-edit-tags-field-wrapper--events' );
			this.$bulkEditTagsEvents = $( '#sugar-calendar-bulk-edit-tags-events' );
			this.$bulkEditTagsTerms = $( '#sugar-calendar-bulk-edit-tags-terms' );
			this.$bulkEditTagsSave = $( '.sugar-calendar-bulk-edit-tags-save' );
			this.$bulkEditTagsCancel = $( '.sugar-calendar-bulk-edit-tags-cancel' );
			this.$bulkEditTagsMessage = $( '.sugar-calendar-bulk-edit-tags-message' );

			// Variables for tags functionality.
			this.currentTagsBackup = null;
			this.currentTagsChoicesObj = null;
			this.bulkEditIsOpen = false;

			this.bindEvents();
			this.initializeTooltips();
			this.initializeTagsTool();
			this.initializeTagsFilter();

			// Initialize listeners for choices when items are added or removed.
			this.initializeChoicesEventHandlers();
		},

		bindEvents: function () {

			this.$calendarDropdown.on( 'change', ( e ) => $( e.target ).parents( 'form' ).submit() );
			this.$screenOptionsToggle.on( 'click', this.onScreenOptionsToggleClick.bind( this ) );
			this.$columnFields.on( 'click', this.onColumnsChange.bind( this ) );
			this.$bulkEditTagsCancel.on( 'click', this.cancelBulkEditTagsClick.bind( this ) );
		},

		onScreenOptionsToggleClick: function ( e ) {
			this.$screenOptionsToggle.toggleClass( 'open' );
			this.$screenOptionsMenu.fadeToggle( 200 );
		},

		/**
		 * Update columns when the screen options are changed.
		 *
		 * @since 3.7.0
		 */
		onColumnsChange: function ( e ) {

			this.updateColumnsChange(
				this.getAllColumns()
			);
		},

		/**
		 * Get all columns.
		 *
		 * @since 3.7.0
		 *
		 * @returns {Array} Columns.
		 */
		getAllColumns: function () {

			const app = this;

			const columns = this.$columnFields.map( function () {

				const $this = $( this );

				let isVisible = $this.is( ':checked' );

				// Title column is always visible.
				if ( $this.val() === 'title' ) {

					isVisible = true;

				} else if ( $this.val() === 'tags' && app.bulkEditIsOpen ) {

					// Tags column is visible when bulk edit is open.
					isVisible = true;
				}

				return {
					id: $this.val(),
					visible: isVisible,
				};
			} ).toArray();

			return columns;
		},

		/**
		 * Update columns when the screen options are changed.
		 *
		 * @since 3.7.0
		 */
		updateColumnsChange: function ( columns ) {

			this.hideColumns( columns );
			this.hideEventSpans( columns );
			this.updateGridColumnLayout( columns );
			this.updateHiddenColumns( columns );

			// Adjust the width of the Bulk Edit Tags wrapper events.
			this.adjustBulkEditTagsColspans( columns );
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
		},

		/**
		 * Initialize tags tool.
		 *
		 * Live edit tags in the events table.
		 *
		 * @since 3.7.0
		 */
		initializeTagsTool: function () {

			// Bind events for tags editing
			$( this.$tableEventsList )
				.on( 'click', '.sugar-calendar-column-tags-edit-link', this.editTagsClick.bind(this) )
				.on( 'click', '.sugar-calendar-column-tags-edit-cancel', this.cancelEditTagsClick.bind(this) )
				.on( 'click', '.sugar-calendar-column-tags-edit-save', this.saveTagsClick.bind(this) )
				.on( 'click', '.sugar-calendar-column-tags-form .choices .choices__arrow', this.closeChoices.bind(this) )
				.on( 'keydown', '.sugar-calendar-column-tags-form input', this.addCustomTagInput.bind(this) );

			// Bind events for bulk edit tags.
			$( this.$tableNavActionButton1 )
				.on( 'click', this.confirmBulkAction.bind(this) );
			$( this.$tableNavActionButton2 )
				.on( 'click', this.confirmBulkAction.bind(this) );
			$( this.$bulkEditTagsEvents )
				.on( 'removeItem', this.bulkEditTagsFormRemoveItem.bind(this) );
			$( this.$bulkEditTagsSave )
				.on( 'click', this.saveBulkEditTagsClick.bind(this) );
		},

		/**
		 * Confirm forms bulk action.
		 *
		 * @since 3.7.0
		 */
		confirmBulkAction: function( event ) {

			if ( this.$bulkActionSelector.val() === 'edit_tags' ) {

				event.preventDefault();

				this.openBulkEditTags();

				return;
			}
		},

		/**
		 * Open bulk edit tags UI.
		 *
		 * @since 3.7.0
		 */
		openBulkEditTags: function() {

			let events = [],
				eventsValue = [],
				tagsValue = [];

			this.$listTableRows.find( 'input:checked' ).each( function() {

				let
					$input = $( this ),
					$tr = $input.closest( 'tr' ),
					$name = $tr.find( '.column-title > strong a:first-child span' ),
					$tags = $tr.find( '.sugar-calendar-column-tags-links' ),
					formTags = $tags.data( 'tags' ).toString() || '';

				events.push( {
					value: $input.val(),
					label: _.escape( $name.text() ),
				} );

				eventsValue.push( $input.val() );
				formTags = formTags.length ? formTags.split( ',' ) : [];
				tagsValue = _.union( tagsValue, formTags );
			} );

			// If no events are selected, return.
			if ( events.length === 0 ) {
				return;
			}

			// Get all columns and ensure tags is visible.
			let columns = this.getAllColumns();

			columns.forEach( column => {
				if ( column.id === 'tags' ) {
					column.visible = true;
				}
			} );

			this.updateColumnsChange( columns );

			// Show the bulk edit tags row.
			this.$bulkEditTagsRow.removeClass( 'sugar-calendar-hidden' );

			// Disable column options for tag.
			this.bulkEditIsOpen = true;

			// Initialize Choices.js for events.
			this.initChoicesJS( this.$bulkEditTagsEvents )
				.clearStore()
				.setChoices(
					events,
					'value',
					'label',
					true
				)
				.setChoiceByValue( eventsValue );

			// Initialize Choices.js for tags.
			this.initChoicesJS( this.$bulkEditTagsTerms )
				.removeActiveItems()
				.setChoiceByValue( tagsValue );

			// Update message.
			this.updateBulkEditTagsFormMessage( eventsValue );
		},

		/**
		 * Adjust the width of the Bulk Edit Tags td colspans.
		 *
		 * @since 3.7.0
		 *
		 * @param {Array} columns Columns.
		 */
		adjustBulkEditTagsColspans: function( columns ) {

			const
				$eventsTd = this.$bulkEditTagsEvents.parents( 'td' ),
				$tagsTd = this.$bulkEditTagsTerms.parents( 'td' ),
				$saveTd = this.$bulkEditTagsSave.parents( 'td' ),
				$messageTd = this.$bulkEditTagsMessage.parents( 'td' );

			let
				eventsTdColspan = 2,
				tagsTdColspan = 1;

			columns.forEach( column => {

				if (
					( column.id === 'start' && column.visible )
					||
					( column.id === 'end' && column.visible )
				) {
					eventsTdColspan += 1;
				}

				if (
					( column.id === 'duration' && column.visible )
					||
					( column.id === 'repeat' && column.visible )
				) {
					tagsTdColspan += 1;
				}
			} );

			$eventsTd.attr( 'colspan', eventsTdColspan );
			$tagsTd.attr( 'colspan', tagsTdColspan );
			$saveTd.attr( 'colspan', eventsTdColspan + tagsTdColspan );
			$messageTd.attr( 'colspan', eventsTdColspan + tagsTdColspan );
		},

		/**
		 * Update the message below the Bulk Edit Tags form.
		 *
		 * @since 3.7.0
		 *
		 * @param {Array} eventsValue Events value.
		 */
		updateBulkEditTagsFormMessage: function( eventsValue ) {

			var msg = this.settings.strings.bulk_edit_n_events;

			if ( eventsValue.length === 1 ) {
				msg = this.settings.strings.bulk_edit_one_event;
			}

			this.$bulkEditTagsMessage.html(
				msg.replace( '%d', eventsValue.length )
			);
		},

		/**
		 * Remove item from the Bulk Edit Tags form.
		 *
		 * @since 3.7.0
		 *
		 * @param {object} event Event object.
		 */
		bulkEditTagsFormRemoveItem: function( event ) {

			const formsValue = $( event.target ).data( 'choicesjs' ).getValue( true );

			if ( formsValue.length === 0 ) {
				this.cancelBulkEditTagsClick( event );
			}

			this.updateBulkEditTagsFormMessage( formsValue );
		},

		/**
		 * Cancel bulk edit tags.
		 *
		 * @since 3.7.0
		 */
		cancelBulkEditTagsClick: function( event ) {

			event.preventDefault();

			this.bulkEditIsOpen = false;

			this.$bulkEditTagsRow.addClass( 'sugar-calendar-hidden' );
		},

		/**
		 * Initialize Choices.js
		 *
		 * @since 3.7.0
		 *
		 * @param {jQuery} $select Select input element
		 *
		 * @return {Choices|boolean} Choices.js instance or false
		 */
		initChoicesJS: function( $select ) {

			// Skip in certain cases.
			if (
				! this.settings.choicesjs_config
				||
				! $select.length
				||
				typeof window.Choices !== 'function'
			) {
				return false;
			}

			// Create configuration.
			const config = this.settings.choicesjs_config;

			// Set noResultsText to indicate users can add new tags.
			if ( $select.attr( 'id' ) === 'sugar-calendar-tags-filter' ) {

				config.noResultsText = this.settings.strings.no_results_text;

			} else {

				config.noResultsText = this.settings.strings.add_new_tag;
			}

			// Configure callback for initialization.
			config.callbackOnInit = function () {

				$select.closest( '.choices__inner' ).append( '<div class="choices__arrow"></div>' );

				SugarCalendar.Admin.Events.showMoreButtonForChoices( this.containerOuter.element );
			};

			// Initialize or get existing Choices.js instance.
			let choicesObj;

			if ( $select.data( 'choice' ) === 'active' ) {
				choicesObj = $select.data( 'choicesjs' );
			} else {
				choicesObj = new Choices( $select[0], config );
			}

			// Backup current value.
			const currentValue = choicesObj.getValue( true );

			// Update all tags choices.
			choicesObj
				.clearStore()
				.setChoices(
					this.settings.all_tags_choices || [],
					'value',
					'label',
					true
				)
				.setChoiceByValue( currentValue );

			// Store the Choices.js instance with the select.
			$select.data( 'choicesjs', choicesObj );

			return choicesObj;
		},

		/**
		 * Close Choices.js instance.
		 *
		 * @since 3.7.0
		 *
		 * @param {object} event Event object.
		 */
		closeChoices: function ( event ) {

			event.preventDefault();

			// If the dropdown is active, close it.
			if ( this.currentTagsChoicesObj && this.currentTagsChoicesObj.dropdown.isActive ) {
				this.currentTagsChoicesObj.hideDropdown();
			}
		},

		/**
		 * Click on the Edit link in the Tags column.
		 *
		 * @since 3.7.0
		 *
		 * @param {object} event Event object.
		 */
		editTagsClick: function( event ) {

			event.preventDefault();

			const
				$link = $( event.target ),
				$td = $link.closest( 'td' ),
				$tbody = $td.closest( 'tbody' ),
				$tagsLinks = $td.find( '.sugar-calendar-column-tags-links' ),
				$tagsForm = $td.find( '.sugar-calendar-column-tags-form' ),
				$select = $tagsForm.find( 'select' );

			// Hide all other opened tags edit form, but exclude those in bulk edit row.
			$tbody.find( '.sugar-calendar-column-tags-links.sugar-calendar-hidden' )
				.not( '.sugar-calendar-bulk-edit-tags-row *' )
				.removeClass( 'sugar-calendar-hidden' );

			$tbody.find( '.sugar-calendar-column-tags-form:not(.sugar-calendar-hidden)' )
				.not( '.sugar-calendar-bulk-edit-tags-row *' )
				.addClass( 'sugar-calendar-hidden' );

			// Show current tags edit form, hide links.
			$tagsLinks.addClass( 'sugar-calendar-hidden' );
			$tagsForm.removeClass( 'sugar-calendar-hidden' );

			// Store current opened Choice.js object and its value.
			this.currentTagsChoicesObj = this.initChoicesJS( $select );
			this.currentTagsBackup = this.currentTagsChoicesObj.getValue( true );
		},

		/**
		 * Click on the Cancel button in the Tags edit form.
		 *
		 * @since 3.7.0
		 *
		 * @param {object} event Event object.
		 */
		cancelEditTagsClick: function( event ) {

			event.preventDefault();

			const
				$btn = $( event.target ),
				$td = $btn.closest( 'td' ),
				$tagsLinks = $td.find( '.sugar-calendar-column-tags-links' ),
				$tagsForm = $td.find( '.sugar-calendar-column-tags-form' );

			// Restore saved value from the backup.
			this.currentTagsChoicesObj
				.removeActiveItems()
				.setChoiceByValue( this.currentTagsBackup );

			// Hide form, show links.
			$tagsLinks.removeClass( 'sugar-calendar-hidden' );
			$tagsForm.addClass( 'sugar-calendar-hidden' );
		},

		/**
		 * Get tags value from Choices.js
		 *
		 * @since 3.7.0
		 *
		 * @param {Choices} choicesObj Choices.js instance
		 * @return {Array} Array of tag objects
		 */
		getTagsValue: function( choicesObj ) {

			if ( ! choicesObj || typeof choicesObj.getValue !== 'function' ) {
				return [];
			}

			const tagsValue = choicesObj.getValue();
			const tags = [];

			for ( let i = 0; i < tagsValue.length; i++ ) {
				tags.push( {
					value: tagsValue[i].value,
					label: tagsValue[i].label,
				} );
			}

			return tags;
		},

		/**
		 * Click on the Save button in the Tags edit form.
		 *
		 * @since 3.7.0
		 *
		 * @param {object} event Event object.
		 */
		saveTagsClick: function( event ) {

			event.preventDefault();

			const
				$btn = $( event.target ),
				$td = $btn.closest( 'td' ),
				$tagsLinks = $td.find( '.sugar-calendar-column-tags-links' ),
				$tagsForm = $td.find( '.sugar-calendar-column-tags-form' ),
				$spinner = $tagsForm.find( '.sugar-calendar-spinner' ),
				eventId = $tagsLinks.data( 'event-id' );

			// Show spinner, hide save button.
			$btn.addClass( 'sugar-calendar-hidden' );
			$spinner.removeClass( 'sugar-calendar-hidden' );

			this.saveTagsAjax(
				{
					events: [ eventId ],
					tags: this.getTagsValue( this.currentTagsChoicesObj ),
				},
				( response ) => {

					if ( ! response.success ) {

						// Show error message.
						alert( response.data || this.settings.strings.error );

						return;
					}

					// Update tags links in the column.
					$tagsLinks.find( '.sugar-calendar-column-tags-links-list' ).html( response.data.tags_links );

					// Update tags ids data attribute.
					$tagsLinks.data( 'tags', response.data.tags_ids );

					// Update choices data.
					if ( response.data.all_tags_choices ) {
						this.settings.all_tags_choices = response.data.all_tags_choices;
					}

					// Update the select options.
					const $select = $tagsForm.find( 'select' );
					$select.html( response.data.tags_options );

					// Update Choices.js instance if it exists.
					const choicesObj = $select.data( 'choicesjs' );

					if ( choicesObj ) {
						choicesObj
							.clearStore()
							.setChoices(
								this.settings.all_tags_choices || [],
								'value',
								'label',
								true
							);

						if ( response.data.tags_ids ) {
							choicesObj.setChoiceByValue( response.data.tags_ids.split( ',' ) );
						}
					}
				},
				() => {

					// Hide spinner, show save button.
					$btn.removeClass( 'sugar-calendar-hidden' );
					$spinner.addClass( 'sugar-calendar-hidden' );

					// Hide form, show tags links.
					$tagsLinks.removeClass( 'sugar-calendar-hidden' );
					$tagsForm.addClass( 'sugar-calendar-hidden' );
				}
			);
		},

		/**
		 * Click on the Save button in the Bulk Edit Tags form.
		 *
		 * @since 3.7.0
		 *
		 * @param {object} event Event object.
		 */
		saveBulkEditTagsClick: function( event ) {

			event.preventDefault();

			const
				app = this,
				$btn = $( event.target ),
				$spinner = $btn.find( '.sugar-calendar-spinner' ),
				data = {
					events: this.$bulkEditTagsEvents.data( 'choicesjs' ).getValue( true ),
					tags: this.getTagsValue( this.$bulkEditTagsTerms.data( 'choicesjs' ) ),
				};

			// Show spinner.
			$spinner.removeClass( 'sugar-calendar-hidden' );

			this.saveTagsAjax(
				data,
				( res ) => {

					// Update tags links and options in selected rows.
					$( '#the-list .tags.column-tags' ).each( function() {

						var $td = $( this ),
							$columnLinks = $td.find( '.sugar-calendar-column-tags-links' ),
							eventId = $columnLinks.data( 'event-id' ) + '',
							$select = $td.find( '.sugar-calendar-column-tags-form select' ),
							choicesObj = $select.data( 'choicesjs' );

						if ( data.events.indexOf( eventId ) < 0 ) {
							return;
						}

						// Update tags links in the column.
						$columnLinks.data( 'tags', res.data.tags_ids );

						// Update tags links in the column.
						$columnLinks.find( '.sugar-calendar-column-tags-links-list' ).html( res.data.tags_links );

						// Update tags options in still not converted selects.
						$select.html( res.data.tags_options );

						if ( choicesObj ) {
							choicesObj
								.clearStore()
								.setChoices(
									app.settings.all_tags_choices || [],
									'value',
									'label',
									true
								)
								.setChoiceByValue( res.data.tags_ids.split( ',' ) );
						}
					} );
				},
				() => {

					// Hide spinner.
					$spinner.addClass( 'sugar-calendar-hidden' );

					// Hide form.
					this.$bulkEditTagsRow.addClass( 'sugar-calendar-hidden' );
				}
			);
		},

		/**
		 * Save tags AJAX call routine.
		 *
		 * @since 3.7.0
		 *
		 * @param {object}   data   Post data.
		 * @param {Function} done   Callback on success.
		 * @param {Function} always Always callback.
		 */
		saveTagsAjax: function( data, done, always ) {

			const app = this;

			$.post(
				app.settings.ajax_url,
				$.extend(
					{
						action: 'sugar_calendar_save_event_tags',
						nonce: app.settings.strings.nonce,
					},
					data
				)
			).done( function( res ) {

				if ( ! res.success || ! res.data ) {

					alert( res.data || app.settings.strings.error );

					return;
				}

				app.updateAllTagsChoices( res.data.all_tags_choices );

				if ( typeof done === 'function' ) {
					done( res );
				}

			} ).fail( function( jqXHR, textStatus, errorThrown ) {

				// Show generic error message.
				alert( app.settings.strings.error );

			} ).always( function() {

				if ( typeof always === 'function' ) {
					always();
				}
			} );
		},

		/**
		 * Update all tags choices storage.
		 *
		 * @since 3.7.0
		 *
		 * @param {Array} allTagsChoices New all tags choices.
		 */
		updateAllTagsChoices: function( allTagsChoices ) {

			if ( ! allTagsChoices ) {
				return;
			}

			this.settings.all_tags_choices = allTagsChoices;

			// Update Tags Filter items.
			this.initChoicesJS( this.$tagsFilterSelect );
		},

		/**
		 * Add custom tag on keyboard input
		 *
		 * @since 3.7.0
		 *
		 * @param {object} event Event object.
		 */
		addCustomTagInput: function( event ) {

			// Only process for Enter or comma key.
			if ( [ 'Enter', ',' ].indexOf( event.key ) < 0 ) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();

			const $input = $( event.target );
			const $select = $input.closest( '.choices' ).find( 'select' );
			const choicesObj = $select.data( 'choicesjs' );

			// Verify we have a Choices instance and input value.
			if ( ! choicesObj || event.target.value.length === 0 ) {
				return;
			}

			// Get the tag label and clean it - add escaping for security.
			const tagLabel = _.escape( event.target.value.trim() );

			// Skip if empty.
			if ( tagLabel === '' ) {
				choicesObj.clearInput();
				return;
			}

			// Get existing tag labels more efficiently.
			const existingLabels = _.map( choicesObj.getValue(), 'label' ).map( ( label ) => {
				return label.toLowerCase().trim();
			} );

			// Skip if already exists.
			if ( existingLabels.indexOf( tagLabel.toLowerCase() ) >= 0 ) {
				choicesObj.clearInput();
				return;
			}

			// Check if tag exists in all available tags first.
			const existingTag = _.find( this.settings.all_tags_choices || [], {
				label: tagLabel,
			} );

			if ( existingTag && existingTag.value ) {
				// Use existing tag if found.
				choicesObj.setChoiceByValue( existingTag.value );
			} else {
				// Add as new tag.
				choicesObj.setChoices(
					[ {
						value: tagLabel,
						label: tagLabel,
						selected: true,
					} ],
					'value',
					'label',
					false
				);
			}

			// Clear the input field.
			choicesObj.clearInput();
		},

		/**
		 * Display/hide show more icon inside multiselect dropdown.
		 *
		 * @since 3.7.0
		 *
		 * @param {string} container Container element.
		*/
		showMoreButtonForChoices( container ) {

			if ( $( container ).data( 'type' ) === 'select-one' ) {
				return;
			}

			const first = $( container ).find( '.choices__list--multiple .choices__item' ).first(),
				last = $( container ).find( '.choices__list--multiple .choices__item' ).last();

			$( container ).removeClass( 'choices__show-more' );

			if ( first.length > 0 && last.length > 0 && first.position().top !== last.position().top ) {
				$( container ).addClass( 'choices__show-more' );
			}
		},

		/**
		 * Initialize event handlers for choices.
		 *
		 * @since 3.7.0
		 */
		initializeChoicesEventHandlers() {

			// Show more button for choices.
			$( document ).on( 'addItem removeItem', '.choices:not(.is-disabled)', function() {
				SugarCalendar.Admin.Events.showMoreButtonForChoices( this );
			} );

			// Remove focus from input when dropdown is hidden.
			$( document ).on( 'hideDropdown', '.choices:not(.is-disabled)', function() {
				$( this ).find( '.choices__inner input.choices__input' ).trigger( 'blur' );
			} );
		},

		/**
		 * Initialize tags filter in the table nav.
		 *
		 * @since 3.7.0
		 */
		initializeTagsFilter: function() {

			// Skip if filter select doesn't exist.
			if ( ! this.$tagsFilterSelect.length ) {
				return;
			}

			// Initialize ChoicesJS for the filter dropdown.
			const filterChoices = this.initChoicesJS( this.$tagsFilterSelect );

			// Skip if initialization failed.
			if ( ! filterChoices ) {
				return;
			}

			// Handle filter button click.
			this.$tagsFilterButton.on( 'click', function() {
				const tagId = $( '#sugar-calendar-tags-filter' ).val();
				const currentUrl = window.location.href;

				// Create new URL with or without tags parameter.
				let newUrl;
				if ( tagId ) {
					newUrl = SugarCalendar.Admin.Events.addQueryArg( 'sc_event_tags', tagId, currentUrl );
				} else {
					newUrl = SugarCalendar.Admin.Events.removeQueryArg( 'sc_event_tags', currentUrl );
				}

				// Redirect to filtered URL.
				window.location.href = newUrl;
			} );
		},

		/**
		 * Add query argument to URL.
		 *
		 * @since 3.7.0
		 *
		 * @param {string} key   Query key.
		 * @param {string} value Query value.
		 * @param {string} url   URL to modify.
		 *
		 * @return {string} Modified URL.
		 */
		addQueryArg: function( key, value, url ) {
			const re = new RegExp( '([?&])' + key + '=.*?(&|$)', 'i' );
			const separator = -1 !== url.indexOf( '?' ) ? '&' : '?';

			if ( url.match( re ) ) {
				return url.replace( re, '$1' + key + '=' + value + '$2' );
			}

			return url + separator + key + '=' + value;
		},

		/**
		 * Remove query argument from URL.
		 *
		 * @since 3.7.0
		 *
		 * @param {string} key Query key.
		 * @param {string} url URL to modify.
		 *
		 * @return {string} Modified URL.
		 */
		removeQueryArg: function( key, url ) {
			const urlParts = url.split( '?' );

			if ( urlParts.length >= 2 ) {
				const prefix = encodeURIComponent( key ) + '=';
				const parts = urlParts[1].split( /[&;]/g );

				// Reverse iteration to remove from the end.
				for ( let i = parts.length; i-- > 0; ) {
					if ( -1 !== parts[i].lastIndexOf( prefix, 0 ) ) {
						parts.splice( i, 1 );
					}
				}

				url = urlParts[0] + ( parts.length > 0 ? '?' + parts.join( '&' ) : '' );
			}

			return url;
		},
	};

	SugarCalendar.Admin.Events.init( settings );

	window.SugarCalendar = SugarCalendar;

} )( jQuery, tippy, sugar_calendar_admin_events );
