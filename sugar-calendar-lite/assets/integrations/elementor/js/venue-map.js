'use strict';

const SCElementorEditorVenueMap = window.SCElementorEditorVenueMap || ( function( document, window, $ ) {

	const app = {

		/**
		 * Runtime variables.
		 *
		 * @since 3.10.0
		 */
		runtimeVars: {
			/**
			 * Google Map instance.
			 *
			 * @since 3.10.0
			 */
			map: null,

			/**
			 * Geocoder instance.
			 *
			 * @since 3.10.0
			 */
			geocoder: null,
		},

		/**
		 * Start the engine.
		 *
		 * @since 3.10.0
		 */
		init() {

			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 3.10.0
		 */
		ready() {

			window.elementorFrontend.hooks.addAction(
				'frontend/element_ready/widget',
				function( $scope ) {

					const $widget_type = $scope.data( 'widget_type' );

					if (
						$widget_type === 'sugar-calendar-event-venue-map.default' ||
						$widget_type === 'sugar-calendar-event-location-map.default'
					) {
						app.setupVenueMapWidget( $scope );
					}
				}
			)
		},

		/**
		 * Setup the venue map widget.
		 *
		 * @since 3.10.0
		 *
		 * @param {object} $scope 
		 */
		setupVenueMapWidget( $scope ) {

			const $mapContainers = $scope.find( '.sc_map_canvas' );

			if ( $mapContainers && $mapContainers.length > 0 ) {
				$mapContainers.each( function() {
					app.setupMap( $( this ) );
				} );
			}
		},

		/**
		 * Setup the Google Maps.
		 *
		 * @since 3.10.0
		 *
		 * @param {jQuery} $mapCanvas Map canvas.
		 */
		setupMap( $mapCanvas ) {

			if ( $mapCanvas.data( 'loc' ) ) {
				app.geocode( $mapCanvas, $mapCanvas.data( 'loc' ) );
				return;
			}

			if (
				$mapCanvas.data( 'lat' ) &&
				$mapCanvas.data( 'lng' )
			) {
				app.loadMap( $mapCanvas, $mapCanvas.data( 'lat' ), $mapCanvas.data( 'lng' ) );
			}
		},

		/**
		 * Load the map.
		 *
		 * @since 3.10.0
		 *
		 * @param {jQuery} $mapCanvas Map canvas.
		 * @param {number} lat        Latitude.
		 * @param {number} lng        Longitude.
		 */
		loadMap( $mapCanvas, lat, lng ) {

			const map = new google.maps.Map( $mapCanvas[0], {
				zoom: 15,
				center: { lat: lat, lng: lng },
				mapTypeId: google.maps.MapTypeId.ROADMAP
			});

			// Add marker.
			new google.maps.Marker({
				position: { lat: lat, lng: lng },
				map: map
			});

			if ( $mapCanvas.data( 'height' ) ) {
				$mapCanvas.css( 'height', $mapCanvas.data( 'height' ) );
			} else {
				$mapCanvas.css( 'height', '400px' );
			}

			$mapCanvas.data( 'map_loaded', 1 );
		},

		/**
		 * Get the coordinates of a location.
		 *
		 * @since 3.10.0
		 *
		 * @param {jQuery} $mapCanvas Map canvas.
		 * @param {string} address    Location to get the coordinates.
		 */
		geocode( $mapCanvas, address ) {

			if ( ! app.runtimeVars.geocoder ) {
				app.runtimeVars.geocoder = new google.maps.Geocoder();
			}

			app.runtimeVars.geocoder.geocode( { address: address } )
				.then( ( result ) => {
					const { results } = result;

					if (
						results.length <= 0 ||
						! results[0].geometry ||
						! results[0].geometry.location
					) {
						return;
					}

					const location = results[0].geometry.location;

					app.loadMap( $mapCanvas, location.lat(), location.lng() );

					app.maybeSaveCoordinates( address, location.lat(), location.lng(), $mapCanvas.data( 'nonce' ) );
				});
		},

		/**
		 * Save the coordinates of the address in the backend.
		 *
		 * @since 3.10.0
		 *
		 * @param {string} address Address.
		 * @param {number} lat     Latitude.
		 * @param {number} lng     Longitude.
		 * @param {string} nonce   Nonce.
		 */
		maybeSaveCoordinates( address, lat, lng, nonce ) {

			if ( ! nonce ) {
				return;
			}

			$.post(
				sugar_calendar_venue_frontend_general.ajax_url,
				{
					action: 'sugar_calendar_venue_save_coordinates',
					nonce: nonce,
					address: address,
					lat: lat,
					lng: lng
				},
				function( response ) {}
			);
		}
	};

	return app;
}( document, window, jQuery ) );

SCElementorEditorVenueMap.init();
