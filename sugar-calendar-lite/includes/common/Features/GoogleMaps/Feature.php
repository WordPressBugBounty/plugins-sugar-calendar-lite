<?php

namespace Sugar_Calendar\Common\Features\GoogleMaps;

use Sugar_Calendar\Common\Features\FeatureAbstract;
use Sugar_Calendar\Helpers;
use Sugar_Calendar\Helpers\WP;

/**
 * Class Feature.
 *
 * The Google Maps Feature.
 *
 * @since 3.0.0
 */
class Feature extends FeatureAbstract {

	/**
	 * Nonce for Address field in the meta box.
	 *
	 * @since 3.0.0
	 *
	 * @var string
	 */
	public static $sc_maps_meta_box_nonce = 'sc_maps_meta_box_nonce';

	/**
	 * Get the Google Maps Feature requirements.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function get_requirements() {

		return [];
	}

	/**
	 * Setup the Settings.
	 *
	 * @since 3.0.0
	 */
	protected function setup() {}

	/**
	 * Hooks.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	protected function hooks() {

		add_action( 'init', [ $this, 'remove_legacy_plugin_hooks' ], 1 );

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'sc_after_event_content', [ $this, 'show_map' ] );
		add_action( 'wp_head', [ $this, 'map_css' ] );
		add_action( 'save_post', [ $this, 'meta_box_save' ] );
		add_action( 'wp_ajax_sugar_calendar_venue_save_coordinates', [ $this, 'save_coordinates' ] );
		add_action( 'wp_ajax_nopriv_sugar_calendar_venue_save_coordinates', [ $this, 'save_coordinates' ] );

		if ( ! $this->maps_is_20() ) {
			add_action( 'sc_event_meta_box_after', [ $this, 'add_forms_meta_box' ] );
		}
	}

	/**
	 * Ajax action for saving coordinates.
	 *
	 * @since 3.10.0
	 */
	public function save_coordinates() {

		check_ajax_referer( 'sugar_calendar_venue_save_coordinates', 'nonce' );

		if ( empty( $_POST['address'] ) || empty( $_POST['lat'] ) || empty( $_POST['lng'] ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid request.', 'sugar-calendar-lite' ) ] );
		}

		$address = sanitize_text_field( wp_unslash( $_POST['address'] ) );
		$lat     = sanitize_text_field( wp_unslash( $_POST['lat'] ) );
		$lng     = sanitize_text_field( wp_unslash( $_POST['lng'] ) );

		if ( empty( $address ) || empty( $lat ) || empty( $lng ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid request.', 'sugar-calendar-lite' ) ] );
		}

		$address_hash = 'scgm_' . md5( $address );
		$cache_value  = [
			'lat'     => $lat,
			'lng'     => $lng,
			'address' => $address,
		];

		set_transient( $address_hash, $cache_value );
	}

	/**
	 * Show admin address field.
	 *
	 * @since 3.0.0
	 */
	public function add_forms_meta_box() {

		// 2.0 has a default address field so we do not need to register one.
		if ( $this->maps_is_20() ) {
			return;
		}

		global $post;
		?>

        <tr class="sc_meta_box_row">
            <td class="sc_meta_box_td" colspan="2" valign="top"><?php esc_html_e( 'Event Location', 'sugar-calendar-lite' ); ?></td>
            <td class="sc_meta_box_td" colspan="4">
                <input type="text" class="regular-text" name="sc_map_address" value="<?php echo esc_attr( $this->get_address( $post->ID ) ); ?> "/>
                <span class="description"><?php esc_html_e( 'Enter the event address.', 'sugar-calendar-lite' ); ?></span>
                <br/>
                <input type="hidden" name="sc_maps_meta_box_nonce" value="<?php echo wp_create_nonce( self::$sc_maps_meta_box_nonce ); ?>"/>
            </td>
        </tr>

		<?php
	}

	/**
	 * Register scripts.
	 *
	 * @since 3.0.0
	 */
	public function enqueue_scripts() {

		$key = $this->get_api_key();

		if ( empty( $key ) ) {
			return;
		}

		wp_register_script(
			'sugar-calendar-features-venues-frontend-general',
			SC_PLUGIN_ASSETS_URL . 'js/features/venues/frontend/general' . WP::asset_min() . '.js',
			[ 'jquery' ],
			Helpers::get_asset_version(),
			true
		);

		wp_register_script(
			'sc-google-maps-api',
			'//maps.googleapis.com/maps/api/js?callback=scGoogleMapsCB&key=' . rawurldecode( $key ),
			[ 'sugar-calendar-features-venues-frontend-general' ],
			'20201021'
		);

		$pts = sugar_calendar_allowed_post_types();
		$tax = sugar_calendar_get_object_taxonomies( $pts );

		if ( is_singular( $pts ) || is_post_type_archive( $pts ) || is_tax( $tax ) ) {
			wp_enqueue_script( 'sc-google-maps-api' );

			wp_localize_script(
				'sugar-calendar-features-venues-frontend-general',
				'sugar_calendar_venue_frontend_general',
				[
					'ajax_url' => admin_url( 'admin-ajax.php' ),
				]
			);
		}
	}

	/**
	 * Remove the legacy plugin hooks.
	 *
	 * @since 3.0.0
	 */
	public function remove_legacy_plugin_hooks() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		// Remove Actions.
		remove_action( 'sugar_calendar_register_settings', 'sc_maps_regsiter_api_key_setting' );
		remove_action( 'sc_event_meta_box_after', 'sc_maps_add_forms_meta_box' );
		remove_action( 'init', 'sc_maps_register_scripts' );
		remove_action( 'wp_enqueue_scripts', 'sc_maps_enqueue_scripts' );
		remove_action( 'wp_head', 'sc_maps_map_css' );
		remove_action( 'save_post', 'sc_maps_meta_box_save' );
		remove_action( 'sc_after_event_content', 'sc_maps_show_map' );
		remove_action( 'init', 'sc_maps_init' );

		// Remove Filters.
		remove_filter( 'sugar_calendar_settings_sections', 'sc_maps_register_maps_section' );
		remove_filter( 'sugar_calendar_settings_subsections', 'sc_maps_register_maps_subsection' );
	}

	/**
	 * Displays the event map.
	 *
	 * @since 3.0.0
	 * @since 3.5.0 Add $force_refresh parameter.
	 * @since 3.10.0 Moved the JS in `general.js`.
	 *
	 * @param int  $event_id      Event ID.
	 * @param bool $force_refresh Whether to force a refresh of the coordinates.
	 */
	public function show_map( $event_id = 0, $force_refresh = false ) {

		if ( ! $this->get_api_key() ) {
			return;
		}

		/**
		 * Filter the `$coordinates` of the map for the event.
		 *
		 * This filter allows you to modify the coordinates of the map for the event.
		 *
		 * @since 3.5.0
		 *
		 * @param array|bool $coordinates Coordinates of the map for the event.
		 * @param int        $event_id    Event ID.
		 */
		$coordinates = apply_filters(
			'sugar_calendar_common_features_google_maps_feature_coordinates',
			false,
			$event_id
		);

		$address = false;

		if ( empty( $coordinates ) ) {
			$address = $this->get_address( $event_id );

			if ( empty( $address ) ) {
				return;
			}

			$coordinates = $this->get_coordinates( $address );
		}

		if ( empty( $coordinates ) && ! empty( $address ) ) {
			printf(
				'<div data-loc="%1$s" data-nonce="%2$s" class="sc_map_canvas"></div>',
				esc_attr( $address ),
				esc_attr( wp_create_nonce( 'sugar_calendar_venue_save_coordinates' ) )
			);
		} elseif ( ! empty( $coordinates ) ) {
			printf(
				'<div data-lat="%1$s" data-lng="%2$s" class="sc_map_canvas"></div>',
				esc_attr( $coordinates['lat'] ),
				esc_attr( $coordinates['lng'] )
			);
		}
		?>
		<?php
	}

	/**
	 * Return the Google Maps API Key.
	 *
	 * @since 3.0.0
	 * @since 3.5.0 Change into static method.
	 *
	 * @return string
	 */
	public function get_api_key() {

		return Helpers::get_google_maps_api_key();
	}

	/**
	 * Retrieve event address.
	 *
	 * @since 3.0.0
	 * @since 3.10.0 Make the return value filterable.
	 *
	 * @param int $event_id Event ID.
	 *
	 * @return string
	 */
	public function get_address( $event_id = 0 ) {

		if ( empty( $event_id ) ) {
			$event_id = get_the_ID();
		}

		if ( ! $this->maps_is_20() ) {
			return get_post_meta( $event_id, 'sc_map_address', true );
		}

		$event = sugar_calendar_get_event_by_object( $event_id );

		/**
		 * Filter the event address.
		 *
		 * @since 3.10.0
		 *
		 * @param string $address Event address.
		 * @param Event  $event   Event object.
		 */
		return apply_filters(
			'sugar_calendar_common_features_google_maps_feature_address',
			$event->location,
			$event
		);
	}

	/**
	 * Retrieve coordinates for an address.
	 *
	 * Coordinates are cached using transients and a hash of the address
	 *
	 * @since 3.0.0
	 * @since 3.5.0 Add check for API key.
	 *
	 * @param string $address       Address to geocode.
	 * @param bool   $force_refresh Whether to force a refresh of the coordinates.
	 *
	 * @return array|string An array of coordinates or a string error message.
	 */
	public function get_coordinates( $address, $force_refresh = false ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh, Generic.Metrics.NestingLevel.MaxExceeded

		// Check for API key.
		if ( empty( $this->get_api_key() ) ) {
			return esc_html__(
				'Google Maps API key is missing.',
				'sugar-calendar-lite'
			);
		}

		// Create the transient hash.
		$address_hash = 'scgm_' . md5( $address );

		// Check for this transient.
		$coordinates = get_transient( $address_hash );
		$data        = $coordinates;

		if ( empty( $coordinates ) ) {
			return false;
		}

		return $data;
	}

	/**
	 * Are we running Sugar Calendar 2.0?
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function maps_is_20() {

		if ( ! defined( 'SC_PLUGIN_VERSION' ) ) {
			return false;
		}

		$sc_version = preg_replace( '/[^0-9.].*/', '', SC_PLUGIN_VERSION );

		return version_compare( $sc_version, '2.0', '>=' );
	}

	/**
	 * Fixes a problem with responsive themes.
	 *
	 * @since 3.0.0
	 */
	public function map_css() {

		?>
        <style type="text/css">
            .sc_map_canvas img {
                max-width: none;
            }
        </style>
		<?php
	}

	/**
	 * Save Address field.
	 *
	 * Save data from meta box.
	 *
	 * @since 3.0.0
	 *
	 * @param int $event_id Event ID.
	 *
	 * @return void|int
	 */
	public function meta_box_save( $event_id ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		// 2.0 has a default address field so we do not need to save one.
		if ( $this->maps_is_20() ) {
			return;
		}

		if (
			empty( $_POST['sc_maps_meta_box_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( $_POST['sc_maps_meta_box_nonce'] ), self::$sc_maps_meta_box_nonce )
		) {
			return $event_id;
		}

		if (
			( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
			( defined( 'DOING_AJAX' ) && DOING_AJAX ) |
			isset( $_REQUEST['bulk_edit'] )
		) {
			return $event_id;
		}

		global $post;

		if ( isset( $post->post_type ) && $post->post_type === 'revision' ) {
			return $event_id;
		}

		if ( ! current_user_can( 'edit_post', $event_id ) ) {
			return $event_id;
		}

		$address = empty( $_POST['sc_map_address'] ) ? '' : sanitize_text_field( $_POST['sc_map_address'] );

		update_post_meta(
			$event_id,
			'sc_map_address',
			$address
		);
	}
}
