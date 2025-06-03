/* globals jQuery, sugar_calendar_admin_addons */
(function( $, settings ) {

	'use strict';

	let SugarCalendar = window.SugarCalendar || {};
	SugarCalendar.Admin = SugarCalendar.Admin || {};

	SugarCalendar.Admin.Addons = {

		/**
		 * Element bindings for Addons List page.
		 *
		 * @since 3.7.0
		 */
		init: function() {

			// Toggle an addon state.
			$( document ).on( 'change', '.sugar-calendar-addons-list-item .sugar-calendar-toggle-control input', function( event ) {
				event.preventDefault();

				if ( $( this ).hasClass( 'disabled' ) ) {
					return false;
				}

				SugarCalendar.Admin.Addons.addonToggleNew( $( this ) );
			} );

			$( document ).on( 'click', '.sugar-calendar-addons-list-item button', function( event ) {
				event.preventDefault();

				if ( $( this ).hasClass( 'disabled' ) ) {
					return false;
				}

				SugarCalendar.Admin.Addons.addonToggleNew( $( this ) );
			} );
		},

		/**
		 * Change plugin/addon state.
		 *
		 * @since 3.7.0
		 *
		 * @param {string}   plugin        Plugin slug or URL for download.
		 * @param {string}   state         State status activate|deactivate|install.
		 * @param {string}   pluginType    Plugin type addon or plugin.
		 * @param {Function} callback      Callback for get result from AJAX.
		 * @param {Function} errorCallback Callback for get error from AJAX.
		 */
		setAddonState( plugin, state, pluginType, callback, errorCallback ) {
			const tasks = {
				activate: 'activate_addon',
				install: 'install_addon',
				deactivate: 'deactivate_addon',
				incompatible: 'activate_addon',
			};
			const task = tasks[state];

			if ( ! task ) {
				return;
			}

			const data = {
				task,
				plugin,
				type: pluginType,
			};

			$.post( settings.ajax_url, data, function( res ) {
				callback( res );
			} ).fail( function( xhr ) {
				errorCallback( xhr );
			} );
		},

		/**
		 * Toggle addon state.
		 *
		 * @since 3.7.0
		 *
		 * @param {Object} $btn Button element.
		 */
		// eslint-disable-next-line max-lines-per-function, complexity
		addonToggleNew( $btn ) {
			const $footer = $btn.parents( '.sugar-calendar-addons-list-item-footer' );
			const classes = {
				active: 'sugar-calendar-addons-list-item-footer-active',
				activating: 'sugar-calendar-addons-list-item-footer-activating',
				incompatible: 'sugar-calendar-addons-list-item-footer-incompatible',
				installed: 'sugar-calendar-addons-list-item-footer-installed',
				missing: 'sugar-calendar-addons-list-item-footer-missing',
				goToUrl: 'sugar-calendar-addons-list-item-footer-go-to-url',
				withError: 'sugar-calendar-addons-list-item-footer-with-error',
			};

			// Open url in new tab.
			if ( $footer.hasClass( classes.goToUrl ) ) {
				window.open( $btn.attr( 'data-plugin' ), '_blank' );
				return;
			}

			$btn.prop( 'disabled', true );

			let checked = $btn.is( ':checked' );
			let cssClass;
			const plugin = $footer.attr( 'data-plugin' );
			const pluginType = $footer.attr( 'data-type' );
			const $addon = $btn.parents( '.sugar-calendar-addons-list-item' );
			const state = this.getAddonState( $footer, classes, $btn );

			/**
			 * Handle error.
			 *
			 * @param {Object} res Response object.
			 */
			function handleError( res ) {
				$footer.addClass( classes.withError );

				if ( typeof res.data === 'object' ) {
					$footer.append( `<div class="sugar-calendar-addons-list-item-footer-error"><p>${pluginType === 'addon' ? settings.addon_error : settings.plugin_error}</p></div>` );
				} else {
					$footer.append( `<div class="sugar-calendar-addons-list-item-footer-error"><p>${res.data}</p></div>` );
				}

				if ( state === 'install' ) {
					checked = false;
					SugarCalendar.Admin.Addons.removeSpinnerFromButton( $btn );
				} else if ( state === 'deactivate' ) {
					checked = true;
				} else if ( state === 'activate' ) {
					checked = false;
				}
			}

			/**
			 * Handle success.
			 *
			 * @param {Object} res Response object.
			 */
			function handleSuccess( res ) {
				if ( state === 'install' ) {
					cssClass = classes.active;
					checked = true;

					$footer.attr( 'data-plugin', res.data.basename );

					if ( ! res.data.is_activated ) {
						cssClass = classes.installed;
						checked = false;
					}

					$btn.hide();
					$btn = $btn.closest( '.sugar-calendar-addons-list-item' ).find( '.sugar-calendar-toggle-control input' );
				} else if ( state === 'activate' ) {
					$footer.find( '.sugar-calendar-addons-list-item-footer-settings-link' ).fadeIn( 150 );
					cssClass = classes.active;
					checked = true;
				} else if ( state === 'deactivate' ) {
					$footer.find( '.sugar-calendar-addons-list-item-footer-settings-link' ).fadeOut( 150 );
					cssClass = classes.installed;
					checked = false;
				}

				$footer.removeClass( classes.active + ' ' + classes.incompatible + ' ' + classes.installed + ' ' + classes.missing ).addClass( cssClass );
			}

			this.setAddonState( plugin, state, pluginType, function( res ) {
				if ( res.success ) {
					handleSuccess( res );
				} else {
					handleError( res );
				}

				SugarCalendar.Admin.Addons.updateAddonButtonPropertiesAndUI( $btn, $addon, $footer, classes, checked );
			}, function() {
				handleError( {
					data: settings.server_error,
				} );

				SugarCalendar.Admin.Addons.updateAddonButtonPropertiesAndUI( $btn, $addon, $footer, classes, checked );
			} );
		},

		/**
		 * Add spinner to button.
		 *
		 * @since 3.7.0
		 *
		 * @param {Object} $button Button element.
		 */
		addSpinnerToButton( $button ) {
			const spinnerBlue = '<i class="sugar-calendar-loading-spinner"></i>';
			const originalWidth = $button.width();

			$button.data( 'original-text', $button.html() );
			$button.width( originalWidth ).html( spinnerBlue );
		},

		/**
		 * Remove spinner from button.
		 *
		 * @since 3.7.0
		 *
		 * @param {Object} $button Button element.
		 */
		removeSpinnerFromButton( $button ) {
			$button.html( $button.data( 'original-text' ) );
		},

		/**
		 * Get addon state.
		 *
		 * @since 3.7.0
		 *
		 * @param {Object} $footer Footer element.
		 * @param {Object} classes Classes object.
		 * @param {Object} $button Button element.
		 *
		 * @return {string} State.
		 */
		getAddonState( $footer, classes, $button ) {
			if ( $footer.hasClass( classes.active ) || $footer.hasClass( classes.incompatible ) ) {
				return 'deactivate';
			}

			if ( $footer.hasClass( classes.installed ) ) {
				return 'activate';
			}

			if ( $footer.hasClass( classes.missing ) ) {
				this.addSpinnerToButton( $button );
				return 'install';
			}

			return '';
		},

		/**
		 * Update button properties and UI.
		 *
		 * @since 3.7.0
		 *
		 * @param {Object}  $btn    Button element.
		 * @param {Object}  $addon  Addon element.
		 * @param {Object}  $footer Footer element.
		 * @param {Object}  classes Classes object.
		 * @param {boolean} checked Checked state.
		 */
		updateAddonButtonPropertiesAndUI( $btn, $addon, $footer, classes, checked ) {
			$btn.prop( 'checked', checked );
			$btn.prop( 'disabled', false );
			$btn.siblings( '.sugar-calendar-toggle-control-status' ).html( $btn.siblings( '.sugar-calendar-toggle-control-status' ).data( checked ? 'on' : 'off' ) );

			if ( $addon.find( '.sugar-calendar-addons-list-item-footer-error' ).length > 0 ) {
				setTimeout( function() {
					$footer.removeClass( classes.withError );
					$addon.find( '.sugar-calendar-addons-list-item-footer-error' ).remove();
				}, 6000 );
			}
		},
	};

	SugarCalendar.Admin.Addons.init( settings );

	window.SugarCalendar = SugarCalendar;

})( jQuery, sugar_calendar_admin_addons );
