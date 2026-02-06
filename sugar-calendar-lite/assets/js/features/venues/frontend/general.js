'use strict';

const SCVenuesFrontendGeneral = window.SCVenuesFrontendGeneral || ( function( document, window, $ ) {

	const app = {

		/**
		 * Runtime variables.
		 *
		 * @since 3.10.0
		 */
		runtimeVars: {
			/**
			 * Geocoder instance.
			 *
			 * @since 3.10.0
			 */
			geocoder: null,

			/**
			 * DOM elements.
			 *
			 * @since 3.10.0
			 */
			doms: {
				$mapContainers: null,
			}
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

			app.cacheRuntimeVars();
			app.setupMaps();
		},

		/**
		 * Cache runtime variables.
		 *
		 * @since 3.10.0
		 */
		cacheRuntimeVars() {

			app.runtimeVars.doms.$mapContainers = $( '.sc_map_canvas' );
		},

		/**
		 * Setup the Google Maps.
		 *
		 * @since 3.10.0
		 */
		setupMaps() {

			if ( app.runtimeVars.doms.$mapContainers.length <= 0 ) {
				return;
			}

			app.runtimeVars.doms.$mapContainers.each( function() {
				const $mapCanvas = $( this );

				if ( $mapCanvas.data( 'loc' ) ) {
					app.geocode( $mapCanvas, $mapCanvas.data( 'loc' ) );
				} else if ( $mapCanvas.data( 'lat' ) && $mapCanvas.data( 'lng' ) ) {
					app.loadMap( $mapCanvas, $mapCanvas.data( 'lat' ), $mapCanvas.data( 'lng' ) );
				}
			} );
		},

		/**
		 * Get the coordinates of a location.
		 *
		 * @since 3.10.0
		 *
		 * @param {jQuery} $mapCanvas Map canvas.
		 * @param {string} address Location to get the coordinates.
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
		},

		/**
		 * Load the map.
		 *
		 * @since 3.10.0
		 *
		 * @param {jQuery} $mapCanvas Map canvas.
		 * @param {number} lat Latitude.
		 * @param {number} lng Longitude.
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
			} else if ( $mapCanvas.data( 'size' ) && $mapCanvas.data( 'size' ) === 'medium' ) {
				$mapCanvas.css( 'height', '200px' );
				$mapCanvas.css( 'width', '100%' );
			} else {
				$mapCanvas.css( 'height', '400px' );
			}
		}
	};

	return app;
}( document, window, jQuery ) );

/**
 * Google Maps callback.
 *
 * @since 3.10.0
 */
function scGoogleMapsCB() {

	SCVenuesFrontendGeneral.init();
}
