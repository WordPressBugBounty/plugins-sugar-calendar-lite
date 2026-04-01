import { Fragment, useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import SmartTagItem from '../SmartTagItem';
import styles from './index.module.scss';

/**
 * Smart Tags Dropdown Component
 *
 * @since {VERSION}
 *
 * @param {Object}   props
 * @param {Function} props.onTagClick  - Tag click handler
 * @param {Function} props.onClose     - Close dropdown handler
 * @param {Array}    props.categories  - Array of { label: string, tags: Array<{value, label}> }
 */
const SmartTagsDropdown = ( {
	onTagClick,
	onClose,
	categories,
} ) => {
	// Internal search state
	const [ searchQuery, setSearchQuery ] = useState( '' );
	const dropdownRef = useRef( null );

	// Filter tags based on search query
	const filterTags = ( tags ) => {
		if ( ! searchQuery ) {
			return tags;
		}
		return tags.filter(
			( tag ) =>
				tag.label.toLowerCase().includes( searchQuery.toLowerCase() ) ||
				tag.value.toLowerCase().includes( searchQuery.toLowerCase() )
		);
	};

	// Get filtered categories
	const filteredCategories = categories.map( ( category ) => ( {
		...category,
		tags: filterTags( category.tags ),
	} ) ).filter( ( category ) => category.tags.length > 0 );

	// Handle click outside to close dropdown - memoized to ensure stable reference
	const handleClickOutside = useCallback(
		( event ) => {
			if (
				event.target.closest( '.mce-btn, button[class*="mce-"]' ) ||
				event.target.closest( '[class*="sugar-bookings-smart-tags"]' )
			) {
				return;
			}

			if (
				dropdownRef.current &&
				! dropdownRef.current.contains( event.target )
			) {
				onClose();
			}
		},
		[ onClose ]
	);

	useEffect( () => {
		// Use capture to ensure we catch the event before other handlers
		document.addEventListener( 'mousedown', handleClickOutside, true );

		return () => {
			document.removeEventListener(
				'mousedown',
				handleClickOutside,
				true
			);
		};
	}, [ handleClickOutside ] );

	return (
		<div
			ref={ dropdownRef }
			className={ `${ styles.dropdown } ${ styles.openDown }` }
		>
			<div className={ styles.title }>Smart Tags</div>

			<div className={ styles.searchContainer }>
				<input
					type="search"
					className={ styles.searchInput }
					placeholder={ __(
						'Search',
						'sugar-calendar-lite'
					) }
					value={ searchQuery }
					onChange={ ( e ) => setSearchQuery( e.target.value ) }
				/>
				{ searchQuery && (
					<i
						className={ `fa fa-times-circle ${ styles.searchClose }` }
						aria-hidden="true"
						onClick={ () => setSearchQuery( '' ) }
					/>
				) }
			</div>

			<ul className={ styles.list }>
				{ filteredCategories.map( ( category ) => (
					<Fragment key={ category.label }>
						<li data-value="0">
							<span className={ styles.heading }>
								{ category.label }
							</span>
						</li>
						{ category.tags.map( ( tag ) => (
							<SmartTagItem
								key={ tag.value }
								tag={ tag }
								onClick={ onTagClick }
							/>
						) ) }
					</Fragment>
				) ) }

				{ filteredCategories.length === 0 && (
					<div className={ styles.noResults }>
						{ __(
							'Sorry, no results found',
							'sugar-calendar-lite'
						) }
					</div>
				) }
			</ul>
		</div>
	);
};

export default SmartTagsDropdown;
