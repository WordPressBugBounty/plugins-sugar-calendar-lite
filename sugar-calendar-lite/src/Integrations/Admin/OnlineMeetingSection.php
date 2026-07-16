<?php

namespace Sugar_Calendar\Integrations\Admin;

use Sugar_Calendar\Integrations\IntegrationCapabilityRegistry;
use Sugar_Calendar\Integrations\IntegrationsDisabler;
use Sugar_Calendar\Integrations\MeetingProviderInterface;
use Sugar_Calendar\Integrations\OAuthRelay\OAuthConnectionModel;
use Sugar_Calendar\Integrations\OnlineMeeting;

/**
 * "Online" event-editor metabox section.
 *
 * Provider-agnostic: the dropdown is populated from every registered
 * MeetingProviderInterface (Zoom today; Google Meet later with no change
 * here). Persists the `online_provider` event meta via the
 * sugar_calendar_event_to_save filter — the same mechanism core uses for
 * `location`/`color` (non-column keys in the to-save array are stored as
 * event meta by the BerlinDB event store).
 *
 * @since 3.12.0
 */
class OnlineMeetingSection {

	/**
	 * Register hooks.
	 *
	 * @since 3.12.0
	 */
	public function hooks() {

		// Register `online_provider` as a known event-meta key. SCE's event
		// store only persists registered meta keys from the to-save array
		// (unregistered keys are silently dropped), so without this the
		// dropdown value added by save() never lands in wp_sc_eventmeta.
		add_filter( 'sugar_calendar_meta_data', [ $this, 'register_meta' ] );

		// Render inside the unified Location tab, below the Venue UI. The venue
		// handlers (display_venue_section Pro / event_metabox_venue_education
		// Lite) hook this action at the default priority 10, so 20 places the
		// Online Platform block after them — matching Figma node 11573-39930.
		add_action( 'sugar_calendar_admin_meta_box_location_section', [ $this, 'display' ], 20 );

		add_filter( 'sugar_calendar_event_to_save', [ $this, 'save' ] );
	}

	/**
	 * Register the `online_provider` event-meta key + its sanitizer.
	 *
	 * @since 3.12.0
	 *
	 * @param array $schema Event meta-data schema (key => register_meta args).
	 *
	 * @return array
	 */
	public function register_meta( $schema ) {

		$schema['online_provider'] = [
			'type'              => 'string',
			'description'       => '',
			'single'            => true,
			'sanitize_callback' => 'sanitize_key',
			'auth_callback'     => null,
			'show_in_rest'      => false,
		];

		$schema['online_visibility'] = [
			'type'              => 'string',
			'description'       => '',
			'single'            => true,
			'sanitize_callback' => 'sanitize_key',
			'auth_callback'     => null,
			'show_in_rest'      => false,
		];

		$schema['custom_link_url'] = [
			'type'              => 'string',
			'description'       => '',
			'single'            => true,
			'sanitize_callback' => 'esc_url_raw',
			'auth_callback'     => null,
			'show_in_rest'      => false,
		];

		return $schema;
	}

	/**
	 * Render the section body.
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return void
	 */
	public function display( $event ) {

		$event_id  = ! empty( $event->id ) ? $event->id : 0;
		$current   = $event_id ? (string) get_event_meta( $event_id, 'online_provider', true ) : '';
		$providers = IntegrationCapabilityRegistry::instance()->get( MeetingProviderInterface::class );

		$usage_url = admin_url( 'admin.php?page=sugarcalendar-settings&section=license-usage' );

		// Whether any provider is out of credits — gates whether the (selection-aware)
		// red invalid message is rendered into the DOM at all.
		$has_out_of_credits = $this->any_provider_out_of_credits( $providers );

		// Is the currently-saved provider the one that's out of credits? Drives the
		// INITIAL invalid border + credits message; the editor JS re-evaluates this
		// against the selected option's data-out-of-credits flag on every change.
		$current_out_of_credits = $this->is_provider_out_of_credits( $providers, $current );
		?>
		<?php // Wrapper (display:contents, layout-transparent) so the Pro "Add New Venue" flow can hide the whole Online section as one element. ?>
		<div class="sugar-calendar-metabox__online-section">
		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--online">
			<label for="online_provider"><?php esc_html_e( 'Online', 'sugar-calendar-lite' ); ?></label>
			<div class="sugar-calendar-metabox__field">
				<select id="online_provider" name="online_provider"<?php echo $current_out_of_credits ? ' class="sugar-calendar-metabox__online-provider--invalid"' : ''; ?>>
					<option value="" <?php selected( $current, '' ); ?>><?php esc_html_e( 'None', 'sugar-calendar-lite' ); ?></option>
					<?php
					foreach ( $providers as $slug => $provider ) {
						$this->render_provider_option( $slug, $provider, $current );
					}
					?>
					<option value="<?php echo esc_attr( OnlineMeeting::PROVIDER_CUSTOM ); ?>" <?php selected( $current, OnlineMeeting::PROVIDER_CUSTOM ); ?>><?php esc_html_e( 'Custom Link', 'sugar-calendar-lite' ); ?></option>
				</select>

				<p class="desc sugar-calendar-metabox__online-description"<?php echo $current_out_of_credits ? ' style="display: none;"' : ''; ?>><?php esc_html_e( 'Choose your online platform where the event will be hosted.', 'sugar-calendar-lite' ); ?></p>

				<?php if ( $has_out_of_credits ) : ?>
					<?php // Kept in the DOM whenever a provider is out of credits so the editor JS can reveal it when that provider is selected; initial visibility tracks the saved selection. ?>
					<p class="desc sugar-calendar-metabox__online-credits-error"<?php echo $current_out_of_credits ? '' : ' style="display: none;"'; ?>>
						<?php
						printf(
							/* translators: %1$s - opening anchor tag to Settings → Usage; %2$s - closing anchor tag. */
							esc_html__( 'You are out of Integration usage credits. Click %1$shere%2$s to learn more', 'sugar-calendar-lite' ),
							'<a href="' . esc_url( $usage_url ) . '">',
							'</a>'
						);
						?>
					</p>
				<?php endif; ?>

				<p class="sugar-calendar-metabox__create-meeting-error" role="alert" style="display: none;"></p>
			</div>
			<a href="#" role="button" class="sugar-calendar-metabox__create-meeting" style="display: none;">
				<?php esc_html_e( 'Create Meeting', 'sugar-calendar-lite' ); ?>
			</a>
		</div>

		<?php $this->render_meeting_details( $event ); ?>
		<?php $this->render_custom_link_field( $event ); ?>
		<?php $this->render_visibility_field( $event ); ?>
		</div><?php // .sugar-calendar-metabox__online-section ?>
		<?php
	}

	/**
	 * Whether any meeting provider is connected but out of credits.
	 *
	 * Resolved before the <select> renders to decide whether the (selection-aware)
	 * red invalid message is present in the DOM at all.
	 *
	 * @since 3.12.0
	 *
	 * @param array $providers Map of slug => MeetingProviderInterface.
	 *
	 * @return bool
	 */
	private function any_provider_out_of_credits( $providers ) {

		// Integrations switched off (Lite kill-switch): providers render read-only,
		// so the out-of-credits state never applies.
		if ( IntegrationsDisabler::is_disabled() ) {
			return false;
		}

		foreach ( $providers as $slug => $provider ) {

			// Out of credits = unavailable despite an active connection (the credits
			// gate is the only site-wide reason a connected provider goes unavailable).
			if ( ! $provider->is_available() && OAuthConnectionModel::find_active_by_provider( $slug ) !== null ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a specific provider slug is connected but out of credits.
	 *
	 * "Out of credits" = an active connection exists yet the provider is not
	 * available (the credits gate is the only site-wide reason a connected
	 * provider becomes unavailable).
	 *
	 * @since 3.12.0
	 *
	 * @param array  $providers Map of slug => MeetingProviderInterface.
	 * @param string $slug      Provider slug to test (may be '' or unknown).
	 *
	 * @return bool
	 */
	private function is_provider_out_of_credits( $providers, $slug ) {

		if ( IntegrationsDisabler::is_disabled() || $slug === '' || ! isset( $providers[ $slug ] ) ) {
			return false;
		}

		return ! $providers[ $slug ]->is_available()
			&& OAuthConnectionModel::find_active_by_provider( $slug ) !== null;
	}

	/**
	 * Render one provider <option> for the Online dropdown.
	 *
	 * Disables a provider option when it has no active connection (you can't
	 * create a meeting without one) or when the Lite "Disable Integrations"
	 * kill-switch is on. A connected-but-out-of-credits provider stays SELECTABLE
	 * (labelled plainly, flagged with data-out-of-credits so the editor JS shows
	 * the invalid state and blocks creation). The CURRENT selection is never
	 * disabled — a disabled selected option is not submitted, which would silently
	 * drop online_provider on an unrelated save.
	 *
	 * @since 3.12.0
	 *
	 * @param string $slug     Provider slug.
	 * @param object $provider MeetingProviderInterface instance.
	 * @param string $current  Currently-saved provider slug.
	 *
	 * @return void
	 */
	private function render_provider_option( $slug, $provider, $current ) {

		$available = $provider->is_available();
		$label     = $provider->get_display_name();
		$read_only = IntegrationsDisabler::is_disabled();

		// Connection state drives both the label and selectability. Skip the
		// lookup under the kill-switch — read-only disables regardless, and the
		// option stays the plain provider name (no connect/credit hints).
		$connected = $read_only ? false : ( OAuthConnectionModel::find_active_by_provider( $slug ) !== null );

		if ( ! $read_only && ! $available && ! $connected ) {
			/* translators: %s - provider display name. */
			$label = sprintf( esc_html__( '%s (not connected)', 'sugar-calendar-lite' ), $label );
		}

		// Out of credits = connected but unavailable. Stays selectable; the plain
		// label + data-out-of-credits drive the invalid state (Figma 11521-11229).
		$out_of_credits = ! $read_only && ! $available && $connected;

		// Disabled when the kill-switch is on OR there is no connection to create
		// against — but never the current selection (see method docblock).
		$disabled = ( $read_only || ! $connected ) && $current !== $slug;
		?>
		<option
			value="<?php echo esc_attr( $slug ); ?>"
			<?php selected( $current, $slug ); ?>
			<?php disabled( $disabled ); ?>
			<?php echo $out_of_credits ? ' data-out-of-credits="1"' : ''; ?>
		><?php echo esc_html( $label ); ?></option>
		<?php
	}

	/**
	 * Render the created-meeting block (status card + copyable links + Show to).
	 *
	 * Renders only when a meeting exists (join_url present), matching the Figma
	 * "created" state. Admin-only — no front-end surface.
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return void
	 */
	private function render_meeting_details( $event ) {

		$event_id = ! empty( $event->id ) ? $event->id : 0;

		if ( empty( $event_id ) ) {
			return;
		}

		$join_url = (string) get_event_meta( $event_id, 'join_url', true );

		if ( $join_url === '' ) {
			return;
		}

		$meeting_id = (string) get_event_meta( $event_id, 'meeting_id', true );
		$password   = (string) get_event_meta( $event_id, 'meeting_password', true );
		$slug       = (string) get_event_meta( $event_id, 'meeting_provider', true );

		$provider = $slug !== ''
			? IntegrationCapabilityRegistry::instance()->find( MeetingProviderInterface::class, $slug )
			: null;

		$provider_name = $provider ? $provider->get_display_name() : __( 'Online', 'sugar-calendar-lite' );
		$host_url      = $provider ? $provider->get_host_url( $event ) : '';
		$icon_url      = $slug !== '' ? SC_PLUGIN_ASSETS_URL . 'images/integrations/integration-' . $slug . '.png' : '';

		// The whole block links to the host URL ("View" leads to the host link);
		// fall back to the join URL when the host URL is unavailable.
		$view_url = $host_url !== '' ? $host_url : $join_url;
		?>
		<div class="sugar-calendar-metabox__online-meeting" data-provider="<?php echo esc_attr( $slug ); ?>">

			<div class="sugar-calendar-metabox__online-card">
				<a class="sugar-calendar-metabox__online-card-view" href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php if ( $provider !== null && $icon_url !== '' ) : ?>
						<img class="sugar-calendar-metabox__online-card-icon" src="<?php echo esc_url( $icon_url ); ?>" alt="" />
					<?php endif; ?>
					<span class="sugar-calendar-metabox__online-card-meta">
						<strong>
							<?php
							/* translators: %s - online meeting provider name (e.g. Zoom). */
							printf( esc_html__( '%s Meeting Created!', 'sugar-calendar-lite' ), esc_html( $provider_name ) );
							?>
						</strong>
						<?php if ( $meeting_id !== '' ) : ?>
							<span class="sugar-calendar-metabox__online-card-id">
								<?php
								/* translators: %s - provider meeting id. */
								printf( esc_html__( 'ID: %s', 'sugar-calendar-lite' ), esc_html( $meeting_id ) );
								?>
							</span>
						<?php endif; ?>
					</span>
				</a>
				<button type="button" class="sugar-calendar-metabox__online-card-remove" data-provider-name="<?php echo esc_attr( $provider_name ); ?>">
					<?php esc_html_e( 'Remove', 'sugar-calendar-lite' ); ?>
				</button>
			</div>

			<?php
			if ( $host_url !== '' ) {
				$this->render_copy_row( 'online_host_link', __( 'Host Link', 'sugar-calendar-lite' ), $host_url );
			}

			$this->render_copy_row( 'online_join_link', __( 'Join Link', 'sugar-calendar-lite' ), $join_url );

			if ( $password !== '' ) {
				$this->render_copy_row( 'online_password', __( 'Password', 'sugar-calendar-lite' ), $password );
			}
			?>

		</div>
		<?php
	}

	/**
	 * Render the Custom Link "Event Link" field (editable URL + Copy).
	 *
	 * Always present in the DOM so a client-side switch to Custom Link has a
	 * field to fill; hidden unless Custom Link is the current selection (the
	 * editor JS keeps visibility in sync). save() only persists the value when
	 * online_provider is `custom`, so a hidden stale value is ignored.
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return void
	 */
	private function render_custom_link_field( $event ) {

		$event_id  = ! empty( $event->id ) ? $event->id : 0;
		$current   = $event_id ? (string) get_event_meta( $event_id, 'online_provider', true ) : '';
		$url       = $event_id ? (string) get_event_meta( $event_id, 'custom_link_url', true ) : '';
		$is_custom = ( $current === OnlineMeeting::PROVIDER_CUSTOM );
		?>
		<div class="sugar-calendar-metabox__online-custom"<?php echo $is_custom ? '' : ' style="display: none;"'; ?>>
			<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--online-detail">
				<label for="custom_link_url"><?php esc_html_e( 'Event Link', 'sugar-calendar-lite' ); ?></label>
				<div class="sugar-calendar-metabox__field sugar-calendar-metabox__online-copy">
					<input type="url" id="custom_link_url" name="custom_link_url" class="sugar-calendar-metabox__online-field" value="<?php echo esc_url( $url ); ?>" placeholder="<?php esc_attr_e( 'Add your link here', 'sugar-calendar-lite' ); ?>" />
					<button type="button" class="sugar-calendar-metabox__copy" data-copy-target="custom_link_url"><?php esc_html_e( 'Copy', 'sugar-calendar-lite' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the shared "Show to" (visibility) field.
	 *
	 * Shared by the provider-meeting and Custom Link states so there is exactly
	 * one `online_visibility` control in the DOM (two would submit ambiguously).
	 * Shown when an online option is active — a provider meeting exists OR Custom
	 * Link is selected; the editor JS keeps visibility in sync as the dropdown
	 * changes.
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return void
	 */
	private function render_visibility_field( $event ) {

		$event_id    = ! empty( $event->id ) ? $event->id : 0;
		$provider    = $event_id ? (string) get_event_meta( $event_id, 'online_provider', true ) : '';
		$has_meeting = $event_id && (string) get_event_meta( $event_id, 'join_url', true ) !== '';
		$is_custom   = ( $provider === OnlineMeeting::PROVIDER_CUSTOM );

		$visibility = (string) get_event_meta( $event_id, 'online_visibility', true );
		$visibility = in_array( $visibility, [ 'everyone', 'attendees' ], true ) ? $visibility : 'attendees';

		$show = $has_meeting || $is_custom;
		?>
		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--online-visibility"<?php echo $show ? '' : ' style="display: none;"'; ?>>
			<label><?php esc_html_e( 'Show to', 'sugar-calendar-lite' ); ?></label>
			<div class="sugar-calendar-metabox__field">
				<div class="sugar-calendar-metabox__online-visibility">
					<label>
						<input type="radio" name="online_visibility" value="everyone" <?php checked( $visibility, 'everyone' ); ?> />
						<?php esc_html_e( 'Everyone', 'sugar-calendar-lite' ); ?>
					</label>
					<label>
						<input type="radio" name="online_visibility" value="attendees" <?php checked( $visibility, 'attendees' ); ?> />
						<?php esc_html_e( 'Only Attendees', 'sugar-calendar-lite' ); ?>
					</label>
				</div>
			</div>
			<p class="desc"><?php esc_html_e( "Selecting 'Only Attendees' will restrict the link to ticket and RSVP attendees.", 'sugar-calendar-lite' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the meeting-details block to a string (for the AJAX create response).
	 *
	 * @since 3.12.0
	 *
	 * @param object $event SCE Event object.
	 *
	 * @return string
	 */
	public function render_details_html( $event ): string {

		ob_start();
		$this->render_meeting_details( $event );

		return (string) ob_get_clean();
	}

	/**
	 * Render one read-only, copyable detail row (label + field + Copy button).
	 *
	 * @since 3.12.0
	 *
	 * @param string $id    Input id (also the copy-button target).
	 * @param string $label Row label (unescaped — escaped here).
	 * @param string $value Field value.
	 *
	 * @return void
	 */
	private function render_copy_row( $id, $label, $value ) {
		?>
		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--online-detail">
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<div class="sugar-calendar-metabox__field sugar-calendar-metabox__online-copy">
				<input type="text" id="<?php echo esc_attr( $id ); ?>" class="sugar-calendar-metabox__online-field" value="<?php echo esc_attr( $value ); ?>" readonly />
				<button type="button" class="sugar-calendar-metabox__copy" data-copy-target="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Copy', 'sugar-calendar-lite' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Persist `online_provider` into the event meta via the to-save array.
	 *
	 * The metabox nonce (sc_mb_nonce) was already verified by Metaboxes::save()
	 * before this filter fires, matching the core add_location_to_save pattern.
	 * The value is constrained to '' or a REGISTERED provider slug.
	 *
	 * @since 3.12.0
	 *
	 * @param array $event Event data to save.
	 *
	 * @return array
	 */
	public function save( $event ) {

		$registered = array_keys( IntegrationCapabilityRegistry::instance()->get( MeetingProviderInterface::class ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$posted = isset( $_POST['online_provider'] ) ? sanitize_key( wp_unslash( $_POST['online_provider'] ) ) : '';

		if ( in_array( $posted, $registered, true ) ) {
			// A registered provider — clear any stale custom link.
			$event['online_provider'] = $posted;
			$event['custom_link_url'] = '';
		} elseif ( $posted === OnlineMeeting::PROVIDER_CUSTOM ) {
			$event['online_provider'] = OnlineMeeting::PROVIDER_CUSTOM;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$event['custom_link_url'] = isset( $_POST['custom_link_url'] ) ? esc_url_raw( wp_unslash( $_POST['custom_link_url'] ) ) : '';
		} else {
			$event['online_provider'] = '';
			$event['custom_link_url'] = '';
		}

		// `online_visibility` is the per-event "Show to" intent (everyone|attendees).
		// The radio renders only once a meeting exists, so on a meeting-less save the
		// posted value is absent and this stores the default ('attendees') — the read
		// side (render_meeting_details) defaults the same way. Front-end enforcement
		// reads this via Integrations\OnlineMeeting (public event page, ticketing
		// receipt/order/emails, and the RSVP attendee page/email).
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$visibility = isset( $_POST['online_visibility'] ) ? sanitize_key( wp_unslash( $_POST['online_visibility'] ) ) : '';

		$event['online_visibility'] = in_array( $visibility, [ 'everyone', 'attendees' ], true ) ? $visibility : 'attendees';

		return $event;
	}
}
