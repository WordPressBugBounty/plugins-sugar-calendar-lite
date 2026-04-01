import clsx from 'clsx';
import PropTypes from 'prop-types';
import { Controller } from 'react-hook-form';

import FieldDescription from '../FieldDescription';
import FieldErrorMessage from '../FieldErrorMessage';
import FieldLabel from '../FieldLabel';
import styles from './index.module.scss';

/**
 * Field component for form fields with label, error, and integrated RHF Controller.
 *
 * @since {VERSION}
 *
 * @param {Object}              props
 * @param {string}              [props.label]       - The label text/content.
 * @param {string}              [props.htmlFor]     - The input's id for accessibility.
 * @param {boolean}             [props.required]    - Whether the field is required.
 * @param {string}              [props.className]   - Additional class for the wrapper.
 * @param {string}              [props.name]        - Field name for RHF.
 * @param {Object}              [props.rules]       - RHF validation rules.
 * @param {string}              [props.description] - The description text/content.
 * @param {React.ComponentType} [props.as]          - The input component to render (e.g., FormInput, FormSelect).
 * @param {Object}              [props.inputProps]  - Props to pass to the input component.
 * @param {React.ReactNode}     [props.children]    - Fallback children (for legacy/manual usage).
 */
export default function Field( {
	label,
	htmlFor,
	required,
	className,
	name,
	rules,
	description,
	as: InputComponent,
	inputProps = {},
	children,
} ) {
	// If using React Hook Form integration.
	const hasRHF = name && InputComponent;
	const fieldId = inputProps.id || name;

	return (
		<div className={ clsx( styles.field, className ) }>
			{ ( label || description ) && (
				<div className={ styles.fieldHeader }>
					{ label && (
						<FieldLabel
							htmlFor={ htmlFor || fieldId }
							required={ required }
							className={ styles.label }
						>
							{ label }
						</FieldLabel>
					) }
					{ description && (
						<FieldDescription className={ styles.description }>
							{ description }
						</FieldDescription>
					) }
				</div>
			) }

			<div className={ styles.fieldBody }>
				{ hasRHF ? (
					<>
						<Controller
							name={ name }
							rules={ rules }
							render={ ( { field, fieldState } ) => (
								<>
									<InputComponent
										id={ fieldId }
										{ ...field }
										{ ...inputProps }
										error={ !! fieldState.error }
									/>
								</>
							) }
						/>
						<FieldErrorMessage name={ name } />
					</>
				) : (
					children
				) }
			</div>
		</div>
	);
}

Field.propTypes = {
	label: PropTypes.node,
	htmlFor: PropTypes.string,
	required: PropTypes.bool,
	className: PropTypes.string,
	name: PropTypes.string,
	rules: PropTypes.object,
	description: PropTypes.node,
	as: PropTypes.elementType,
	inputProps: PropTypes.object,
	children: PropTypes.node,
};
