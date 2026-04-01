import { __ } from '@wordpress/i18n';
import clsx from 'clsx';
import PropTypes from 'prop-types';
import React from 'react';

import styles from './index.module.scss';

/**
 * Loader component.
 *
 * Displays a visually appealing loading spinner.
 *
 * @since {VERSION}
 *
 * @param {Object} props
 * @param {string} props.className - Class name.
 * @return {JSX.Element} Loader component.
 */
export default function Loader( { className } ) {
	return (
		<div
			className={ clsx( styles.loader, className ) }
			aria-label={ __( 'Loading', 'sugar-calendar-bookings-lite' ) }
			role="status"
		>
			<span className={ styles.visuallyHidden }>
				{ __( 'Loading…', 'sugar-calendar-bookings-lite' ) }
			</span>
		</div>
	);
}

Loader.propTypes = {
	className: PropTypes.string,
};
