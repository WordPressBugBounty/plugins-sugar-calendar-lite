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
			this.$educationModalLinks = $( '.sce-lite-education-modal-link' );

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
						text: sugar_calendar_admin_education.sce_admin_settings_feeds_education.upgrade_button,
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

					const alreadyPurchased = '<a href="' + alreadyPurchasedURL.toString() + '" target="_blank" rel="noopener noreferrer" class="already-purchased">' + sugar_calendar_admin_education.sce_admin_settings_feeds_education.already_purchased + '</a>';
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
		 * Open the "this is a Pro feature" upgrade modal.
		 *
		 * The shared opener behind every Pro-feature prompt. All copy and UTM
		 * values arrive through `options`, so it reads no screen-specific global
		 * and can be called from any admin page that has jquery-confirm loaded.
		 *
		 * @since 3.13.0
		 *
		 * @param {Object} options                  Modal options.
		 * @param {string} options.title            Modal title.
		 * @param {string} options.content          Modal body.
		 * @param {string} options.bonus            Discount note under the buttons.
		 * @param {string} options.upgradeButton    Confirm-button label.
		 * @param {string} options.alreadyPurchased "Already purchased?" link label.
		 * @param {string} options.utmLocale        utm_locale value.
		 * @param {string} options.utmMedium        Medium base, e.g. 'event-metabox-event-venues'.
		 * @param {Object} [options.thankYou]       Thank-you modal copy. Falls back to
		 *                                          the global when omitted.
		 */
		openUpgradeModal: function( options ) {

			const opts = $.extend(
				{
					title: '',
					content: '',
					bonus: '',
					upgradeButton: '',
					alreadyPurchased: '',
					utmLocale: '',
					utmMedium: '',
					thankYou: null,
				},
				options || {}
			);

			$.alert( {
				theme: 'light,sce-admin-education',
				title: opts.title,
				bootstrapClasses: { container: 'container sce-jquery-confirm-container', containerFluid: 'container-fluid', row: 'row' },
				backgroundDismiss: true,
				boxWidth: '550px',
				buttons: {
					confirm: {
						text: opts.upgradeButton,
						btnClass: 'btn-confirm sce-jquery-confirm-button sugar-calendar-btn-primary sugar-calendar-btn-lg',
						keys: [ 'enter' ],
						action: function() { // eslint-disable-line object-shorthand
							window.open(
								SugarCalendar.Admin.Education.campaignURL( 'https://sugarcalendar.com/lite-upgrade/', 'Upgrade to Pro', opts ),
								'_blank'
							);
							SugarCalendar.Admin.Education.openUpradeThankYouModal( opts.utmMedium, opts.thankYou );
						},
					},
				},
				onOpenBefore: function() { // eslint-disable-line object-shorthand
					const discountNote = '<div class="discount-note"><p>' + opts.bonus + '</p></div>';
					const alreadyPurchasedURL = SugarCalendar.Admin.Education.campaignURL(
						'https://sugarcalendar.com/docs/events/upgrading-from-sugar-calendar-lite-to-a-paid-license/',
						'Already%20purchased',
						opts
					);
					const alreadyPurchased = '<a href="' + alreadyPurchasedURL + '" target="_blank" rel="noopener noreferrer" class="already-purchased">' + opts.alreadyPurchased + '</a>';

					$( '.jconfirm-buttons' ).after( discountNote + alreadyPurchased );
				},
				icon: 'sce-icon sce-icon__lock',
				escapeKey: true,
				content: opts.content,
				useBootstrap: false,
				closeIcon: true,
			} );
		},

		/**
		 * Build a campaign-tagged sugarcalendar.com URL for the upgrade modal.
		 *
		 * @since 3.13.0
		 *
		 * @param {string} base       Destination URL.
		 * @param {string} utmContent utm_content value, passed through as given.
		 * @param {Object} opts       Modal options carrying utmMedium / utmLocale.
		 *
		 * @return {string} The tagged URL.
		 */
		campaignURL: function( base, utmContent, opts ) {

			const url = new URL( base );

			url.searchParams.set( 'utm_source', 'WordPress' );
			url.searchParams.set( 'utm_medium', 'upgrade-modal-' + opts.utmMedium );
			url.searchParams.set( 'utm_content', utmContent );
			url.searchParams.set( 'utm_campaign', 'liteplugin' );
			url.searchParams.set( 'utm_locale', opts.utmLocale );

			return url.toString();
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

			if ( this.$educationModalLinks.length ) {
				this.$educationModalLinks.on( 'click', this.onEducationModalLinkClick );
			}
		},

		/**
		 * Display the Pro Upgrade modal for any generic
		 * `.sce-lite-education-modal-link` trigger (event metabox fields,
		 * Tools page Pro-feature previews, ...). Moved here from
		 * admin-event-lite.js (#729/#737) -- this bundle is already shared
		 * by every page that needs the modal, so the handler now lives
		 * alongside the rest of the education modal code instead of in a
		 * bundle scoped to the event editor.
		 *
		 * `this` is the clicked DOM element (jQuery's default handler
		 * binding), not the `SugarCalendar.Admin.Education` object -- same
		 * pattern as `openFeedsUpgradeModal` above.
		 *
		 * @since 3.11.0
		 */
		onEducationModalLinkClick: function () {

			const $this = $( this );

			let featTitle = sugar_calendar_admin_education.sce_admin_upgrade_modal_title_default;
			let featName  = $this.data( 'feat-name' ) ? $this.data( 'feat-name' ) : sugar_calendar_admin_education.sce_admin_upgrade_modal_feature_name;
			const featId = $this.data( 'feat-id' ) ? $this.data( 'feat-id' ) : sugar_calendar_admin_education.sce_admin_upgrade_modal_feature_name;

			if ( featName ) {
				featTitle = featName + ' ' + sugar_calendar_admin_education.sce_admin_upgrade_modal_content.upgrade_title;
				featName = featName + ' ' + sugar_calendar_admin_education.sce_admin_upgrade_modal_feature_name;
			}

			const featContent = sugar_calendar_admin_education.sce_admin_upgrade_modal_content.upgrade_content.replace( '[feat-name]', featName );
			const modalCopy   = sugar_calendar_admin_education.sce_admin_upgrade_modal_content;

			SugarCalendar.Admin.Education.openUpgradeModal( {
				title: featTitle,
				content: featContent,
				bonus: modalCopy.upgrade_bonus,
				upgradeButton: modalCopy.upgrade_button,
				alreadyPurchased: modalCopy.already_purchased,
				utmLocale: modalCopy.utm_locale,
				utmMedium: 'event-metabox-' + featId,
			} );
		},

		/**
		 * Open the upgrade thank you modal.
		 *
		 * @since 3.11.0
		 * @since 3.13.0 Accept the copy directly, for screens that localize their
		 *                  own object instead of the shared education global.
		 *
		 * @param {string} utm_medium The UTM medium.
		 * @param {Object} [copy]     Modal copy ( title / content / ok ). Falls back
		 *                            to the shared education global when omitted.
		 */
		openUpradeThankYouModal: function( utm_medium, copy ) {

			const text = copy || sugar_calendar_admin_education.sce_admin_upgrade_thank_you_modal;

			$.alert( {
				theme: 'light,sce-admin-education',
				title: text.title,
				content: text.content,
				icon: 'fa fa-info-circle',
				type: 'blue',
				boxWidth: '565px',
				buttons: {
					confirm: {
						text: text.ok,
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
