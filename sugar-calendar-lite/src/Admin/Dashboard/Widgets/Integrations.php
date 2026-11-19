<?php

namespace Sugar_Calendar\Admin\Dashboard\Widgets;

use Sugar_Calendar\Admin\Dashboard\AccessContext;
use Sugar_Calendar\Admin\Dashboard\Templates;
use Sugar_Calendar\Admin\Dashboard\WidgetState;
use Sugar_Calendar\Admin\Pages\Integrations\IntegrationPageRegistry;
use Sugar_Calendar\Admin\Pages\SettingsIntegrationsTab;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Integrations\OAuthRelay\Credits\CreditsPresenter;
use Sugar_Calendar\Integrations\OAuthRelay\Credits\CreditsService;
use Sugar_Calendar\Integrations\OAuthRelay\Credits\CreditsSignalRenderer;

use function Sugar_Calendar\AddOn\Ticketing\Admin\Settings\get_section_url;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\is_sandbox;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\stripe_is_connected;

/**
 * "Streamline Your Workflow with Integrations and Addons".
 *
 * @since 3.18.0
 */
class Integrations extends AbstractWidget {

	/**
	 * Column.
	 *
	 * @since 3.18.0
	 */
	const COLUMN = 'main';

	/**
	 * Sort position.
	 *
	 * @since 3.18.0
	 */
	const ORDER = 30;

	/**
	 * Card state per integration status: what the button offers. The badge comes from the catalog.
	 *
	 * @since 3.18.0
	 */
	const INTEGRATION_STATES = [
		'active'        => 'connected',
		'auth_error'    => 'disabled',
		'switched_off'  => 'disabled',
		'not_connected' => 'connect',
	];

	/**
	 * Widget identifier.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'integrations';
	}

	/**
	 * Widget title.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_title(): string {

		return __( 'Streamline Your Workflow with Integrations and Addons', 'sugar-calendar-lite' );
	}

	/**
	 * Always visible.
	 *
	 * @since 3.18.0
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 */
	public function get_state( AccessContext $access ): WidgetState {

		return new WidgetState( true, 'data' );
	}

	/**
	 * The usage indicator from the stored credits read. When a fetch is due, the container's marker
	 * has the page script refresh it after load.
	 *
	 * The container prints even with nothing stored, so a first successful refresh has somewhere to land.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Range data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 */
	protected function render_head_aside( string $variant, array $data, AccessContext $access ): string {

		$service = new CreditsService();

		return sprintf(
			'<div class="sugar-calendar-credits-refresh"%1$s>%2$s</div>',
			$service->is_refresh_due() ? ' data-credits-refresh' : '',
			$this->get_credits_redraw( $service->get_cached_credits(), false )['credits_html']
		);
	}

	/**
	 * Render the cards.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Range data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 */
	protected function render_body( string $variant, array $data, AccessContext $access ): string {

		$list = $this->get_card_list();

		return Templates::get_html(
			'widgets/integrations',
			[
				'cards' => array_map( [ $this, 'get_card' ], array_keys( $list ), $list ),
			]
		);
	}

	/**
	 * What a credits read redraws: the usage indicator and, when asked, the integration cards, whose
	 * badges follow it.
	 *
	 * @since 3.18.0
	 *
	 * @param array|null $credits    Credits read.
	 * @param bool       $with_cards Whether to redraw the integration cards too.
	 *
	 * @return array `credits_html`, and `cards_html` by card slug.
	 */
	public function get_credits_redraw( ?array $credits, bool $with_cards ): array {

		ob_start();

		( new CreditsSignalRenderer() )->render( new CreditsPresenter( $credits ), self::UTM_MEDIUM );

		$redraw = [
			'credits_html' => (string) ob_get_clean(),
			'cards_html'   => [],
		];

		if ( ! $with_cards ) {
			return $redraw;
		}

		foreach ( $this->get_card_list() as $slug => $entry ) {
			if ( $entry['type'] === 'integration' ) {
				$redraw['cards_html'][ $slug ] = $this->get_card_html( $slug );
			}
		}

		return $redraw;
	}

	/**
	 * The cards in display order, by slug: the integrations as the catalog lists them, then Stripe,
	 * then the add-ons. Settings' coming-soon integrations are left out.
	 *
	 * An integration's icon is cast: a page the filter adds is untyped, and a null would throw on
	 * the typed closure and take the Dashboard down.
	 *
	 * @since 3.18.0
	 *
	 * @return array Slug => `type` (`integration`, `stripe` or `addon`), `name` and `logo` (URL).
	 */
	protected function get_card_list(): array {

		$entry = static function ( string $type, string $name, string $logo ): array {
			return [
				'type' => $type,
				'name' => $name,
				'logo' => $logo,
			];
		};
		$cards = [];

		foreach ( IntegrationPageRegistry::get_integrations() as $slug => $integration ) {
			$cards[ $slug ] = $entry( 'integration', IntegrationPageRegistry::get_short_name( $integration ), (string) $integration->get_icon_url() );
		}

		$addon_icons = SC_PLUGIN_ASSETS_URL . 'images/addon-icons/';

		// `+=` keeps an integration the filter added under a slug a Stripe or add-on card also uses.
		$cards += [
			'stripe' => $entry( 'stripe', __( 'Stripe', 'sugar-calendar-lite' ), SC_PLUGIN_ASSETS_URL . 'images/setup-checklist/stripe-app-icon.svg' ),
		];

		$cards += [
			'event-ticketing' => $entry( 'addon', __( 'Advanced Event Ticketing', 'sugar-calendar-lite' ), $addon_icons . 'event-ticketing.png' ),
			'rsvp'            => $entry( 'addon', __( 'RSVP', 'sugar-calendar-lite' ), $addon_icons . 'rsvp.png' ),
			'zapier'          => $entry( 'addon', __( 'Zapier', 'sugar-calendar-lite' ), $addon_icons . 'zapier.png' ),
		];

		return $cards;
	}

	/**
	 * One card, rendered as the grid renders it, for a redraw: the add-on endpoint's reply and the
	 * credits refresh.
	 *
	 * @since 3.18.0
	 *
	 * @param string $slug Card slug.
	 *
	 * @return string Empty for a slug the grid does not have.
	 */
	public function get_card_html( string $slug ): string {

		$list = $this->get_card_list();

		if ( ! isset( $list[ $slug ] ) ) {
			return '';
		}

		return Templates::get_html( 'widgets/integration-card', [ 'card' => $this->get_card( $slug, $list[ $slug ] ) ] );
	}

	/**
	 * One card in its state now.
	 *
	 * @since 3.18.0
	 *
	 * @param string $slug  Card slug.
	 * @param array  $entry Its `get_card_list()` entry.
	 *
	 * @return array See `card()`.
	 */
	protected function get_card( string $slug, array $entry ): array {

		if ( $entry['type'] === 'integration' ) {
			$integration = IntegrationPageRegistry::get_integrations()[ $slug ];
			$status      = $this->get_integration_status( $integration );
			$card        = $this->card( $slug, $entry['name'], $entry['logo'], self::INTEGRATION_STATES[ $status ] ?? '' );

			$card['badge'] = $this->get_integration_badge( $integration );

			return $card;
		}

		if ( $entry['type'] === 'stripe' ) {
			return $this->get_stripe_card( $slug, $entry );
		}

		return $this->card( $slug, $entry['name'], $entry['logo'], $this->get_addon_state( $slug ) );
	}

	/**
	 * The Stripe card: Connect or Manage, both on Settings > Payments, Stripe's settings home, and
	 * a badge naming the mode while connected.
	 *
	 * @since 3.18.0
	 *
	 * @param string $slug  Card slug.
	 * @param array  $entry Its `get_card_list()` entry.
	 *
	 * @return array See `card()`.
	 */
	private function get_stripe_card( string $slug, array $entry ): array {

		$connected = stripe_is_connected();
		$card      = $this->card( $slug, $entry['name'], $entry['logo'], $connected ? 'connected' : 'connect' );

		$card['url'] = get_section_url( [ 'section' => 'payments' ] );

		if ( $connected ) {
			// Orange, as the ticketing screens' "Test Mode: On" link: no real payment goes through.
			$card['badge'] = is_sandbox()
				? [
					'text'  => __( 'Test Mode', 'sugar-calendar-lite' ),
					'color' => 'orange',
				]
				: [
					'text'  => __( 'Connected', 'sugar-calendar-lite' ),
					'color' => 'green',
				];
		}

		return $card;
	}

	/**
	 * An integration's status, as its Settings > Integrations panel reads it.
	 *
	 * @since 3.18.0
	 *
	 * @param object $integration Integration page.
	 *
	 * @return string `active`, `auth_error`, `not_connected` or, for a relay integration while Lite's
	 *                Disable Integrations switch is on, `switched_off`.
	 */
	private function get_integration_status( $integration ): string {

		// Turning the switch on deletes the relay connections, so the row alone would say Not Connected.
		return IntegrationPageRegistry::is_inert( $integration ) ? 'switched_off' : (string) $integration->get_status();
	}

	/**
	 * An integration card's badge, from the catalog's rule Settings' panel also shows, so the two
	 * screens say the same thing. A card never connected shows none: it offers Connect instead.
	 *
	 * @since 3.18.0
	 *
	 * @param object $integration Integration page.
	 *
	 * @return array|null `text` and `color`, or null for none.
	 */
	private function get_integration_badge( $integration ): ?array {

		$badge = IntegrationPageRegistry::get_badge( $integration );

		if ( $badge['class'] === 'neutral' ) {
			return null;
		}

		return [
			'text'  => $badge['label'],
			'color' => $badge['class'] === 'out-of-tokens' ? 'red' : $badge['class'],
		];
	}

	/**
	 * An add-on's state. The add-ons need a paid plan, so on Lite each card offers the upgrade.
	 *
	 * @since 3.18.0
	 *
	 * @param string $slug Add-on card slug.
	 *
	 * @return string
	 */
	protected function get_addon_state( string $slug ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- The Pro subclass decides per add-on.

		return 'upgrade';
	}

	/**
	 * One card.
	 *
	 * @since 3.18.0
	 *
	 * @param string $slug  Integration or add-on slug.
	 * @param string $name  Display name.
	 * @param string $logo  Logo URL.
	 * @param string $state Card state: `connect`, `connected`, `disabled` or `upgrade`.
	 *
	 * @return array `task` names a button's AJAX action; `message` fills the card's status line.
	 */
	protected function card( string $slug, string $name, string $logo, string $state ): array {

		return array_merge(
			[
				'slug'  => $slug,
				'name'  => $name,
				'logo'  => $logo,
				'state' => $state,
			],
			$this->get_action( $slug, $state ),
			[
				// The integration cards replace it with Settings' badge.
				'badge'   => $state === 'upgrade' ? [
					'text'  => __( 'Pro', 'sugar-calendar-lite' ),
					'color' => 'platinum',
				] : null,
				'task'    => '',
				'message' => '',
			]
		);
	}

	/**
	 * A card's button for its state.
	 *
	 * @since 3.18.0
	 *
	 * @param string $slug  Integration or add-on slug.
	 * @param string $state Card state.
	 *
	 * @return array `action` (the label), `url` and `target_blank`.
	 */
	private function get_action( string $slug, string $state ): array {

		$labels = [
			'connected' => __( 'Manage', 'sugar-calendar-lite' ),
			'disabled'  => __( 'Manage', 'sugar-calendar-lite' ),
			'upgrade'   => __( 'Upgrade', 'sugar-calendar-lite' ),
			'connect'   => __( 'Connect', 'sugar-calendar-lite' ),
		];
		$url    = '';

		if ( $state === 'upgrade' ) {
			$url = Helpers::get_upgrade_link(
				[
					'medium'  => self::UTM_MEDIUM,
					'content' => 'integrations-' . $slug,
				]
			);
		} elseif ( in_array( $state, [ 'connected', 'disabled', 'connect' ], true ) ) {
			$url = SettingsIntegrationsTab::get_integration_url( $slug );
		}

		// A state this class does not know gets nothing to click, rather than a wrong Connect.
		return [
			'action'       => $labels[ $state ] ?? '',
			'url'          => $url,
			'target_blank' => $state === 'upgrade',
		];
	}
}
