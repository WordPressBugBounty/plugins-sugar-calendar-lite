import { useSelect } from '@wordpress/data';

// Default query for tags.
const defaultTagQuery = { per_page: -1 };

/**
 * Common function to get the arguments for `getEntityRecords`.
 *
 * @since 3.5.0
 * @param {Object} query Query parameters for fetching tags.
 * @returns {Array} Arguments for `getEntityRecords`.
 */
const getTagQueryArgs = ( slug, query = defaultTagQuery ) => {
	return ['taxonomy', slug, query];
};

/**
 * Fetch tags using the WordPress REST API.
 *
 * @since 3.5.0
 * @param {Object} query Query parameters for fetching tags.
 * @returns {Array|null} The list of tags or null if not loaded.
 */
export const useTags = ( slug, query = defaultTagQuery) => {
	return useSelect((select) => {
		return select('core').getEntityRecords(...getTagQueryArgs( slug, query ));
	});
};

/**
 * Check if the request to get tags is resolved.
 *
 * @since 3.5.0
 * @param {Object} query Query parameters for fetching tags.
 * @returns {boolean} True if the resolution is finished, false otherwise.
 */
export const hasFinishedGettingTags = ( slug, query = defaultTagQuery ) => {
	return useSelect((select) => {
		return select('core/data').hasFinishedResolution('core', 'getEntityRecords', getTagQueryArgs( slug, query ));
	});
};

/**
 * Handle tag selection changes.
 *
 * @since 3.5.0
 * @param {Array|null} selectedOptions The selected tag options.
 * @param {Function} setAttributes The block's `setAttributes` function.
 */
export const onChangeTags = (selectedOptions, setAttributes) => {
	const selectedTagIds = selectedOptions ? selectedOptions.map(option => option.value) : [];
	setAttributes({ tags: selectedTagIds });
};
