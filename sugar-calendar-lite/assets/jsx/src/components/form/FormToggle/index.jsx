import { __ } from '@wordpress/i18n';
import clsx from 'clsx';
import { forwardRef } from 'react';

/**
 * FormToggle component for toggle fields.
 *
 * @since {VERSION}
 *
 * @param {Object}   props
 * @param {boolean}  props.value          - Toggle value.
 * @param {Function} props.onChange       - Change handler.
 * @param {string}   props.id             - Input ID.
 * @param {string}   props.name           - Input name.
 * @param {boolean}  props.disabled       - Whether the input is disabled.
 * @param {string}   props.toggleLabelOn  - Label for the on state.
 * @param {boolean}  props.hasLabel       - Whether the component has a label.
 * @param {string}   props.toggleLabelOff - Label for the off state.
 * @param {boolean}  [props.error]        - Error state.
 * @param {string}   [props.className]    - Additional class name.
 * @param {Object}   props.rest           - Rest of the props.
 * @param {Object}   ref                  - Forwarded ref.
 */
const FormToggle = forwardRef( function FormToggle(
	{
		value,
		onChange,
		id,
		name,
		hasLabel = false,
		toggleLabelOn = __( 'On', 'sugar-calendar-bookings-lite' ),
		toggleLabelOff = __( 'Off', 'sugar-calendar-bookings-lite' ),
		error = false,
		className,
		...rest
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
		<div
			className={ clsx(
				'sugar-calendar-bookings-toggle-control',
				className,
				{
					'sugar-calendar-bookings-toggle-control--error': error,
				}
			) }
		>
			<input
				ref={ ref }
				type="checkbox"
				id={ id }
				name={ name }
				checked={ value }
				onChange={ handleChange }
				{ ...rest }
			/>
			{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control */ }
			<label
				className="sugar-calendar-bookings-toggle-control-icon"
				htmlFor={ id }
			></label>
			{ hasLabel && (
				<>
					<label
						className="sugar-calendar-bookings-toggle-control-status sugar-calendar-bookings-toggle-control-status-on"
						htmlFor={ id }
					>
						{ toggleLabelOn }
					</label>
					<label
						className="sugar-calendar-bookings-toggle-control-status sugar-calendar-bookings-toggle-control-status-off"
						htmlFor={ id }
					>
						{ toggleLabelOff }
					</label>
				</>
			) }
		</div>
	);
} );

export default FormToggle;
