/**
 * External dependencies
 */
import clsx from 'clsx';
import { AnimatePresence, motion } from 'framer-motion';
import PropTypes from 'prop-types';

/**
 * Internal dependencies
 */
import { ReactComponent as BackIcon } from '../../../../images/icons/arrow-left.svg';
import { ReactComponent as CloseIcon } from '../../../../images/icons/times.svg';
import styles from './index.module.scss';
import SidebarContent from './SidebarContent';
import SidebarFooter from './SidebarFooter';
import SidebarLayout from './SidebarLayout';
import SidebarTabs from './SidebarTabs';

/**
 * Sidebar component.
 *
 * @since {VERSION}
 *
 * @param {Object}          props            Component props.
 * @param {string}          props.title      Sidebar title.
 * @param {React.ReactNode} props.children   Sidebar content.
 * @param {boolean}         [props.nested]   If true, sidebar is nested.
 * @param {Function}        [props.onClose]  Close handler.
 * @param {boolean}         [props.isOpen]   If true, sidebar is open.
 * @param {string}          [props.type]     Sidebar type.
 * @param {Function}        [props.onExited] Exited handler.
 *
 * @return {JSX.Element} Sidebar component.
 */
function Sidebar( {
	title,
	children,
	nested = false,
	onClose,
	isOpen = true,
	onExited,
	type,
} ) {
	const handleClose = () => {
		if ( onClose ) {
			onClose();
		}
	};

	return (
		<AnimatePresence>
			{ isOpen && (
				<motion.aside
					className={ clsx( styles.sidebar, styles[ type ] ) }
					role="dialog"
					aria-modal="true"
					tabIndex={ -1 }
					initial={ { x: '100%' } }
					animate={ { x: 0 } }
					exit={ { x: '100%' } }
					transition={ { duration: 0.3, ease: 'easeInOut' } }
					onAnimationComplete={ ( definition ) => {
						if ( definition.x === '100%' && onExited ) {
							onExited();
						}
					} }
				>
					<header className={ styles.sidebarHeader }>
						{ nested && type !== 'secondary' ? (
							<>
								<button
									className={ styles.sidebarBack }
									onClick={ handleClose }
									aria-label="Back"
									type="button"
								>
									<BackIcon />
								</button>
								<span className={ styles.sidebarTitle }>
									{ title }
								</span>
							</>
						) : (
							<>
								<span className={ styles.sidebarTitle }>
									{ title }
								</span>
								<button
									className={ styles.sidebarClose }
									onClick={ handleClose }
									aria-label="Close sidebar"
									type="button"
								>
									<CloseIcon />
								</button>
							</>
						) }
					</header>
					<div className={ styles.sidebarBody }>{ children }</div>
				</motion.aside>
			) }
		</AnimatePresence>
	);
}

Sidebar.Layout = SidebarLayout;
Sidebar.Tabs = SidebarTabs;
Sidebar.Content = SidebarContent;
Sidebar.Footer = SidebarFooter;

Sidebar.propTypes = {
	title: PropTypes.string,
	children: PropTypes.node,
	nested: PropTypes.bool,
	onClose: PropTypes.func,
	isOpen: PropTypes.bool,
	onExited: PropTypes.func,
};

export default Sidebar;
