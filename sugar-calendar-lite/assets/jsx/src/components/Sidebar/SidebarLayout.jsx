import clsx from 'clsx';
import PropTypes from 'prop-types';

import styles from './index.module.scss';

/**
 * Main layout wrapper for sidebar content.
 *
 * @since {VERSION}
 *
 * @param {Object}          props
 * @param {React.ReactNode} props.children    Sidebar content.
 * @param {string}          [props.className] Additional CSS class names.
 * @return {JSX.Element} SidebarLayout component.
 */
export default function SidebarLayout( { children, className } ) {
	return (
		<div className={ clsx( styles.sidebarContainer, className ) }>
			{ children }
		</div>
	);
}

SidebarLayout.propTypes = {
	children: PropTypes.node.isRequired,
	className: PropTypes.string,
};
