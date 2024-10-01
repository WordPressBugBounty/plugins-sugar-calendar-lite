/* globals jQuery, jconfirm, sugar_calendar_admin_connect */

( function ( $, settings ) {

	'use strict';

	let SugarCalendar = window.SugarCalendar || {};
	SugarCalendar.Admin = SugarCalendar.Admin || {};

	SugarCalendar.Admin.Connect = {

		init: function ( settings ) {

			this.settings = settings;
			this.$connectBtn = $( '#sugar-calendar-setting-license-key-button' );
			this.$connectKey = $( '#sugar-calendar-setting-license-key' );

			this.$connectBtn.on( 'click', this.gotoUpgradeUrl.bind( this ) );

			this.setConfirmDefaults();
		},

		setConfirmDefaults: function () {

			jconfirm.defaults = {
				typeAnimated: false,
				draggable: false,
				animateFromElement: false,
				boxWidth: '400px',
				useBootstrap: false,
			};
		},

		gotoUpgradeUrl: function () {

			this.$connectBtn.prop( 'disabled', true );

			$.post( this.settings.ajax_url, {
				task: 'connect_url',
				key: this.$connectKey.val(),
			} ).done( ( response ) => {
				if ( response.success ) {
					if ( response.data.reload ) {
						$.alert( this.proAlreadyInstalled( response ) );
						return;
					}
					window.location.href = response.data.url;
					return;
				}

				// Default to generic error if no message is present.
				const message = (
					response.data &&
					response.data.message
				) ? response.data.message : this.settings.text.server_error;

				$.alert( {
					title: false,
					content: message,
					icon: this.getIcon( 'exclamation-circle-solid-orange.svg' ),
					type: 'orange',
					buttons: {
						confirm: {
							text: this.settings.text.ok,
							btnClass: 'sugar-calendar-btn sugar-calendar-btn-lg sugar-calendar-btn-primary',
							keys: ['enter'],
						},
					},
				} );
			} ).fail( ( xhr ) => {
				this.failAlert( xhr );
			} ).always( () => {
				this.$connectBtn.prop( 'disabled', false );
			} );
		},

		proAlreadyInstalled: function ( response ) {

			return {
				title: this.settings.text.almost_done,
				content: response.data.message,
				icon: this.getIcon( 'check-circle-solid-green.svg' ),
				type: 'green',
				buttons: {
					confirm: {
						text: this.settings.text.plugin_activate_btn,
						btnClass: 'sugar-calendar-btn sugar-calendar-btn-lg sugar-calendar-btn-green',
						keys: ['enter'],
						action: function () {
							window.location.reload();
						},
					},
				},
			};
		},

		failAlert: function ( xhr ) {

			$.alert( {
				title: this.settings.text.oops,
				content: this.settings.text.server_error + '<br>' + xhr.status + ' ' + xhr.statusText + ' ' + xhr.responseText,
				icon: this.getIcon( 'exclamation-circle-regular-red.svg' ),
				type: 'red',
				buttons: {
					confirm: {
						text: this.settings.text.ok,
						btnClass: 'sugar-calendar-btn sugar-calendar-btn-lg sugar-calendar-btn-red',
						keys: ['enter'],
					},
				},
			} );
		},

		getIcon: function ( icon ) {

			const iconPath = `${this.settings.plugin_url}assets/images/icons/${icon}"`
			const iconElement = `"></i><img src="${iconPath}" style="width: 46px; height: 46px;"><i class="`;

			return iconElement;
		},
	};

	SugarCalendar.Admin.Connect.init( settings );

	window.SugarCalendar = SugarCalendar;

} )( jQuery, sugar_calendar_admin_connect );
