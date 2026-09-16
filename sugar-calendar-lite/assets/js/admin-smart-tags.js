/**
 * WYSIWYG Smart Tags.
 *
 * Adds a Smart Tags toolbar button and a searchable dropdown to any TinyMCE
 * editor that was rendered with a `smart_tags` payload by
 * Sugar_Calendar\Helpers\UI::wysiwyg_editor().
 *
 * @since 3.14.0
 */
/* global tinymce */

'use strict';

/**
 * Sugar Calendar Smart Tags.
 *
 * @since 3.14.0
 */
const SugarCalendarSmartTags = {

	/**
	 * Open dropdowns, keyed by TinyMCE editor id.
	 *
	 * @since 3.14.0
	 */
	dropdowns: {},

	/**
	 * Get the localized strings and the button icon URL.
	 *
	 * @since 3.14.0
	 *
	 * @return {Object} Localized data.
	 */
	l10n() {
		return window.sugar_calendar_smart_tags || {};
	},

	/**
	 * Get the smart tags for a specific editor.
	 *
	 * @since 3.14.0
	 *
	 * @param {string} editorId Editor ID.
	 *
	 * @return {Object} Smart tags, grouped by object type.
	 */
	getTags( editorId ) {
		const registry = window.sugarCalendarSmartTags || {};

		return registry[ editorId ] || {};
	},

	/**
	 * Start the engine.
	 *
	 * @since 3.14.0
	 */
	init() {
		this.registerPlugin();
	},

	/**
	 * Register the Smart Tags plugin with TinyMCE.
	 *
	 * @since 3.14.0
	 */
	registerPlugin() {
		const self = this;

		tinymce.PluginManager.add( 'smarttags', function( editor ) {
			editor.addButton( 'smarttags', {
				text: '',
				tooltip: self.l10n().button_tooltip,
				icon: false,
				image: self.l10n().icon_url,
				classes: 'sugar-calendar-smart-tags-mce-button',
				onclick: () => self.toggleDropdown( editor ),
			} );

			editor.on( 'click', () => self.closeDropdown( editor.id ) );
		} );
	},

	/**
	 * Toggle the smart tags dropdown.
	 *
	 * @since 3.14.0
	 *
	 * @param {Object} editor TinyMCE editor instance.
	 */
	toggleDropdown( editor ) {
		const { id: editorId } = editor;

		// Close if already open.
		if ( this.dropdowns[ editorId ] ) {
			this.closeDropdown( editorId );

			return;
		}

		// Close all other dropdowns.
		Object.keys( this.dropdowns ).forEach( ( id ) => this.closeDropdown( id ) );

		// Show dropdown.
		this.showDropdown( editor );
	},

	/**
	 * Show the smart tags dropdown.
	 *
	 * @since 3.14.0
	 *
	 * @param {Object} editor TinyMCE editor instance.
	 */
	showDropdown( editor ) {
		const { id: editorId } = editor;
		const tags = this.getTags( editorId );

		// If no tags are configured for this editor, do nothing.
		if ( ! tags || Object.keys( tags ).length === 0 ) {
			return;
		}

		const dropdown = this.createDropdownElement( editorId );

		// The editor container is gone (or TinyMCE has not finished mounting),
		// so there is nothing to anchor the dropdown to.
		if ( ! dropdown ) {
			return;
		}

		const handleClickOutside = ( e ) => {
			if (
				! dropdown.contains( e.target ) &&
				! e.target.closest( '.mce-btn, button[class*="mce-"]' )
			) {
				this.closeDropdown( editorId );
			}
		};

		// Store dropdown state.
		this.dropdowns[ editorId ] = {
			element: dropdown,
			editor,
			clickOutsideHandler: handleClickOutside,
		};

		// Setup dropdown interactions.
		this.setupDropdownHandlers( dropdown, tags, editorId );

		// Attach to DOM and setup outside click handler.
		document.body.appendChild( dropdown );
		setTimeout( () => document.addEventListener( 'mousedown', handleClickOutside ), 100 );
	},

	/**
	 * Create the dropdown element.
	 *
	 * @since 3.14.0
	 *
	 * @param {string} editorId TinyMCE editor ID.
	 *
	 * @return {Element|null} Dropdown element, or null when the editor is not mounted.
	 */
	createDropdownElement( editorId ) {
		const iframe = document.querySelector( `#${ editorId }_ifr` );
		const editorContainer = iframe ? iframe.closest( '.mce-tinymce' ) : null;

		if ( ! editorContainer ) {
			return null;
		}

		const { top, right } = editorContainer.getBoundingClientRect();
		const dropdown = document.createElement( 'div' );

		dropdown.id = `smart-tags-dropdown-${ editorId }`;
		dropdown.className = 'sugar-calendar-smart-tags-dropdown open-down';
		dropdown.style.cssText = `
			position: absolute;
			top: ${ top + window.scrollY + 12 }px;
			right: ${ window.innerWidth - right + window.scrollX + 8 }px;
		`;
		dropdown.innerHTML = `
			<div class="sugar-calendar-smart-tags-title">${ this.l10n().title }</div>
			<div class="sugar-calendar-smart-tags-search-container">
				<input
					type="search"
					class="sugar-calendar-smart-tags-search-input"
					placeholder="${ this.l10n().search_placeholder }"
					autocomplete="off"
				/>
			</div>
			<ul class="sugar-calendar-smart-tags-list"></ul>
		`;

		return dropdown;
	},

	/**
	 * Setup the dropdown event handlers.
	 *
	 * @since 3.14.0
	 *
	 * @param {Element} dropdown Dropdown element.
	 * @param {Object}  tags     Smart tags.
	 * @param {string}  editorId TinyMCE editor ID.
	 */
	setupDropdownHandlers( dropdown, tags, editorId ) {
		const searchInput = dropdown.querySelector( '.sugar-calendar-smart-tags-search-input' );
		const tagsList = dropdown.querySelector( '.sugar-calendar-smart-tags-list' );

		// Initial render.
		this.renderTags( tagsList, tags );

		// Search handler.
		searchInput.addEventListener( 'input', ( e ) => {
			const query = e.target.value.toLowerCase().trim();
			const filtered = this.filterTags( tags, query );

			this.renderTags( tagsList, filtered, query );
		} );

		// Tag click handler (event delegation).
		tagsList.addEventListener( 'click', ( e ) => {
			const button = e.target.closest( 'button[data-tag]' );

			if ( button ) {
				e.preventDefault();
				e.stopPropagation();
				this.insertSmartTag( editorId, button.getAttribute( 'data-tag' ) );
				this.closeDropdown( editorId );
			}
		} );

		searchInput.focus();
	},

	/**
	 * Filter tags based on the search query.
	 *
	 * @since 3.14.0
	 *
	 * @param {Object} tags  All tags, grouped by object type.
	 * @param {string} query Search query.
	 *
	 * @return {Object} Filtered tags.
	 */
	filterTags( tags, query ) {
		if ( ! query ) {
			return tags;
		}

		const matchesQuery = ( tag ) =>
			tag.label.toLowerCase().includes( query ) ||
			tag.value.toLowerCase().includes( query );

		return Object.keys( tags ).reduce( ( filtered, key ) => {
			const filteredTags = ( tags[ key ] || [] ).filter( matchesQuery );

			if ( filteredTags.length ) {
				filtered[ key ] = filteredTags;
			}

			return filtered;
		}, {} );
	},

	/**
	 * Render tags in the list.
	 *
	 * @since 3.14.0
	 *
	 * @param {Element} listElement List container element.
	 * @param {Object}  tags        Tags to render.
	 * @param {string}  query       Search query (optional).
	 */
	renderTags( listElement, tags, query = '' ) {
		const html = Object.keys( tags )
			.filter( ( key ) => tags[ key ] && tags[ key ].length )
			.map( ( key ) => {
				const heading = key
					.replace( /_/g, ' ' )
					.split( ' ' )
					.map( ( word ) => word.charAt( 0 ).toUpperCase() + word.slice( 1 ) )
					.join( ' ' );

				return `
					<li data-value="0">
						<span class="sugar-calendar-smart-tags-heading">${ heading }</span>
					</li>
					${ tags[ key ]
		.map(
			( tag ) => `
						<li class="sugar-calendar-smart-tags-item" data-value="${ tag.value }">
							<button type="button" class="sugar-calendar-smart-tags-button" data-tag="${ tag.value }">
								${ tag.label }
							</button>
						</li>
					`
		)
		.join( '' ) }
				`;
			} )
			.join( '' );

		listElement.innerHTML =
			html ||
			( query ?
				`<div class="sugar-calendar-smart-tags-no-results">${ this.l10n().no_results }</div>` :
				'' );
	},

	/**
	 * Close the smart tags dropdown.
	 *
	 * @since 3.14.0
	 *
	 * @param {string} editorId TinyMCE editor ID.
	 */
	closeDropdown( editorId ) {
		const dropdown = this.dropdowns[ editorId ];

		if ( ! dropdown ) {
			return;
		}

		if ( dropdown.element ) {
			dropdown.element.remove();
		}

		document.removeEventListener( 'mousedown', dropdown.clickOutsideHandler );
		delete this.dropdowns[ editorId ];
	},

	/**
	 * Insert a smart tag into the editor.
	 *
	 * Tags are brace-wrapped: that is the form the email tag parsers
	 * (Emails::do_tags() and the RSVP equivalent) look for.
	 *
	 * @since 3.14.0
	 *
	 * @param {string} editorId TinyMCE editor ID.
	 * @param {string} tagValue Tag value to insert.
	 */
	insertSmartTag( editorId, tagValue ) {
		const dropdown = this.dropdowns[ editorId ];

		if ( dropdown && dropdown.editor ) {
			dropdown.editor.insertContent( ` {${ tagValue }} ` );
		}
	},
};

( function() {
	SugarCalendarSmartTags.init();
}() );
