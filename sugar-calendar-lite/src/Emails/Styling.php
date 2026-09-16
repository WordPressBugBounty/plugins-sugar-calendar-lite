<?php

namespace Sugar_Calendar\Emails;

/**
 * The single read accessor for Email Styling values.
 *
 * Nothing else in the codebase may decide where these values come from.
 *
 * Storage is a deliberate shortcut: the `setting_names()` keys live in the
 * Event Ticketing `sc_et_settings` option and are written only by the ticketing
 * `Admin\Settings\handle_post()`; no production code deletes that option.
 * `Installation::EVIDENCE_OPTIONS` reads the same option as proof of an existing
 * install, so `install_defaults()` must run after classification, and sc-rsvp
 * version-gates its Modern path on this shape. A core-owned option is follow-up.
 *
 * @since 3.14.0
 */
class Styling {

	/**
	 * The setting key for the Email Template selection.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const SETTING_TEMPLATE = 'email_styling_template';

	/**
	 * The setting key for the Header Image attachment.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const SETTING_HEADER_IMAGE = 'email_styling_header_image';

	/**
	 * The setting key for the Header Image size.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const SETTING_HEADER_IMAGE_SIZE = 'email_styling_header_image_size';

	/**
	 * The setting key for the background color.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const SETTING_COLOR_BACKGROUND = 'email_styling_color_background';

	/**
	 * The setting key for the body color.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const SETTING_COLOR_BODY = 'email_styling_color_body';

	/**
	 * The setting key for the text color.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const SETTING_COLOR_TEXT = 'email_styling_color_text';

	/**
	 * The setting key for the accent color.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const SETTING_COLOR_ACCENT = 'email_styling_color_accent';

	/**
	 * The setting key for the branding toggle.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const SETTING_HIDE_BRANDING = 'email_hide_branding';

	/**
	 * The Modern template identifier, as stored by the settings UI.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const TEMPLATE_MODERN = 'modern';

	/**
	 * The Plain Text template identifier, as stored by the settings UI.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const TEMPLATE_PLAIN_TEXT = 'plain-text';

	/**
	 * The legacy template slug `Emails` already understands.
	 *
	 * "Plain Text" is the compatibility format, which means the existing legacy
	 * HTML wrapper — not `text/plain`. Anything that is not Modern resolves here.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const LEGACY_TEMPLATE = 'default';

	/**
	 * The presentation defaults.
	 *
	 * These are the values the approved settings UI renders. The Figma comp uses
	 * a different accent (#fa6400) and background (#f0ece6); the product uses
	 * these, because the email must render what the color picker shows.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function defaults() {

		return [
			'template'          => self::TEMPLATE_MODERN,
			'header_image_id'   => 0,
			'header_image'      => '',
			'header_image_size' => 'medium',
			'colors'            => [
				'background' => '#e9eaec',
				'body'       => '#ffffff',
				'text'       => '#333333',
				'accent'     => '#ff641f',
			],
			'hide_branding'     => false,
		];
	}

	/**
	 * Return the flat option names owned by Email Styling.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function setting_names() {

		return [
			self::SETTING_TEMPLATE,
			self::SETTING_HEADER_IMAGE,
			self::SETTING_HEADER_IMAGE_SIZE,
			self::SETTING_COLOR_BACKGROUND,
			self::SETTING_COLOR_BODY,
			self::SETTING_COLOR_TEXT,
			self::SETTING_COLOR_ACCENT,
			self::SETTING_HIDE_BRANDING,
		];
	}

	/**
	 * Sanitize one stored Email Styling value.
	 *
	 * @since 3.14.0
	 *
	 * @param string $key   Option key.
	 * @param mixed  $value Raw value.
	 *
	 * @return mixed
	 */
	public static function sanitize_setting( $key, $value ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded -- The eight flat option keys deliberately share one sanitizer.

		$defaults = self::defaults();

		if ( $key === self::SETTING_TEMPLATE ) {
			return in_array( $value, [ self::TEMPLATE_MODERN, self::TEMPLATE_PLAIN_TEXT ], true )
				? $value
				: self::TEMPLATE_PLAIN_TEXT;
		}

		if ( $key === self::SETTING_HEADER_IMAGE ) {
			if (
				( ! is_int( $value ) || $value < 0 )
				&&
				( ! is_string( $value ) || ! ctype_digit( $value ) )
			) {
				return 0;
			}

			$image_id = absint( $value );

			if ( $image_id === 0 || ! wp_attachment_is_image( $image_id ) ) {
				return 0;
			}

			return $image_id;
		}

		if ( $key === self::SETTING_HEADER_IMAGE_SIZE ) {
			return is_string( $value ) && array_key_exists( $value, self::header_image_widths() )
				? $value
				: $defaults['header_image_size'];
		}

		$color_key = str_replace( 'email_styling_color_', '', $key );

		if ( array_key_exists( $color_key, $defaults['colors'] ) ) {
			$color = is_string( $value ) ? sanitize_hex_color( $value ) : null;

			return $color === null || $color === '' ? $defaults['colors'][ $color_key ] : $color;
		}

		if ( $key === self::SETTING_HIDE_BRANDING ) {
			return (bool) $value;
		}

		return $value;
	}

	/**
	 * Convert flat option values into the effective Email Styling shape.
	 *
	 * @since 3.14.0
	 *
	 * @param array $settings Flat option values.
	 *
	 * @return array
	 */
	public static function from_settings( $settings ) {

		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		$settings = wp_parse_args( $settings, array_fill_keys( self::setting_names(), null ) );
		$image_id = self::sanitize_setting( self::SETTING_HEADER_IMAGE, $settings[ self::SETTING_HEADER_IMAGE ] );
		$image    = $image_id > 0 ? wp_get_attachment_image_url( $image_id, 'full' ) : false;

		if ( $image === false ) {
			$image_id = 0;
			$image    = '';
		}

		return [
			'template'          => self::sanitize_setting( self::SETTING_TEMPLATE, $settings[ self::SETTING_TEMPLATE ] ),
			'header_image_id'   => $image_id,
			'header_image'      => $image,
			'header_image_size' => self::sanitize_setting( self::SETTING_HEADER_IMAGE_SIZE, $settings[ self::SETTING_HEADER_IMAGE_SIZE ] ),
			'colors'            => [
				'background' => self::sanitize_setting( self::SETTING_COLOR_BACKGROUND, $settings[ self::SETTING_COLOR_BACKGROUND ] ),
				'body'       => self::sanitize_setting( self::SETTING_COLOR_BODY, $settings[ self::SETTING_COLOR_BODY ] ),
				'text'       => self::sanitize_setting( self::SETTING_COLOR_TEXT, $settings[ self::SETTING_COLOR_TEXT ] ),
				'accent'     => self::sanitize_setting( self::SETTING_COLOR_ACCENT, $settings[ self::SETTING_COLOR_ACCENT ] ),
			],
			'hide_branding'     => self::sanitize_setting( self::SETTING_HIDE_BRANDING, $settings[ self::SETTING_HIDE_BRANDING ] ),
		];
	}

	/**
	 * Whether the current build may hide the Sugar Calendar branding.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public static function can_hide_branding() {

		/**
		 * Filters whether Email Styling may hide the Sugar Calendar branding.
		 *
		 * @since 3.14.0
		 *
		 * @param bool $can_hide_branding Whether the branding may be hidden.
		 */
		// phpcs:ignore WPForms.Comments.PHPDocHooks.RequiredHookDocumentation, WPForms.PHP.ValidateHooks.InvalidHookName -- This entitlement is independent of the styling-values filter.
		return (bool) apply_filters( 'sugar_calendar_emails_can_hide_branding', false );
	}

	/**
	 * Normalize a partial effective styling shape.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $values Effective styling values.
	 *
	 * @return array
	 */
	public static function normalize( $values ) {

		$defaults = self::defaults();

		if ( ! is_array( $values ) ) {
			return $defaults;
		}

		$values           = array_merge( $defaults, $values );
		$values['colors'] = array_merge( $defaults['colors'], is_array( $values['colors'] ) ? $values['colors'] : [] );

		$values['template']          = self::sanitize_setting( self::SETTING_TEMPLATE, $values['template'] );
		$values['header_image_id']   = self::sanitize_setting( self::SETTING_HEADER_IMAGE, $values['header_image_id'] );
		$values['header_image']      = $values['header_image_id'] > 0 ? wp_get_attachment_image_url( $values['header_image_id'], 'full' ) : '';
		$values['header_image_size'] = self::sanitize_setting( self::SETTING_HEADER_IMAGE_SIZE, $values['header_image_size'] );

		if ( $values['header_image'] === false ) {
			$values['header_image_id'] = 0;
			$values['header_image']    = '';
		}

		foreach ( $defaults['colors'] as $key => $default ) {
			$values['colors'][ $key ] = self::sanitize_setting( 'email_styling_color_' . $key, $values['colors'][ $key ] );
		}

		$values['hide_branding'] = (bool) $values['hide_branding'] && self::can_hide_branding();

		return $values;
	}

	/**
	 * Header image widths in pixels, keyed by size.
	 *
	 * The numeric authority. `EmailStylingSection` keeps its own literal labels
	 * so translators receive whole sentences rather than a sprintf skeleton;
	 * `StylingTest::test_the_settings_labels_agree_with_the_width_map()` is what
	 * keeps the two in step.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function header_image_widths() {

		return [
			'small'  => 120,
			'medium' => 180,
			'large'  => 240,
		];
	}

	/**
	 * Store the template default for a proven-new installation.
	 *
	 * Defensive on purpose: storage can change between the caller's
	 * classification and this write, and the method is safe to call directly.
	 * Anything already stored under the template key, and any storage that is
	 * not an array, is left exactly as it is. No copy and no marker is written.
	 *
	 * @since 3.14.0
	 *
	 * @return bool Whether the option was updated.
	 */
	public static function install_defaults() {

		$settings = get_option( 'sc_et_settings', [] );

		if ( ! is_array( $settings ) || array_key_exists( self::SETTING_TEMPLATE, $settings ) ) {
			return false;
		}

		$settings[ self::SETTING_TEMPLATE ] = self::TEMPLATE_MODERN;

		return update_option( 'sc_et_settings', $settings, false );
	}

	/**
	 * Resolve a caller-supplied flat Email Styling snapshot.
	 *
	 * This method reads no options. It converts the supplied snapshot, applies
	 * the public styling filter, and normalizes the final values for consumers.
	 *
	 * @since 3.14.0
	 *
	 * @param array $settings Flat option values.
	 *
	 * @return array
	 */
	public static function resolve( array $settings ): array {

		$resolved = self::from_settings( $settings );

		/**
		 * Filters the Email Styling values used to render transactional emails.
		 *
		 * A partial array is merged over the complete resolved snapshot. A
		 * non-array return is ignored.
		 *
		 * @since 3.14.0
		 *
		 * @param array $resolved Complete resolved Email Styling values.
		 */
		$filtered = apply_filters( 'sugar_calendar_emails_styling', $resolved );
		$values   = $resolved;

		if ( is_array( $filtered ) ) {
			$filtered_colors  = isset( $filtered['colors'] ) && is_array( $filtered['colors'] )
				? $filtered['colors']
				: [];
			$values           = array_merge( $resolved, $filtered );
			$values['colors'] = array_merge( $resolved['colors'], $filtered_colors );
		}

		return self::normalize( $values );
	}

	/**
	 * The current styling values, with every key guaranteed present.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function get() {

		$settings = get_option( 'sc_et_settings', [] );

		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		return self::resolve( $settings );
	}

	/**
	 * Resolve the stored template to a slug `Emails::get_template()` understands.
	 *
	 * @since 3.14.0
	 *
	 * @return string Either `modern` or `default`.
	 */
	public static function email_template() {

		return self::get()['template'] === self::TEMPLATE_MODERN
			? self::TEMPLATE_MODERN
			: self::LEGACY_TEMPLATE;
	}

	/**
	 * The header image width in pixels for the current size setting.
	 *
	 * @since 3.14.0
	 *
	 * @param array $styling Styling values from `get()`.
	 *
	 * @return int
	 */
	public static function header_image_width( $styling ) {

		$widths = self::header_image_widths();
		$size   = $styling['header_image_size'];

		return $widths[ $size ] ?? $widths['medium'];
	}
}
