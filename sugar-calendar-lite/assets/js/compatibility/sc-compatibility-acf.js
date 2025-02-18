/**
 * Sugar Calendar ACF Compatibility.
 *
 * Ensure that the Sugar Calendar and ACF datepicker styling stays independent of each other.
 *
 * @since 3.5.1
 */
jQuery( document ).ready( function ( $ ) {

	const // Register the parent element.
		datePickerElement = $( '#ui-datepicker-div' ),
		acfWrapper = datePickerElement.parent( '.acf-ui-datepicker' );

	// If not acfWrapper, return.
	if ( ! acfWrapper.length ) {
		return;
	}

	const // Save parent class.
		parentId = acfWrapper.attr( 'class' ),
		switchId = 'sugar-calendar-acf-compat';

	// Switch the datepicker class to SC.
	const switchDatepickerSc = function () {

		$( acfWrapper ).attr( 'class', switchId );

		$( datePickerElement )
			.removeClass( 'ui-datepicker' )
			.addClass( 'sugar-calendar-datepicker' );
	};

	// Switch the datepicker class to ACF.
	const switchDatepickerAcf = function () {

		$( acfWrapper ).attr( 'class', parentId );

		$( datePickerElement )
			.removeClass( 'sugar-calendar-datepicker' )
			.addClass( 'ui-datepicker' );
	};

	// SC DatePicker listeners.
	$( '#start_date' ).on( 'click', switchDatepickerSc );
	$( '#end_date' ).on( 'click', switchDatepickerSc );
	$( '#recurrence_end_date' ).on( 'click', switchDatepickerSc );

	// ACF DatePicker listeners.
	$( '.acf-date-picker input.hasDatepicker' ).on( 'click', switchDatepickerAcf );
} );
