/**
 * Internal dependencies
 */
import styles from './index.module.scss';

/**
 * Smart Tag Item Component
 *
 * @since {VERSION}
 *
 * @param {Object}   props
 * @param {Object}   props.tag     - Smart tag object with value and label
 * @param {Function} props.onClick - Click handler function
 */
const SmartTagItem = ( { tag, onClick } ) => {
	return (
		<li className={ styles.item } data-value={ tag.value }>
			<button
				type="button"
				className={ styles.button }
				data-type="field"
				onClick={ () => onClick( tag.value ) }
			>
				{ tag.label }
			</button>
		</li>
	);
};

export default SmartTagItem;
