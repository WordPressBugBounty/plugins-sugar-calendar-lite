/* globals jQuery, Choices, sugar_calendar_admin_calendar */
( function ( $, Choices, settings ) {

	'use strict';

	let SugarCalendar = window.SugarCalendar || {};
	SugarCalendar.Admin = SugarCalendar.Admin || {};

	SugarCalendar.Admin.Calendar = {

		/**
		 * Initialize.
		 *
		 * @since 3.0.0
		 */
		init: function ( settings ) {

			this.settings = settings;
			this.$settingsMetabox = $( '#calendar_settings' );

			let $tagDom = $( 'input#tag-name' );

			if ( $tagDom.length <= 0 ) {
				// If we're then we are editing an existing calendar.
				$tagDom = $( 'input#name' );
			}

			this.$tagDom = $tagDom;

			this.bindEvents();

			// Initialize ChoiceJS dropdowns.
			this.initChoicesJS();

			// Initialize color pickers.
			this.initColorPickers();
		},

		/**
		 * Bind events.
		 *
		 * @since 3.8.0 Added auto slug Calendar name on blur.
		 */
		bindEvents: function () {

			$( 'button.handlediv', this.$settingsMetabox ).on( 'click', this.toggleMetabox.bind( this ) );

			this.$tagDom.on( 'blur', this.generateSlugFromName.bind( this ) );
		},

		initChoicesJS: function () {

			$( '.choicesjs-select' ).each( ( i, el ) => {
				new Choices( el, {
					itemSelectText: '',
				} );
			} );
		},

		initColorPickers: function () {
			$( '#term-color' ).wpColorPicker( {
				palettes: this.settings.palette,
			} );
		},

		toggleMetabox: function () {
			this.$settingsMetabox.toggleClass( 'closed' );
		},

		/**
		 * Generate slug from tag name field when it loses focus.
		 * 
		 * @since 3.8.0
		 */
		generateSlugFromName: function () {

			const $tagSlug = $( '#tag-slug' );

			if ( $tagSlug.val().length > 0 ) {
				return;
			}

			$tagSlug.val( this.createSlug( this.$tagDom.val() ) );
		},

		/**
		 * Create a URL-friendly slug from text.
		 * 
		 * @since 3.8.0
		 *
		 * @param {string} text - The text to convert to a slug
		 *
		 * @returns {string} The slug
		 */
		createSlug: function ( text ) {
			if ( ! text ) {
				return '';
			}

			return text
				.toLowerCase()
				.trim()
				.replace( /[\s\-_]+/g, '-' )  // Replace spaces, hyphens, and underscores with single hyphen
				.replace( /[^\w\-]+/g, '' )   // Remove all non-word characters except hyphens
				.replace( /\-\-+/g, '-' )     // Replace multiple hyphens with single hyphen
				.replace( /^-+/, '' )         // Remove leading hyphens
				.replace( /-+$/, '' );        // Remove trailing hyphens
		},
	};

	SugarCalendar.Admin.Calendar.init( settings );

	window.SugarCalendar = SugarCalendar;

} )( jQuery, Choices, sugar_calendar_admin_calendar );
