const defaultItems = [
	'event_name',
	'event_date',
	'event_location',
];

export default ( { setProps, onKeyDown, items } ) => {
	const suggestionItems = items || defaultItems;

	return [ {
		char: '{',
		command: ( { editor, range, props } ) => {
			// Increase range.to by one when the next node is of type "text"
			// and starts with a space character.
			const nodeAfter = editor.view.state.selection.$to.nodeAfter;
			const overrideSpace = nodeAfter?.text?.startsWith( ' ' );

			if ( overrideSpace ) {
				range.to += 1;
			}

			editor
				.chain()
				.focus()
				.insertContentAt( range, [
					{
						type: 'mention',
						attrs: { ...props, mentionSuggestionChar: '{' },
					},
					{
						type: 'text',
						// Use non-breaking space so the browser doesn't collapse
						// it as insignificant trailing whitespace.
						text: '\u00A0',
					},
				] )
				.run();

			editor.view.dom.ownerDocument.defaultView
				?.getSelection()
				?.collapseToEnd();
		},
		items: ( { query } ) => {
			return suggestionItems
				.filter( ( item ) => item.toLowerCase().startsWith( query.toLowerCase() ) )
				.slice( 0, 5 );
		},
		render: () => {
			return {
				onStart: ( props ) => {
					setProps( {
						...props,
						visible: true,
					} );
				},

				onUpdate( props ) {
					setProps( {
						...props,
						visible: true,
					} );
				},

				onKeyDown( props ) {
					if ( props.event.key === 'Escape' ) {
						setProps( {
							...props,
							visible: false,
						} );

						return true;
					}

					return onKeyDown( props );
				},

				onExit( props ) {
					setProps( {
						...props,
						visible: false,
					} );

					return true;
				},
			};
		},
	} ];
};
