import { forwardRef } from '@wordpress/element';
import clsx from 'clsx';

import styles from './index.module.scss';

/**
 * FormTextarea component for textarea fields.
 *
 * @since {VERSION}
 *
 * @param {Object}   props
 * @param {string}   props.value         - Textarea value.
 * @param {Function} props.onChange      - Change handler.
 * @param {string}   [props.placeholder] - Placeholder text.
 * @param {boolean}  [props.required]    - Whether the field is required.
 * @param {string}   [props.className]   - Additional class name.
 * @param {number}   [props.rows]        - Number of rows.
 * @param {boolean}  [props.error]       - Error state.
 */
const FormTextarea = forwardRef( function FormTextarea(
	{
		value,
		onChange,
		placeholder = '',
		required = false,
		className,
		rows = 4,
		error = false,
		...rest
	},
	ref
) {
	return (
		<textarea
			ref={ ref }
			value={ value }
			onChange={ onChange }
			placeholder={ placeholder }
			required={ required }
			className={ clsx( styles.input, className, {
				[ styles.error ]: error,
			} ) }
			rows={ rows }
			{ ...rest }
		/>
	);
} );

export default FormTextarea;
