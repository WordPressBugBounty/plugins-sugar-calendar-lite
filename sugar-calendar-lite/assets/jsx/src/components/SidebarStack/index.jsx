/**
 * External dependencies
 */
import { AnimatePresence, motion } from 'framer-motion';
import React from 'react';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { ReactComponent as ErrorIcon } from '../../../../images/icons/exclamation-circle.svg';
import useStore from '../../store';
import Loader from '../Loader';
import Sidebar from '../Sidebar';
import styles from './index.module.scss';

/**
 * SidebarStack component.
 *
 * @since {VERSION}
 *
 * @return {JSX.Element} SidebarStack component.
 */
export default function SidebarStack() {
	const { sidebarStack, closeSidebar, removeClosedSidebar } = useStore();

	const overlayVisible = sidebarStack.some( ( sidebar ) => sidebar.isOpen );

	return (
		<>
			<AnimatePresence>
				{ overlayVisible && (
					<motion.div
						className={ styles.sidebarOverlay }
						aria-hidden="true"
						initial={ { opacity: 0 } }
						animate={ { opacity: 1 } }
						exit={ { opacity: 0 } }
						transition={ { duration: 0.3, ease: 'easeInOut' } }
					/>
				) }
			</AnimatePresence>
			{ sidebarStack.length > 0 &&
				sidebarStack.map( ( sidebar, i ) => {
					const {
						id,
						isOpen,
						title,
						loading,
						error,
						content: ContentComponent,
						data,
						props = {},
						type,
					} = sidebar;

					let content = null;

					if ( loading ) {
						content = (
							<div className={ styles.sidebarLoader }>
								<Loader />
							</div>
						);
					} else if ( error ) {
						content = (
							<div className={ styles.sidebarError } role="alert">
								<ErrorIcon
									className={ styles.sidebarErrorIcon }
								/>
								<div className={ styles.sidebarErrorTitle }>
									{ __(
										'Heads up!',
										'sugar-calendar-bookings'
									) }
								</div>
								<div className={ styles.sidebarErrorMessage }>
									{ error }
								</div>
							</div>
						);
					} else if ( ContentComponent ) {
						content = React.createElement( ContentComponent, {
							data,
							...props,
						} );
					}

					return (
						<Sidebar
							key={ id }
							title={ title }
							isOpen={ isOpen }
							nested={ i > 0 }
							onClose={ () => closeSidebar( id ) }
							onExited={ removeClosedSidebar }
							type={ type }
						>
							{ content }
						</Sidebar>
					);
				} ) }
		</>
	);
}
