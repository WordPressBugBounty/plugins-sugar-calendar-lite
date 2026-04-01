/**
 * External dependencies
 */
import { create } from 'zustand';

const useStore = create( ( set ) => ( {
	/**
	 * Sidebar stack.
	 *
	 * @since 3.11.0
	 */
	sidebarStack: [],

	/**
	 * Accumulated event IDs for the Notify Attendees sidebar.
	 *
	 * Persists across sidebar open/close cycles until page reload.
	 *
	 * @since 3.11.0
	 */
	accumulatedEventIds: [],

	/**
	 * Add event IDs to the accumulated list (deduplicating).
	 *
	 * @since 3.11.0
	 *
	 * @param {Array} eventIds - Event IDs to add.
	 */
	addAccumulatedEventIds: ( eventIds ) =>
		set( ( state ) => ( {
			accumulatedEventIds: [
				...new Set( [
					...state.accumulatedEventIds,
					...eventIds
						.map( ( id ) => parseInt( id, 10 ) )
						.filter( ( id ) => ! isNaN( id ) && id > 0 ),
				] ),
			],
		} ) ),

	/**
	 * Open a sidebar.
	 *
	 * Sidebar config:
	 * - title: string
	 * - loadContent: function - Async function to load the content.
	 * - loadData: function - Async function to load the data.
	 * - content: any - Content to pass to the sidebar.
	 * - data: any - Data to pass to the content.
	 *
	 * It's possible to load content and data from async functions or
	 * pass content and data directly. Also, static content and async
	 * data can be mixed.
	 *
	 * @example Load content and data from async functions.
	 * openSidebar( 'location-form', {
	 *   title: 'Add New Location',
	 *   loadContent: () => import( './LocationForm' ),
	 *   loadData: () => api.get( 'locations' ),
	 * } );
	 *
	 * @example Pass content and data directly.
	 * openSidebar( 'location-form', {
	 *   title: 'Add New Location',
	 *   content: () => <LocationForm />,
	 *   data: {
	 *     name: 'New Location',
	 *     address: '123 Main St',
	 *     city: 'Anytown',
	 *     state: 'CA',
	 *     zip: '12345',
	 *   },
	 * } );
	 *
	 * @since 3.11.0
	 *
	 * @param {string} id            - Unique sidebar id.
	 * @param {Object} sidebarConfig - Sidebar config.
	 */
	openSidebar: async ( id, sidebarConfig ) => {
		// Remove any sidebar with the same id, then push the new one (open immediately)
		set( ( state ) => {
			const filteredStack = state.sidebarStack.filter(
				( item ) => item.id !== id
			);
			return {
				sidebarStack: [
					...filteredStack,
					{
						...sidebarConfig,
						id,
						isOpen: true,
						loading:
							sidebarConfig.loadContent || sidebarConfig.loadData,
						error: null,
						data: sidebarConfig.data || null,
						content: sidebarConfig.content || null,
					},
				],
			};
		} );

		if ( ! sidebarConfig.loadContent && ! sidebarConfig.loadData ) {
			return;
		}

		try {
			const [ content, { data } ] = await Promise.all( [
				sidebarConfig.loadContent ?
					sidebarConfig
						.loadContent()
						.then( ( module ) => module.default || module ) :
					Promise.resolve( null ),
				sidebarConfig.loadData ?
					sidebarConfig.loadData() :
					Promise.resolve( null ),
			] );
			set( ( state ) => ( {
				sidebarStack: state.sidebarStack.map( ( item ) =>
					item.id === id ?
						{
							...item,
							loading: false,
							content: content || item.content,
							data: data || item.data,
						} :
						item
				),
			} ) );
		} catch ( error ) {
			set( ( state ) => ( {
				sidebarStack: state.sidebarStack.map( ( item ) =>
					item.id === id ? { ...item, loading: false, error } : item
				),
			} ) );
		}
	},

	/**
	 * Close a sidebar by id.
	 *
	 * @since 3.11.0
	 *
	 * @param {string} id - Sidebar id.
	 */
	closeSidebar: ( id ) =>
		set( ( state ) => {
			const idx = state.sidebarStack.findIndex(
				( item ) => item.id === id
			);
			if ( idx === -1 ) {
				return {};
			}
			// Set isOpen: false for the sidebar with id and all above it
			const newStack = state.sidebarStack.map( ( item, i ) =>
				i >= idx ? { ...item, isOpen: false } : item
			);
			return { sidebarStack: newStack };
		} ),

	/**
	 * Close all sidebars.
	 *
	 * @since 3.11.0
	 */
	closeAllSidebars: () =>
		set( ( state ) => ( {
			sidebarStack: state.sidebarStack.map( ( item ) => ( {
				...item,
				isOpen: false,
			} ) ),
		} ) ),

	/**
	 * Remove closed sidebar from the stack.
	 *
	 * This is required, since we can't remove sidebar from the stack
	 * immidiatelly beacuse of animation.
	 *
	 * @since 3.11.0
	 */
	removeClosedSidebar: () =>
		set( ( state ) => {
			const stack = [ ...state.sidebarStack ];
			while (
				stack.length > 0 &&
				stack[ stack.length - 1 ].isOpen === false
			) {
				stack.pop();
			}
			return { sidebarStack: stack };
		} ),
} ) );

export default useStore;
