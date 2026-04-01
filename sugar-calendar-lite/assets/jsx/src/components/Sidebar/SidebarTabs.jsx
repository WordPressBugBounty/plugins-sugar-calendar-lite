import clsx from 'clsx';
import PropTypes from 'prop-types';

import styles from './index.module.scss';

/**
 * Sidebar tabs navigation component.
 *
 * @since {VERSION}
 *
 * @param {Object}   props
 * @param {Array}    props.tabs        Array of tab objects with id and label.
 * @param {string}   props.activeTab   Currently active tab id.
 * @param {Function} props.onTabChange Callback when tab is changed.
 * @return {JSX.Element} SidebarTabs component.
 */
export default function SidebarTabs( { tabs, activeTab, onTabChange } ) {
	return (
		<nav className={ styles.sidebarTabs }>
			{ tabs.map( ( tab, index ) => (
				// eslint-disable-next-line jsx-a11y/click-events-have-key-events
				<div
					key={ tab.id }
					className={ clsx( styles.sidebarTab, {
						[ styles.sidebarTabActive ]: activeTab === tab.id,
					} ) }
					role="tab"
					tabIndex={ index }
					onClick={ () => onTabChange( tab.id ) }
				>
					{ tab.label }
				</div>
			) ) }
		</nav>
	);
}

SidebarTabs.propTypes = {
	tabs: PropTypes.arrayOf(
		PropTypes.shape( {
			id: PropTypes.string.isRequired,
			label: PropTypes.node.isRequired,
		} )
	).isRequired,
	activeTab: PropTypes.string.isRequired,
	onTabChange: PropTypes.func.isRequired,
};
