/* globals jQuery, sugar_calendar_admin_settings */
( function ( $, settings ) {

	'use strict';

	const // Elements.
		$mapsApiForm = $( 'form.sugar-calendar-admin-content__settings-form' ),
		$apiKeyInput = $( '#sugar-calendar-setting-maps_google_api_key' ),
		$clonedForm = $mapsApiForm.clone();

	/**
	 * Submit maps settings.
	 *
	 * @since 3.5.0
	 *
	 * @param {Event} e Event object.
	 * @return {void}
	 */
	const submitMapsSettings = function ( e ) {

		const apiKey = $apiKeyInput.val();

		// If apiKey is empty, return.
		if ( ! apiKey ) {
			return;
		}

		e.preventDefault();

		$.ajax( {
			url: settings.ajax_url,
			method: 'POST',
			dataType: 'json',
			data: {
				task: 'verify_maps_api_key',
				api_key: apiKey,
			},
			success: function ( response ) {

				// If response.data.success is true, submit the form "normally".
				if ( response.data.success ) {

					// Set clone form api key.
					$clonedForm.find( '#sugar-calendar-setting-maps_google_api_key' ).val( apiKey );

					// Replace original form with cloned form.
					$mapsApiForm.replaceWith( $clonedForm );

					// Submit clone form.
					$clonedForm.find( 'button[name="sugar-calendar-submit"]' ).click();

					return;

				} else {

					// Show.
					$.alert( {
						title: false,
						content: response.data.message,
						icon: getIcon( 'exclamation-circle-solid-red.svg' ),
						type: 'red',
						buttons: {
							confirm: {
								text: settings.text.ok,
								btnClass: 'sugar-calendar-btn sugar-calendar-btn-lg sugar-calendar-btn-primary sugar-calendar-btn__box-color',
								keys: ['enter'],
							},
						},
					} );
				}
			},
		} );
	}
	$mapsApiForm.on( 'submit', submitMapsSettings );

	const getIcon = function ( icon ) {

		const iconPath = `${settings.plugin_url}assets/images/icons/${icon}"`
		const iconElement = `"></i><img src="${iconPath}" style="width: 46px; height: 46px;"><i class="`;

		return iconElement;
	}

} )( jQuery, sugar_calendar_admin_settings );
