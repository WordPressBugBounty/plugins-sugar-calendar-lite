/**
 * External dependencies
 */
import clsx from 'clsx';
import PropTypes from 'prop-types';

/**
 * Button component.
 *
 * Uses global class names for styling.
 *
 * @since 1.0.0
 *
 * @param {Object}                                         props              - Component props.
 * @param {'sm'|'md'|'lg'}                                 props.size         - Button size.
 * @param {'primary'|'secondary'|'tertiary'|'green'|'red'} props.variant      - Button style variant.
 * @param {string}                                         [props.type]       - Button type attribute.
 * @param {boolean}                                        [props.fullWidth]  - If true, button is full width.
 * @param {boolean}                                        [props.disabled]   - If true, button is disabled.
 * @param {string}                                         [props.className]  - Additional class names.
 * @param {boolean}                                        [props.loading]    - If true, button is loading.
 * @param {React.ReactNode}                                [props.iconBefore] - Icon before button content.
 * @param {React.ReactNode}                                [props.iconAfter]  - Icon after button content.
 * @param {React.ReactNode}                                props.children     - Button content.
 * @param {Object}                                         [props.rest]       - Other button props.
 * @return {JSX.Element} Button element.
 */
function Button( {
	size = 'md',
	variant = 'primary',
	type = 'button',
	fullWidth = false,
	disabled = false,
	loading = false,
	className,
	children,
	iconBefore,
	iconAfter,
	...rest
} ) {
	const classes = clsx(
		'sugar-calendar-btn',
		size && `sugar-calendar-btn-${ size }`,
		variant && `sugar-calendar-btn-${ variant }`,
		{
			'sugar-calendar-btn-block': fullWidth,
			'sugar-calendar-btn-inactive': disabled,
			'sugar-calendar-btn-loading': loading,
		},
		className
	);

	return (
		<button
			type={ type }
			className={ classes }
			disabled={ disabled }
			{ ...rest }
		>
			{ iconBefore && (
				<span className="sugar-calendar-bookings-btn-icon">
					{ iconBefore }
				</span>
			) }
			{ children }
			{ iconAfter && (
				<span className="sugar-calendar-bookings-btn-icon">
					{ iconAfter }
				</span>
			) }
		</button>
	);
}

Button.propTypes = {
	size: PropTypes.oneOf( [ 'sm', 'md', 'lg' ] ),
	variant: PropTypes.oneOf( [
		'primary',
		'secondary',
		'tertiary',
		'text',
		'green',
		'red',
	] ),
	type: PropTypes.string,
	fullWidth: PropTypes.bool,
	disabled: PropTypes.bool,
	className: PropTypes.string,
	loading: PropTypes.bool,
	children: PropTypes.node.isRequired,
};

export default Button;
