import clsx from 'clsx';
import PropTypes from 'prop-types';

import styles from './index.module.scss';

/**
 * FieldDescription component for form field descriptions.
 *
 * @since {VERSION}
 *
 * @param {Object}          props
 * @param {React.ReactNode} props.children    - The description content.
 * @param {string}          [props.className] - Additional class name.
 */
function FieldDescription( { children, className } ) {
	return (
		<div className={ clsx( styles.description, className ) }>
			{ children }
		</div>
	);
}

FieldDescription.propTypes = {
	children: PropTypes.node.isRequired,
	className: PropTypes.string,
};

export default FieldDescription;
