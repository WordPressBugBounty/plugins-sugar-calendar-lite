jQuery( document ).ready( function( $ ) {

	const // Localized vars.
		popupTitle = scShortcodeHelper.title,
		popupWidth = scShortcodeHelper.width,
		popupHeight = scShortcodeHelper.height,
		inlineId = scShortcodeHelper.inlineId,
		identifier = scShortcodeHelper.identifier,
		shortcodeConfig = scShortcodeHelper.config;

	const // Selectors.
		shortcodeContentSelector = '.sc-sh-content',
		shortcodeTypeSelector = 'input[name="sc_sh_type"]',
		shortcodeDisplayOptionsSelector = '.sc-sh-display-options',
		shortcodeDisplayOptionsDataShortcodeAttribute = 'data-shortcode',
		allCalendarsCheckboxSelector = '#sc-sh-all-calendars',
		calendarsSelectSelector = '#sc-sh-calendars',
		groupEventsByWeekCheckboxSelector = '#sc-sh-sc_events_list-group-events-by-week',
		eventsPerPageFieldSelector = '#sc-sh-sc_events_list-events-per-page',
		maximumEventsToShowFieldSelector = '#sc-sh-sc_events_list-maximum-events-to-show',
		insertShortcodeButtonSelector = '.sc-insert-shortcode-button',
		insertShortcodeConfirmSelector = '.sc-insert-shortcode-confirm';

	/**
	 * Helpers to get current shortcode and attribute config.
	 */
	const getSelectedShortcode = () => $( shortcodeTypeSelector + ':checked' ).val();
	const getAttributeConfig = ( shortcode, key ) => {
		if ( ! shortcodeConfig[shortcode] || ! shortcodeConfig[shortcode].attributes[key] ) {
			return null;
		}
		return shortcodeConfig[shortcode].attributes[key];
	};

	/**
	 * Set a form element to its default value based on attribute config.
	 */
	const setElementValueToDefault = ( $el, attrConfig ) => {
		if ( ! $el || ! $el.length || ! attrConfig ) {
			return;
		}

		const def = typeof attrConfig.default !== 'undefined' ? attrConfig.default : '';

		if ( $el.is( 'select' ) ) {
			const choicesObj = $el.data( 'choicesjs' );
			if ( choicesObj ) {
				choicesObj.removeActiveItems();
				if ( Array.isArray( def ) && def.length ) {
					choicesObj.setChoiceByValue( def.map( String ) );
				} else if ( typeof def === 'string' && def !== '' ) {
					choicesObj.setChoiceByValue( def );
				}
			} else {
				$el.val( Array.isArray( def ) ? def : def );
			}
		} else if ( $el.is( ':checkbox' ) ) {
			$el.prop( 'checked', !! def );
		} else {
			$el.val( def );
		}
	};

	/**
	 * Empty a form element (clear value/selection, uncheck).
	 */
	const setElementValueToEmpty = ( $el ) => {
		if ( ! $el || ! $el.length ) {
			return;
		}

		if ( $el.is( 'select' ) ) {
			const choicesObj = $el.data( 'choicesjs' );
			if ( choicesObj ) {
				choicesObj.removeActiveItems();
			} else {
				$el.val( $el.prop( 'multiple' ) ? [] : '' );
			}
		} else if ( $el.is( ':checkbox' ) ) {
			$el.prop( 'checked', false );
		} else {
			$el.val( '' );
		}
	};

	/**
	 * Reset fields to default values. If key is provided, resets only that field.
	 */
	const resetFieldsToDefault = ( shortcode, key ) => {
		if ( ! shortcode || ! shortcodeConfig[shortcode] ) {
			return;
		}

		const attrs = shortcodeConfig[shortcode].attributes || {};

		if ( key ) {
			const cfg = getAttributeConfig( shortcode, key );
			if ( cfg && cfg.input_id ) {
				setElementValueToDefault( $( `#${cfg.input_id}` ), cfg );
			}
			return;
		}

		$.each( attrs, ( attrKey, cfg ) => {
			if ( cfg && cfg.input_id ) {
				setElementValueToDefault( $( `#${cfg.input_id}` ), cfg );
			}
		} );
	};

	/**
	 * Reset a specific attribute to empty.
	 */
	const resetAttributeToEmpty = ( shortcode, key ) => {
		const cfg = getAttributeConfig( shortcode, key );
		if ( cfg && cfg.input_id ) {
			setElementValueToEmpty( $( `#${cfg.input_id}` ) );
		}
	};

	/**
	 * Construct the shortcode.
	 *
	 * @since 3.9.0
	 *
	 * @return {string}
	 */
	const constructShortcode = () => {

		const shortcode = $( shortcodeTypeSelector + ':checked' ).val();
		let allCalendarsChecked = $( allCalendarsCheckboxSelector ).is( ':checked' );
		const isGrouped = $( groupEventsByWeekCheckboxSelector ).is( ':checked' );
		const shortcodeName = shortcodeConfig[shortcode].name;

		let shortcodeAttributes = {};

		// Loop through the selected shortcode attributes config,
		// read input by id, then add to the shortcode attributes object.
		// If the value is the same as the default value, don't add to the shortcode.
		$.each( shortcodeConfig[shortcode].attributes, ( key, value ) => {

			// Skip calendars when "all calendars" is selected.
			if ( key === 'calendars' && allCalendarsChecked ) {
				return;
			}

			// Skip list pagination fields when grouping by week is enabled.
			if (
				shortcode === 'sc_events_list' && isGrouped &&
				( key === 'events_per_page' || key === 'maximum_events_to_show' )
			) {
				return;
			}

			const element = $( `#${value.input_id}` );

			// Check if element exists.
			if ( element.length === 0 ) {
				return;
			}

			// Check either select or checkbox.
			if ( element.is( 'select' ) ) {

				const attrConf = shortcodeConfig[shortcode].attributes[key] || {};
				const attrType = attrConf.type || '';
				const defaultVal = typeof attrConf.default !== 'undefined' ? attrConf.default : '';

				let valueSelected = element.val();
				let normalizedSelected;
				let normalizedDefault;

				if ( Array.isArray( valueSelected ) ) {
					normalizedSelected = valueSelected.join( ',' );
				} else if ( valueSelected === null || typeof valueSelected === 'undefined' ) {
					normalizedSelected = '';
				} else {
					normalizedSelected = String( valueSelected );
				}

				if ( attrType === 'array_int' ) {
					normalizedDefault = Array.isArray( defaultVal ) ? defaultVal.join( ',' ) : ( defaultVal ?? '' );
				} else if ( attrType === 'int' ) {
					normalizedDefault = String( defaultVal );
				} else {
					normalizedDefault = typeof defaultVal === 'undefined' ? '' : String( defaultVal );
				}

				// If same as default value, don't add to the shortcode.
				if ( normalizedSelected === normalizedDefault ) {
					return;
				}

				shortcodeAttributes[key] = normalizedSelected;

			} else if ( element.is( ':checkbox' ) ) {

				const value = element.is( ':checked' ) ? true : false;

				// If same as default value, don't add to the shortcode.
				if ( value === shortcodeConfig[shortcode].attributes[key].default ) {
					return;
				}

				shortcodeAttributes[key] = value;
			} else {
				// Handle other input types (e.g., text/number)
				const attrConf = shortcodeConfig[shortcode].attributes[key] || {};
				const defaultVal = typeof attrConf.default !== 'undefined' ? attrConf.default : '';
				let valueSelected = element.val();
				const normalizedSelected = ( valueSelected === null || typeof valueSelected === 'undefined' ) ? '' : String( valueSelected ).trim();
				const normalizedDefault = typeof defaultVal === 'undefined' ? '' : String( defaultVal );

				// If same as default value, don't add to the shortcode.
				if ( normalizedSelected === normalizedDefault ) {
					return;
				}

				shortcodeAttributes[key] = normalizedSelected;
			}
		});

		// If "all calendars" is unchecked, ensure calendars attribute is included.
		allCalendarsChecked = $( allCalendarsCheckboxSelector ).is( ':checked' );
		if ( ! allCalendarsChecked ) {
			const selectedCalendars = $( calendarsSelectSelector ).val() || [];
			if ( selectedCalendars.length > 0 ) {
				shortcodeAttributes['calendars'] = selectedCalendars.join( ',' );
			}
		}

		// Build shortcode string with attributes
		let shortcodeString = `[${shortcodeName}`;

		// Add attributes to shortcode
		Object.keys(shortcodeAttributes).forEach(key => {
			const value = shortcodeAttributes[key];

			// Handle different value types
			if (typeof value === 'boolean') {
				shortcodeString += ` ${key}="${value ? 'true' : 'false'}"`;
			} else if (typeof value === 'number') {
				shortcodeString += ` ${key}="${value}"`;
			} else {
				shortcodeString += ` ${key}="${value}"`;
			}
		});

		shortcodeString += ']';

		return shortcodeString;
	}

	/**
	 * Show/hide Events List specific fields based on selected shortcode type.
	 *
	 * @since 3.9.0
	 */
	const toggleEventsListUniqueFields = () => {
		const shortcode = getSelectedShortcode();
		const $groupEventsField = $( groupEventsByWeekCheckboxSelector ).closest( '.sc-sh-field' );
		const $eventsPerPageField = $( eventsPerPageFieldSelector ).closest( '.sc-sh-field' );
		const $maxEventsField = $( maximumEventsToShowFieldSelector ).closest( '.sc-sh-field' );

		if ( shortcode === 'sc_events_list' ) {
			// Show Events List specific fields
			$groupEventsField.show();
			$eventsPerPageField.show();
			$maxEventsField.show();
			// Re-apply group events toggle logic
			onGroupEventsToggle();
		} else {
			// Hide Events List specific fields for other shortcode types
			$groupEventsField.hide();
			$eventsPerPageField.hide();
			$maxEventsField.hide();
		}
	};

	/**
	 * Event: Shortcode Type Change.
	 *
	 * @since 3.9.0
	 */
	const toggleShortcodeDisplayOptions = () => {

		// Get which shortcode is selected.
		const shortcode = $( shortcodeTypeSelector + ':checked' ).val();

		$( shortcodeDisplayOptionsSelector ).hide();
		$( `${shortcodeDisplayOptionsSelector}[${shortcodeDisplayOptionsDataShortcodeAttribute}="${shortcode}"]` ).show();

		$( shortcodeContentSelector ).attr( 'type-selected', shortcode );

		// Show/hide Events List specific fields
		toggleEventsListUniqueFields();
	}
	$( document ).on( 'change', shortcodeTypeSelector, toggleShortcodeDisplayOptions );

	// Enable/disable calendars multiselect based on the all-calendars toggle and initialize Choices.js.
	const onAllCalendarsToggle = () => {
		const isAll = $( allCalendarsCheckboxSelector ).is( ':checked' );
		const $calSelect = $( calendarsSelectSelector );
		$calSelect.prop( 'disabled', isAll );
		// Show/hide the calendars field container
		const $container = $calSelect.closest( '.sc-sh-field' );
		if ( $container.length ) {
			if ( isAll ) {
				$container.hide();
				// Reset calendars to empty when all-calendars is on for sc_events_calendar only
				if ( getSelectedShortcode() === 'sc_events_calendar' ) {
					resetAttributeToEmpty( 'sc_events_calendar', 'calendars' );
				}
			} else {
				$container.show();
			}
		}
	};

	// Initialize Choices.js for calendars multiselect if available.
	if ( typeof window.Choices === 'function' ) {
		const $calSel = $( calendarsSelectSelector );
		if ( $calSel.length ) {
			const choicesObj = new Choices( $calSel[0], {
				removeItemButton: true,
				searchEnabled: true,
				shouldSort: false,
				position: 'auto',
				itemSelectText: '',
				callbackOnInit: function () {
					$calSel.closest( '.choices__inner' ).append( '<div class="choices__arrow"></div>' );
				},
			} );
			$calSel.data( 'choicesjs', choicesObj );
			onAllCalendarsToggle();
		}
	}
	$( document ).on( 'change', allCalendarsCheckboxSelector, onAllCalendarsToggle );

	// Show/hide Events per page and Maximum events to show based on Group events per week.
	const onGroupEventsToggle = () => {
		const isGrouped = $( groupEventsByWeekCheckboxSelector ).is( ':checked' );
		const $eventsPerPage = $( eventsPerPageFieldSelector ).closest( '.sc-sh-field' );
		const $maxEvents = $( maximumEventsToShowFieldSelector ).closest( '.sc-sh-field' );
		if ( isGrouped ) {
			$eventsPerPage.hide();
			$maxEvents.hide();
			// Reset values to empty when grouped by week is enabled
			resetAttributeToEmpty( 'sc_events_list', 'events_per_page' );
			resetAttributeToEmpty( 'sc_events_list', 'maximum_events_to_show' );
		} else {
			$eventsPerPage.show();
			$maxEvents.show();
			// Restore default values when fields are shown
			resetFieldsToDefault( 'sc_events_list', 'events_per_page' );
			resetFieldsToDefault( 'sc_events_list', 'maximum_events_to_show' );
		}
	};

	$( document ).on( 'change', groupEventsByWeekCheckboxSelector, onGroupEventsToggle );

	/**
	 * Reset all fields in the modal to defaults when the ThickBox closes.
	 */
	const resetAllFieldsToDefault = () => {
		// Reset both shortcode configs since the modal contains fields for both
		resetFieldsToDefault( 'sc_events_calendar' );
		resetFieldsToDefault( 'sc_events_list' );

		// Reset custom controls not in config
		$( allCalendarsCheckboxSelector ).prop( 'checked', true );

		// Re-apply UI visibility after resets
		onAllCalendarsToggle();
		toggleEventsListUniqueFields();
		onGroupEventsToggle();
	};

	// ThickBox unload fires when the modal is closed
	$( document ).on( 'tb_unload', resetAllFieldsToDefault );

	/**
	 * Event: Open ThickBox modal on toolbar button click.
	 *
	 * @since 3.9.0
	 */
	const openThickBoxModal = ( e ) => {

		e.preventDefault();

		const args = {
			width: popupWidth,
			height: popupHeight,
			inlineId: inlineId,
		};

		// Build inline content URL for ThickBox (TB_inline requires inlineId).
		const url = '#TB_inline?' + $.param( args );

		// Open the modal.
		if ( typeof tb_show === 'function' ) {

			tb_show( popupTitle, url );

			toggleShortcodeDisplayOptions();
			onAllCalendarsToggle();
			onGroupEventsToggle();
		}
	}
	$( document ).on( 'click', insertShortcodeButtonSelector, openThickBoxModal );

	/**
	 * Event: Shortcode insert confirm. Add Shortcode button.
	 *
	 * @since 3.9.0
	 */
	const insertShortcode = ( e ) => {

		e.preventDefault();

		const shortcode = constructShortcode();

		if ( window.wp && window.wp.media && window.wp.media.editor ) {
			window.wp.media.editor.insert( shortcode );
		}

		if ( typeof tb_remove === 'function' ) {
			tb_remove();
		}
	}
	$( document ).on( 'click', insertShortcodeConfirmSelector, insertShortcode );

	// Patch the tb_show function to trigger a custom event.
	const _tb_show = window.tb_show;

	// Wrap it to inject our hook.
	window.tb_show = function(caption, url, imageGroup) {
		// Call the original ThickBox opener.
		var result = _tb_show.apply(this, arguments);

		// Add custom event.
		$(document).trigger('thickbox:opened', { caption: caption, url: url });

		return result;
	};

	// Add custom event listener for popup open.
	$(document).on('thickbox:opened', function(e, data){

		$('.sc-shortcode-helper-modal-wrap')
			.parents('#TB_window')
			.attr( 'sc-popup', identifier);
	});
} );
