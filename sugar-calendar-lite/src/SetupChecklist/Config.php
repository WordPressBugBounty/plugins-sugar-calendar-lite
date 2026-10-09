<?php

namespace Sugar_Calendar\SetupChecklist;

use Sugar_Calendar\Admin\Pages\EventNew;
use Sugar_Calendar\Admin\Pages\SettingsGeneralTab;
use Sugar_Calendar\Admin\Pages\SettingsIntegrationsTab;
use Sugar_Calendar\Admin\Pages\ToolsMigrateTab;
use Sugar_Calendar\Helpers\Helpers;

/**
 * Presentation catalogue for the Setup Checklist page.
 *
 * Iconify class strings are stored whole because Tailwind's source scan reads literals;
 * a class assembled from parts at render time emits no utility.
 *
 * @since 3.15.0
 */
class Config {

	/**
	 * Canonical shared group IDs. The string values must remain stable for filters and markup.
	 *
	 * @since 3.15.0
	 */
	const GROUP_CREATE_FIRST_EVENT       = 'create-first-event';
	const GROUP_IMPROVE_EVENT_EXPERIENCE = 'improve-event-experience';
	const GROUP_PAYMENT_GATEWAY          = 'payment-gateway';

	/**
	 * Canonical Lite item IDs. The string values are the persisted identity and must not change.
	 *
	 * @since 3.15.0
	 */
	const ITEM_CREATE_EVENT               = 'create-event';
	const ITEM_IMPORT_THE_EVENTS_CALENDAR = 'import-the-events-calendar';
	const ITEM_EMBED_CALENDAR             = 'embed-calendar';
	const ITEM_VISIT_EVENTS_PAGE          = 'visit-events-page';
	const ITEM_DATE_TIME_SETTINGS         = 'date-time-settings';
	const ITEM_TIMEZONE                   = 'timezone';
	const ITEM_EMAIL_DELIVERY             = 'email-delivery';
	const ITEM_CONNECT_ZOOM               = 'connect-zoom';
	const ITEM_PRIVACY_COMPLIANCE         = 'privacy-compliance';
	const ITEM_AI_ASSISTANT               = 'ai-assistant';
	const ITEM_NEWSLETTER                 = 'newsletter';
	const ITEM_USAGE_TRACKING             = 'usage-tracking';
	const ITEM_CONNECT_STRIPE             = 'connect-stripe';

	/**
	 * Post statuses that count as saved: not an auto-draft and not trashed.
	 *
	 * One list for every check, so the page selector never offers a page whose save would not complete the row.
	 *
	 * @since 3.15.0
	 *
	 * @var string[]
	 */
	const SAVED_POST_STATUSES = [ 'publish', 'draft', 'pending', 'private', 'future' ];

	/**
	 * The extra POST fields a row's click may carry, from its `input` key.
	 *
	 * Reads the filtered catalogue, so a Pro row can declare input too.
	 *
	 * @since 3.15.0
	 *
	 * @param string $item_id Canonical item ID.
	 *
	 * @return string[]
	 */
	public static function get_item_input( $item_id ) {

		foreach ( self::get_groups() as $group ) {
			if ( isset( $group['items'][ $item_id ]['input'] ) ) {
				return (array) $group['items'][ $item_id ]['input'];
			}
		}

		return [];
	}

	/**
	 * IDs of the rows a recorded State history entry completes, from their `history_backed` key.
	 *
	 * Reads the filtered catalogue, so a Pro row can declare it too.
	 *
	 * @since 3.15.0
	 *
	 * @return string[]
	 */
	public static function get_history_item_ids() {

		$item_ids = [];

		foreach ( (array) self::get_groups() as $group ) {
			foreach ( (array) ( $group['items'] ?? [] ) as $item_id => $item ) {
				if ( ! empty( $item['history_backed'] ) ) {
					$item_ids[] = $item_id;
				}
			}
		}

		return $item_ids;
	}

	/**
	 * Ordered checklist groups common to both editions.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public static function get_groups() {

		$groups = [
			self::GROUP_CREATE_FIRST_EVENT       => [
				'title' => __( 'Create Your First Event', 'sugar-calendar-lite' ),
				'items' => self::get_create_first_event_items(),
			],
			self::GROUP_IMPROVE_EVENT_EXPERIENCE => [
				'title' => __( 'Improve Your Event Experience', 'sugar-calendar-lite' ),
				'items' => self::get_improve_event_experience_items(),
			],
			self::GROUP_PAYMENT_GATEWAY          => [
				'title' => __( 'Connect to Payment Gateway', 'sugar-calendar-lite' ),
				'items' => self::get_payment_gateway_items(),
			],
		];

		/**
		 * Filters the Setup Checklist groups.
		 *
		 * Pro adds its own group and drops the Lite-only usage-tracking row here.
		 *
		 * @since 3.15.0
		 *
		 * @param array $groups Ordered groups, keyed by stable group id.
		 */
		return apply_filters( 'sugar_calendar_setup_checklist_groups', $groups ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- named for the feature, not this class.
	}

	/**
	 * "Create Your First Event" rows.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private static function get_create_first_event_items() {

		return [
			self::ITEM_CREATE_EVENT               => [
				'title'       => __( 'Create an Event', 'sugar-calendar-lite' ),
				'description' => __( 'Add your first event and pick the calendar it belongs to.', 'sugar-calendar-lite' ),
				'action'      => [
					'action'  => 'create',
					'type'    => 'link',
					'label'   => __( 'Create Event', 'sugar-calendar-lite' ),
					'url'     => admin_url( EventNew::get_slug() ),
					'variant' => 'primary',
					'icon'    => 'sc:icon-[fa6-solid--arrow-right]',
				],
			],
			self::ITEM_IMPORT_THE_EVENTS_CALENDAR => [
				'title'       => __( 'Import From The Events Calendar', 'sugar-calendar-lite' ),
				'description' => __( 'We detected The Events Calendar on your website. Bring your existing events over in just a few clicks.', 'sugar-calendar-lite' ),
				'action'      => [
					'action'  => 'import',
					'type'    => 'button',
					'label'   => __( 'Import Events', 'sugar-calendar-lite' ),
					'url'     => ToolsMigrateTab::get_url(),
					'variant' => 'secondary',
					'icon'    => 'sc:icon-[fa6-solid--arrow-right]',
				],
			],
			self::ITEM_EMBED_CALENDAR             => [
				'title'          => __( 'Add Your Calendar to a Page', 'sugar-calendar-lite' ),
				'description'    => __( 'Select an existing page and we’ll add the Sugar Calendar block to it for you.', 'sugar-calendar-lite' ),
				'action'         => [
					'action' => 'embed',
					'type'   => 'page-selector',
					'label'  => __( 'Embed to a Page', 'sugar-calendar-lite' ),
				],
				'input'          => [ 'page_id' ],
				'history_backed' => true,
			],
			self::ITEM_VISIT_EVENTS_PAGE          => [
				'title'            => __( 'Visit Your Events Page', 'sugar-calendar-lite' ),
				'description'      => __( 'Every event you create automatically appears on your site’s /events archive page. You can also change the page’s slug.', 'sugar-calendar-lite' ),
				'description_link' => [
					'label' => __( 'Learn More', 'sugar-calendar-lite' ),
					'url'   => 'https://sugarcalendar.com/docs/events/updating-the-event-slug/',
				],
				'action'           => [
					'action' => 'visit',
					'type'   => 'link',
					'label'  => __( 'View Events Page', 'sugar-calendar-lite' ),
					'url'    => (string) get_post_type_archive_link( sugar_calendar_get_event_post_type_id() ),
				],
				'history_backed'   => true,
			],
		];
	}

	/**
	 * "Improve Your Event Experience" rows.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private static function get_improve_event_experience_items() {

		return [
			self::ITEM_DATE_TIME_SETTINGS => [
				'title'       => __( 'Fine-Tune Date & Time Settings', 'sugar-calendar-lite' ),
				'description' => __( 'Set your date format, time format, and the first day of your week.', 'sugar-calendar-lite' ),
				'action'      => [
					'action' => 'settings',
					'type'   => 'link',
					'label'  => __( 'Review Settings', 'sugar-calendar-lite' ),
					'url'    => SettingsGeneralTab::get_url(),
				],
			],
			self::ITEM_TIMEZONE           => [
				'title'       => __( 'Set Up Your Time Zone', 'sugar-calendar-lite' ),
				'description' => __( 'Choose the time zone your events are scheduled in so they always display at the right time.', 'sugar-calendar-lite' ),
				'action'      => [
					'action' => 'settings',
					'type'   => 'link',
					'label'  => __( 'Set Time Zone', 'sugar-calendar-lite' ),
					'url'    => SettingsGeneralTab::get_url(),
				],
			],
			self::ITEM_EMAIL_DELIVERY     => [
				'title'       => __( 'Make Sure Event Emails Get Delivered', 'sugar-calendar-lite' ),
				'description' => __( 'Keep event notifications out of spam by sending them through the free WP Mail SMTP plugin.', 'sugar-calendar-lite' ),
				'action'      => [
					'action'        => 'setup',
					'type'          => 'button',
					'partner_key'   => 'wp-mail-smtp',
					'label'         => __( 'Set Up WP Mail SMTP', 'sugar-calendar-lite' ),
					'plugin_labels' => [
						'install'    => __( 'Install WP Mail SMTP', 'sugar-calendar-lite' ),
						'activate'   => __( 'Activate WP Mail SMTP', 'sugar-calendar-lite' ),
						'installing' => __( 'Installing...', 'sugar-calendar-lite' ),
						'activating' => __( 'Activating...', 'sugar-calendar-lite' ),
					],
				],
			],
			self::ITEM_CONNECT_ZOOM       => [
				'title'       => __( 'Host Online Events', 'sugar-calendar-lite' ),
				'description' => __( 'Connect Zoom to automatically create meeting links for your virtual events.', 'sugar-calendar-lite' ),
				'action'      => [
					'action' => 'connect',
					'type'   => 'button',
					'label'  => __( 'Connect Zoom', 'sugar-calendar-lite' ),
					'url'    => SettingsIntegrationsTab::get_integration_url( 'zoom' ),
				],
			],
			self::ITEM_PRIVACY_COMPLIANCE => [
				'title'       => __( 'Set Up Privacy Compliance', 'sugar-calendar-lite' ),
				'description' => __( 'Improve GDPR, CCPA, and privacy compliance with cookie banner and consent records.', 'sugar-calendar-lite' ),
				'action'      => [
					'action'        => 'setup',
					'type'          => 'button',
					'partner_key'   => 'wpconsent',
					'label'         => __( 'Set Up WPConsent', 'sugar-calendar-lite' ),
					'plugin_labels' => [
						'install'    => __( 'Install WPConsent', 'sugar-calendar-lite' ),
						'activate'   => __( 'Activate WPConsent', 'sugar-calendar-lite' ),
						'installing' => __( 'Installing...', 'sugar-calendar-lite' ),
						'activating' => __( 'Activating...', 'sugar-calendar-lite' ),
					],
				],
			],
			self::ITEM_AI_ASSISTANT       => [
				'title'       => __( 'Connect Your AI Assistant', 'sugar-calendar-lite' ),
				'description' => __( 'Manage your events and calendars with Claude, ChatGPT, and more via the free WPVibe plugin.', 'sugar-calendar-lite' ),
				'action'      => [
					'action'        => 'setup',
					'type'          => 'button',
					'partner_key'   => 'wpvibe',
					'label'         => __( 'Install WPVibe', 'sugar-calendar-lite' ),
					'plugin_labels' => [
						'install'    => __( 'Install WPVibe', 'sugar-calendar-lite' ),
						'activate'   => __( 'Activate WPVibe', 'sugar-calendar-lite' ),
						'installing' => __( 'Installing...', 'sugar-calendar-lite' ),
						'activating' => __( 'Activating...', 'sugar-calendar-lite' ),
					],
				],
			],
			self::ITEM_NEWSLETTER         => [
				'title'          => __( 'Smart Recommendations from Sugar Calendar', 'sugar-calendar-lite' ),
				'description'    => __( 'Get helpful suggestions from Sugar Calendar on how to improve your events and grow your business.', 'sugar-calendar-lite' ),
				'action'         => [
					'action'         => 'subscribe',
					'type'           => 'email',
					'label'          => __( 'Sign Up for Free', 'sugar-calendar-lite' ),
					'failed_message' => __( 'Signing up failed. Please try again later.', 'sugar-calendar-lite' ),
				],
				'input'          => [ 'email' ],
				'history_backed' => true,
			],
			self::ITEM_USAGE_TRACKING     => [
				'title'       => __( 'Help Improve Sugar Calendar', 'sugar-calendar-lite' ),
				'description' => __( 'Collect non-sensitive information from your website, to help us fix bugs faster, make smarter decisions, and build features that actually matter to you.', 'sugar-calendar-lite' ),
				'action'      => [
					'action' => 'enable',
					'type'   => 'button',
					'label'  => __( 'Count Me In!', 'sugar-calendar-lite' ),
				],
			],
		];
	}

	/**
	 * "Connect to Payment Gateway" rows.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	private static function get_payment_gateway_items() {

		return [
			self::ITEM_CONNECT_STRIPE => [
				'title'       => __( 'Securely Accept Credit Card Payments', 'sugar-calendar-lite' ),
				'description' => __( 'Accept credit card payments, Apple Pay, Google Pay, ACH, and more with Sugar Calendar Events Stripe integration.', 'sugar-calendar-lite' ),
				'note'        => __( 'Note: 3% platform fees apply. Remove them with the Event Ticketing addon, available on', 'sugar-calendar-lite' ),
				'note_link'   => [
					'label' => __( 'Plus and higher plans', 'sugar-calendar-lite' ),
					'url'   => Helpers::get_upgrade_link(
						[
							'medium'  => 'setup-checklist',
							'content' => 'Plus and higher plans',
						]
					),
				],
				'action'      => [
					'action' => 'connect',
					'type'   => 'stripe',
					'label'  => __( 'Connect with Stripe', 'sugar-calendar-lite' ),
				],
			],
		];
	}

	/**
	 * Lite upsell cards. Informational only, and never counted toward progress.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public static function get_upsell_cards() {

		return [
			[
				'title'       => __( 'Recurring Events', 'sugar-calendar-lite' ),
				'description' => __( 'Schedule daily, weekly, or monthly repeats.', 'sugar-calendar-lite' ),
				'icon'        => 'sc:icon-[fa6-solid--repeat]',
			],
			[
				'title'       => __( 'Advanced Event Ticketing', 'sugar-calendar-lite' ),
				'description' => __( 'Sell multiple ticket types per event with 0% fees.', 'sugar-calendar-lite' ),
				'icon'        => 'sc:icon-[fa6-solid--ticket]',
			],
			[
				'title'       => __( 'RSVP', 'sugar-calendar-lite' ),
				'description' => __( 'Let attendees RSVP and track your headcount.', 'sugar-calendar-lite' ),
				'icon'        => 'sc:icon-[fa6-solid--user-check]',
			],
			[
				'title'       => __( 'Community Events', 'sugar-calendar-lite' ),
				'description' => __( 'Let visitors submit their own events from your site.', 'sugar-calendar-lite' ),
				'icon'        => 'sc:icon-[fa6-solid--users]',
			],
			[
				'title'       => __( 'Registration Forms', 'sugar-calendar-lite' ),
				'description' => __( 'Collect attendee details with registration forms.', 'sugar-calendar-lite' ),
				'icon'        => 'sc:icon-[fa6-solid--clipboard-list]',
			],
			[
				'title'       => __( 'More Integration Credits', 'sugar-calendar-lite' ),
				'description' => __( 'Get more credits for Zoom and other integrations.', 'sugar-calendar-lite' ),
				'icon'        => 'sc:icon-[fa6-solid--plug]',
			],
			[
				'title'       => __( 'Speakers', 'sugar-calendar-lite' ),
				'description' => __( 'Add speaker profiles and pages to your events.', 'sugar-calendar-lite' ),
				'icon'        => 'sc:icon-[fa6-solid--microphone]',
			],
			[
				'title'       => __( 'Venues', 'sugar-calendar-lite' ),
				'description' => __( 'Manage venues with maps, details, and pages.', 'sugar-calendar-lite' ),
				'icon'        => 'sc:icon-[fa6-solid--location-dot]',
			],
			[
				'title'       => __( 'Calendar Feeds', 'sugar-calendar-lite' ),
				'description' => __( 'Let visitors subscribe via Google or iCal.', 'sugar-calendar-lite' ),
				'icon'        => 'sc:icon-[fa6-solid--rss]',
			],
			[
				'title'       => __( 'Priority Support', 'sugar-calendar-lite' ),
				'description' => __( 'Get help from our events experts.', 'sugar-calendar-lite' ),
				'icon'        => 'sc:icon-[fa6-solid--headset]',
			],
			[
				'title'       => __( 'Zapier', 'sugar-calendar-lite' ),
				'description' => __( 'Connect your events to 8,000+ apps.', 'sugar-calendar-lite' ),
				'icon'        => 'sc:icon-[fa6-solid--bolt]',
			],
		];
	}

	/**
	 * Sister-plugin cards. Never counted toward progress.
	 *
	 * Keys match the partner catalogue; `icon` is the basename of an SVG under
	 * `assets/images/setup-checklist/`.
	 *
	 * @since 3.15.0
	 *
	 * @return array
	 */
	public static function get_growth_tools() {

		return [
			'aioseo'          => [
				'key'         => 'aioseo',
				'title'       => __( 'AIOSEO', 'sugar-calendar-lite' ),
				'description' => __( 'Improve SEO rankings with AI tools and get valuable insights.', 'sugar-calendar-lite' ),
				'icon'        => 'brand-aioseo',
			],
			'universally'     => [
				'key'         => 'universally',
				'title'       => __( 'Universally', 'sugar-calendar-lite' ),
				'description' => __( 'Easily translate your website into 110+ languages within minutes using AI.', 'sugar-calendar-lite' ),
				'icon'        => 'brand-universally',
			],
			'duplicator'      => [
				'key'         => 'duplicator',
				'title'       => __( 'Duplicator', 'sugar-calendar-lite' ),
				'description' => __( 'Easy, fast, and secure WordPress backups and website migrations.', 'sugar-calendar-lite' ),
				'icon'        => 'brand-duplicator',
			],
			'reviews-feed'    => [
				'key'         => 'reviews-feed',
				'title'       => __( 'Reviews Feed', 'sugar-calendar-lite' ),
				'description' => __( 'Show customer reviews from Google, Yelp, TripAdvisor, and more to boost sales.', 'sugar-calendar-lite' ),
				'icon'        => 'brand-reviews-feed',
			],
			'optinmonster'    => [
				'key'         => 'optinmonster',
				'title'       => __( 'OptinMonster', 'sugar-calendar-lite' ),
				'description' => __( 'Get more email subscribers & sales with the #1 CRO toolkit for WordPress.', 'sugar-calendar-lite' ),
				'icon'        => 'brand-optinmonster',
			],
			'monsterinsights' => [
				'key'         => 'monsterinsights',
				'title'       => __( 'MonsterInsights', 'sugar-calendar-lite' ),
				'description' => __( 'Website analytics made easy for WordPress. Form tracking, reports, and more.', 'sugar-calendar-lite' ),
				'icon'        => 'brand-monsterinsights',
			],
		];
	}
}
