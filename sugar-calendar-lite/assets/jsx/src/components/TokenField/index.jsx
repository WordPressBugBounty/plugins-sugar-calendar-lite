/**
 * WordPress dependencies
 */
import { FormTokenField } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
/**
 * External dependencies
 */
import clsx from 'clsx';
import PropTypes from 'prop-types';
import React from 'react';

/**
 * Internal dependencies
 */
import styles from './index.module.scss';

/**
 * Field wrapper component for consistent form field layout.
 *
 * @since {VERSION}
 *
 * @param {Object} props
 * @param {string} props.label - Field label text
 * @param {string} props.htmlFor - ID of the input element for accessibility
 * @param {boolean} props.required - Whether the field is required
 * @param {string} props.error - Error message to display
 * @param {React.Node} props.children - Field input element
 * @param {string} props.className - Additional CSS classes
 * @return {JSX.Element} Field wrapper component.
 */
function Field( { label, htmlFor, required, error, children, className } ) {
	return (
		<div className={ clsx( styles.field, className ) }>
			{ label && (
				<label
					htmlFor={ htmlFor }
					className={ clsx( styles.fieldLabel, { [ styles.fieldLabelRequired ]: required } ) }
				>
					{ label }
					{ required && <span className={ styles.required }>*</span> }
				</label>
			) }
			<div className={ styles.fieldInput }>
				{ children }
			</div>
			{ error && (
				<div className={ styles.fieldError } role="alert">
					{ error }
				</div>
			) }
		</div>
	);
}

/**
 * TokenField component.
 *
 * A field component that wraps WordPress FormTokenField for selecting/entering tokens.
 * Based on WordPress FormTokenField: https://developer.wordpress.org/block-editor/reference-guides/components/form-token-field/
 *
 * @since {VERSION}
 *
 * @param {Object} props
 * @param {string} props.label - Field label text
 * @param {string} props.htmlFor - ID of the input element for accessibility
 * @param {boolean} props.required - Whether the field is required
 * @param {string} props.error - Error message to display
 * @param {string} props.className - Additional CSS classes
 * @param {Array} props.value - Current token values
 * @param {Function} props.onChange - Callback when tokens change
 * @param {Array} props.suggestions - Array of suggested token values
 * @param {string} props.placeholder - Placeholder text for the input
 * @param {number} props.maxLength - Maximum number of tokens allowed
 * @param {boolean} props.disabled - Whether the field is disabled
 * @param {Function} props.displayTransform - Transform function for displaying tokens
 * @param {Function} props.saveTransform - Transform function for saving tokens
 * @param {boolean} props.tokenizeOnSpace - Whether to create tokens on space key
 * @param {number} props.maxSuggestions - Maximum number of suggestions to display
 * @param {Object} props.messages - Custom messages for screen readers
 * @return {JSX.Element} TokenField component.
 */
export default function TokenField( {
	label,
	htmlFor,
	required = false,
	error,
	className,
	value = [],
	onChange,
	suggestions = [],
	placeholder = __( 'Enter values...', 'sugar-calendar' ),
	maxLength,
	disabled = false,
	displayTransform,
	saveTransform,
	tokenizeOnSpace = true,
	maxSuggestions = 100,
	messages,
	...restProps
} ) {
	return (
		<Field
			label={ label }
			htmlFor={ htmlFor }
			required={ required }
			error={ error }
			className={ className }
		>
			<FormTokenField
				className={ styles.tokenField }
				value={ value }
				onChange={ onChange }
				suggestions={ suggestions }
				placeholder={ placeholder }
				maxLength={ maxLength }
				disabled={ disabled }
				displayTransform={ displayTransform }
				saveTransform={ saveTransform }
				tokenizeOnSpace={ tokenizeOnSpace }
				maxSuggestions={ maxSuggestions }
				messages={ messages }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				{ ...restProps }
			/>
		</Field>
	);
}

Field.propTypes = {
	label: PropTypes.string,
	htmlFor: PropTypes.string,
	required: PropTypes.bool,
	error: PropTypes.string,
	children: PropTypes.node.isRequired,
	className: PropTypes.string,
};

TokenField.propTypes = {
	label: PropTypes.string,
	htmlFor: PropTypes.string,
	required: PropTypes.bool,
	error: PropTypes.string,
	className: PropTypes.string,
	value: PropTypes.arrayOf( PropTypes.oneOfType( [ PropTypes.string, PropTypes.object ] ) ),
	onChange: PropTypes.func.isRequired,
	suggestions: PropTypes.arrayOf( PropTypes.string ),
	placeholder: PropTypes.string,
	maxLength: PropTypes.number,
	disabled: PropTypes.bool,
	displayTransform: PropTypes.func,
	saveTransform: PropTypes.func,
	tokenizeOnSpace: PropTypes.bool,
	maxSuggestions: PropTypes.number,
	messages: PropTypes.object,
};
