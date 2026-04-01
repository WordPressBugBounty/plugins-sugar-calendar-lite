/**
 * WordPress dependencies
 */
import {
	forwardRef,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
/**
 * External dependencies
 */
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import iconTags from '../../../../../images/attendees-notifications/icon-tags.svg';
import { SMART_TAGS } from './data/constants';
import styles from './index.module.scss';
import SmartTagsDropdown from './SmartTagsDropdown';

/**
 * WysiwygEditor component that wraps WordPress TinyMCE editor.
 *
 * @since {VERSION}
 *
 * @param {Object}   props
 * @param {string}   props.value            - Editor content value.
 * @param {Function} props.onChange         - Change handler for content updates.
 * @param {string}   [props.placeholder]    - Placeholder text.
 * @param {boolean}  [props.required]       - Whether the field is required.
 * @param {string}   [props.className]      - Additional class name.
 * @param {number}   [props.rows]           - Number of rows for the editor.
 * @param {boolean}  [props.error]          - Error state.
 * @param {Object}   [props.editorSettings] - Custom TinyMCE settings.
 * @param {string}   [props.id]             - Unique ID for the editor instance.
 */
const WysiwygEditor = forwardRef( function WysiwygEditor(
	{
		value = '',
		onChange,
		placeholder = '',
		required = false,
		className,
		rows = 10,
		error = false,
		editorSettings = {},
		id,
		smartTagCategories,
		...rest
	},
	ref
) {
	const editorRef = useRef( null );
	const editorInstanceRef = useRef( null );
	const isInitializedRef = useRef( false );

	// Smart Tags dropdown state
	const [ isDropdownOpen, setIsDropdownOpen ] = useState( false );

	// Helper functions
	const toggleDropdown = () => {
		setIsDropdownOpen( ( prevState ) => ! prevState );
	};
	const closeDropdown = () => setIsDropdownOpen( false );

	// Build categories for SmartTagsDropdown — use prop or fallback to Bookings constants.
	const categories = smartTagCategories || [
		{ label: 'Service Fields', tags: SMART_TAGS.SERVICE_FIELDS },
		{ label: 'General', tags: SMART_TAGS.GENERAL },
	];

	const handleSmartTagClick = ( tagValue ) => {
		if ( editorInstanceRef.current ) {
			editorInstanceRef.current.insertContent( ` {${ tagValue }} ` );
		}
		closeDropdown();
	};

	// Generate unique ID if not provided
	const editorId = useMemo(
		() =>
			id ||
			`wysiwyg-editor-${ Math.random().toString( 36 ).substr( 2, 9 ) }`,
		[ id ]
	);

	// Default TinyMCE settings
	const defaultSettings = useMemo(
		() => ( {
			selector: `#${ editorId }`,
			height: rows * 20,
			menubar: false,
			toolbar:
				'formatselect bold italic bullist numlist blockquote alignleft aligncenter alignright link smarttags',
			plugins: 'lists link',
			link_context_toolbar: false,
			link_title: false,
			link_quicklink: false,
			formats: {
				alignleft: [
					{
						selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li',
						styles: { textAlign: 'left' },
						deep: false,
						remove: 'none',
					},
				],
				aligncenter: [
					{
						selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li',
						styles: { textAlign: 'center' },
						deep: false,
						remove: 'none',
					},
				],
				alignright: [
					{
						selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li',
						styles: { textAlign: 'right' },
						deep: false,
						remove: 'none',
					},
				],
			},
			content_style:
				'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; font-size: 14px; }',
			placeholder,
			relative_urls: false,
			remove_script_host: false,
			convert_urls: false,
			branding: false,
			...editorSettings,
		} ),
		[ editorId, rows, placeholder, editorSettings ]
	);

	// Initialize TinyMCE editor
	useEffect( () => {
		// Function to initialize TinyMCE
		const initTinyMCE = () => {
			// Ensure WordPress TinyMCE is available
			if ( ! window.tinyMCE ) {
				return;
			}

			// Check if editor already exists and remove it
			try {
				const existingEditor = window.tinyMCE.get( editorId );
				if ( existingEditor ) {
					window.tinyMCE.remove( `#${ editorId }` );
				}
			} catch ( initError ) {
				// Editor doesn't exist, continue
			}

			// Don't initialize if already done
			if ( isInitializedRef.current ) {
				return;
			}

			// Initialize the editor
			window.tinyMCE.init( {
				...defaultSettings,
				setup: ( editor ) => {
					editorInstanceRef.current = editor;

					editor.addButton( 'smarttags', {
						text: '',
						tooltip: __(
							'Smart Tags',
							'sugar-calendar-lite'
						),
						icon: false,
						image: iconTags,
						classes: 'sugar-bookings-smart-tags-mce-button',
						onclick: toggleDropdown,
					} );

					// Handle content changes
					editor.on( 'change keyup', () => {
						const content = editor.getContent();
						if ( onChange ) {
							onChange( content );
						}
					} );

					// Handle blur events for validation
					editor.on( 'blur', () => {
						if ( required && ! editor.getContent().trim() ) {
							editor.getBody().style.borderColor = '#d63638';
						} else {
							editor.getBody().style.borderColor = '';
						}
					} );

					// Close dropdown when clicking inside editor
					editor.on( 'click', () => {
						closeDropdown();
					} );
				},
				init_instance_callback: ( editor ) => {
					// Set initial content after editor is fully initialized
					if ( value ) {
						editor.setContent( value );
					}
				},
			} );

			isInitializedRef.current = true;
		};

		// Try to initialize immediately
		initTinyMCE();

		// If TinyMCE is not available, wait for it to load
		if ( ! window.tinyMCE ) {
			let attempts = 0;
			const maxAttempts = 50; // 5 seconds max wait time

			const checkTinyMCE = () => {
				if ( window.tinyMCE ) {
					initTinyMCE();
				} else if ( attempts < maxAttempts ) {
					attempts++;
					// Check again in 100ms
					setTimeout( checkTinyMCE, 100 );
				} else {
					// Fallback to regular textarea if TinyMCE fails to load
					// eslint-disable-next-line no-console
					console.warn(
						'TinyMCE failed to load, falling back to textarea'
					);
				}
			};
			checkTinyMCE();
		}

		// Cleanup function
		return () => {
			if ( editorInstanceRef.current ) {
				try {
					window.tinyMCE.remove( `#${ editorId }` );
				} catch ( cleanupError ) {
					// Editor might already be removed
				}
				editorInstanceRef.current = null;
				isInitializedRef.current = false;
			}
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] ); // Only run on mount

	// Update content when value prop changes externally
	useEffect( () => {
		if ( editorInstanceRef.current && isInitializedRef.current ) {
			const currentContent = editorInstanceRef.current.getContent();
			if ( currentContent !== value ) {
				editorInstanceRef.current.setContent( value || '' );
			}
		}
	}, [ value ] );

	// Update error state
	useEffect( () => {
		if ( editorInstanceRef.current && isInitializedRef.current ) {
			const body = editorInstanceRef.current.getBody();
			if ( body ) {
				body.style.borderColor = error ? '#d63638' : '';
			}
		}
	}, [ error ] );

	return (
		<div className={ clsx( styles.wrapper, className ) }>
			<button className="button insert-media add_media">
				<span className="wp-media-buttons-icon"></span>
				{ __( 'Add Media', 'sugar-calendar-lite' ) }
			</button>
			<textarea
				ref={ ( element ) => {
					// Handle both ref forwarding and internal ref
					if ( typeof ref === 'function' ) {
						ref( element );
					} else if ( ref ) {
						ref.current = element;
					}
					editorRef.current = element;
				} }
				id={ editorId }
				className={ clsx( styles.editor, {
					[ styles.error ]: error,
				} ) }
				{ ...rest }
			/>

			{ /* Smart Tags Dropdown */ }
			{ isDropdownOpen && (
				<SmartTagsDropdown
					onTagClick={ handleSmartTagClick }
					onClose={ closeDropdown }
					categories={ categories }
				/>
			) }
		</div>
	);
} );

export default WysiwygEditor;
