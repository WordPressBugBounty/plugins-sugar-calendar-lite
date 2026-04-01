'use strict';

const SugarCalendarAdminEventLite = window.SugarCalendarAdminEventLite || ( function( document, window, $, ajaxurl ) {

	/**
	 * Elements holder.
	 *
	 * @since 3.11.0
	 *
	 * @type {Object}
	 */
	const el = {};

	/**
	 * Event Lite JS.
	 *
	 * @since 3.11.0
	 *
	 * @type {Object}
	 */
	const app = {

		/**
		 * Start the engine.
		 *
		 * @since 3.11.0
		 */
		init() {

			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 3.11.0
		 */
		ready() {

			app.setup();
			app.bindEvents();
		},

		/**
		 * Setup.
		 *
		 * @since 3.11.0
		 */
		setup() {

			el.$educationModalLinks = $( '.sce-lite-education-modal-link' );
		},

		/**
		 * Bind events.
		 *
		 * @since 3.11.0
		 */
		bindEvents() {

			el.$educationModalLinks.on( 'click', app.onEducationModalLinkClick );
		},

		/**
		 * Display the Pro Upgrade modal.
		 *
		 * @since 3.11.0
		 */
		onEducationModalLinkClick() {

			const $this = $( this );

			let featTitle = sugar_calendar_admin_education.sce_admin_upgrade_modal_title_default;
			let featName  = $this.data( 'feat-name' ) ? $this.data( 'feat-name' ) : sugar_calendar_admin_education.sce_admin_upgrade_modal_feature_name;
			const featId = $this.data( 'feat-id' ) ? $this.data( 'feat-id' ) : sugar_calendar_admin_education.sce_admin_upgrade_modal_feature_name;

			if ( featName ) {
				featTitle = featName + ' ' + sugar_calendar_admin_education.sce_admin_upgrade_modal_content.upgrade_title;
				featName = featName + ' ' + sugar_calendar_admin_education.sce_admin_upgrade_modal_feature_name;
			}

			const featContent = sugar_calendar_admin_education.sce_admin_upgrade_modal_content.upgrade_content.replace( '[feat-name]', featName );

			$.alert({
				theme: 'light,sce-admin-education',
				title: featTitle,
				bootstrapClasses: { container: 'container sce-jquery-confirm-container', containerFluid: 'container-fluid', row: 'row' },
				backgroundDismiss: true,
				boxWidth: '550px',
				buttons: {
					confirm: {
						text: 'Upgrade to Pro',
						btnClass: 'btn-confirm sce-jquery-confirm-button sugar-calendar-btn-primary sugar-calendar-btn-lg',
						keys: [ 'enter' ],
						action: function() {
							const upgradeURL = new URL( 'https://sugarcalendar.com/lite-upgrade/' );
							upgradeURL.searchParams.set( 'utm_source', 'WordPress' );
							upgradeURL.searchParams.set( 'utm_medium', 'upgrade-modal-event-metabox-' + featId );
							upgradeURL.searchParams.set( 'utm_content', 'Upgrade to Pro');
							upgradeURL.searchParams.set( 'utm_campaign', 'liteplugin' );
							upgradeURL.searchParams.set( 'utm_locale', sugar_calendar_admin_education.sce_admin_upgrade_modal_content.utm_locale );

							window.open( upgradeURL, '_blank' );
							SugarCalendar.Admin.Education.openUpradeThankYouModal( 'event-metabox-' + featId );
						}
					},
				},
				onOpenBefore: function() { // eslint-disable-line object-shorthand
					let $btnc = $( '.jconfirm-buttons' );
					const discountNote = '<div class="discount-note"><p>' + sugar_calendar_admin_education.sce_admin_upgrade_modal_content.upgrade_bonus + '</p></div>';

					const alreadyPurchasedURL = new URL( 'https://sugarcalendar.com/docs/events/upgrading-from-sugar-calendar-lite-to-a-paid-license/' );
					alreadyPurchasedURL.searchParams.set( 'utm_source', 'WordPress' );
					alreadyPurchasedURL.searchParams.set( 'utm_medium', 'upgrade-modal-event-metabox-' + featId );
					alreadyPurchasedURL.searchParams.set( 'utm_content', 'Already%20purchased' );
					alreadyPurchasedURL.searchParams.set( 'utm_campaign', 'liteplugin' );
					alreadyPurchasedURL.searchParams.set( 'utm_locale', sugar_calendar_admin_education.sce_admin_upgrade_modal_content.utm_locale );

					const alreadyPurchased = '<a href="' + alreadyPurchasedURL.toString() + '" target="_blank" rel="noopener noreferrer" class="already-purchased">Already purchased?</a>';
					$btnc.after( discountNote + alreadyPurchased );
				},
				icon: 'sce-icon sce-icon__lock',
				escapeKey: true,
				content: featContent,
				useBootstrap: false,
				closeIcon: true,
			});
		},
	};

	return app;
} ( document, window, jQuery,ajaxurl ) );

SugarCalendarAdminEventLite.init();