/**
 * External dependencies
 */
import { forwardRef } from 'react';
import clsx from 'clsx';
import PropTypes from 'prop-types';

/**
 * Internal dependencies
 */
import styles from './index.module.scss';

/**
 * FormCheckbox component for toggle fields.
 *
 * @since {VERSION}
 *
 * @param {Object}   props
 * @param {boolean}  props.value          - Toggle value.
 * @param {Function} props.onChange       - Change handler.
 * @param {string}   props.id             - Input ID.
 * @param {string}   props.name           - Input name.
 * @param {boolean}  props.disabled       - Whether the input is disabled.
 * @param {boolean}  props.label       - Label.
 * @param {string}   props.className    - Additional class name.
 * @param {Object}   ref                  - Forwarded ref.
 */
const FormCheckbox = forwardRef( function FormCheckbox(
	{
		value,
		onChange,
		id,
		name,
		label,
		className,
		required = false,
		error = false,
		...props
	},
	ref
) {
	id = id || name;

	let handleChange;

	if ( onChange ) {
		handleChange = ( event ) => {
			onChange( event.target.checked );
		};
	}

	return (
		<div className={ styles.checkbox }>
			<input
				ref={ ref }
				type="checkbox"
				id={ id }
				name={ name }
				checked={ value }
				value="1"
				required={ required }
				onChange={ handleChange }
				className={ clsx( className, {
					[ styles.error ]: error,
				} ) }
				{ ...props }
			/>
			{ label && (
				<label htmlFor={ id }>
					{ label }
				</label>
			) }
		</div>
	);
} );

FormCheckbox.propTypes = {
	value: PropTypes.bool,
	onChange: PropTypes.func.isRequired,
	required: PropTypes.bool,
	className: PropTypes.string,
	error: PropTypes.bool,
};

export default FormCheckbox;
