/* globals sugar_calendar_admin_email_notifications */

/**
 * Internal dependencies
 */
import Sidebar from '../../components/Sidebar';
/**
 * External dependencies
 */
import { FormProvider, useForm } from 'react-hook-form';
import yup from '../../utils/validation';
import $ from 'jquery';

import styles from './index.module.scss';
import { yupResolver } from '@hookform/resolvers/yup';
import FieldRow from '../../components/form/FieldRow';
import Field from '../../components/form/Field';
import FormSelect from '../../components/form/FormSelect';

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import FormCheckbox from '../../components/form/FormCheckbox';
import { useCallback, useEffect, useRef, useState } from 'react';
import FormInput from '../../components/form/FormInput';
import FormPlaceholderInput from '../../components/form/FormPlaceholderInput';
import WysiwygEditor from '../../components/form/WysiwygEditor';
import Button from '../../components/Button';
import { ReactComponent as SullieIcon } from '../../../../images/sullie.svg';

const NOTICE_CONFIG = {
	sending: {
		className: styles.noticeInfo,
		dashicon: 'dashicons-clock',
		getMessage: () => __( 'Sending emails to attendees...', 'sugar-calendar-lite' ),
	},
	queued: {
		className: styles.noticeSuccess,
		dashicon: 'dashicons-yes-alt',
		getMessage: ( result ) =>
			sprintf(
				__( 'Notifications are being sent in the background to %d recipients.', 'sugar-calendar-lite' ),
				result?.total || 0
			),
	},
	success: {
		className: styles.noticeSuccess,
		dashicon: 'dashicons-yes-alt',
		getMessage: ( result ) =>
			sprintf(
				__( 'Emails sent successfully to %d recipients.', 'sugar-calendar-lite' ),
				result?.total || 0
			),
	},
	warning: {
		className: styles.noticeWarning,
		dashicon: 'dashicons-warning',
		getMessage: ( result ) =>
			sprintf(
				__( 'Emails sent, but %1$d of %2$d could not be delivered.', 'sugar-calendar-lite' ),
				result?.failed || 0,
				result?.total || 0
			),
	},
	error: {
		className: styles.noticeError,
		icon: '\u2717',
		getMessage: () => __( 'Failed to send emails. Please try again.', 'sugar-calendar-lite' ),
	},
};

function NotifyNotice( { status, result } ) {
	const config = NOTICE_CONFIG[ status ];

	if ( ! config ) {
		return null;
	}

	return (
		<div className={ `${ styles.notice } ${ config.className }` }>
			{ config.dashicon
				? <span className={ `dashicons ${ config.dashicon } ${ styles.noticeIcon }` }></span>
				: <span className={ styles.noticeIcon }>{ config.icon }</span>
			}
			<span>{ config.getMessage( result ) }</span>
		</div>
	);
}

const schema = yup
	.object()
	.shape( {
		events: yup.array().of(
			yup.object().shape( {
				value: yup.number().integer().required(),
				label: yup.string().required(),
			} )
		).min( 1, __( 'Events field must have at least 1 item.', 'sugar-calendar-lite' ) ),
		add_custom_recipients: yup.boolean(),
		custom_recipients: yup.array().of( yup.string().email() ),
		from_email: yup.string().required( __( 'Send from Email field is required.', 'sugar-calendar-lite' ) ),
		from_name: yup.string().required( __( 'Send from Name field is required.', 'sugar-calendar-lite' ) ),
		email_subject: yup.string().required( __( 'Email Subject field is required.', 'sugar-calendar-lite' ) ),
		email_body: yup.string().required( __( 'Email Body field is required.', 'sugar-calendar-lite' ) ),
	} );

export default function NotifyForm( { data } ) {
	const [ isLoading, setIsLoading ] = useState( false );
	const [ customRecipients, setCustomRecipients ] = useState( [] );
	const [ attendeesCount, setAttendeesCount ] = useState( 0 );
	const [ filterRSVPAttendees, setFilterRSVPAttendees ] = useState( false );
	const [ sendStatus, setSendStatus ] = useState( 'idle' );
	const [ sendResult, setSendResult ] = useState( null );
	const [ showUpsell, setShowUpsell ] = useState( true );
	const [ defaultEventOptions, setDefaultEventOptions ] = useState( [] );
	const [ eventsMeta, setEventsMeta ] = useState( {} );
	const [ isLoadingMeta, setIsLoadingMeta ] = useState( false );

	// Ref to hold the latest eventsMeta so subscription callbacks avoid stale closures.
	const eventsMetaRef = useRef( eventsMeta );
	eventsMetaRef.current = eventsMeta;

	const isEventEditPage = sugar_calendar_admin_email_notifications?.is_event_edit_page;

	const ajaxUrl = sugar_calendar_admin_email_notifications?.ajax_url;

	const ajaxPost = ( task, params = {} ) =>
		$.post( ajaxUrl, { task, ...params } );

	const filterRSVPOptions = [
		{
			value: 'all',
			label: __( 'All', 'sugar-calendar-lite' ),
		},
		{
			value: 'going',
			label: __( 'Going', 'sugar-calendar-lite' ),
		},
		{
			value: 'not_going',
			label: __( 'Not Going', 'sugar-calendar-lite' ),
		},
	];

	// Read smart tags from PHP-localized data and transform for components.
	const smartTags = sugar_calendar_admin_email_notifications?.smart_tags;

	const smartTagCategories = smartTags
		? Object.values( smartTags ).map( ( group ) => ( {
			label: group.label,
			tags: group.tags.map( ( t ) => ( { value: t.value, label: t.description } ) ),
		} ) )
		: undefined;

	const smartTagSuggestions = smartTags
		? Object.values( smartTags ).flatMap( ( group ) =>
			group.tags.map( ( t ) => t.value )
		)
		: undefined;

	const defaultValues = {
		events: [],
		from_email: data?.from_email || '',
		from_name: data?.from_name || '',
		email_subject: '',
		add_custom_recipients: false,
		custom_recipients: data?.custom_recipients || [],
		filter_rsvp: 'going',
	};

	const form = useForm( {
		defaultValues,
		resolver: yupResolver( schema ),
		mode: 'onTouched',
	} );

	const {
		handleSubmit,
		setValue,
		getValues,
		watch,
	} = form;

	const addCustomRecipients = watch( 'add_custom_recipients' );
	const watchedEvents = watch( 'events' );

	const debounceTimerRef = useRef( null );

	// Clean up debounce timer on unmount.
	useEffect( () => {
		return () => {
			if ( debounceTimerRef.current ) {
				clearTimeout( debounceTimerRef.current );
			}
		};
	}, [] );

	const loadOptions = ( inputValue, callback ) => {
		if ( debounceTimerRef.current ) {
			clearTimeout( debounceTimerRef.current );
		}

		debounceTimerRef.current = setTimeout( () => {
			ajaxPost( 'email_notifications_search_events', {
				search: inputValue,
			} ).done( ( response ) => {
				if ( ! response?.success ) {
					callback( [] );
					return;
				}

				callback(
					response.data.events.map( ( e ) => ( {
						value: e.id,
						label: e.title,
					} ) )
				);
			} ).fail( () => {
				callback( [] );
			} );
		}, 300 );
	};

	const fetchEventMeta = useCallback( ( eventIds ) => {
		if ( ! eventIds.length ) {
			return;
		}

		setIsLoadingMeta( true );

		ajaxPost( 'email_notifications_get_event_meta', {
			event_ids: eventIds,
		} ).done( ( response ) => {
			if ( ! response?.success ) {
				return;
			}

			setEventsMeta( ( prev ) => {
				const next = { ...prev };

				// Intersect with current selection to handle race condition:
				// user may have deselected while request was in flight.
				const currentEvents = getValues( 'events' ) || [];
				const currentIds = currentEvents.map( ( e ) =>
					typeof e === 'object' ? e.value : e
				);

				response.data.events.forEach( ( event ) => {
					if ( currentIds.includes( event.id ) ) {
						next[ event.id ] = event;
					}
				} );

				return next;
			} );
		} ).always( () => {
			setIsLoadingMeta( false );
		} );
	}, [ getValues ] );

	const calculateAttendeesCount = ( formEvents, formAddCustomRecipients, formCustomRecipients, formFilterRsvp ) => {
		const selectedIds = ( formEvents || [] ).map( ( e ) =>
			typeof e === 'object' ? e.value : e
		);

		const meta = eventsMetaRef.current;
		let count = selectedIds
			.filter( ( id ) => meta[ id ] )
			.map( ( id ) => {
				const event = meta[ id ];
				let attendees = event.ticket_count;

				if ( formFilterRsvp === 'going' ) {
					attendees += event.rsvps_going_count;
				} else if ( formFilterRsvp === 'not_going' ) {
					attendees += event.rsvps_count - event.rsvps_going_count;
				} else {
					attendees += event.rsvps_count;
				}

				return attendees;
			} )
			.reduce( ( sum, attendees ) => sum + attendees, 0 );

		if ( formAddCustomRecipients ) {
			count += formCustomRecipients.length;
		}

		setAttendeesCount( count );
	};

	const updateFilterRSVPAttendees = ( events ) => {
		const selectedIds = ( events || [] ).map( ( e ) =>
			typeof e === 'object' ? e.value : e
		);

		const hasRsvp = selectedIds.some( ( id ) => eventsMetaRef.current[ id ]?.has_rsvp );

		setFilterRSVPAttendees( hasRsvp );
	};

	useEffect( () => {
		const preSelectedIds = data?.events || [];

		ajaxPost( 'email_notifications_search_events', {
			search: '',
			event_ids: preSelectedIds,
		} ).done( ( response ) => {
			if ( ! response?.success ) {
				return;
			}

			const options = response.data.events.map( ( e ) => ( {
				value: e.id,
				label: e.title,
			} ) );

			setDefaultEventOptions( options );

			// Set pre-selected events as full {value, label} objects.
			if ( preSelectedIds.length > 0 ) {
				const preSelectedOptions = options.filter( ( o ) =>
					preSelectedIds.includes( o.value )
				);

				if ( preSelectedOptions.length > 0 ) {
					setValue( 'events', preSelectedOptions, {
						shouldValidate: false,
					} );

					// Fetch metadata for pre-selected events.
					fetchEventMeta( preSelectedIds );
				}
			}
		} );
	}, [] );

	useEffect( () => {
		const unsubscribe = form.subscribe( {
			name: [
				'events',
				'add_custom_recipients',
				'custom_recipients',
				'filter_rsvp',
			],
			formState: {
				values: true,
			},
			callback: ( { values } ) => {
				const selectedEvents = values.events || [];
				const selectedIds = selectedEvents.map( ( e ) =>
					typeof e === 'object' ? e.value : e
				);

				// Fetch metadata for newly selected events.
				const newIds = selectedIds.filter( ( id ) => ! eventsMetaRef.current[ id ] );

				if ( newIds.length > 0 ) {
					fetchEventMeta( newIds );
				}

				// Remove deselected events from metadata.
				setEventsMeta( ( prev ) => {
					const next = {};

					selectedIds.forEach( ( id ) => {
						if ( prev[ id ] ) {
							next[ id ] = prev[ id ];
						}
					} );

					return next;
				} );

				// Recalculate counts and RSVP filter.
				calculateAttendeesCount(
					values.events,
					values.add_custom_recipients,
					values.custom_recipients,
					values.filter_rsvp
				);
				updateFilterRSVPAttendees( values.events );
			},
		} );

		return () => unsubscribe();
	}, [ form, fetchEventMeta ] );

	// Recalculate counts when eventsMeta changes (after async fetch completes).
	useEffect( () => {
		const values = getValues();

		calculateAttendeesCount(
			values.events,
			values.add_custom_recipients,
			values.custom_recipients,
			values.filter_rsvp
		);
		updateFilterRSVPAttendees( values.events );
	}, [ eventsMeta ] );

	const onCustomRecipientCreate = async ( inputValue ) => {
		const newRecipient = {
			label: inputValue,
			value: inputValue,
		};

		setCustomRecipients( ( prev ) => [ ...prev, newRecipient ] );

		const currentTags = getValues( 'custom_recipients' ) || [];

		setValue(
			'custom_recipients',
			[ ...currentTags, newRecipient.value ],
			{
				shouldValidate: true,
				shouldTouch: true,
			}
		);
	};

	const onEmailSubjectChange = ( value ) => {
		setValue(
			'email_subject',
			value,
			{
				shouldValidate: true,
				shouldTouch: true,
			}
		);
	};

	const onSubmit = async function( formData ) {
		setSendStatus( 'sending' );
		setSendResult( null );
		setIsLoading( true );

		// Extract event IDs from {value, label} objects.
		const submitData = {
			...formData,
			events: ( formData.events || [] ).map( ( e ) => e.value ),
		};

		$.post(
			ajaxUrl,
			{
				task: 'email_notifications_notify_attendees',
				data: submitData,
			}
		).done( ( response ) => {
			if ( ! response?.success ) {
				setSendStatus( 'error' );
			} else if ( response.data.job_id ) {
				// Background queue response.
				setSendStatus( 'queued' );
				setSendResult( { total: response.data.total } );
			} else if ( response.data.failed_count > 0 ) {
				// Synchronous fallback response.
				setSendStatus( 'warning' );
				setSendResult( { total: response.data.total, failed: response.data.failed_count } );
			} else {
				setSendStatus( 'success' );
				setSendResult( { total: response.data.total, failed: 0 } );
			}
		} ).fail( () => {
			setSendStatus( 'error' );
		} ).always( () => {
			setIsLoading( false );
		} );
	};

	const onDismissUpsell = ( e ) => {
		e.preventDefault();

		setShowUpsell( false );

		$.post(
			sugar_calendar_admin_email_notifications?.ajax_url,
			{
				task: 'email_notifications_dismiss_upsell',
				upsell_id: 'smtp_upsell',
			}
		);
	};

	const onPreview = ( e ) => {
		e.preventDefault();

		handleSubmit( () => {
			const emailSubject = getValues( 'email_subject' );
			const tinyMCEEditor = window.tinyMCE?.get( 'email_body' );
			const emailBody = tinyMCEEditor ? tinyMCEEditor.getContent() : getValues( 'email_body' );

			$.post(
				sugar_calendar_admin_email_notifications?.ajax_url,
				{
					task: 'email_notifications_store_preview_data',
					data: {
						email_subject: emailSubject,
						email_body: emailBody,
						events: ( getValues( 'events' ) || [] ).map( ( e ) => e.value ),
					},
				},
				( response ) => {
					if ( response?.success && response?.data?.preview_url ) {
						window.open( response.data.preview_url, '_blank' );
					}
				}
			);
		} )();
	};

	return (
		<Sidebar.Layout>
			<Sidebar.Content>
				<FormProvider { ...form }>
					<form className={ styles.notifyForm } onSubmit={ handleSubmit( onSubmit ) }
						autoComplete="off">
						<FieldRow>
							<Field
								name="events"
								label={ __( 'Select Event Attendees', 'sugar-calendar-lite' ) }
								htmlFor="events"
								required
								as={ FormSelect }
								inputProps={ {
									id: 'events',
									async: true,
									simpleValue: false,
									isMulti: true,
									closeMenuOnSelect: false,
									isDisabled: isEventEditPage,
									defaultOptions: defaultEventOptions,
									loadOptions: loadOptions,
									cacheOptions: true,
									placeholder: __( 'Type to search events...', 'sugar-calendar-lite' ),
									noOptionsMessage: () => __( 'No events found.', 'sugar-calendar-lite' ),
									loadingMessage: () => __( 'Searching events...', 'sugar-calendar-lite' ),
								} }
							/>
							<p>
								{ isEventEditPage
									? __( 'Attendees of this event will receive this email.', 'sugar-calendar-lite' )
									: __( 'Attendees from the events above will receive this email. Only events with attendees will be shown.', 'sugar-calendar-lite' )
								}
							</p>
						</FieldRow>

						{ filterRSVPAttendees &&
							<FieldRow>
								<Field
									name="filter_rsvp"
									label={ __( 'Filter RSVP Attendees', 'sugar-calendar-lite' ) }
									htmlFor="filter_rsvp"
									as={ FormSelect }
									inputProps={ {
										id: 'filter_rsvp',
										options: filterRSVPOptions,
									} }
								/>
								<p>
									{ __( 'Select which RSVP Attendees to send the notification.', 'sugar-calendar-lite' ) }
								</p>
							</FieldRow>
						}

						<FieldRow>
							<Field
								name="add_custom_recipients"
								htmlFor="add_custom_recipients"
								as={ FormCheckbox }
								inputProps={ {
									id: 'add_custom_recipients',
									label: __( 'Add Custom Recipients', 'sugar-calendar-lite' ),
								} }
							/>
						</FieldRow>
						{
							addCustomRecipients && (
								<FieldRow>
									<Field
										name="custom_recipients"
										label={ __( 'Custom Recipients', 'sugar-calendar-lite' ) }
										htmlFor="custom_recipients"
										as={ FormSelect }
										inputProps={ {
											id: 'custom_recipients',
											options: customRecipients,
											isMulti: true,
											closeMenuOnSelect: false,
											allowCreate: true,
											onCreateOption: onCustomRecipientCreate,
										} }
									/>
									<p>
										{ __( 'Multiple email addresses supported. press “,” or enter to add a new email.', 'sugar-calendar-lite' ) }
									</p>
								</FieldRow>
							)
						}
						<FieldRow columns={ 2 }>
							<Field
								name="from_email"
								label={ __(
									'Send From Email',
									'sugar-calendar-lite'
								) }
								required
								as={ FormInput }
								inputProps={ {
									placeholder: __(
										'Email address',
										'sugar-calendar-lite'
									),
								} }
							/>
							<Field
								name="from_name"
								label={ __(
									'Send From Name',
									'sugar-calendar-lite'
								) }
								required
								as={ FormInput }
								inputProps={ {
									placeholder: __(
										'From name',
										'sugar-calendar-lite'
									),
								} }
							/>
						</FieldRow>
						<FieldRow>
							<Field
								name="email_subject"
								label={ __(
									'Email Subject',
									'sugar-calendar-lite'
								) }
								required
								as={ FormPlaceholderInput }
								inputProps={ {
									onChange: onEmailSubjectChange,
									suggestions: smartTagSuggestions,
									smartTagCategories,
								} }
							/>
						</FieldRow>
						<FieldRow>
							<Field
								name="email_body"
								label={ __(
									'Email Body',
									'sugar-calendar-lite'
								) }
								required
								as={ WysiwygEditor }
								inputProps={ {
									rows: 5,
									smartTagCategories,
								} }
							/>
						</FieldRow>
					</form>
				</FormProvider>
				{ ! isLoadingMeta && attendeesCount === 0 && ( watchedEvents || [] ).length > 0 &&
					<div className={ `${ styles.notice } ${ styles.noticeWarning }` } style={ { marginBottom: 20 } }>
						<span className={ `dashicons dashicons-warning ${ styles.noticeIcon }` }></span>
						<span>{ __( 'The selected events have no attendees. Add custom recipients or select different events.', 'sugar-calendar-lite' ) }</span>
					</div>
				}
				{ data?.meta?.upsell && showUpsell && <div className={ styles.upsell }>
					<SullieIcon></SullieIcon>
					<dl>
						<dt>{ __( 'Make Sure These Emails Reach Attendees', 'sugar-calendar-lite' ) }</dt>
						<dd dangerouslySetInnerHTML={ { __html: sprintf(
							'%1$s <a href="%2$s" target="_blank" rel="noreferrer">%3$s</a>',
							__( 'Solve common email deliverability issues for good.', 'sugar-calendar-lite' ),
							'https://wpmailsmtp.com/pricing/',
							__( 'Get WP Mail SMTP!', 'sugar-calendar-lite' )
						) } }>
						</dd>
					</dl>
					<button
						type="button"
						className={ styles.upsellDismiss }
						onClick={ onDismissUpsell }
						aria-label={ __( 'Dismiss', 'sugar-calendar-lite' ) }
					>
						&times;
					</button>
				</div> }
				{ sendStatus !== 'idle' && <NotifyNotice status={ sendStatus } result={ sendResult } /> }
			</Sidebar.Content>
			<Sidebar.Footer className={ styles.sidebarFooter }>
				<a href="#" className={ styles.previewLink } onClick={ onPreview }>
					{ __( 'Preview', 'sugar-calendar-lite' ) }
				</a>
				<Button
					onClick={ handleSubmit( onSubmit ) }
					disabled={ isLoading || isLoadingMeta || attendeesCount === 0 }
					loading={ isLoading }
				>
					{ isLoadingMeta
						? __( 'Calculating...', 'sugar-calendar-lite' )
						: attendeesCount > 0
							? __( 'Send ({count} Recipients)', 'sugar-calendar-lite' ).replace( '{count}', attendeesCount )
							: __( 'Add Recipients', 'sugar-calendar-lite' )
					}
				</Button>
			</Sidebar.Footer>
		</Sidebar.Layout>
	);
}
