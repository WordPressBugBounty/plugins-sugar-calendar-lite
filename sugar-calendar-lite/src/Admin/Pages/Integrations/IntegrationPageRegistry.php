<?php

namespace Sugar_Calendar\Admin\Pages\Integrations;

use Sugar_Calendar\Integrations\IntegrationsDisabler;
use Sugar_Calendar\Integrations\OAuthRelay\Credits\CreditsService;

/**
 * The integrations Settings › Integrations and the Dashboard both show: the list, the card names,
 * the rows to come, and each integration's state and badge. Each screen draws them as Figma does.
 *
 * @since 3.18.0
 */
class IntegrationPageRegistry {

	/**
	 * The integrations, built once per request.
	 *
	 * @since 3.18.0
	 *
	 * @var array<string, object>|null
	 */
	private static $integrations = null;

	/**
	 * The integrations by slug: the built-in ones in Figma's order, then any the filter adds.
	 *
	 * The first one is Settings' default when no `?integration=` is set.
	 *
	 * @since 3.18.0
	 *
	 * @return array<string, object>
	 */
	public static function get_integrations(): array {

		if ( self::$integrations === null ) {
			$integrations = [
				'google-maps'     => new GoogleMaps(),
				'google-calendar' => new GoogleCalendar(),
				'zoom'            => new Zoom(),
			];

			/**
			 * Filter the integrations on Settings → Integrations and the Dashboard.
			 *
			 * Each value must implement Integrations\IntegrationPageInterface
			 * (get_slug/get_name/get_icon_url/get_status/render_content).
			 * Extending Integrations\AbstractIntegrationPage is recommended —
			 * it supplies no-op defaults for the optional hooks()/handle_post()/
			 * get_help_url()/is_local() both screens probe for. An integration on
			 * the Sugar Calendar relay returns false from is_local(), so Lite's
			 * Disable Integrations switch and the relay credits apply to it.
			 *
			 * @since 3.12.0
			 * @since 3.18.0 Also lists the Dashboard's integration cards.
			 *
			 * @param array<string, object> $integrations Slug → instance.
			 */
			self::$integrations = (array) apply_filters( 'sugar_calendar_admin_settings_integrations', $integrations ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName -- Released in 3.12.0 under this name.
		}

		return self::$integrations;
	}

	/**
	 * The integrations to come, listed after the others with nothing to open.
	 *
	 * @since 3.18.0
	 *
	 * @return array<int, array{slug:string, name:string, icon:string}>
	 */
	public static function get_coming_soon(): array {

		$image_base = SC_PLUGIN_ASSETS_URL . 'images/integrations/';

		return [
			[
				'slug' => 'sms',
				'name' => esc_html__( 'SMS', 'sugar-calendar-lite' ),
				'icon' => $image_base . 'integration-sms.png',
			],
			[
				'slug' => 'whatsapp',
				'name' => esc_html__( 'WhatsApp', 'sugar-calendar-lite' ),
				'icon' => $image_base . 'integration-whatsapp.png',
			],
		];
	}

	/**
	 * An integration's name on a Dashboard card: Figma's card says "Google Calendar" where
	 * Settings' heading says "Google Calendar / Meets"; every other card uses the page's name.
	 *
	 * @since 3.18.0
	 *
	 * @param object $integration Integration page.
	 *
	 * @return string
	 */
	public static function get_short_name( $integration ): string {

		$short_names = [
			'google-calendar' => esc_html__( 'Google Calendar', 'sugar-calendar-lite' ),
		];

		return $short_names[ $integration->get_slug() ] ?? (string) $integration->get_name();
	}

	/**
	 * Whether an integration runs on the site alone rather than through the relay, so neither Lite's
	 * Disable Integrations switch nor the relay credits touch it. One without is_local() counts as
	 * local: only a relay integration opts in.
	 *
	 * @since 3.18.0
	 *
	 * @param object $integration Integration page.
	 *
	 * @return bool
	 */
	private static function is_local( $integration ): bool {

		return ! method_exists( $integration, 'is_local' ) || $integration->is_local();
	}

	/**
	 * Whether Lite's Disable Integrations switch has turned an integration off.
	 *
	 * @since 3.18.0
	 *
	 * @param object $integration Integration page.
	 *
	 * @return bool
	 */
	public static function is_inert( $integration ): bool {

		return IntegrationsDisabler::is_disabled() && ! self::is_local( $integration );
	}

	/**
	 * Whether the account is out of the relay credits a connected integration spends. One never
	 * connected spends nothing yet, so it reads Not Connected; the usage ring and the global
	 * notice carry the account's state.
	 *
	 * Reads the stored read (no HTTP); on Settings the header's forced read has just stored a fresh one.
	 *
	 * @since 3.18.0
	 *
	 * @param object $integration Integration page.
	 *
	 * @return bool
	 */
	public static function is_out_of_tokens( $integration ): bool {

		return ! self::is_local( $integration )
			&& $integration->get_status() !== 'not_connected'
			&& ( new CreditsService() )->is_out_of_credits();
	}

	/**
	 * An integration's badge, the one rule both screens show: Lite's Disable Integrations switch
	 * first, then an account out of credits, then the connection status.
	 *
	 * @since 3.18.0
	 *
	 * @param object $integration Integration page.
	 *
	 * @return array `class` (`green`, `yellow`, `red`, `neutral` or `out-of-tokens`) and `label`.
	 */
	public static function get_badge( $integration ): array {

		if ( self::is_inert( $integration ) ) {
			return [
				'class' => 'red',
				'label' => esc_html__( 'Disabled', 'sugar-calendar-lite' ),
			];
		}

		if ( self::is_out_of_tokens( $integration ) ) {
			return [
				'class' => 'out-of-tokens',
				'label' => esc_html__( 'Out of Tokens', 'sugar-calendar-lite' ),
			];
		}

		$badges = self::get_status_badges();

		return $badges[ $integration->get_status() ] ?? $badges['not_connected'];
	}

	/**
	 * The badge for each connection status.
	 *
	 * @since 3.18.0
	 *
	 * @return array<string, array{class:string, label:string}>
	 */
	private static function get_status_badges(): array {

		return [
			'active'        => [
				'class' => 'green',
				'label' => esc_html__( 'Connected', 'sugar-calendar-lite' ),
			],
			'auth_error'    => [
				'class' => 'yellow',
				'label' => esc_html__( 'Reconnect required', 'sugar-calendar-lite' ),
			],
			'disabled'      => [
				'class' => 'red',
				'label' => esc_html__( 'Connection lost', 'sugar-calendar-lite' ),
			],
			'not_connected' => [
				'class' => 'neutral',
				'label' => esc_html__( 'Not Connected', 'sugar-calendar-lite' ),
			],
		];
	}
}
