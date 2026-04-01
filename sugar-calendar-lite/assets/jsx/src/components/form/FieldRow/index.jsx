import clsx from 'clsx';
import PropTypes from 'prop-types';

import styles from './index.module.scss';

/**
 * FieldRow component for arranging form fields side by side.
 *
 * @since {VERSION}
 *
 * @param {Object}          props
 * @param {React.ReactNode} props.children    Child form fields to display in a row.
 * @param {number}          [props.columns=1] Number of columns to display (1, 2, 3, etc.).
 * @return {JSX.Element} FieldRow component.
 */
export default function FieldRow( { children, columns = 1 } ) {
	const columnClass = styles[ 'fieldRowColumns' + columns ];

	return (
		<div className={ clsx( styles.fieldRow, columnClass ) }>
			{ children }
		</div>
	);
}

FieldRow.propTypes = {
	children: PropTypes.node.isRequired,
	columns: PropTypes.number,
};
