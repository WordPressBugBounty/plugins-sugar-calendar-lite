import { __ } from '@wordpress/i18n';

// Smart Tags data for Sugar Calendar Bookings
export const SMART_TAGS = {
	SERVICE_FIELDS: [
		{
			value: 'service_name',
			label: __(
				'Service Name',
				'sugar-calendar-bookings-scheduling-appointments-lite'
			),
		},
		{
			value: 'service_duration',
			label: __(
				'Service Duration',
				'sugar-calendar-bookings-scheduling-appointments-lite'
			),
		},
		{
			value: 'service_price',
			label: __(
				'Service Price',
				'sugar-calendar-bookings-scheduling-appointments-lite'
			),
		},
		{
			value: 'service_locations',
			label: __(
				'Service Locations',
				'sugar-calendar-bookings-scheduling-appointments-lite'
			),
		},
	],
	GENERAL: [
		{
			value: 'site_name',
			label: __(
				'Site Name',
				'sugar-calendar-bookings-scheduling-appointments-lite'
			),
		},
		{
			value: 'admin_email',
			label: __(
				'Admin Email',
				'sugar-calendar-bookings-scheduling-appointments-lite'
			),
		},
	],
};
