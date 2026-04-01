import Field from '../Field';
import FormToggle from '../FormToggle';
import styles from './index.module.scss';

/**
 * ToggleField component for form toggle fields.
 *
 * @since {VERSION}
 *
 * @param {Object} props - The component props.
 */
export default function ToggleField( props ) {
	return (
		<Field
			className={ styles.toggleField }
			{ ...props }
			as={ FormToggle }
		/>
	);
}
