<?php

namespace Sugar_Calendar\Common\Features\GoogleMaps;

use Sugar_Calendar\Common\Features\FeatureAbstract;
use Sugar_Calendar\Helpers;

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

		if ( ! $this->maps_is_20() ) {
			add_action( 'sc_event_meta_box_after', [ $this, 'add_forms_meta_box' ] );
		}
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
			'sc-google-maps-api',
			'//maps.googleapis.com/maps/api/js?key=' . rawurldecode( $key ),
			[],
			'20201021'
		);

		$pts = sugar_calendar_allowed_post_types();
		$tax = sugar_calendar_get_object_taxonomies( $pts );

		if ( is_singular( $pts ) || is_post_type_archive( $pts ) || is_tax( $tax ) ) {
			wp_enqueue_script( 'sc-google-maps-api' );
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

		if ( empty( $coordinates ) ) {
			$address = $this->get_address( $event_id );

			if ( empty( $address ) ) {
				return;
			}

			$coordinates = $this->get_coordinates( $address, $force_refresh );
		}

		if ( is_string( $coordinates ) ) {
			?>
			<script type="text/javascript">
				console.warn( '[SCGM]: <?php echo esc_js( $coordinates ); ?>' );
			</script>
			<?php
		}

		if ( ! is_array( $coordinates ) ) {
			return;
		}

		$map_id = uniqid( 'sc_map_' . $event_id );

		ob_start();
		?>
		<div class="sc_map_canvas" id="<?php echo esc_attr( $map_id ); ?>" style="width: 100%; height: 400px; margin-bottom: 1em; background-color: rgba(0,0,0,0.1)"></div>
		<script type="text/javascript">

			/**
			 * Unique function for this specific location
			 *
			 * @since 3.0.0
			 */
			function sc_run_map_<?php echo $map_id; ?>() {

				// Define variables
				var element = document.getElementById( '<?php echo $map_id; ?>' ),

					// Latitude & Longitude
					location = new google.maps.LatLng(
						'<?php echo $coordinates['lat']; ?>',
						'<?php echo $coordinates['lng']; ?>'
					),

					// Options
					map_options = {
						zoom: 15,
						center: location,
						mapTypeId: google.maps.MapTypeId.ROADMAP
					},

					// Create Map with Options
					map_<?php echo $map_id; ?> = new google.maps.Map( element, map_options ),
					marker = {
						position: location,
						map: map_<?php echo $map_id; ?>
					};

				// Create marker
				new google.maps.Marker( marker );
			}

			// Call if Google API exists
			if ( typeof google !== 'undefined' ) {
				sc_run_map_<?php echo $map_id; ?>();

				// Warn if Google API does not exist
			} else {
				console.warn( '[SCGM]: Check your Google API Key' );
			}
		</script>
		<?php
		echo ob_get_clean();
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

		return $event->location;
	}

	/**
	 * Verify the Google Maps API key.
	 *
	 * @since 3.5.0
	 *
	 * @param string $api_key                 API key.
	 * @param string $default_location_string Default location string.
	 *
	 * @return array
	 */
	public function verify_api_key( $api_key, $default_location_string = 'New York, NY' ) {

		$verification_response = [
			'success' => false,
			'code'    => null,
			'message' => null,
		];

		// Escape the default location string.
		$default_location_string = esc_attr( $default_location_string );

		$url = add_query_arg(
			[
				'address' => urlencode( $default_location_string ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
				'sensor'  => 'false',
				'key'     => $api_key,
			],
			'https://maps.googleapis.com/maps/api/geocode/json'
		);

		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			$verification_response['code']    = 'wp_error';
			$verification_response['message'] = esc_html__( 'An error occurred while connecting to Google Maps Services.', 'sugar-calendar-lite' );

			return $verification_response;
		}

		$data = wp_remote_retrieve_body( $response );

		if ( is_wp_error( $data ) ) {
			$verification_response['code']    = 'wp_error';
			$verification_response['message'] = esc_html__( 'An error occurred while verifying your API key.', 'sugar-calendar-lite' );

			return $verification_response;
		}

		if ( $response['response']['code'] !== 200 ) {
			$verification_response['code']    = 'http_error';
			$verification_response['message'] = esc_html__(
				'Unable to contact Google API service.',
				'sugar-calendar-lite'
			);

			return $verification_response;
		}

		$data = json_decode( $data );

		if ( empty( $data ) ) {

			$verification_response['code']    = 'empty_response';
			$verification_response['message'] = esc_html__( 'Something went wrong while verifying your API key.', 'sugar-calendar-lite' );

			return $verification_response;

		} elseif ( isset( $data->status ) && $data->status !== 'OK' ) {

			$verification_response['code']    = esc_html( $data->status );
			$verification_response['message'] = esc_html( $data->error_message );

			return $verification_response;
		}

		// If we get here, the API key is valid.
		$verification_response['success'] = true;
		$verification_response['code']    = 'api_key_valid';
		$verification_response['message'] = esc_html__( 'API key verified.', 'sugar-calendar-lite' );

		return $verification_response;
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

		// Not cached, or forcing a refresh.
		if ( ! empty( $force_refresh ) || ( $coordinates === false ) ) {

			$url = add_query_arg(
				[
					'address' => urlencode( $address ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
					'sensor'  => 'false',
					'key'     => $this->get_api_key(),
				],
				'https://maps.googleapis.com/maps/api/geocode/json'
			);

			$response = wp_remote_get( $url );

			if ( is_wp_error( $response ) ) {
				return $response->get_error_message();
			}

			$data = wp_remote_retrieve_body( $response );

			if ( is_wp_error( $data ) ) {
				return $response->get_error_message();
			}

			if ( $response['response']['code'] !== 200 ) {
				return esc_html__(
					'Unable to contact Google API service.',
					'sugar-calendar-lite'
				);
			}

			$data = json_decode( $data );

			if ( empty( $data ) ) {
				return esc_html__( 'Something went wrong while retrieving your map.', 'sugar-calendar-lite' );
			} elseif ( isset( $data->status ) && $data->status !== 'OK' ) {

				switch ( $data->status ) {
					// phpcs:disable WPForms.Formatting.Switch.RemoveEmptyLineBefore, WPForms.Formatting.Switch.AddEmptyLineBefore, WPForms.Formatting.EmptyLineBeforeReturn.AddEmptyLineBeforeReturnStatement
					case 'ZERO_RESULTS':
						return esc_html__( 'No location found for the entered address.', 'sugar-calendar-lite' );

					case 'INVALID_REQUEST':
						return esc_html__( 'Invalid request. Did you enter an address?', 'sugar-calendar-lite' );

					case 'REQUEST_DENIED':
						return sprintf(
						/* translators: %s: error message. */
							esc_html__(
								'Request Denied. %s',
								'sugar-calendar-lite'
							),
							esc_js( $data->error_message )
						);
					// phpcs:enable WPForms.Formatting.Switch.RemoveEmptyLineBefore, WPForms.Formatting.Switch.AddEmptyLineBefore, WPForms.Formatting.EmptyLineBeforeReturn.AddEmptyLineBeforeReturnStatement
				}
			}

			$coordinates = $data->results[0]->geometry->location;

			$cache_value['lat']     = $coordinates->lat;
			$cache_value['lng']     = $coordinates->lng;
			$cache_value['address'] = (string) $data->results[0]->formatted_address;

			set_transient( $address_hash, $cache_value, MONTH_IN_SECONDS );

			$data = $cache_value;
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
