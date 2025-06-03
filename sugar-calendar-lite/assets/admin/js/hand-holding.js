/* globals */
'use strict';

/**
 * Hand-holding JS.
 * 
 * @since 3.7.0
 */
var SCHandHolding = window.SCHandHolding || ( function( document, window, $ ) {

	/**
	 * Element DOM.
	 *
	 * @since 3.7.0
	 */
	const $el = {
		$overlay: $( '#sc-hand-holding__overlay' ),
		$tooltip: $( '#sc-hand-holding__tooltip' ),
		$tooltipArrow: $( '#sc-hand-holding__tooltip__arrow' ),
		$tooltipClose: $( '#sc-hand-holding__tooltip__close' ),
		$tooltipTitle: $( '#sc-hand-holding__tooltip strong' ),
		$tooltipContent: $( '#sc-hand-holding__tooltip #sc-hand-holding__tooltip__content' ),
		$tooltipCurrentProgress: $( '#sc-hand-holding__tooltip__footer__progress__current' ),
		$tooltipTotalProgress: $( '#sc-hand-holding__tooltip__footer__progress__total' ),
		$nextBtn: $( '#sc-hand-holding__tooltip__footer__next' ),
		$saveDraftBtn: $( '#save-post' ),
	};

	/**
	 * Public functions and properties.
	 *
	 * @since 3.7.0
	 *
	 * @type {object}
	 */
	const app = {

		/**
		 * Whether to count as exit browser.
		 *
		 * @since 3.7.0
		 *
		 * @type {boolean}
		 */
		shouldCountAsExitBrowser: true,

		/**
		 * jQuery confirm start modal.
		 *
		 * @since 3.7.0
		 *
		 * @type {object}
		 */
		jcStart: false,

		/**
		 * jQuery confirm cancel modal.
		 *
		 * @since 3.7.0
		 *
		 * @type {object}
		 */
		jcCancel: false,

		/**
		 * jQuery confirm end modal.
		 *
		 * @since 3.7.0
		 *
		 * @type {object}
		 */
		jcEnd: false,

		/**
		 * Current step counter.
		 *
		 * @since 3.7.0
		 *
		 * @type {number}
		 */
		stepCounter: false,

		/**
		 * Start the engine.
		 *
		 * @since 3.7.0
		 */
		init: function() {
			
			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 3.7.0
		 */
		ready: function() {

			switch ( sugar_calendar_hand_holding.status ) {
				case 'publish':
					app.initJCEnd();
					break;

				case 'complete':
					break;

				default:
					app.initJCStart();
					break;
			}

			$el.$tooltipTotalProgress.text( sugar_calendar_hand_holding.steps.length );

			app.events();
		},

		/**
		 * Initialize the start modal.
		 *
		 * @since 3.7.0
		 */
		initJCStart: function() {
			app.jcStart = $.confirm({
				autoClose: false,
				backgroundDismiss: false,
				boxWidth: '600px',
				buttons: {
					cancel: {
						isHidden: true,
					}
				},
				content: '<div id="sc-hand-holding__start__jquery-confirm-content" class="sc-hand-holding__jquery-confirm-content">'
					+ '<div id="sc-hand-holding__start__jquery-confirm-content__header" class="sc-hand-holding__jquery-confirm-content__header">'
						+ '<img src="' + sugar_calendar_hand_holding.strings.start_modal.image_url + '" />'
					+ '</div>'
					+ '<div id="sc-hand-holding__start__jquery-confirm-content__body" class="sc-hand-holding__jquery-confirm-content__body">'
						+ '<div id="sc-hand-holding__start__jquery-confirm-content__body__text" class="sc-hand-holding__jquery-confirm-content__body__text">'
							+ '<h4>' + sugar_calendar_hand_holding.strings.start_modal.content_title + '</h4>'
							+ '<p>' + sugar_calendar_hand_holding.strings.start_modal.content + '</p>'
						+ '</div>'
						+ '<div id="sc-hand-holding__start__jquery-confirm-content__body__buttons" class="sc-hand-holding__jquery-confirm-content__body__buttons sc-hand-holding__btn sc-hand-holding__btn-primary">'
							+ '<a href="#">' + sugar_calendar_hand_holding.strings.start_modal.title + '</a>'
						+ '</div>'
					+ '</div>'
				+ '</div>',
				draggable: false,
				escapeKey: false,
				theme: 'sc-hand-holding',
				title: '',
				useBootstrap: false,
				onContentReady: function() {
					let jc = this;

					this.$content.find( '#sc-hand-holding__start__jquery-confirm-content__body__buttons' ).on( 'click', function( e ) {
						e.preventDefault();

						app.updateHandHoldingStatus( 'start' );

						jc.close();
						app.start();
					} );
				}
			});
		},

		/**
		 * Initialize the end modal.
		 *
		 * @since 3.7.0
		 */
		initJCEnd: function() {

			app.jcEnd = $.confirm({
				autoClose: false,
				backgroundDismiss: false,
				boxWidth: '600px',
				buttons: {
					cancel: {
						isHidden: true,
					}
				},
				content: '<div id="sc-hand-holding__end__jquery-confirm-content" class="sc-hand-holding__jquery-confirm-content">'
					+ '<div id="sc-hand-holding__end__jquery-confirm-content__header" class="sc-hand-holding__jquery-confirm-content__header">'
						+ '<img src="' + sugar_calendar_hand_holding.strings.end_modal.image_url + '" />'
					+ '</div>'
					+ '<div id="sc-hand-holding__end__jquery-confirm-content__body" class="sc-hand-holding__jquery-confirm-content__body">'
						+ '<div id="sc-hand-holding__end__jquery-confirm-content__body__text" class="sc-hand-holding__jquery-confirm-content__body__text">'
							+ '<h4>' + sugar_calendar_hand_holding.strings.end_modal.content_title + '</h4>'
							+ '<p>' + sugar_calendar_hand_holding.strings.end_modal.content + '</p>'
						+ '</div>'
						+ '<div class="sc-hand-holding__divider"></div>'
						+ '<div id="sc-hand-holding__end__jquery-confirm-content__buttons" class="sc-hand-holding__jquery-confirm-content__buttons">'
							+ '<a class="sc-hand-holding__btn sc-hand-holding__btn-primary" id="sc-hand-holding__end__jquery-confirm-content__buttons__finish" href="#">' + sugar_calendar_hand_holding.strings.end_modal.button_finish.label + '</a>'
							+ '<a target="_blank" id="sc-hand-holding__end__jquery-confirm-content__buttons__documentation" href="' + sugar_calendar_hand_holding.urls.sc_ext_docs + '">' + sugar_calendar_hand_holding.strings.end_modal.button_docs.label + '</a>'
						+ '</div>'
					+ '</div>'
				+ '</div>',
				draggable: false,
				escapeKey: false,
				onContentReady: function() {
					let jc = this;

					this.$content.find( '#sc-hand-holding__end__jquery-confirm-content__buttons__finish' ).on( 'click', function( e ) {
						e.preventDefault();

						app.shouldCountAsExitBrowser = false;

						app.updateHandHoldingStatus( 'complete', function() {
							window.location.href = sugar_calendar_hand_holding.urls.admin_calendar;
						} );
					} );
				},
				theme: 'sc-hand-holding',
				title: '',
				useBootstrap: false,
			});
		},

		/**
		 * Register events.
		 *
		 * @since 3.7.0
		 */
		events: function() {

			window.addEventListener(
				'pagehide',
				app.tabExit
			);
			$el.$tooltipClose.on( 'click', app.closeTooltip );
			$el.$nextBtn.on( 'click', app.nextBtnClick );
			$el.$saveDraftBtn.one( 'click', app.saveDraftBtnClick );
			$( '#publish' ).one( 'click', app.publishBtnClick );
		},

		/**
		 * Count tab exit as cancelled.
		 *
		 * @since 3.7.0
		 *
		 * @param {Event} e The Event object.
		 */
		tabExit: function( e ) {

			if ( ! app.shouldCountAsExitBrowser ) {
				return;
			}

			// Handle the case where the browser doesn't support navigator.sendBeacon.
			if ( ! navigator.sendBeacon ) {
				return;
			}

			const formData = new FormData();
			formData.append( 'action', 'sc_hand_holding_status' );
			formData.append( 'nonce', sugar_calendar_hand_holding.nonce );
			formData.append( 'status', 'exit-browser' );
			formData.append( 'step', sugar_calendar_hand_holding.steps[app.stepCounter].key );

			navigator.sendBeacon(
				sugar_calendar_hand_holding.urls.ajax_url,
				formData
			);
		},

		/**
		 * Start the hand holding process.
		 *
		 * @since 3.7.0
		 */
		start: function() {

			// We want to activate the overlay.
			$el.$overlay.css( 'display', 'block' );

			app.nextStep();
		},

		/**
		 * Close the tooltip.
		 */
		closeTooltip: function() {

			$el.$tooltip.css( 'display', 'none' );
			$el.$overlay.css( 'display', 'none' );

			if ( app.jcCancel === false ) {
				app.jcCancel = $.confirm({
					autoClose: false,
					backgroundDismiss: false,
					boxWidth: '500px',
					buttons: {
						cancel: {
							isHidden: true,
						}
					},
					content: '<div id="sc-hand-holding__cancel__jquery-confirm-content" class="sc-hand-holding__jquery-confirm-content">'
						+ '<div id="sc-hand-holding__cancel__jquery-confirm-content__body" class="sc-hand-holding__jquery-confirm-content__body">'
							+ '<h4>' + sugar_calendar_hand_holding.strings.cancel_modal.content_title + '</h4>'
							+ '<div id="sc-hand-holding__cancel__jquery-confirm-content__buttons" class="sc-hand-holding__jquery-confirm-content__buttons">'
								+ '<a id="sc-hand-holding__cancel__jquery-confirm-content__buttons__no" href="#">' + sugar_calendar_hand_holding.strings.cancel_modal.no.label + '</a>'
								+ '<a id="sc-hand-holding__cancel__jquery-confirm-content__buttons__yes" href="#">' + sugar_calendar_hand_holding.strings.cancel_modal.yes.label + '</a>'
							+ '</div>'
						+ '</div>'
					+ '</div>',
					draggable: false,
					escapeKey: false,
					onContentReady: function() {
						let jc = this;

						// "No" button resumes the hand holding.
						this.$content.find( '#sc-hand-holding__cancel__jquery-confirm-content__buttons__no' ).on( 'click', function( e ) {
							e.preventDefault();

							jc.close();

							app.continueSteps();
						} );

						// "Yes" cancels the hand holding.
						this.$content.find( '#sc-hand-holding__cancel__jquery-confirm-content__buttons__yes' ).on( 'click', function( e ) {
							e.preventDefault();

							app.shouldCountAsExitBrowser = false;

							app.updateHandHoldingStatus( 'cancel', function() {
								window.location.href = sugar_calendar_hand_holding.urls.admin_calendar;
							} );
						} );
					},
					theme: 'sc-hand-holding',
					title: '',
					useBootstrap: false,
				});
			} else {
				app.jcCancel.open();
			}
		},

		/**
		 * Perform the next step.
		 *
		 * @since 3.7.0
		 */
		nextStep: function() {

			if ( app.stepCounter === false ) {
				app.stepCounter = 0;
			} else {
				// We want to remove the highlight from the previous step.
				sugar_calendar_hand_holding.steps[app.stepCounter].highlights.forEach( highlight => {
					$( highlight ).removeClass( 'sc-hand-holding__highlighted-element' );
				} );

				// Remove the highlight from the previous container.
				$( sugar_calendar_hand_holding.steps[app.stepCounter].container ).removeClass( 'sc-hand-holding__highlighted-element' );

				if ( sugar_calendar_hand_holding.steps[app.stepCounter].complete ) {
					app.completeSteps();
					return;
				}

				app.stepCounter++;
			}

			app.performStep();
		},

		/**
		 * Perform the current step.
		 *
		 * @since 3.7.0
		 */
		performStep: function() {

			// Current step.
			const step = sugar_calendar_hand_holding.steps[app.stepCounter];

			if ( step.complete ) {
				$el.$nextBtn.html( sugar_calendar_hand_holding.strings.done );
			}

			// Setup the tooltip.
			$el.$tooltipTitle.html( step.tooltip.title );
			$el.$tooltipContent.html( step.tooltip.content );
			$el.$tooltipCurrentProgress.text( app.stepCounter + 1 );

			if ( step.type === 'panel' ) {
				// We want to open the panel first.
				$( step.highlights[0] ).click();
			}

			if ( typeof step.dummy !== 'undefined' ) {

				switch ( step.key ) {
					case 'details':
						let sc_editor = null;

						if ( typeof tinymce !== 'undefined' ) {
							sc_editor = tinymce.get( 'post_content' );
						}

						// If TinyMCE is available, use it.
						if ( sc_editor ) {
							sc_editor.setContent( step.dummy.value );
							sc_editor.fire( 'change' );
							sc_editor.save();
						} else {
							sc_editor = $( '#post_content' )
							sc_editor.val( step.dummy.value );
						}

						break;

					case 'duration':
						// Set the dummy dates.
						$( '#start_date' ).datepicker( 'setDate', step.dummy.value );
						$( '#end_date' ).datepicker( 'setDate', step.dummy.value );

						// Set time.
						$( '#start_time_hour' ).val( '08' );
						$( '#start_time_minute' ).val( '00' );
						$( '#end_time_hour' ).val( '10' );
						$( '#end_time_minute' ).val( '00' );
						$( '#start_time_am_pm' ).val( 'pm' );
						$( '#end_time_am_pm' ).val( 'pm' );
						break;

					default:
						// Title.

						// Handle screen reader.
						$( step.dummy.screenReader ).addClass( 'screen-reader-text' );

						// Add dummy value.
						$( step.dummy.field ).val( step.dummy.value );

						$( '#title' ).trigger( 'propertychange' );
						break;
				}
			}

			// We want to highlight the elements
			step.highlights.forEach( highlight => {
				$( highlight ).addClass( 'sc-hand-holding__highlighted-element' );
			} );

			// We also want to highlight the container.
			const stepContainer = $( step.container );

			stepContainer.addClass( 'sc-hand-holding__highlighted-element' );

			let allowedPlacement = 'right';

			if ( step.key === 'calendars' || step.key === 'publish' ) {
				allowedPlacement = 'left';
			}

			// Adjust the position of the tooltip.
			FloatingUIDOM.computePosition(
				stepContainer[0],
				$el.$tooltip[0],
				{
					placement: step.tooltip.placement,
					middleware: [
						FloatingUIDOM.offset( 5 ),
						FloatingUIDOM.autoPlacement({
							allowedPlacements: [ allowedPlacement ]
						}),
						FloatingUIDOM.arrow({
							element: $el.$tooltipArrow[0],
						})
					]
				}
			).
			then( ( { x, y, placement, middlewareData } ) => {

				Object.assign(
					$el.$tooltip[0].style,
					{
						left: `${x}px`,
						top: `${y}px`,
					}
				);

				const {x: arrowX, y: arrowY} = middlewareData.arrow;

				const staticSide = {
					top: 'bottom',
					right: 'left',
					bottom: 'top',
					left: 'right',
				}[placement.split('-')[0]];

				Object.assign( $el.$tooltipArrow[0].style, {
					left: arrowX != null ? `${arrowX}px` : '',
					top: arrowY != null ? `${arrowY}px` : '',
					right: '',
					bottom: '',
					[staticSide]: '-4px',
				} );
			});
		},

		/**
		 * Continue the hand holding.
		 *
		 * @since 3.7.0
		 */
		continueSteps: function() {

			$el.$tooltip.css( 'display', 'block' );
			$el.$overlay.css( 'display', 'block' );

			app.performStep();
		},

		/**
		 * Next button click.
		 *
		 * @param {Event} e The event object.
		 *
		 * @since 3.7.0
		 */
		nextBtnClick: function( e ) {

			e.preventDefault();

			app.nextStep();
		},

		/**
		 * Complete the hand holding.
		 *
		 * @since 3.7.0
		 */
		completeSteps: function() {

			app.shouldCountAsExitBrowser = false;

			$( '#publish' ).trigger( 'click' );
		},

		/**
		 * Save draft button click.
		 *
		 * @param {Event} e The event object.
		 *
		 * @since 3.7.0
		 */
		saveDraftBtnClick: function( e ) {
			e.preventDefault();

			app.shouldCountAsExitBrowser = false;

			app.updateHandHoldingStatus( 'draft', function() {
				$el.$saveDraftBtn.trigger( 'click' );

				// Hide the tooltip and overlay.
				$el.$tooltip.css( 'display', 'none' );
				$el.$overlay.css( 'display', 'none' );
			} );
		},

		/**
		 * Publish button click.
		 *
		 * @param {Event} e The event object.
		 *
		 * @since 3.7.0
		 */
		publishBtnClick: function( e ) {
			e.preventDefault();

			app.shouldCountAsExitBrowser = false;

			app.updateHandHoldingStatus( 'publish', function() {
				$( '#publish' ).trigger( 'click' );

				// Hide the tooltip and overlay.
				$el.$tooltip.css( 'display', 'none' );
				$el.$overlay.css( 'display', 'none' );
			} );
		},

		/**
		 * Update the hand holding status.
		 *
		 * @param {string} status The new status.
		 * @param {function} cb   The callback function.
		 *
		 * @since 3.7.0
		 */
		updateHandHoldingStatus: function( status, cb ) {

			if ( typeof cb !== 'function' ) {
				cb = function() {};
			}

			const appStepCounter = ( app.stepCounter === false ) ? 0 : app.stepCounter;

			$.post(
				sugar_calendar_hand_holding.urls.ajax_url,
				{
					action: 'sc_hand_holding_status',
					status: status,
					nonce: sugar_calendar_hand_holding.nonce,
					step: sugar_calendar_hand_holding.steps[appStepCounter].key,
				},
				cb
			);
		}
	};

	// Provide access to public functions/properties.
	return app;

}( document, window, jQuery ) );

// Initialize.
SCHandHolding.init();
