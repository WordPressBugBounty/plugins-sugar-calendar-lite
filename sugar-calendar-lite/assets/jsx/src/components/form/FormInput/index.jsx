/**
 * WordPress dependencies
 */
import { forwardRef } from '@wordpress/element';
/**
 * External dependencies
 */
import clsx from 'clsx';
import PropTypes from 'prop-types';

/**
 * Internal dependencies
 */
import styles from './index.module.scss';

/**
 * FormInput component for text input fields.
 *
 * @since {VERSION}
 *
 * @param {Object}        props
 * @param {string|number} props.value         - Input value.
 * @param {Function}      props.onChange      - Change handler.
 * @param {string}        [props.type]        - Input type.
 * @param {string}        [props.placeholder] - Placeholder text.
 * @param {boolean}       [props.required]    - Whether the field is required.
 * @param {string}        [props.className]   - Additional class name.
 * @param {boolean}       [props.error]       - Error state.
 * @param {Object}        ref                 - Ref forwarded to the input element.
 */

const FormInput = forwardRef( function FormInput(
	{
		value,
		onChange,
		type = 'text',
		placeholder = '',
		required = false,
		className,
		error = false,
		...rest
	},
	ref
) {
	let handleChange = null;

	if ( type === 'number' && ! required ) {
		handleChange = function( e ) {
			onChange( e.target.value === '' ? null : e.target.value );
		};
	}

	return (
		<input
			ref={ ref }
			type={ type }
			value={ value ?? '' }
			onChange={ handleChange || onChange }
			placeholder={ placeholder }
			required={ required }
			className={ clsx( styles.input, className, {
				[ styles.error ]: error,
			} ) }
			{ ...rest }
		/>
	);
} );

FormInput.propTypes = {
	value: PropTypes.oneOfType( [ PropTypes.string, PropTypes.number ] ),
	onChange: PropTypes.func.isRequired,
	type: PropTypes.string,
	placeholder: PropTypes.string,
	required: PropTypes.bool,
	className: PropTypes.string,
	error: PropTypes.bool,
};

export default FormInput;
