import clsx from 'clsx';
import PropTypes from 'prop-types';

import styles from './index.module.scss';

/**
 * FieldLabel component for form field labels.
 *
 * @since {VERSION}
 *
 * @param {Object}          props
 * @param {string}          props.htmlFor     - The id of the input this label is for.
 * @param {React.ReactNode} props.children    - The label content.
 * @param {string}          [props.className] - Additional class name.
 * @param {boolean}         [props.required]  - Whether the field is required.
 */
function FieldLabel( { htmlFor, children, className, required = false } ) {
	return (
		<label
			htmlFor={ htmlFor }
			className={ clsx( styles.label, className ) }
		>
			{ children }
			{ required && (
				<span className={ styles.required } aria-hidden="true">
					*
				</span>
			) }
		</label>
	);
}

FieldLabel.propTypes = {
	htmlFor: PropTypes.string,
	children: PropTypes.node.isRequired,
	className: PropTypes.string,
	required: PropTypes.bool,
};

export default FieldLabel;
