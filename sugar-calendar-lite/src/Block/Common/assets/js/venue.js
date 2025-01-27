// ./../../Common/assets/js/venue.js
import { useSelect } from '@wordpress/data';

// Default query for venues.
const defaultVenueQuery = { per_page: -1, status: 'publish' };

/**
 * Common function to get the arguments for `getEntityRecords`.
 *
 * @since 3.5.0
 * @param {Object} query Query parameters for fetching venues.
 * @returns {Array} Arguments for `getEntityRecords`.
 */
const getVenueQueryArgs = (query = defaultVenueQuery) => {
	return ['postType', 'sugarcalendar_venue', query];
};

/**
 * Fetch venues using the WordPress REST API.
 *
 * @since 3.5.0
 * @param {Object} query Query parameters for fetching venues.
 * @returns {Array|null} The list of venues or null if not loaded.
 */
export const useVenues = (query = defaultVenueQuery) => {
	return useSelect((select) => {
		return select('core').getEntityRecords(...getVenueQueryArgs(query));
	});
};

/**
 * Check if the request to get venues is resolved.
 *
 * @since 3.5.0
 * @param {Object} query Query parameters for fetching venues.
 * @returns {boolean} True if the resolution is finished, false otherwise.
 */
export const hasFinishedGettingVenues = (query = defaultVenueQuery) => {
	return useSelect((select) => {
		return select('core/data').hasFinishedResolution('core', 'getEntityRecords', getVenueQueryArgs(query));
	});
};

/**
 * Handle venue selection changes.
 *
 * @since 3.5.0
 * @param {Array|null} selectedOptions The selected venue options.
 * @param {Function} setAttributes The block's `setAttributes` function.
 */
export const onChangeVenues = (selectedOptions, setAttributes) => {
	const selectedVenueIds = selectedOptions ? selectedOptions.map(option => option.value) : [];
	setAttributes({ venues: selectedVenueIds });
};
