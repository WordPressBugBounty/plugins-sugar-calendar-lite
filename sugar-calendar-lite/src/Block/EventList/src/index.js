/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * All files containing `style` keyword are bundled together. The code used
 * gets applied both to the front of your site and to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './style.scss';

/**
 * Internal dependencies
 */
import Edit from './edit';
import save from './save';
import metadata from './block.json';

const scIcon = (
	<svg width="18" height="19" viewBox="0 0 18 19" fill="none" xmlns="http://www.w3.org/2000/svg">
		<path fill-rule="evenodd" clip-rule="evenodd"
			  d="M4.03233 0.926845C4.03233 0.698208 4.13626 0.469571 4.30254 0.303289C4.48961 0.137007 4.71824 0.0330811 4.96767 0.0330811C5.21709 0.0330811 5.46651 0.137007 5.63279 0.303289C5.79908 0.469571 5.903 0.677423 5.903 0.926845L12.097 0.926845C12.097 0.698208 12.2009 0.469571 12.3672 0.303289C12.5335 0.137007 12.7829 0.0330811 13.0323 0.0330811C13.2818 0.0330811 13.5312 0.137007 13.6975 0.303289C13.8637 0.469571 13.9677 0.677423 13.9677 0.926845L14.2794 0.926845C16.3372 0.926845 18 2.58966 18 4.6474L18 14.2917C18 16.3495 16.3372 18.0123 14.2794 18.0123L3.72055 18.0123C1.66282 18.0123 0 16.3495 0 14.2917L0 4.6474C0 2.58966 1.66282 0.926845 3.72055 0.926845L4.03233 0.926845ZM15.7136 13.4188C15.7136 14.0631 15.4642 14.6659 15.0069 15.1232C14.5497 15.5804 13.9469 15.8298 13.3025 15.8298L4.69746 15.8298C3.36721 15.8298 2.28637 14.749 2.28637 13.4188V12.9823C2.28637 12.9823 2.34873 12.8368 2.41109 12.8368L11.1409 12.8368C11.6813 12.8368 12.097 12.4211 12.097 11.8807C12.097 11.361 11.6605 10.9245 11.1409 10.9245L3.36721 10.9245C3.07621 10.9245 2.806 10.8206 2.59815 10.6128C2.3903 10.4049 2.28637 10.1347 2.28637 9.86449L2.28637 4.81368C2.28637 4.35641 2.47344 3.91992 2.78522 3.60814C3.097 3.29636 3.53349 3.10929 3.99076 3.10929C3.99076 3.10929 3.99076 3.10929 4.01155 3.10929C4.01155 3.10929 4.01155 3.10929 4.01155 3.13008C4.01155 3.58735 4.38568 3.96149 4.84296 3.96149H5.07159C5.52887 3.96149 5.903 3.58735 5.903 3.13008C5.903 3.13008 5.903 3.08851 5.94457 3.08851L12.0554 3.08851C12.0554 3.08851 12.097 3.08851 12.097 3.13008C12.097 3.58735 12.4711 3.96149 12.9284 3.96149H13.157C13.6143 3.96149 13.9885 3.58735 13.9885 3.13008V3.10929C13.9885 3.10929 13.9885 3.10929 14.0092 3.10929C14.9654 3.10929 15.7136 3.87835 15.7136 4.81368L15.7136 5.95687C15.7136 5.95687 15.6513 6.10237 15.5889 6.10237L6.87991 6.10237C6.36028 6.10237 5.92379 6.51807 5.92379 7.05849C5.92379 7.57812 6.36028 8.01461 6.87991 8.01461L14.6536 8.01461C14.9446 8.01461 15.2148 8.11853 15.4226 8.32638C15.6305 8.53424 15.7344 8.80444 15.7344 9.07465V13.4188H15.7136Z"
			  fill="#1E1E1E"/>
	</svg>
);

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
registerBlockType( metadata.name, {
	/**
	 * @see ./edit.js
	 */
	edit: Edit,

	/**
	 * @see ./save.js
	 */
	save,

	icon: scIcon,
} );
