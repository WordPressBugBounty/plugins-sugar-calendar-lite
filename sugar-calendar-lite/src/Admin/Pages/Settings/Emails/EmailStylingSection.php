<?php

namespace Sugar_Calendar\Admin\Pages\Settings\Emails;

use Sugar_Calendar\Emails\Styling;
use Sugar_Calendar\Helpers\UI;

/**
 * Render Email Styling controls on Settings → Emails.
 *
 * @since 3.14.0
 */
class EmailStylingSection {

	/**
	 * Render the section.
	 *
	 * @since 3.14.0
	 *
	 * @return void
	 */
	public static function render() {

		$styling = Styling::get();
		?>
		<div class="sugar-calendar-settings-email-styling">
			<div class="sugar-calendar-settings-emails__heading">
				<h4><?php esc_html_e( 'Attendee Email Styling', 'sugar-calendar-lite' ); ?></h4>
				<p class="desc"><?php esc_html_e( 'Customize your email template and styling.', 'sugar-calendar-lite' ); ?></p>
			</div>
			<?php
			self::render_template_picker( $styling );
			self::render_header_image( $styling );
			self::render_color_scheme( $styling );
			self::render_hide_branding( $styling );
			?>
		</div>
		<?php
	}

	/**
	 * Return the Header Image size choices.
	 *
	 * Labels only; the pixel widths come from `Styling::header_image_widths()`
	 * and reach the control as `data-width`, so the two cannot drift.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	private static function get_header_image_sizes() {

		return [
			'small'  => __( 'Small', 'sugar-calendar-lite' ),
			'medium' => __( 'Medium', 'sugar-calendar-lite' ),
			'large'  => __( 'Large', 'sugar-calendar-lite' ),
		];
	}

	/**
	 * Render the Email Template cards.
	 *
	 * @since 3.14.0
	 *
	 * @param array $styling Effective styling values.
	 *
	 * @return void
	 */
	private static function render_template_picker( $styling ) {

		UI::image_radio_group(
			[
				'id'      => 'email_styling_template',
				'name'    => 'email_styling_template',
				'value'   => $styling['template'],
				'label'   => __( 'Email Template', 'sugar-calendar-lite' ),
				'options' => [
					Styling::TEMPLATE_MODERN     => [
						'label' => __( 'Modern', 'sugar-calendar-lite' ),
						'image' => SC_PLUGIN_ASSETS_URL . 'images/email-template-modern.svg',
					],
					Styling::TEMPLATE_PLAIN_TEXT => [
						'label' => __( 'Plain Text', 'sugar-calendar-lite' ),
						'image' => SC_PLUGIN_ASSETS_URL . 'images/email-template-plain-text.svg',
					],
				],
			]
		);
	}

	/**
	 * Render Header Image.
	 *
	 * @since 3.14.0
	 *
	 * @param array $styling Effective styling values.
	 *
	 * @return void
	 */
	private static function render_header_image( $styling ) {

		UI::image_upload(
			[
				'id'           => 'email_styling_header_image',
				'name'         => 'email_styling_header_image',
				'value'        => $styling['header_image_id'],
				'image_url'    => $styling['header_image'],
				'size_name'    => 'email_styling_header_image_size',
				'size_label'   => __( 'Header image size', 'sugar-calendar-lite' ),
				'size_value'   => $styling['header_image_size'],
				'size_options' => self::get_header_image_sizes(),
				'size_widths'  => Styling::header_image_widths(),
				'label'        => __( 'Header Image', 'sugar-calendar-lite' ),
				'description'  => __(
					'Upload or choose a logo to be displayed at the top of email notifications.',
					'sugar-calendar-lite'
				),
				'class'        => [ 'sugar-calendar-settings-emails__modern-only' ],
			]
		);
	}

	/**
	 * Render Color Scheme.
	 *
	 * @since 3.14.0
	 *
	 * @param array $styling Effective styling values.
	 *
	 * @return void
	 */
	private static function render_color_scheme( $styling ) {

		$labels = [
			'background' => __( 'Background', 'sugar-calendar-lite' ),
			'body'       => __( 'Body', 'sugar-calendar-lite' ),
			'text'       => __( 'Text', 'sugar-calendar-lite' ),
			'accent'     => __( 'Accent', 'sugar-calendar-lite' ),
		];

		$defaults = Styling::defaults()['colors'];
		$colors   = [];

		foreach ( $styling['colors'] as $key => $value ) {

			// A styling filter may add colors this screen has no control for.
			if ( ! isset( $labels[ $key ] ) ) {
				continue;
			}

			$colors[] = [
				'name'    => 'email_styling_color_' . $key,
				'value'   => $value,
				'label'   => $labels[ $key ],
				'default' => $defaults[ $key ] ?? '',
			];
		}

		UI::color_scheme_input(
			[
				'id'     => 'email_styling_colors',
				'label'  => __( 'Color Scheme', 'sugar-calendar-lite' ),
				'class'  => [ 'sugar-calendar-settings-emails__modern-only' ],
				'colors' => $colors,
			]
		);
	}

	/**
	 * Render the Pro and Lite Hide Branding states.
	 *
	 * @since 3.14.0
	 *
	 * @param array $styling Effective styling values.
	 *
	 * @return void
	 */
	private static function render_hide_branding( $styling ) {

		$can_hide_branding = Styling::can_hide_branding();
		$branding_label    = __( 'Hide Sugar Calendar Branding', 'sugar-calendar-lite' );
		$classes           = [ 'sugar-calendar-settings-emails__branding', 'sugar-calendar-settings-emails__modern-only' ];

		// Same condition that makes the badge a modal trigger: on Lite the whole
		// row is the click surface, not just the badge.
		if ( ! $can_hide_branding && ! sugar_calendar()->is_pro() ) {
			$classes[] = 'sugar-calendar-settings-emails__branding--locked';
		}
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<?php
		UI::toggle_control(
			[
				'id'       => 'email_hide_branding',
				'name'     => 'email_hide_branding',
				'value'    => $styling['hide_branding'],
				'disabled' => ! $can_hide_branding,
				'label'    => $branding_label,
			]
		);

		if ( ! $can_hide_branding ) {
			EmailsList::print_pro_prompt( 'email-branding', $branding_label );
		}
		?>
		</div>
		<?php
	}
}
