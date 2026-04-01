/**
 * External dependencies
 */
import React, { forwardRef, useEffect, useImperativeHandle, useState } from 'react';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import styles from './index.module.scss';

export default forwardRef( ( { items = [], command, visible, ...props }, ref ) => {
	const [ selectedIndex, setSelectedIndex ] = useState( 0 );

	const selectItem = ( index ) => {
		const item = items[ index ];

		if ( item ) {
			command( { id: `${ item }}` } );
		}
	};

	const upHandler = () => {
		setSelectedIndex( ( selectedIndex + items.length - 1 ) % items.length );
	};

	const downHandler = () => {
		setSelectedIndex( ( selectedIndex + 1 ) % items.length );
	};

	const enterHandler = () => {
		selectItem( selectedIndex );
	};

	useEffect( () => setSelectedIndex( 0 ), [ items ] );

	useImperativeHandle( ref, () => ( {
		onKeyDown: ( { event } ) => {
			if ( event.key === 'ArrowUp' ) {
				upHandler();
				return true;
			}

			if ( event.key === 'ArrowDown' ) {
				downHandler();
				return true;
			}

			if ( event.key === 'Enter' ) {
				enterHandler();
				return true;
			}

			return false;
		},
	} ) );

	return visible ? (
		<div className={ styles.dropdownMenu }>
			{ items.length ? (
				items.map( ( item, index ) => (
					<button
						className={ clsx( index === selectedIndex ? 'is-selected' : '' ) }
						key={ index }
						onMouseDown={ ( event ) => {
							event.preventDefault();
							selectItem( index );
						} }
					>
						{ item }
					</button>
				) )
			) : (
				<div className="item">No result</div>
			) }
		</div>
	) : <></>;
} );
