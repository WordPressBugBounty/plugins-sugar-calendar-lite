jQuery(document).ready(function ($) {

	const // Search UI selectors.
		$tagsSearchInput = $( '#tag-search-input' )
		$tagsSubmitButton = $( '#search-submit' );

	// Set search placeholder text.
	if ( $tagsSearchInput.length ) {
		$tagsSearchInput.attr( 'placeholder', sugarCalendarAdminTags.searchTagsPlaceholder );
	}

	// Reset search button text.
	if ( $tagsSubmitButton.length ) {
		$tagsSubmitButton.attr( 'value', sugarCalendarAdminTags.searchTagsSubmit );
	}
});
