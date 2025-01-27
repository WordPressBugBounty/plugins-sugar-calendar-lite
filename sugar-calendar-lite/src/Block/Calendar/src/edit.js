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
	SelectControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalHeading as Heading
} from '@wordpress/components';

import ServerSideRender from '@wordpress/server-side-render';

import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

import Select from 'react-select';

import { useVenues, hasFinishedGettingVenues, onChangeVenues } from './../../Common/assets/js/venue';

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
			setAttributes( { calendarId: clientId } );
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

	const // Request the venues.
		venues = useVenues(),
		isVenuesResolved = hasFinishedGettingVenues();
		console.log( 'venues', venues );

	const onChangeDisplay = ( display ) => {
		setAttributes( { display: display } );
	};

	const onShowBlockHeader = ( showBlockHeader ) => {
		setAttributes( {
			showBlockHeader: showBlockHeader,
			allowUserChangeDisplay: showBlockHeader,
			showFilters: showBlockHeader,
			showSearch: showBlockHeader,
		} );
	};

	const onAllowUserChangeDisplay = ( allowUserChangeDisplay ) => {
		setAttributes( { allowUserChangeDisplay: allowUserChangeDisplay } );
	}

	const onShowFilters = ( showFilters ) => {
		setAttributes( { showFilters: showFilters } );
	};

	const onShowSearch = ( showSearch ) => {
		setAttributes( { showSearch: showSearch } );
	};

	const onChangeAccentColor = ( accentColor ) => {
		setAttributes( { accentColor: accentColor } );
	}

	const showCalendarFilterSection = hasFinishedGettingCalendars && calendars && calendars.length > 1;
	const showVenuesFilterSection = isVenuesResolved && venues && venues.length > 0;

	return (
		<>
			<InspectorControls>

				{
					( showCalendarFilterSection || showVenuesFilterSection ) &&
					(

						<PanelBody
							title={ __( 'Settings', 'sugar-calendar-block' ) }
							initialOpen={ true }
						>
							{
								showCalendarFilterSection &&
								<>
									<Heading
										level={3}>
										{ __( 'Calendars', 'sugar-calendar-block' ) }
									</Heading>
									<Select
										className="sugar-calendar-block__calendars"
										classNamePrefix="sc-calendar-block-select"
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
								</>
							}

							{
								showVenuesFilterSection &&
								<>
									<Heading
										level={3}>
										{ __( 'Venues', 'sugar-calendar-block' ) }
									</Heading>
									<Select
										className="sugar-calendar-block__venues"
										classNamePrefix="sc-venue-block-select"
										isMulti
										options={
											venues.map((venue) => {
												return {
													value: venue.id,
													label: venue.title.rendered,
												};
											})
										}
										onChange={(selectedOptions) => {
											onChangeVenues(selectedOptions, setAttributes);
										}}
										value={attributes.venues ? attributes.venues.map((venueId) => {
											const venue = venues.find((venue) => venue.id === venueId);
											return venue
												? {
													value: venue.id,
													label: venue.title.rendered,
												}
												: null;
										}) : []}
									/>
								</>
							}
						</PanelBody>
					)
				}

				<PanelBody
					title={ __( 'Display', 'sugar-calendar-block' ) }
					initialOpen={ true }
				>
					<ToggleGroupControl
						onChange={ onChangeDisplay }
						label={ __( 'Display Type', 'sugar-calendar-block' ) }
						value={ attributes.display }
						isBlock>
						<ToggleGroupControlOption value="month" label={ __( 'Month', 'sugar-calendar-block' ) } />
						<ToggleGroupControlOption value="week" label={ __( 'Week', 'sugar-calendar-block' ) } />
						<ToggleGroupControlOption value="day" label={ __( 'Day', 'sugar-calendar-block' ) } />
					</ToggleGroupControl>

					<ToggleControl
						label={ __( 'Show Block Header', 'sugar-calendar-event-list-block' ) }
						checked={ attributes.showBlockHeader }
						onChange={ onShowBlockHeader }
					/>

					<ToggleControl
						label={ __( 'Allow Users to Change Display', 'sugar-calendar-block' ) }
						checked={ attributes.allowUserChangeDisplay }
						onChange={ onAllowUserChangeDisplay }
					/>

					<ToggleControl
						label={ __( 'Show Filters', 'sugar-calendar-event-list-block' ) }
						checked={ attributes.showFilters }
						onChange={ onShowFilters }
						disabled={ ! attributes.showBlockHeader }
					/>

					<ToggleControl
						label={ __( 'Show Search', 'sugar-calendar-event-list-block' ) }
						checked={ attributes.showSearch }
						onChange={ onShowSearch }
						disabled={ ! attributes.showBlockHeader }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Styles', 'sugar-calendar-block' ) }
					initialOpen={ false }
				>
					<SelectControl
						label={ __( 'Appearance', 'sugar-calendar-block' ) }
						value={attributes.appearance}
						options={ [
							{ label: __( 'Light', 'sugar-calendar-block' ), value: 'light' },
							{ label: __( 'Dark', 'sugar-calendar-block' ), value: 'dark' },
						] }
						onChange={ ( mode ) => setAttributes( { appearance: mode } ) }
					/>

					<Heading
						level={3}>
						{ __( 'Colors', 'sugar-calendar-block' ) }
					</Heading>

					<PanelColorSettings
						__experimentalIsRenderedInSidebar
						showTitle={ false }
						className="sugar-calendar-block__colors"
						colorSettings={ [
							{
								value: attributes.accentColor,
								onChange: onChangeAccentColor,
								label: __( 'Accent', 'sugar-calendar-block' )
							},
						] }
					/>
				</PanelBody>
			</InspectorControls>

			<div {...useBlockProps()}>
				<ServerSideRender
					attributes={ attributes }
					key="sugar-calendar-block-server-side-renderer"
					block="sugar-calendar/block"
				/>
			</div>
		</>
	);
}
