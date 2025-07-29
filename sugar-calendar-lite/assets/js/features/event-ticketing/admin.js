jQuery( document ).ready( function( $ ) {

	// Use event delegation to handle both existing and dynamically added checkboxes
	$( document ).on( 'change', '.sugar-calendar-metabox__field-row--ticket_limit_capacity input[type="checkbox"]', function( e ) {

		$( e.target )
			.parents( '.sugar-calendar-metabox__field-row--ticket_limit_capacity' )
			.toggleClass(
				'sugar-calendar-metabox__field-row--ticket_limit_capacity-enabled',
				$( this ).is( ':checked' )
			)
			.toggleClass(
				'sugar-calendar-metabox__field-row--ticket_limit_capacity-disabled',
				$( this ).is( ':not(:checked)' )
			);
	});

	// Force quantity field to a minimum of 1 only on focusout.
	$( document ).on( 'focusout', '.sugar-calendar-metabox__field-row--ticket_quantity input[type="number"]', function() {
		if ( !$(this).val() || $(this).val() < 1 ) {
			$(this).val( 1 );
		}
	});
} );
