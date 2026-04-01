import clsx from 'clsx';
import PropTypes from 'prop-types';

import styles from './index.module.scss';

/**
 * Sidebar footer component.
 *
 * @since {VERSION}
 *
 * @param {Object}          props
 * @param {React.ReactNode} props.children    Footer content.
 * @param {string}          [props.className] Additional CSS class names.
 * @return {JSX.Element} SidebarFooter component.
 */
export default function SidebarFooter( { children, className } ) {
	return (
		<footer className={ clsx( styles.sidebarFooter, className ) }>
			{ children }
		</footer>
	);
}

SidebarFooter.propTypes = {
	children: PropTypes.node,
	className: PropTypes.string,
};
