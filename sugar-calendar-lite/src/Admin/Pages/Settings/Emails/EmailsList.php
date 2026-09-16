<?php

namespace Sugar_Calendar\Admin\Pages\Settings\Emails;

use Sugar_Calendar\Emails\EmailInterface;
use Sugar_Calendar\Emails\EmailRegistry;
use Sugar_Calendar\EmailStatus;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;

/**
 * Renders the configurable-email card groups on Settings → Emails.
 *
 * One card per group, one row per email. A row is three columns: the title and
 * description, an Edit link, and an enable toggle. Rows whose add-on is not
 * active (`pro_only`) replace the last two columns with an upgrade prompt.
 *
 * @since 3.14.0
 */
class EmailsList {

	/**
	 * Render every group.
	 *
	 * @since 3.14.0
	 */
	public static function render() {

		$grouped = EmailRegistry::by_group();
		$labels  = self::group_labels();
		?>
		<div class="sugar-calendar-settings-emails">
			<?php foreach ( $labels as $group => $label ) : ?>
				<?php
				if ( empty( $grouped[ $group ] ) ) {
					continue;
				}
				?>

				<div class="sugar-calendar-settings-emails__heading">
					<h4><?php echo esc_html( $label ); ?></h4>
				</div>

				<div class="sugar-calendar-settings-emails__group" data-group="<?php echo esc_attr( $group ); ?>">
					<?php foreach ( $grouped[ $group ] as $id => $email ) : ?>
						<?php self::render_row( $id, $email ); ?>
					<?php endforeach; ?>
				</div>

			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render a single email row.
	 *
	 * @since 3.14.0
	 *
	 * @param string         $id    Email id.
	 * @param EmailInterface $email Listed email.
	 */
	private static function render_row( $id, EmailInterface $email ) {

		$status = self::status_state( $email );
		?>
		<div class="sugar-calendar-settings-emails__row" data-email-id="<?php echo esc_attr( $id ); ?>">

			<div class="sugar-calendar-settings-emails__row-info">
				<span class="sugar-calendar-settings-emails__row-title"><?php echo esc_html( $email->title() ); ?></span>
				<span class="sugar-calendar-settings-emails__row-desc"><?php echo esc_html( $email->description() ); ?></span>
			</div>

			<div class="sugar-calendar-settings-emails__row-actions">
				<?php if ( $email->is_pro_only() ) : ?>

					<?php self::render_pro_prompt( $email ); ?>

				<?php else : ?>

					<a class="sugar-calendar-settings-emails__row-edit"
					   href="<?php echo esc_url( self::get_edit_url( $id ) ); ?>">
						<?php esc_html_e( 'Edit', 'sugar-calendar-lite' ); ?>
					</a>

					<?php if ( $status['note'] !== '' ) : ?>

						<span class="sugar-calendar-settings-emails__row-note">
							<?php echo esc_html( $status['note'] ); ?>
						</span>

					<?php endif; ?>

					<span class="sugar-calendar-settings-emails__row-toggle"
						<?php if ( $status['disabled'] ) : ?>
							title="<?php echo esc_attr( $status['description'] ); ?>"
						<?php endif; ?>
					>
						<?php
						// A toggle that cannot control anything renders disabled
						// rather than as a switch that silently does nothing.
						//
						// NOTE: a disabled checkbox posts nothing — identically to
						// an unchecked one — so the save path must never read
						// absence as "switched off". It writes only the ids whose
						// controls this screen actually rendered writable; see
						// EmailsConfigTab::save_email_status().
						UI::toggle_control(
							[
								'id'       => 'email_enabled_' . $id,
								'name'     => 'email_enabled_' . $id,
								'value'    => $status['value'],
								'disabled' => $status['disabled'],
							],
							true
						);
						?>
					</span>

				<?php endif; ?>
			</div>

		</div>
		<?php
	}

	/**
	 * Render the upgrade prompt shown in place of Edit + toggle.
	 *
	 * On a Pro site whose plan includes the add-on a PRO badge is a dead end,
	 * so the row shows an "Install RSVP add-on" link instead — the same
	 * account-page link the event editor's RSVP section shows.
	 *
	 * @since 3.14.0
	 *
	 * @param EmailInterface $email Listed email.
	 */
	private static function render_pro_prompt( EmailInterface $email ) {

		// `lite` is what get_license_type() returns when no key has ever
		// activated, and basic plans do not include the add-on.
		$license_type = sugar_calendar()->get_license_type();

		if ( ! sugar_calendar()->is_pro() || in_array( $license_type, [ 'basic', 'lite' ], true ) ) {
			self::print_pro_prompt( 'rsvp-emails', $email->title() );

			return;
		}

		$install_url = Helpers::get_utm_url(
			'https://sugarcalendar.com/account/licenses/',
			[
				'medium'  => 'rsvp-emails',
				'content' => 'Install RSVP add-on',
			]
		);
		?>
		<a class="sugar-calendar-settings-emails__row-install"
		   href="<?php echo esc_url( $install_url ); ?>"
		   target="_blank"
		   rel="noopener noreferrer">
			<?php esc_html_e( 'Install RSVP add-on', 'sugar-calendar-lite' ); ?>
		</a>
		<?php
	}

	/**
	 * Get the display label for each group.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function group_labels() {

		// __() not esc_html__(): render() escapes at output, and pre-escaping
		// here would double-escape — an apostrophe in a translated label (e.g.
		// Italian "l'amministratore") would render as `l&#039;amministratore`.
		return [
			EmailRegistry::GROUP_ADMIN     => __( 'Configure Emails to Admin', 'sugar-calendar-lite' ),
			EmailRegistry::GROUP_ATTENDEES => __( 'Configure Emails to Attendees', 'sugar-calendar-lite' ),
		];
	}

	/**
	 * Resolve how one email's status control should render.
	 *
	 * Returns the toggle state plus the copy explaining it. There are three
	 * outcomes and they are deliberately worded differently, because they mean
	 * different things to a site owner:
	 *
	 * - no sender at all → "Coming soon"
	 * - a sender, but only manual callers → "Manual delivery only"
	 * - writable → says what disabling does, and what it does not
	 *
	 * A disabled toggle always renders **checked**. It is not "off"; it is "not
	 * yours to set", and showing it off would read as "this email is disabled".
	 *
	 * @since 3.14.0
	 *
	 * @param EmailInterface|null $email Listed email, or null for an unknown or hidden id.
	 *
	 * @return array {
	 *     @type bool   $disabled    Render the control disabled.
	 *     @type bool   $value       Checked state.
	 *     @type string $note        Short chip beside the row, '' when writable.
	 *     @type string $description Longer explanation / title attribute.
	 * }
	 */
	public static function status_state( ?EmailInterface $email ) { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.typeDeclarationFound -- Sugar Calendar requires PHP 7.4+.

		if ( EmailRegistry::is_status_writable( $email ) ) {
			return [
				'disabled'    => false,
				'value'       => EmailStatus::is_enabled( $email->id() ),
				'note'        => '',
				'description' => esc_html__( 'Disabling stops automatic delivery. Manual resend actions remain available.', 'sugar-calendar-lite' ),
			];
		}

		if ( $email === null || ! $email->is_sendable() ) {
			return [
				'disabled'    => true,
				'value'       => true,
				'note'        => esc_html__( 'Coming soon', 'sugar-calendar-lite' ),
				'description' => esc_html__( 'This email is not sent yet, so it cannot be enabled or disabled.', 'sugar-calendar-lite' ),
			];
		}

		// A sender exists but nothing calls it automatically (or the servicing
		// add-on has not opted in yet). Saying "Coming soon" here would be wrong —
		// the email does send, just never on its own.
		return [
			'disabled'    => true,
			'value'       => true,
			'note'        => esc_html__( 'Manual delivery only', 'sugar-calendar-lite' ),
			'description' => esc_html__( 'Automatic delivery is not available for this email; manual Send Email actions still work.', 'sugar-calendar-lite' ),
		];
	}

	/**
	 * Print the upgrade prompt used wherever a control needs a Pro/add-on gate.
	 *
	 * On Lite the badge is an education-modal trigger. On Pro it is an inert
	 * badge: `pro_only` means "the add-on servicing this row is not active", not
	 * "this site is unlicensed", so a paying Pro customer who simply has not
	 * installed the add-on must never be told to buy what they already own.
	 *
	 * Public because the Email Styling section needs the identical markup for its
	 * own gated control — one click target, rendered one way.
	 *
	 * @since 3.14.0
	 *
	 * @param string $feat_id   Feature slug, used in the modal's UTM medium.
	 * @param string $feat_name Human-readable feature name for the modal title.
	 */
	public static function print_pro_prompt( $feat_id, $feat_name ) {

		$classes = [ 'sugar-calendar-settings-emails__pro-prompt' ];
		$is_lite = ! sugar_calendar()->is_pro();

		if ( $is_lite ) {
			$classes[] = 'sce-lite-education-modal-link';
		}
		?>
		<span class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			<?php if ( $is_lite ) : ?>
				data-feat-id="<?php echo esc_attr( $feat_id ); ?>"
				data-feat-name="<?php echo esc_attr( $feat_name ); ?>"
				role="button"
				tabindex="0"
			<?php endif; ?>
		>
			<span class="sugar-calendar__badge__pro-only"><?php esc_html_e( 'PRO', 'sugar-calendar-lite' ); ?></span>
		</span>
		<?php
	}

	/**
	 * Build the edit-screen URL for one email.
	 *
	 * @since 3.14.0
	 *
	 * @param string $id Email id.
	 *
	 * @return string
	 */
	public static function get_edit_url( $id ) {

		return add_query_arg(
			[
				'page'      => 'sugarcalendar-settings',
				'section'   => 'emails',
				'email_cfg' => $id,
			],
			get_admin_url( null, 'admin.php' )
		);
	}
}
