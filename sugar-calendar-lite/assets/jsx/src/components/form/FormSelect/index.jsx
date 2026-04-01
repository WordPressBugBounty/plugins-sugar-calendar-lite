import { forwardRef } from '@wordpress/element';
import clsx from 'clsx';
import Select, { components } from 'react-select';
import Creatable from 'react-select/creatable';
import AsyncSelect from 'react-select/async';

import { ReactComponent as IndicatorIcon } from '../../../../../images/icons/chevron-down.svg';
import { ReactComponent as RemoveIcon } from '../../../../../images/icons/times-circle.svg';
import styles from './index.module.scss';

/**
 * DropdownIndicator component for the select dropdown arrow icon.
 *
 * @since {VERSION}
 *
 * @param {Object} props
 * @return {JSX.Element} DropdownIndicator component.
 */
const DropdownIndicator = ( props ) => (
	<components.DropdownIndicator { ...props }>
		<IndicatorIcon />
	</components.DropdownIndicator>
);

/**
 * MultiValueRemove component for the select dropdown remove icon.
 *
 * @since {VERSION}
 *
 * @param {Object} props
 * @return {JSX.Element} MultiValueRemove component.
 */
const MultiValueRemove = ( props ) => (
	<components.MultiValueRemove { ...props }>
		<RemoveIcon />
	</components.MultiValueRemove>
);

/**
 * FormSelect component for select dropdowns.
 *
 * @since {VERSION}
 *
 * @param {Object}   props
 * @param {any}      props.value         - Selected value(s).
 * @param {Function} props.onChange      - Change handler.
 * @param {Array}    props.options       - Options for the select.
 * @param {boolean}  [props.isMulti]     - Multi-select mode.
 * @param {string}   [props.placeholder] - Placeholder text.
 * @param {string}   [props.className]   - Additional class name.
 * @param {Object}   [props.components]  - Custom components for react-select.
 * @param {boolean}  [props.error]       - Error state.
 * @param {boolean}  [props.allowCreate] - Whether the component allows creating new options.
 * @param {boolean}  [props.async]       - Whether the component uses async loading.
 */
const FormSelect = forwardRef( function FormSelect(
	{
		value,
		onChange,
		options,
		isMulti = false,
		placeholder = '',
		className,
		components: customComponents = {},
		error = false,
		allowCreate = false,
		async = false,
		simpleValue = true,
		...rest
	},
	ref
) {
	let handleChange = null;
	let selectedOption = null;

	// AsyncSelect is incompatible with simpleValue mode because it doesn't
	// use a static options prop for value resolution. Force simpleValue off.
	const effectiveSimpleValue = async ? false : simpleValue;

	if ( effectiveSimpleValue ) {
		const flatOptions = options?.[ 0 ]?.options
			? options.flatMap( ( group ) => group.options )
			: options;

		if ( isMulti ) {
			selectedOption = flatOptions.filter(
				( option ) => value && value.includes( option.value )
			);
		} else {
			selectedOption = flatOptions.find(
				( option ) => option.value === value
			);
		}

		if ( onChange ) {
			handleChange = ( option ) => {
				if ( isMulti ) {
					onChange(
						option.map( ( o ) => o.value ),
						option
					);
				} else {
					onChange( option.value, option );
				}
			};
		}
	} else {
		handleChange = onChange;
		selectedOption = value;
	}

	let SelectComponent;

	if ( allowCreate ) {
		SelectComponent = Creatable;
	} else if ( async ) {
		SelectComponent = AsyncSelect;
	} else {
		SelectComponent = Select;
	}

	return (
		<SelectComponent
			ref={ ref }
			value={ selectedOption }
			onChange={ handleChange }
			options={ options }
			isMulti={ isMulti }
			placeholder={ placeholder }
			className={ clsx( styles.select, className, {
				[ styles.error ]: error,
			} ) }
			classNamePrefix="select"
			components={ {
				DropdownIndicator,
				IndicatorSeparator: () => null,
				ClearIndicator: () => null,
				MultiValueRemove,
				...customComponents,
			} }
			{ ...rest }
		/>
	);
} );

export default FormSelect;
