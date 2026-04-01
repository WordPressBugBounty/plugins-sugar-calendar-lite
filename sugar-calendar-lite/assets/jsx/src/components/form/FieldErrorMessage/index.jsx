import { ErrorMessage } from '@hookform/error-message';
import clsx from 'clsx';
import PropTypes from 'prop-types';

import styles from './index.module.scss';

/**
 * FieldErrorMessage component for displaying form field errors.
 *
 * @since {VERSION}
 *
 * @param {Object} props
 * @param {string} props.name        - Field name.
 * @param {string} [props.className] - Additional class name.
 */
function FieldErrorMessage( { name, className } ) {
	return (
		<ErrorMessage
			name={ name }
			className={ clsx( styles.errorMessage, className ) }
			as="span"
		/>
	);
}

FieldErrorMessage.propTypes = {
	name: PropTypes.string.isRequired,
	className: PropTypes.string,
};

export default FieldErrorMessage;
