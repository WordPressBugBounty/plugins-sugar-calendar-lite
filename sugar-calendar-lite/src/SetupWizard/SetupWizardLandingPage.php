<?php

namespace Sugar_Calendar\SetupWizard;

use Sugar_Calendar\Helpers as Sugar_Calendar_Helpers;
use Sugar_Calendar\Helpers\WP;

/**
 * Class SetupWizardLandingPage.
 *
 * Renders the Setup Wizard welcome screen: a standalone document whose primary
 * call to action posts the wizard bootstrap data to the wizard SPA. The handoff
 * is never automatic, since the user has to initiate the trip off-site from
 * their own admin.
 *
 * @since 3.13.0
 */
class SetupWizardLandingPage {

	/**
	 * Wizard bootstrap data posted to the wizard SPA.
	 *
	 * @since 3.13.0
	 *
	 * @var array
	 */
	private $fields;

	/**
	 * Constructor.
	 *
	 * @since 3.13.0
	 *
	 * @param array $fields Wizard bootstrap data posted to the wizard SPA.
	 */
	public function __construct( array $fields ) {

		$this->fields = $fields;
	}

	/**
	 * Render the welcome screen.
	 *
	 * The caller is responsible for sending the response headers.
	 *
	 * @since 3.13.0
	 *
	 * @return void
	 */
	public function render() {

		$action_url = defined( 'SC_SETUP_WIZARD_URL' ) ? SC_SETUP_WIZARD_URL : SetupWizard::URL;
		$exit_url   = isset( $this->fields['exit_url'] ) ? $this->fields['exit_url'] : admin_url();
		$style_url  = SC_PLUGIN_ASSETS_URL . 'css/admin-setup-wizard-welcome' . WP::asset_min() . '.css';

		/*
		 * Inlined rather than referenced via <img> so the stylesheet can animate
		 * the artwork's own #dash and #hi paths — external CSS cannot cross the
		 * document boundary of an <img>. Mirrors how the wizard SPA animates the
		 * same asset. First-party file, so the markup is emitted verbatim.
		 */
		$mascot_path = SC_PLUGIN_DIR . 'assets/images/mascot.svg';
		$mascot_svg  = is_readable( $mascot_path ) ? file_get_contents( $mascot_path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex,nofollow" />
	<title><?php esc_html_e( 'Sugar Calendar', 'sugar-calendar-lite' ); ?></title>
	<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Standalone document rendered outside the admin enqueue pipeline. ?>
	<link rel="stylesheet" href="<?php echo esc_url( $style_url . '?ver=' . Sugar_Calendar_Helpers::get_asset_version() ); ?>" />
</head>
<body class="sc-setup-wizard">

	<div class="sc-setup-wizard__inner">

		<img
			class="sc-setup-wizard__logo"
			src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/logo.svg' ); ?>"
			alt="<?php esc_attr_e( 'Sugar Calendar', 'sugar-calendar-lite' ); ?>"
		/>

		<form class="sc-setup-wizard__card" method="POST" action="<?php echo esc_url( $action_url ); ?>">

			<?php foreach ( $this->fields as $field_name => $field_value ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $field_value ); ?>" />
			<?php endforeach; ?>

			<?php if ( $mascot_svg ) : ?>
				<div class="sc-setup-wizard__mascot" aria-hidden="true">
					<?php echo $mascot_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>

			<h1 class="sc-setup-wizard__title">
				<?php esc_html_e( 'Welcome to the Sugar Calendar Setup Wizard!', 'sugar-calendar-lite' ); ?>
			</h1>

			<p class="sc-setup-wizard__subtitle">
				<?php esc_html_e( 'Help us tailor your experience as per your needs', 'sugar-calendar-lite' ); ?>
			</p>

			<button type="submit" class="sc-setup-wizard__cta">
				<span><?php esc_html_e( 'Let\'s Get Started', 'sugar-calendar-lite' ); ?></span>
				<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false">
					<path d="M9 3.5L13.5 8L9 12.5M13 8H2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
				</svg>
			</button>

			<p class="sc-setup-wizard__notice">
				<?php
				echo wp_kses(
					__( 'Note: You will be transferred to a Sugar Calendar site to<br />complete the setup wizard.', 'sugar-calendar-lite' ),
					[ 'br' => [] ]
				);
				?>
			</p>

		</form>

		<a class="sc-setup-wizard__exit" href="<?php echo esc_url( $exit_url ); ?>">
			<?php esc_html_e( 'Close and exit the Setup Wizard', 'sugar-calendar-lite' ); ?>
		</a>

	</div>

	<dialog class="sc-setup-wizard__dialog" id="sc-setup-wizard-exit-dialog">
		<h2 class="sc-setup-wizard__dialog-title">
			<?php esc_html_e( 'Are you sure you want to close the setup wizard?', 'sugar-calendar-lite' ); ?>
		</h2>
		<p class="sc-setup-wizard__dialog-text">
			<?php esc_html_e( 'Selecting "Yes" will close the setup wizard and take you back to Sugar Calendar.', 'sugar-calendar-lite' ); ?>
		</p>
		<div class="sc-setup-wizard__dialog-actions">
			<button type="button" class="sc-setup-wizard__dialog-button sc-setup-wizard__dialog-button--primary" data-sc-exit-cancel>
				<?php esc_html_e( 'No', 'sugar-calendar-lite' ); ?>
			</button>
			<button type="button" class="sc-setup-wizard__dialog-button sc-setup-wizard__dialog-button--tertiary" data-sc-exit-confirm>
				<?php esc_html_e( 'Yes', 'sugar-calendar-lite' ); ?>
			</button>
		</div>
	</dialog>

	<script>
		( function () {
			var link   = document.querySelector( '.sc-setup-wizard__exit' );
			var dialog = document.getElementById( 'sc-setup-wizard-exit-dialog' );

			// Without dialog support the link keeps working as a plain exit.
			if ( ! link || ! dialog || typeof dialog.showModal !== 'function' ) {
				return;
			}

			link.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				dialog.showModal();
			} );

			dialog.querySelector( '[data-sc-exit-cancel]' ).addEventListener( 'click', function () {
				dialog.close();
			} );

			dialog.querySelector( '[data-sc-exit-confirm]' ).addEventListener( 'click', function () {
				window.location.href = link.href;
			} );
		}() );
	</script>

</body>
</html>
		<?php
	}
}
