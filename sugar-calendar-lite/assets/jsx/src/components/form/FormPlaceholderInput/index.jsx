/**
 * WordPress dependencies
 */
import { forwardRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * External dependencies
 */
import PropTypes from 'prop-types';
import { EditorContent, useEditor } from '@tiptap/react';
import Document from '@tiptap/extension-document';
import Paragraph from '@tiptap/extension-paragraph';
import Text from '@tiptap/extension-text';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import styles from './index.module.scss';
import Mention from '@tiptap/extension-mention';
import SuggestionList from './SuggestionList';
import { useCallback, useRef, useState } from 'react';
import useSuggestions from './useSuggestions';
import SmartTagsDropdown from '../WysiwygEditor/SmartTagsDropdown';
import iconTags from '../../../../../images/attendees-notifications/icon-tags.svg';

/**
 * FormPlaceholderInput component for text input fields with placeholder support.
 *
 * @since {VERSION}
 *
 * @param {Object}        props
 * @param {string|number} props.value              - Input value.
 * @param {boolean}       [props.required]         - Whether the field is required.
 * @param {string}        [props.className]        - Additional class name.
 * @param {Array}         [props.smartTagCategories] - Smart tag categories for the dropdown button.
 * @param {Object}        ref                      - Ref forwarded to the input element.
 */

const FormPlaceholderInput = forwardRef( function FormPlaceholderInput(
	{
		value,
		onChange,
		error = false,
		suggestions: suggestionItems,
		smartTagCategories,
	},
	ref
) {
	const [ props, setProps ] = useState( {} );
	const [ isDropdownOpen, setIsDropdownOpen ] = useState( false );
	const suggestionListRef = useRef();
	const editorInstanceRef = useRef( null );
	const suggestionCommandRef = useRef( null );

	const suggestions = useSuggestions( {
		setProps,
		onKeyDown: ( e ) => suggestionListRef.current?.onKeyDown( e ),
		items: suggestionItems,
	} );

	// When smartTagCategories is provided, replace the inline suggestion popup
	// with the SmartTagsDropdown. The { char still triggers the Mention extension,
	// but instead of rendering SuggestionList, it opens the SmartTagsDropdown.
	const smartTagSuggestions = smartTagCategories
		? [ {
			char: '{',
			command: ( { editor: ed, range, props: cmdProps } ) => {
				const nodeAfter = ed.view.state.selection.$to.nodeAfter;
				const overrideSpace = nodeAfter?.text?.startsWith( ' ' );

				if ( overrideSpace ) {
					range.to += 1;
				}

				ed.chain()
					.focus()
					.insertContentAt( range, [
						{
							type: 'mention',
							attrs: { ...cmdProps, mentionSuggestionChar: '{' },
						},
						{
							type: 'text',
							text: '\u00A0',
						},
					] )
					.run();

				ed.view.dom.ownerDocument.defaultView
					?.getSelection()
					?.collapseToEnd();
			},
			items: () => [],
			render: () => ( {
				onStart: ( renderProps ) => {
					suggestionCommandRef.current = renderProps.command;
					setIsDropdownOpen( true );
				},
				onUpdate: ( renderProps ) => {
					suggestionCommandRef.current = renderProps.command;
				},
				onKeyDown: ( { event } ) => {
					if ( event.key === 'Escape' ) {
						setIsDropdownOpen( false );
						return true;
					}
					return false;
				},
				onExit: () => {
					suggestionCommandRef.current = null;
					setIsDropdownOpen( false );
				},
			} ),
		} ]
		: suggestions;

	const extensions = [
		Document.extend( {
			content: 'block',
		} ),
		Paragraph,
		Text,
		Mention.configure( {
			HTMLAttributes: {
				class: 'mention',
			},
			suggestions: smartTagSuggestions,
		} ),
	];

	const editor = useEditor( {
		extensions,
		content: value,
		onBlur: () => setProps( {
			...props,
			visible: false,
		} ),
		onUpdate: ( { editor: instance } ) => onChange( instance.getText().replace( /\u00A0/g, ' ' ) ),
	} );

	editorInstanceRef.current = editor;

	const toggleDropdown = useCallback( () => {
		setIsDropdownOpen( ( prev ) => ! prev );
	}, [] );

	const closeDropdown = useCallback( () => {
		setIsDropdownOpen( false );
	}, [] );

	const handleSmartTagClick = useCallback( ( tagValue ) => {
		if ( suggestionCommandRef.current ) {
			// Suggestion system is active (user typed {) — use its command
			// to replace the { trigger with the mention node.
			suggestionCommandRef.current( { id: `${ tagValue }}` } );
		} else if ( editorInstanceRef.current ) {
			// Opened via button click — insert directly at cursor.
			editorInstanceRef.current
				.chain()
				.focus()
				.insertContent( [
					{
						type: 'mention',
						attrs: { id: `${ tagValue }}`, mentionSuggestionChar: '{' },
					},
					{
						type: 'text',
						text: '\u00A0',
					},
				] )
				.run();
		}
		setIsDropdownOpen( false );
	}, [] );

	return (
		<div className={ clsx( styles.inputWrapper, { [ styles.hasSmartTags ]: smartTagCategories } ) }>
			<EditorContent ref={ ref } editor={ editor } className={ clsx( styles.placeHolderInput ) } />
			{ ! smartTagCategories && (
				<SuggestionList { ...props } ref={ suggestionListRef } />
			) }
			{ smartTagCategories && (
				<>
					<button
						type="button"
						className={ styles.smartTagsButton }
						onClick={ toggleDropdown }
						title={ __( 'Smart Tags', 'sugar-calendar-lite' ) }
					>
						<img src={ iconTags } alt={ __( 'Smart Tags', 'sugar-calendar-lite' ) } />
					</button>
					{ isDropdownOpen && (
						<SmartTagsDropdown
							onTagClick={ handleSmartTagClick }
							onClose={ closeDropdown }
							categories={ smartTagCategories }
						/>
					) }
				</>
			) }
		</div>
	);
} );

FormPlaceholderInput.propTypes = {
	value: PropTypes.oneOfType( [ PropTypes.string, PropTypes.number ] ),
	onChange: PropTypes.func.isRequired,
	type: PropTypes.string,
	placeholder: PropTypes.string,
	required: PropTypes.bool,
	className: PropTypes.string,
	error: PropTypes.bool,
};

export default FormPlaceholderInput;
