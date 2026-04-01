import clsx from 'clsx';
import PropTypes from 'prop-types';

import styles from './index.module.scss';

/**
 * Sidebar content wrapper component.
 *
 * @since {VERSION}
 *
 * @param {Object}          props
 * @param {React.ReactNode} props.children    Content to be rendered.
 * @param {string}          [props.className] Additional CSS class names.
 * @return {JSX.Element} SidebarContent component.
 */
export default function SidebarContent( { children, className } ) {
	return (
		<div className={ clsx( styles.sidebarContent, className ) }>
			{ children }
		</div>
	);
}

SidebarContent.propTypes = {
	children: PropTypes.node.isRequired,
	className: PropTypes.string,
};
