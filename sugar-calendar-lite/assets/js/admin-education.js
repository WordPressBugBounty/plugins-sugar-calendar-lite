/* globals jQuery, sugar_calendar_admin_education */
( function ( $, settings ) {

	'use strict';

	let SugarCalendar = window.SugarCalendar || {};
	SugarCalendar.Admin = SugarCalendar.Admin || {};

	SugarCalendar.Admin.Education = {

		/**
		 * Initialize.
		 *
		 * @since 3.0.0
		 */
		init: function ( settings ) {

			this.settings = settings;
			this.$notices = $( '.sugar-calendar-education-notice' );
			this.$dismissButtons = $( '.sugar-calendar-dismiss-notice' );

			this.bindEvents();
		},

		/**
		 * Opens the feed upgrade modal.
		 *
		 * @since 3.11.0
		 */
		openFeedsUpgradeModal: function() {

			const $that = $( this );

			if ( ! $that.data( 'feed-id' ) || ! $that.data( 'feed-name' ) ) {
				return;
			}

			const feedId  = $that.data( 'feed-id' );
			const feedName = $that.data( 'feed-name' );
			const featName  = feedName + ' Feeds'; 
			const featTitle = featName + ' ' + sugar_calendar_admin_education.sce_admin_settings_feeds_education.upgrade_title;
			const featContent = sugar_calendar_admin_education.sce_admin_settings_feeds_education.upgrade_content.replace( '[feat-name]', featName );

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
							upgradeURL.searchParams.set( 'utm_medium', 'upgrade-modal-' + feedId );
							upgradeURL.searchParams.set( 'utm_content', 'Upgrade to Pro' );
							upgradeURL.searchParams.set( 'utm_campaign', 'liteplugin' );
							upgradeURL.searchParams.set( 'utm_locale', sugar_calendar_admin_education.sce_admin_settings_feeds_education.utm_locale );

							window.open( upgradeURL, '_blank' );
							SugarCalendar.Admin.Education.openUpradeThankYouModal( feedId );
						}
					},
				},
				onOpenBefore: function() { // eslint-disable-line object-shorthand
					let $btnc = $( '.jconfirm-buttons' );
					const discountNote = '<div class="discount-note"><p>' + sugar_calendar_admin_education.sce_admin_settings_feeds_education.upgrade_bonus + '</p></div>';

					const alreadyPurchasedURL = new URL( 'https://sugarcalendar.com/docs/events/upgrading-from-sugar-calendar-lite-to-a-paid-license/' );
					alreadyPurchasedURL.searchParams.set( 'utm_source', 'WordPress' );
					alreadyPurchasedURL.searchParams.set( 'utm_medium', 'upgrade-modal-' + feedId );
					alreadyPurchasedURL.searchParams.set( 'utm_content', 'Already%20purchased' );
					alreadyPurchasedURL.searchParams.set( 'utm_campaign', 'liteplugin' );
					alreadyPurchasedURL.searchParams.set( 'utm_locale', sugar_calendar_admin_education.sce_admin_settings_feeds_education.utm_locale );

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

		/**
		 * Bind events.
		 *
		 * @since 3.11.0
		 */
		bindEvents: function () {

			this.$dismissButtons.on( 'click', this.dismissNotice.bind( this ) );

			const $feedsList = $( '#sugar-calendar-settings-feeds-list' );

			if ( $feedsList.length ) {
				$feedsList.on( 'click', 'li', this.openFeedsUpgradeModal );
			}
		},

		/**
		 * Open the upgrade thank you modal.
		 *
		 * @since 3.11.0
		 *
		 * @param {string} utm_medium The UTM medium.
		 */
		openUpradeThankYouModal: function( utm_medium ) {
			$.alert( {
				theme: 'light,sce-admin-education',
				title: sugar_calendar_admin_education.sce_admin_upgrade_thank_you_modal.title,
				content: sugar_calendar_admin_education.sce_admin_upgrade_thank_you_modal.content,
				icon: 'fa fa-info-circle',
				type: 'blue',
				boxWidth: '565px',
				buttons: {
					confirm: {
						text: sugar_calendar_admin_education.sce_admin_upgrade_thank_you_modal.ok,
						btnClass: 'btn-confirm sce-jquery-confirm-upgrade-thank-you-btn sce-jquery-confirm-button sugar-calendar-btn-secondary sugar-calendar-btn-lg',
						keys: [ 'enter' ],
					},
				},
				onOpenBefore: function() { // eslint-disable-line object-shorthand
					const documentationLink = $( '.sce-upgrade-thank-you-modal-documentation-link' );
					const contactLink = $( '.sce-upgrade-thank-you-modal-contact-link' );

					SugarCalendar.Admin.Education.updateLink( documentationLink, 'thank-you-modal-' + utm_medium );
					SugarCalendar.Admin.Education.updateLink( contactLink, 'thank-you-modal-' + utm_medium );
				},
			} );
		},

		/**
		 * Update the href of the hyperlink.
		 *
		 * @since 3.11.0
		 *
		 * @param {jQuery} $link Link jQuery object.
		 * @param {string} utm_medium UTM Medium.
		 */
		updateLink: function( $link, utm_medium ) {

			if ( $link.length <= 0 || $link.attr( 'href' ).length <= 0 ) {
				return;
			}

			const hrefURL = new URL( $link.attr( 'href' ) );

			if ( ! hrefURL ) {
				return;
			}

			hrefURL.searchParams.set( 'utm_medium', utm_medium );

			$link.attr( 'href', hrefURL.toString() );
		},

		/**
		 * Dismiss the notice.
		 *
		 * @since 3.11.0
		 *
		 * @param {Event} e Event object.
		 */
		dismissNotice: function ( e ) {

			const noticeId = $( e.target ).attr( 'data-notice' );
			const $notice = this.$notices.filter( `[data-notice="${noticeId}"]` )

			$.post( this.settings.ajax_url, {
				task: 'education_notice_dismiss',
				notice_id: noticeId,
			} );

			if ( noticeId === 'notice_bar' ) {
				$notice.slideUp( 250, () => $notice.remove() );
			} else {
				$notice.remove();
			}
		},
	};

	SugarCalendar.Admin.Education.init( settings );

	window.SugarCalendar = SugarCalendar;

} )( jQuery, sugar_calendar_admin_education );
