/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { InspectorControls, useBlockProps, PanelColorSettings } from '@wordpress/block-editor';

import {
	PanelBody,
	ToggleControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalHeading as Heading
} from '@wordpress/components';

import ServerSideRender from '@wordpress/server-side-render';

import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

import Select from '../../../../node_modules/react-select';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit( { attributes, setAttributes, clientId } ) {

	const { blockId } = attributes;

	useEffect( () => {
		if ( ! blockId ) {
			setAttributes( { blockId: clientId } );
		}
	}, [] );

	const calendarQuery = {
		per_page: -1
	};

	// Request the calendars.
	const calendars = useSelect( ( select ) => {
		return select( 'core' ).getEntityRecords( 'taxonomy', 'sc_event_category', calendarQuery );
	});

	// Is the request to get the calendars loading resolved?
	const hasFinishedGettingCalendars = useSelect( ( select ) => {
		return select( 'core/data' ).hasFinishedResolution( 'core', 'getEntityRecords', [ 'taxonomy', 'sc_event_category', calendarQuery ] );
	});

	const onChangeCalendars = (selectedOptions) => {
		const selectedCalendarIds = selectedOptions ? selectedOptions.map(option => option.value) : [];
		setAttributes({ calendars: selectedCalendarIds });
	}

	const onChangeDisplay = ( display ) => {
		setAttributes( { display: display } );
	};

	const onAllowUserChangeDisplay = ( allowUserChangeDisplay ) => {
		setAttributes( { allowUserChangeDisplay: allowUserChangeDisplay } );
	}

	const onShowFeaturedImages = ( showFeaturedImages ) => {
		setAttributes( { showFeaturedImages: showFeaturedImages } );
	};

	const onShowDescriptions = ( showDescriptions ) => {
		setAttributes( { showDescriptions: showDescriptions } );
	};

	const onChangeAccentColor = ( accentColor ) => {
		setAttributes( { accentColor: accentColor } );
	};

	const onChangeLinksColor = ( linksColor ) => {
		setAttributes( { linksColor: linksColor } );
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'sugar-calendar-event-list-block' ) }>

					{
						hasFinishedGettingCalendars &&
						calendars &&
						calendars.length > 1 &&
						<Heading
							level={3}>
							Calendars
						</Heading>
					}

					{
						hasFinishedGettingCalendars &&
						calendars &&
						calendars.length > 1 &&
						<Select
							className="sugar-calendar-event-list-block__calendars"
							classNamePrefix="sc-calendar-event-list-block-select"
							isMulti
							options={
								calendars.map( ( calendar ) => {
									return {
										value: calendar.id,
										label: calendar.name
									};
								} )
							}
							onChange={onChangeCalendars}
							value={attributes.calendars ? attributes.calendars.map( ( calendarId ) => {
								const calendar = calendars.find( ( calendar ) => calendar.id === calendarId );
								return {
									value: calendar.id,
									label: calendar.name
								};
							} ) : []}
						/>
					}

					<ToggleGroupControl
						onChange={ onChangeDisplay }
						label={ __( 'Display', 'sugar-calendar-block' ) }
						value={ attributes.display }
						isBlock>
						<ToggleGroupControlOption value="list" label={ __( 'List', 'sugar-calendar-event-list-block' ) } />
						<ToggleGroupControlOption value="grid" label={ __( 'Grid', 'sugar-calendar-event-list-block' ) } />
						<ToggleGroupControlOption value="plain" label={ __( 'Plain', 'sugar-calendar-event-list-block' ) } />
					</ToggleGroupControl>

					<ToggleControl
						label={ __( 'Allow Users to Change Display', 'sugar-calendar-event-list-block' ) }
						checked={ attributes.allowUserChangeDisplay }
						onChange={ onAllowUserChangeDisplay }
					/>

					<ToggleControl
						label={ __( 'Show Featured Images', 'sugar-calendar-event-list-block' ) }
						checked={ attributes.showFeaturedImages }
						onChange={ onShowFeaturedImages }
					/>

					<ToggleControl
						label={ __( 'Show Descriptions', 'sugar-calendar-event-list-block' ) }
						checked={ attributes.showDescriptions }
						onChange={ onShowDescriptions }
					/>

					<Heading
						level={3}>
						{ __( 'Colors', 'sugar-calendar-event-list-block' ) }
					</Heading>

					<PanelColorSettings
						__experimentalIsRenderedInSidebar
						showTitle={ false }
						className="sugar-calendar-event-list-block__colors"
						colorSettings={ [
							{
								value: attributes.accentColor,
								onChange: onChangeAccentColor,
								label: __( 'Accent', 'sugar-calendar-event-list-block' )
							},
							{
								value: attributes.linksColor,
								onChange: onChangeLinksColor,
								label: __( 'Links', 'sugar-calendar-event-list-block' )
							},
						] }
					/>

				</PanelBody>
			</InspectorControls>

			<div {...useBlockProps()}>
				<ServerSideRender
					attributes={ attributes }
					key="sugar-calendar-event-list-block-server-side-renderer"
					block="sugar-calendar/event-list-block"
				/>
			</div>
		</>
	);
}
