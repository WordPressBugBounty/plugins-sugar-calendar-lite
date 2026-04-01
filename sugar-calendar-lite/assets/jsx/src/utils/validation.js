/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * External dependencies
 */
import * as yup from 'yup';

/**
 * Set Yup locale strings.
 *
 * @since 3.11.0
 */
yup.setLocale( {
	mixed: {
		required: __(
			'This field is required.',
			'sugar-calendar-lite'
		),
		notType: __(
			'This field must be a ${type}.',
			'sugar-calendar-lite'
		),
	},
	string: {
		url: __(
			'This field must be a valid URL.',
			'sugar-calendar-lite'
		),
		email: __(
			'This field must be a valid email address.',
			'sugar-calendar-lite'
		),
	},
	number: {
		min: __(
			'This field must be greater than or equal to ${min}.',
			'sugar-calendar-lite'
		),
		max: __(
			'This field must be less than or equal to ${max}.',
			'sugar-calendar-lite'
		),
		integer: __(
			'This field must be an integer.',
			'sugar-calendar-lite'
		),
	},
} );

yup.addMethod( yup.number, 'requiredNumber', function() {
	return this.transform( ( v, o ) =>
		o === '' ? undefined : v
	).required();
} );

export default yup;
