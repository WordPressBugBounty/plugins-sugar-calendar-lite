import { useSelect } from '@wordpress/data';

// Default query for speakers.
const defaultSpeakerQuery = { per_page: -1, status: 'publish' };

/**
 * Common function to get the arguments for `getEntityRecords`.
 *
 * @since 3.7.0
 * @param {Object} query Query parameters for fetching speakers.
 * @returns {Array} Arguments for `getEntityRecords`.
 */
const getSpeakerQueryArgs = (query = defaultSpeakerQuery) => {
	return ['postType', 'sc_speakers', query];
};

/**
 * Fetch speakers using the WordPress REST API.
 *
 * @since 3.7.0
 * @param {Object} query Query parameters for fetching speakers.
 * @returns {Array|null} The list of speakers or null if not loaded.
 */
export const useSpeakers = (query = defaultSpeakerQuery) => {
	return useSelect((select) => {
		return select('core').getEntityRecords(...getSpeakerQueryArgs(query));
	});
};

/**
 * Check if the request to get speakers is resolved.
 *
 * @since 3.7.0
 * @param {Object} query Query parameters for fetching speakers.
 * @returns {boolean} True if the resolution is finished, false otherwise.
 */
export const hasFinishedGettingSpeakers = (query = defaultSpeakerQuery) => {
	return useSelect((select) => {
		return select('core/data').hasFinishedResolution('core', 'getEntityRecords', getSpeakerQueryArgs(query));
	});
};

/**
 * Handle speaker selection changes.
 *
 * @since 3.7.0
 * @param {Array|null} selectedOptions The selected speaker options.
 * @param {Function} setAttributes The block's `setAttributes` function.
 */
export const onChangeSpeakers = (selectedOptions, setAttributes) => {
	const selectedSpeakerIds = selectedOptions ? selectedOptions.map(option => option.value) : [];
	setAttributes({ speakers: selectedSpeakerIds });
};
