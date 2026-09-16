<?php

namespace Sugar_Calendar\AddOn\Ticketing\Admin\Settings;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions as Functions;
use Sugar_Calendar\AddOn\Ticketing\Frontend\EditorPreview;
use Sugar_Calendar\AddOn\Ticketing\Helpers\UI;
use Sugar_Calendar\AddOn\Ticketing\Settings as Settings;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Admin\Area;
use Sugar_Calendar\Admin\Pages\Settings as PageSettings;
use Sugar_Calendar\Admin\Pages\Settings\SingleEmailConfig;
use Sugar_Calendar\Emails\Styling;

/**
 * Register page ids.
 *
 * @since 1.2.0
 * @since 3.7.0 Added support for Emails tab.
 *
 * @param string|null $page_id Current page id.
 */
function admin_area_current_page_id( $page_id ) {

	if ( $page_id === 'settings' ) {
		$section = $_GET['section'] ?? 'general'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		switch ( $section ) {
			case 'payments':
				$page_id = 'settings_payments';
				break;

			case 'tickets':
				$page_id = 'settings_emails';
				break;

			case 'emails':
				$page_id = 'settings_emails';
				break;
		}
	}

	return $page_id;
}

/**
 * Register page classes.
 *
 * @since 1.2.0
 * @since 3.7.0 Added support to Emails tab.
 *
 * @return PageInterface[]
 */
function admin_area_pages( $pages ) {

	$pages['settings_payments'] = $pages['settings'];
	$pages['settings_tickets']  = $pages['settings'];
	$pages['settings_emails']   = $pages['settings'];

	return $pages;
}

/**
 * Return the URL used for the section.
 *
 * @since 1.0.0
 * @since 3.7.0 Change the default to `payment`.
 *
 * @param array $args
 *
 * @return string
 */
function get_section_url( $args = [] ) {

	// Parse arguments
	$r = wp_parse_args( $args, [
		'page'    => PageSettings::get_slug(),
		'section' => 'payments',
	] );

	// Return the URL with query args
	return add_query_arg( $r, admin_url( 'admin.php' ) );
}

/**
 * Add the "Payments" and "Emails" section to the settings sections array.
 *
 * @since 1.0.0
 * @since 3.7.0 Renamed "Tickets" to "Emails".
 *
 * @param array $sections
 *
 * @return array
 */
function add_section( $sections = [] ) {

	$sections['payments'] = [
		'id'   => 'payments',
		'name' => esc_html__( 'Payments', 'sugar-calendar-lite' ),
		'url'  => get_section_url(
			[
				'section' => 'payments',
			]
		),
		'func' => __NAMESPACE__ . '\\section',
	];

	$sections['emails'] = [
		'id'   => 'emails',
		'name' => esc_html__( 'Emails', 'sugar-calendar-lite' ),
		'url'  => get_section_url(
			[
				'section' => 'emails',
			]
		),
		'func' => __NAMESPACE__ . '\\section',
	];

	// Return sections
	return $sections;
}

/**
 * Add the subsections.
 *
 * @since 1.0.0
 *
 * @param array $subsections
 *
 * @return array
 */
function add_subsection( $subsections = [] ) {

	$subsections['payments']['payments'] = [
		'id'   => 'payments',
		'name' => esc_html__( 'Payments', 'sugar-calendar-lite' ),
		'url'  => get_section_url( [ 'subsection' => 'main' ] ),
		'func' => __NAMESPACE__ . '\\payments_section',
	];

	$subsections['emails']['emails'] = [
		'id'   => 'emails',
		'name' => esc_html__( 'Emails', 'sugar-calendar-lite' ),
		'url'  => get_section_url( [ 'subsection' => 'main' ] ),
		'func' => __NAMESPACE__ . '\\emails_section',
	];

	// Return sections
	return $subsections;
}

/**
 * Render the payments section
 *
 * @since 1.0.0
 * @since 3.7.0 Moved "Tickets" related settings here.
 * @since 3.11.0 Moved the Stripe section at the top.
 */
function payments_section() {

	// Prefetch useful values
	$pages        = get_pages();
	$is_sandbox   = Functions\is_sandbox();
	$is_constant  = defined( 'SC_GATEWAY_SANDBOX_MODE' ) && SC_GATEWAY_SANDBOX_MODE;

	UI::heading(
		[
			'title'       => esc_html__( 'Stripe', 'sugar-calendar-lite' ),
			'id'          => 'stripe-connection',
			'description' => sprintf( /* translators: %1$s - Stripe connect description; %2$s - Stripe documentation URL; %3$s - Stripe documentation link. */
				'%1$s <a href="%2$s" target="_blank">%3$s</a>.',
				esc_html__( 'Easily collect credit card payments with Stripe. For getting started and more information, see our', 'sugar-calendar-lite' ),
				esc_url(
					Helpers::get_utm_url(
						'https://sugarcalendar.com/docs/events/event-ticketing-addon/#connecting-stripe',
						[
							'content' => 'Stripe documentation',
							'medium'  => 'settings-payments',
						]
					)
				),
				esc_html__( 'Stripe documentation', 'sugar-calendar-lite' )
			),
		]
	);

	/**
	 * Fires a hook before the Stripe integration section.
	 *
	 * @since 3.11.0
	 */
	do_action( 'sce_et_settings_payments_section_top_before_stripe_integration' );

	// Stripe integration.
	UI::field_wrapper(
		[
			'label' => esc_html__( 'Connection Status', 'sugar-calendar-lite' ),
			'id'    => 'stripe-connect',
		],
		display_stripe_connect_field( $is_sandbox )
	);

	// Test mode
	$test_mode_description = sprintf( /* translators: %1$s - test mode help; %2$s - link to Stripe dashboard; %3$s - link text. */
		'%1$s <a href="%2$s" target="_blank"> %3$s</a>.',
		esc_html__( 'While in test mode no live payments are processed. Be sure to enable Test Mode in your', 'sugar-calendar-lite' ),
		esc_url( 'https://dashboard.stripe.com/' ),
		esc_html__( 'Stripe Dashboard', 'sugar-calendar-lite' )
	);

	if ( $is_constant ) {
		$test_mode_description .= sprintf( /* translators: %1$s - test mode constant note; %2$s - constant. */
			'<br/>%1$s <code>SC_GATEWAY_SANDBOX_MODE</code> %2$s.',
			esc_html__( 'Note: Test Mode is currently enabled via the', 'sugar-calendar-lite' ),
			esc_html__( 'constant', 'sugar-calendar-lite' ),
		);
	}

	UI::toggle_control(
		[
			'id'          => 'sandbox',
			'name'        => 'sandbox',
			'value'       => $is_sandbox,
			'disabled'    => $is_constant,
			'label'       => esc_html__( 'Test Mode', 'sugar-calendar-lite' ),
			'description' => $test_mode_description,
		]
	);

	UI::heading(
		[
			'title' => esc_html__( 'Currency', 'sugar-calendar-lite' ),
		]
	);

	// Currency.
	$currencies       = Functions\get_currencies();
	$current_currency = Functions\get_currency();

	UI::select_input(
		[
			'id'          => 'sc_et_currency',
			'name'        => 'currency',
			'options'     => $currencies,
			'value'       => $current_currency,
			'label'       => esc_html__( 'Currency', 'sugar-calendar-lite' ),
			'description' => esc_html__( 'Choose your currency. Note that some payment gateways have currency restrictions.', 'sugar-calendar-lite' ),
		]
	);

	// Currency Symbol Position.
	$position  = Settings\get_setting( 'currency_position', 'before' );
	$positions = [
		'before' => esc_html__( 'Before ($10)', 'sugar-calendar-lite' ),
		'after'  => esc_html__( 'After (10$)', 'sugar-calendar-lite' ),
	];

	UI::select_input(
		[
			'id'          => 'sc_et_currency_position',
			'name'        => 'currency_position',
			'options'     => $positions,
			'value'       => $position,
			'label'       => esc_html__( 'Currency Symbol Position', 'sugar-calendar-lite' ),
			'description' => esc_html__( 'Choose the location of the currency symbol.', 'sugar-calendar-lite' ),
		]
	);

	// Thousands Separator.
	$thousands             = Settings\get_setting( 'thousands_separator', ',' );
	$thousands_description = sprintf( /* translators: %1$s - field description; %2$s - or.*/
		'%1$s <code>,</code> %2$s <code>.</code>.',
		esc_html__( 'The symbol to separate thousandths. Usually', 'sugar-calendar-lite' ),
		esc_html__( 'or', 'sugar-calendar-lite' ),
	);

	UI::text_input(
		[
			'id'          => 'sc_et_thousands_separator',
			'name'        => 'thousands_separator',
			'value'       => $thousands,
			'label'       => esc_html__( 'Thousands Separator', 'sugar-calendar-lite' ),
			'description' => $thousands_description,
		]
	);

	// Decimal Separator.
	$decimal             = Settings\get_setting( 'decimal_separator', '.' );
	$decimal_description = sprintf( /* translators: %1$s - field description; %2$s - or.*/
		'%1$s <code>,</code> %2$s <code>.</code>.',
		esc_html__( 'The symbol to separate decimal points. Usually', 'sugar-calendar-lite' ),
		esc_html__( 'or', 'sugar-calendar-lite' ),
	);

	UI::text_input(
		[
			'id'          => 'sc_et_decimal_separator',
			'name'        => 'decimal_separator',
			'value'       => $decimal,
			'label'       => esc_html__( 'Decimal Separator', 'sugar-calendar-lite' ),
			'description' => $decimal_description,
		]
	);

	$receipt_page_description = sprintf(
		'%1$s.<br/>%2$s',
		esc_html__( 'The page customers are sent to after completing their ticket purchase', 'sugar-calendar-lite' ),
		sprintf(
			/* translators: %s - shortcode tag, already wrapped in a code element. */
			esc_html__( "If the selected page doesn't have the %s shortcode, you can embed it and preview your changes.", 'sugar-calendar-lite' ),
			'<code>[sc_event_tickets_receipt]</code>'
		)
	);

	// Which pages already hold each shortcode, read from the objects above so the
	// button state costs no query of its own.
	$shortcode_pages = [
		'receipt_page' => get_pages_holding_shortcode( $pages, get_page_shortcode_tag( 'receipt_page' ) ),
		'ticket_page'  => get_pages_holding_shortcode( $pages, get_page_shortcode_tag( 'ticket_page' ) ),
	];

	$pages = wp_list_pluck( $pages, 'post_title', 'ID' );

	if ( empty( $pages ) ) {
		$pages        = [
			esc_html__( 'No pages found', 'sugar-calendar-lite' ),
		];
		$receipt_page = 0;
		$ticket_page  = 0;

	} else {
		$receipt_page = Settings\get_setting( 'receipt_page' );
		$ticket_page  = Settings\get_setting( 'ticket_page' );
	}

	UI::heading(
		[
			'title' => esc_html__( 'Tickets Page Configuration', 'sugar-calendar-lite' ),
		]
	);

	localize_page_action_data( $shortcode_pages );

	// With no saved value, or one the list no longer offers, the select shows its
	// first option, so the buttons answer for the page actually on screen.
	$first_page = (int) array_key_first( $pages );

	$receipt_preview = get_page_action_button( 'receipt_page', isset( $pages[ $receipt_page ] ) ? $receipt_page : $first_page, $shortcode_pages['receipt_page'] );
	$ticket_preview  = get_page_action_button( 'ticket_page', isset( $pages[ $ticket_page ] ) ? $ticket_page : $first_page, $shortcode_pages['ticket_page'] );

	// Use Legacy Template.
	UI::toggle_control(
		[
			'id'          => 'use_legacy_template',
			'name'        => 'use_legacy_template',
			'value'       => Settings\get_setting( 'use_legacy_template', false ),
			'label'       => esc_html__( 'Use Legacy Template', 'sugar-calendar-lite' ),
			'description' => esc_html__( 'Use the legacy HTML table instead of styled templates.', 'sugar-calendar-lite' ),
		]
	);

	UI::select_input(
		[
			'id'          => 'receipt_page',
			'name'        => 'receipt_page',
			'options'     => $pages,
			'value'       => $receipt_page,
			'label'       => esc_html__( 'Payment Success Page', 'sugar-calendar-lite' ),
			'description' => $receipt_page_description,
			'after_input' => $receipt_preview,
		]
	);

	$ticket_page_description = sprintf(
		'%1$s.<br/>%2$s',
		esc_html__( 'The page where customer will view the details of their ticket purchases', 'sugar-calendar-lite' ),
		sprintf(
			/* translators: %s - shortcode tag, already wrapped in a code element. */
			esc_html__( "If the selected page doesn't have the %s shortcode, you can embed it and preview your changes.", 'sugar-calendar-lite' ),
			'<code>[sc_event_tickets_details]</code>'
		)
	);

	UI::select_input(
		[
			'id'          => 'sc_et_ticket_page',
			'name'        => 'ticket_page',
			'options'     => $pages,
			'value'       => $ticket_page,
			'label'       => esc_html__( 'Ticket Details Page', 'sugar-calendar-lite' ),
			'description' => $ticket_page_description,
			'after_input' => $ticket_preview,
		]
	);

	$colors = [];

	// Order comes from the fields array, so the pickers and the sanitizer agree.
	foreach ( Settings\get_color_scheme_fields() as $color_key => $color_field ) {
		$colors[] = [
			'name'    => $color_key,
			'value'   => Settings\get_setting( $color_key, $color_field['default'] ),
			'label'   => $color_field['label'],
			'default' => $color_field['default'],
		];
	}

	// Color Scheme.
	UI::color_scheme_input(
		[
			'id'          => 'color_scheme',
			'label'       => esc_html__( 'Color Scheme', 'sugar-calendar-lite' ),
			'colors'      => $colors,

			// Hidden until Use Legacy Template is on; admin-color-scheme.js reveals it
			// alongside the dimming, so it only ever appears next to a disabled control.
			'description' => esc_html__( 'Color schemes will be disabled when legacy template is turned on', 'sugar-calendar-lite' ),
		]
	);

	// Attendee required fields.
	UI::toggle_control(
		[
			'id'          => 'attendee_fields_is_required',
			'name'        => 'attendee_fields_is_required',
			'value'       => Settings\get_setting( 'attendee_fields_is_required', false ),
			'label'       => esc_html__( 'Attendee Information is Required', 'sugar-calendar-lite' ),
			'description' => __( "Visitors purchasing your event tickets will be required to provide Attendee's information on checkout for every ticket they purchase.", 'sugar-calendar-lite' ),
		]
	);

	do_action( 'sc_et_settings_general_section_bottom' );
}

/**
 * The shortcode tag one of the two ticket page settings expects.
 *
 * @since 3.14.0
 *
 * @param string $key Setting key.
 *
 * @return string Shortcode tag, or '' when the key is not a ticket page setting.
 */
function get_page_shortcode_tag( $key ) {

	switch ( $key ) {
		case 'receipt_page':
			return 'sc_event_tickets_receipt';

		case 'ticket_page':
			return 'sc_event_tickets_details';
	}

	return '';
}

/**
 * Ids of the given pages whose content holds a shortcode.
 *
 * @since 3.14.0
 *
 * @param \WP_Post[] $pages Page objects.
 * @param string     $tag   Shortcode tag.
 *
 * @return int[]
 */
function get_pages_holding_shortcode( $pages, $tag ) {

	$ids = [];

	if ( empty( $tag ) ) {
		return $ids;
	}

	foreach ( $pages as $page ) {
		if ( has_shortcode( (string) $page->post_content, $tag ) ) {
			$ids[] = (int) $page->ID;
		}
	}

	return $ids;
}

/**
 * Hand the page-action buttons their state map, nonces and labels.
 *
 * Localized from the render rather than the enqueue so the map can reuse the page
 * objects already in hand. The script sits in the footer, so this still lands.
 *
 * @since 3.14.0
 *
 * @param array $shortcode_pages Setting key => ids of pages holding its shortcode.
 */
function localize_page_action_data( $shortcode_pages ) {

	wp_localize_script(
		'sc-et-admin-page-shortcode',
		'sc_et_page_shortcode',
		[
			'ajaxurl'  => admin_url( 'admin-ajax.php' ),

			// `?page_id=` resolves any page and survives the canonical redirect with
			// its query string, so Preview can retarget without a permalink map.
			'home'     => home_url( '/' ),
			'nonce'    => wp_create_nonce( 'sc-et-page-action' ),
			'preview'  => wp_create_nonce( EditorPreview::NONCE_ACTION ),
			'embedded' => $shortcode_pages,
			'labels'   => [
				'preview' => esc_html__( 'Preview', 'sugar-calendar-lite' ),
				'embed'   => esc_html__( 'Embed', 'sugar-calendar-lite' ),
			],
			'error'    => esc_html__( 'Could not add the shortcode to that page.', 'sugar-calendar-lite' ),
		]
	);
}

/**
 * The Embed / Preview button for one of the two ticket page settings.
 *
 * Both states are handled in JS, so this renders a button rather than a link: the
 * Preview URL carries the values on screen, which are not known until the click.
 *
 * Empty when no page is set: there is nothing to embed into or to preview.
 *
 * @since 3.14.0
 *
 * @param string $key      Setting key.
 * @param int    $page_id  Page the row currently shows.
 * @param int[]  $embedded Ids of pages already holding this setting's shortcode.
 *
 * @return string Button HTML, or '' when there is nothing to act on.
 */
function get_page_action_button( $key, $page_id, $embedded ) {

	$page_id = (int) $page_id;

	if ( empty( $page_id ) || empty( get_permalink( $page_id ) ) || empty( get_page_shortcode_tag( $key ) ) ) {
		return '';
	}

	$is_embedded = in_array( $page_id, $embedded, true );

	ob_start();

	UI::button(
		[
			'text'   => $is_embedded
				? esc_html__( 'Preview', 'sugar-calendar-lite' )
				: esc_html__( 'Embed', 'sugar-calendar-lite' ),
			'name'   => '',
			'type'   => 'tertiary',
			'class'  => [
				'sugar-calendar-setting-page-action',
				$is_embedded ? 'is-preview' : 'is-embed',
			],
			'submit' => false,
		]
	);

	return ob_get_clean();
}

function display_stripe_connect_field( $is_sandbox ) {

	$url_payment_settings = get_section_url( [ 'section' => 'payments' ] );
	$url_payment_settings = add_query_arg(
		[ '_wpnonce' => wp_create_nonce( 'sc-stripe-connect-completion' ) ],
		$url_payment_settings
	);

	$stripe_connect_url   = Functions\get_stripe_connect_url( $is_sandbox, $url_payment_settings );

	$stripe_disconnect_url = add_query_arg( [
		'sc-stripe-disconnect' => true,
		'page'                 => PageSettings::get_slug(),
		'section'              => 'payments',
		'subsection'           => 'payments',
		'_wpnonce'             => wp_create_nonce( 'sc-stripe-connect-disconnect' ),
	], admin_url( 'admin.php' ) );

	$test_text = _x( 'test', 'current value for sandbox mode', 'sugar-calendar-lite' );
	$live_text = _x( 'live', 'current value for sandbox mode', 'sugar-calendar-lite' );

	if ( true === $is_sandbox ) {
		$current_mode = $test_text;
	} else {
		$current_mode = $live_text;
	}

	ob_start();

	if ( ! Functions\stripe_is_connected() ):
		?>

        <a href="<?php echo esc_url_raw( $stripe_connect_url ); ?>" class="sugar-calendar-stripe-connect">
            <span><?php esc_html_e( 'Connect with', 'sugar-calendar-lite' ); ?></span>
        </a>

        <p class="desc">
			<?php echo wp_kses(
				sprintf( /* translators: %1$s - test mode help; %2$s - link to Stripe dashboard; %3$s - link text; %4$s - test mode enable heads up. */
					'%1$s <a href="%2$s" target="_blank"> %3$s</a> %4$s.',
					esc_html__( 'Securely connect to Stripe with just a few clicks to begin accepting payments!', 'sugar-calendar-lite' ),
					esc_url(
						Helpers::get_utm_url(
							'https://sugarcalendar.com/docs/events/setting-up-event-ticketing-with-sugar-calendar-lite/',
							[
								'medium'  => 'lite-event-tickets',
								'content' => 'Learn more about connecting with Stripe',
							]
						)
					),
					esc_html__( 'Learn more', 'sugar-calendar-lite' ),
					esc_html__( 'about connecting with Stripe', 'sugar-calendar-lite' )
				),
				[
					'a' => [ 'href' => true, 'target' => true ],
				]
			);
			?>
        </p>

	<?php else: ?>

        <p class="description">
			<?php printf( esc_html__( 'Your Stripe account is connected in %s mode.', 'sugar-calendar-lite' ), '<strong>' . $current_mode . '</strong>' ); ?>
            <a href="<?php echo esc_url_raw( $stripe_connect_url ); ?>" class="button button-primary"><?php esc_html_e( 'Reconnect', 'sugar-calendar-lite' ); ?></a>
            <a href="<?php echo esc_url_raw( $stripe_disconnect_url ); ?>" class="button"><?php esc_html_e( 'Disconnect', 'sugar-calendar-lite' ); ?></a>
        </p>

	<?php
	endif;

	return ob_get_clean();
}

/**
 * Render the emails section
 *
 * @since 1.0.0
 * @since 3.6.0 Added `attendee_fields_is_required`.
 * @since 3.7.0 Moved Ticket Details page to "Payments" tab and handle individual email config.
 */
function emails_section() {

	if ( ! empty( $_GET['email_cfg'] ) ) {
		( new SingleEmailConfig() )->init( sanitize_key( $_GET['email_cfg'] ) );

		return;
	}

	UI::heading(
		[
			'title' => esc_html__( 'Email Sender', 'sugar-calendar-lite' ),
		]
	);

	/**
	 * Fires a hook after the Email section's title.
	 *
	 * @since 3.8.0
	 */
	do_action( 'sc_et_settings_emails_section_top' );

	// From Email Address.
	$from_email = Settings\get_setting( 'receipt_from_email' );

	UI::text_input(
		[
			'id'          => 'sc_et_email_from',
			'name'        => 'receipt_from_email',
			'value'       => $from_email,
			'placeholder' => get_bloginfo( 'admin_email' ),
			'label'       => esc_html__( 'From Email Address', 'sugar-calendar-lite' ),
			'description' => esc_html__( 'The email address notifications are sent from.', 'sugar-calendar-lite' ),
		]
	);

	// From Email Name.
	$from_name = Settings\get_setting( 'receipt_from_name' );

	UI::text_input(
		[
			'id'          => 'sc_et_receipt_email_name',
			'name'        => 'receipt_from_name',
			'value'       => $from_name,
			'placeholder' => get_bloginfo( 'name' ),
			'label'       => esc_html__( 'From Email Name', 'sugar-calendar-lite' ),
			'description' => esc_html__( 'The person/business name email notifications are sent from.', 'sugar-calendar-lite' ),
		]
	);

	do_action( 'sc_et_settings_emails_section_bottom' );
}

/**
 * Listens for Stripe Connect completion requests and saves the Stripe API keys.
 *
 * @since 1.0
 */
function process_stripe_connect_completion() {

	// Bail unless the request is on the SC settings payments tab AND carries
	// the trigger params. Mirror of process_stripe_disconnect() at line 588.
	if (
		! ( isset( $_GET['page'] ) && $_GET['page'] === PageSettings::get_slug() )
		|| ! isset( $_GET['sc_gateway_connect_completion'] )
		|| ( 'stripe_connect' !== $_GET['sc_gateway_connect_completion'] )
		|| ! isset( $_GET['state'] )
	) {
		return;
	}

	// Headers already sent, bail.
	if ( headers_sent() ) {
		return;
	}

	// No nonce, bail.
	if ( ! isset( $_GET['_wpnonce'] ) ) {
		return;
	}

	// Current user cannot handle this request, bail.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Invalid nonce, bail. Mirror of process_stripe_disconnect() at line 606.
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'sc-stripe-connect-completion' ) ) {
		return;
	}

	$is_sandbox               = Functions\is_sandbox();
	$url_payment_settings     = get_section_url( [ 'section' => 'payments' ] );
	$state                    = sanitize_text_field( $_GET['state'] );
	$url_customer_site_for_am = add_query_arg(
		[ '_wpnonce' => $_GET['_wpnonce'] ],
		$url_payment_settings
	);

	$sc_credentials_url   = Functions\get_stripe_credentials_url( $is_sandbox, $state, $url_customer_site_for_am );
	$response             = wp_remote_get( esc_url_raw( $sc_credentials_url ) );

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || ! wp_remote_retrieve_body( $response ) ) {

		$message  = '<p>';
		$message .= wp_sprintf(
			__(
				'There was an error getting your Stripe credentials. Please <a href="%s">try again</a>. If you continue to have this problem, please contact support.',
				'sugar-calendar-lite'
			),
			esc_url( $url_payment_settings )
		);
		$message .= '</p>';

		wp_die(
			wp_kses(
				$message,
				[
					'p' => [],
					'a' => [ 'href' => [] ],
				]
			)
		);
	}

	$response = json_decode( $response['body'], true );
	$data     = $response['data'];

	Functions\update_stripe_credentials(
		$data['publishable_key'],
		$data['secret_key'],
		$data['stripe_user_id'],
		$is_sandbox
	);

	$redirect = $url_payment_settings;

	// Redirect
	wp_safe_redirect( $redirect );
	exit;
}

/**
 * Listen for a disconnect URL.
 *
 * Clears out the `sc_stripe_connect_account_id` and the API keys.
 *
 * @since 1.0
 */
function process_stripe_disconnect() {

	// Do not need to handle this request, bail.
	if (
		! ( isset( $_GET['page'] ) && $_GET['page'] === PageSettings::get_slug() ) ||
		! isset( $_GET['sc-stripe-disconnect'] )
	) {
		return;
	}

	// No nonce, bail.
	if ( ! isset( $_GET['_wpnonce'] ) ) {
		return;
	}

	// Current user cannot handle this request, bail.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Invalid nonce, bail.
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'sc-stripe-connect-disconnect' ) ) {
		return;
	}

	if ( Functions\is_sandbox() ) {
		update_option( 'sc_stripe_test_secret', false );
		update_option( 'sc_stripe_test_publishable', false );
	} else {
		update_option( 'sc_stripe_live_secret', false );
		update_option( 'sc_stripe_live_publishable', false );
	}

	update_option( 'sc_stripe_connect_account_id', false );

	$redirect = get_section_url( [ 'section' => 'payments' ] );

	// Redirect
	wp_safe_redirect( $redirect );
	exit;
}

/**
 * Filter post states, and add for ticketing specific page settings.
 *
 * @since 1.1.4
 *
 * @param array $states
 * @param mixed $post
 *
 * @return array
 */
function add_page_states( $states = [], $post = false ) {

	// Bail if not looking at pages
	if ( empty( $post ) || ( 'page' !== get_post_type( $post ) ) ) {
		return $states;
	}

	// Get options
	$receipt_page = (int) Settings\get_setting( 'receipt_page' );
	$ticket_page  = (int) Settings\get_setting( 'ticket_page' );

	// Receipt Page
	if ( $post->ID === $receipt_page ) {
		$states['sc_et_receipt'] = esc_html__( 'Ticket Receipt Page', 'sugar-calendar-lite' );
	}

	// Ticket Page
	if ( $post->ID === $ticket_page ) {
		$states['sc_et_ticket'] = esc_html__( 'Ticket Details Page', 'sugar-calendar-lite' );
	}

	// Return states
	return $states;
}

/**
 * Get setting names.
 *
 * @since 3.3.0
 * @since 3.6.0 Added `attendee_fields_is_required`.
 * @since 3.14.0 Added `ticket_sale_admin_subject` and `ticket_sale_admin_message`.
 * @since 3.14.0 Added `use_legacy_template` and the four color-scheme keys.
 *
 * @return array
 */
function get_setting_names() {

	$names = [
		'sandbox',
		'receipt_page',
		'currency',
		'currency_position',
		'thousands_separator',
		'decimal_separator',
		'ticket_page',
		'receipt_from_email',
		'receipt_from_name',
		'receipt_subject',
		'receipt_message',
		'ticket_subject',
		'ticket_message',
		'ticket_sale_admin_subject',
		'ticket_sale_admin_message',
		'attendee_fields_is_required',
		'use_legacy_template',
	];

	// Derived, not restated: an unlisted color key is dropped by handle_post()
	// with no notice, so the defaults stay the single source of truth.
	$names = array_merge( $names, array_keys( Settings\get_color_scheme_defaults() ), Styling::setting_names() );

	return $names;
}

/**
 * Sanitize a settings value by its key.
 *
 * Message fields render via wp_editor and are sent as text/html, so they keep
 * safe HTML via wp_kses_post(); color fields must be a #rrggbb string; every
 * other field is treated as plain text.
 *
 * @since 3.12.0
 * @since 3.14.0 Added ticket_sale_admin_message.
 * @since 3.14.0 Added the color-field branch.
 *
 * @param string $key   The setting key.
 * @param mixed  $value The raw value to sanitize.
 *
 * @return mixed
 */
function sanitize_setting_value( $key, $value ) {

	if ( in_array( $key, Styling::setting_names(), true ) ) {
		return Styling::sanitize_setting( $key, $value );
	}

	if ( in_array( $key, [ 'receipt_message', 'ticket_message', 'ticket_sale_admin_message' ], true ) ) {
		return wp_kses_post( $value );
	}

	$color_defaults = Settings\get_color_scheme_defaults();

	if ( isset( $color_defaults[ $key ] ) ) {
		$color = sanitize_hex_color( $value );

		// sanitize_hex_color() returns null for a malformed value but passes the
		// empty string straight through, and get_setting() only falls back when a
		// key is absent, so storing '' would leave the field blank for good.
		return ( $color === null || $color === '' ) ? $color_defaults[ $key ] : $color;
	}

	return sanitize_textarea_field( $value );
}

/**
 * Whether a posted message is still the route-sensitive default the editor rendered.
 *
 * The edit screen shows the current template's default while nothing is stored.
 * Storing that copy on an untouched save would pin it to one template, so a later
 * switch to Plain Text would deliver the short Modern body without `{tickets}`
 * and `{receipt_url}`.
 *
 * @since 3.14.0
 *
 * @param string $key   Setting key.
 * @param mixed  $value Posted value.
 *
 * @return bool
 */
function is_unchanged_route_sensitive_message( $key, $value ) {

	if ( $key === 'receipt_message' ) {
		$resolution = Settings\get_receipt_message_resolution();
	} elseif ( $key === 'ticket_message' ) {
		$resolution = Settings\get_ticket_message_resolution();
	} else {
		return false;
	}

	if ( empty( $resolution['route_sensitive'] ) ) {
		return false;
	}

	$rendered = Settings\resolve_email_copy_for_template( $resolution, Styling::get()['template'] );

	return normalize_message_for_comparison( $value ) === normalize_message_for_comparison( $rendered );
}

/**
 * Reduce a message to the form the editor round trip cannot change.
 *
 * @since 3.14.0
 *
 * @param mixed $value Message value.
 *
 * @return string
 */
function normalize_message_for_comparison( $value ) {

	$value = is_string( $value ) ? $value : '';

	return trim( preg_replace( '/\R/', "\n", wp_kses_post( $value ) ) );
}

/**
 * Save the settings.
 *
 * @since 2.2.4
 * @since 3.1.0 Loop through the data instead of the settings.
 * @since 3.3.0 Decouple setting field names.
 * @since 3.6.0 Handling for attendee field checkbox.
 * @since 3.7.0 Changed support from `tickets` to `emails` section.
 * @since 3.14.0 Scoped the `attendee_fields_is_required` write to the Payments section.
 * @since 3.14.0 Added a manage_options capability check.
 * @since 3.14.0 Handling for the use legacy template checkbox.
 *
 * @param array $post_data Array containing the data to be saved.
 */
function handle_post( $post_data ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded -- The settings ownership checks remain in this existing entry point.

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$page      = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
	$section   = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : '';
	$email_cfg = isset( $_GET['email_cfg'] ) ? sanitize_key( $_GET['email_cfg'] ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( ! in_array( $section, [ 'payments', 'emails' ], true ) ) {
		return;
	}

	$settings                = get_setting_names();
	$is_email_styling_screen = $page === 'sugarcalendar-settings' && $section === 'emails' && $email_cfg === '';

	$options = get_option( 'sc_et_settings', [] );

	foreach ( $post_data as $key => $value ) {

		if ( ! in_array( $key, $settings, true ) ) {
			continue;
		}

		if ( in_array( $key, Styling::setting_names(), true ) && ! $is_email_styling_screen ) {
			continue;
		}

		if ( is_unchanged_route_sensitive_message( $key, $value ) ) {
			continue;
		}

		$options[ $key ] = sanitize_setting_value( $key, $value );
	}

	if ( ! empty( $post_data['currency'] ) ) {
		$options['sandbox'] = isset( $post_data['sandbox'] );
	}

	// Checkbox fields - Attendee Fields is Required, Use Legacy Template.
	//
	// Only the Payments section renders these, and an unchecked checkbox posts
	// nothing, so writing them on an Emails save would silently clear a setting
	// the user never saw. Scope them to the section that owns them.
	if ( $section === 'payments' ) {
		$options['attendee_fields_is_required'] = isset( $post_data['attendee_fields_is_required'] );
		$options['use_legacy_template']         = isset( $post_data['use_legacy_template'] );
	}

	if ( $is_email_styling_screen ) {
		$options[ Styling::SETTING_HIDE_BRANDING ] = Styling::can_hide_branding() && isset( $post_data[ Styling::SETTING_HIDE_BRANDING ] );
	}

	update_option( 'sc_et_settings', $options );

	WP::add_admin_notice( esc_html__( 'Settings saved.', 'sugar-calendar-lite' ), WP::ADMIN_NOTICE_SUCCESS );
}

/**
 * Handle AJAX requests for saving settings.
 *
 * @since 3.3.0
 *
 * @return void
 */
function handle_post_ajax() {

	// Bail if not an AJAX request.
	if ( ! wp_doing_ajax() ) {
		return;
	}

	// Verify the nonce.
	if ( ! check_ajax_referer( Area::SLUG, 'nonce', false ) ) {
		wp_send_json_error(
			[
				'success' => false,
				'message' => esc_html__( 'Nonce verification failed.', 'sugar-calendar-lite' ),
			]
		);
	}

	// Current user cannot save these settings, bail.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			[
				'success' => false,
				'message' => esc_html__( 'You do not have permission to save these settings.', 'sugar-calendar-lite' ),
			]
		);
	}

	// Exit if no options are set.
	if (
		! isset( $_POST['options'] )
		||
		! is_array( $_POST['options'] )
		||
		empty( $_POST['options'] )
	) {
		wp_send_json_error(
			[
				'success' => false,
				'message' => esc_html__( 'No options were set.', 'sugar-calendar-lite' ),
			]
		);
	}

	// Posted data.
	$posted_data = $_POST['options'];

	// Setting names.
	$setting_names = array_diff( get_setting_names(), Styling::setting_names() );

	// Current options.
	$options = get_option( 'sc_et_settings', [] );

	// Loop through the data and sanitize.
	foreach ( $posted_data as $key => $value ) {

		// Skip if not a setting.
		if ( ! in_array( $key, $setting_names, true ) ) {
			continue;
		}

		// Switch on the key.
		switch ( $key ) {

			case 'sandbox':
				$options[ $key ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
				break;

			default:
				$options[ $key ] = sanitize_setting_value( $key, $value );
				break;
		}
	}

	// Save the settings.
	$is_settings_saved = update_option( 'sc_et_settings', $options );

	$response = [
		'success' => $is_settings_saved,
		'message' =>
			$is_settings_saved
			?
			esc_html__( 'Settings saved.', 'sugar-calendar-lite' )
			:
			esc_html__( 'Settings not saved.', 'sugar-calendar-lite' ),
	];

	// Return the response.
	wp_send_json( $response );
}

/**
 * Shared guard for the two page-shortcode endpoints.
 *
 * Sends a JSON error and exits unless the request is a valid one.
 *
 * @since 3.14.0
 *
 * @return array The validated page id and the shortcode tag its setting expects.
 */
function get_page_shortcode_ajax_request() {

	check_ajax_referer( 'sc-et-page-action', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error();
	}

	$page_id = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;
	$key     = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';

	// Derived from the setting key, never taken from the request.
	$tag = get_page_shortcode_tag( $key );

	if ( empty( $tag ) || get_post_type( $page_id ) !== 'page' ) {
		wp_send_json_error();
	}

	return [ $page_id, $tag ];
}

/**
 * Answer whether a page currently holds its setting's shortcode.
 *
 * The render-time map can go stale while the tab sits open, so a dropdown change
 * confirms itself against the page as it is now.
 *
 * @since 3.14.0
 */
function handle_check_page_shortcode_ajax() {

	list( $page_id, $tag ) = get_page_shortcode_ajax_request();

	wp_send_json_success(
		[
			'embedded' => has_shortcode( (string) get_post_field( 'post_content', $page_id ), $tag ),
		]
	);
}

/**
 * Append a ticket page shortcode to the end of a page's content.
 *
 * Idempotent, so a stale button label costs a no-op rather than a second copy of
 * the shortcode.
 *
 * @since 3.14.0
 */
function handle_embed_page_shortcode_ajax() {

	list( $page_id, $tag ) = get_page_shortcode_ajax_request();

	if ( ! current_user_can( 'edit_post', $page_id ) ) {
		wp_send_json_error();
	}

	$content = (string) get_post_field( 'post_content', $page_id );

	if ( has_shortcode( $content, $tag ) ) {
		wp_send_json_success( [ 'embedded' => true ] );
	}

	// A block-editor page keeps it in a shortcode block; appended raw it would open
	// as an unexpected Classic block.
	$appended = has_blocks( $content )
		? '<!-- wp:shortcode -->[' . $tag . ']<!-- /wp:shortcode -->'
		: '[' . $tag . ']';

	$updated = wp_update_post(
		[
			'ID'           => $page_id,
			'post_content' => wp_slash( $content === '' ? $appended : $content . "\n\n" . $appended ),
		],
		true
	);

	if ( is_wp_error( $updated ) ) {
		wp_send_json_error();
	}

	wp_send_json_success( [ 'embedded' => true ] );
}

/**
 * Filter the help URL in the Settings page -> Payments tab.
 *
 * @since 3.8.0
 *
 * @param string $help_url The help URL.
 *
 * @return string
 */
function help_url( $help_url ) {

	if ( ! sugar_calendar()->get_admin()->is_page( 'settings_payments' ) ) {
		return $help_url;
	}

	return Helpers::get_utm_url(
		'https://sugarcalendar.com/docs/events/setting-up-event-ticketing-with-sugar-calendar-lite/#connecting-stripe',
		[
			'content' => 'Help',
			'medium'  => 'plugin-settings-payments',
		]
	);
}

/**
 * Add lite education to the Payments section.
 *
 * @since 3.11.0
 */
function add_lite_education() {

	if ( sugar_calendar()->is_pro() ) {
		return;
	}
	?>
	<div id="sugar-calendar__product-education__notice-stripe" class="sugar-calendar__product-education__notice sugar-calendar__product-education__notice-info">
		<div class="sugar-calendar__product-education__notice__content">
			<div class="sugar-calendar__product-education__notice__content__icon">
				<i class="fa-solid fa-circle-info"></i>
			</div>
			<div class="sugar-calendar__product-education__notice__content__text">
				<div class="sugar-calendar__product-education__notice__content__text__title">
					<strong><?php esc_html_e( 'Pay-as-you-go Pricing', 'sugar-calendar-lite' ); ?></strong>
				</div>
				<div class="sugar-calendar__product-education__notice__content__text__description">
					<?php
					$description = sprintf(
						/* translators: %1$s - fee percentage, %2$s - SugarCalendar.com Upgrade page URL. */
						__( '%1$s fee per-transaction + Stripe fees. <a href="%2$s">Upgrade to Pro</a> to remove additional fees and unlock powerful features.', 'sugar-calendar-lite' ),
						'3%',
						Helpers::get_utm_url(
							'https://sugarcalendar.com/lite-upgrade/',
							[
								'content' => 'Upgrade to Pro',
								'medium'  => 'plugin-settings-payments-stripe',
							]
						)
					);

					echo wp_kses(
						$description,
						[
							'a' => [
								'href'   => [],
								'target' => [],
								'rel'    => [],
							]
						]
					)
					?>
				</div>
			</div>
		</div>
	</div>
	<?php
}
