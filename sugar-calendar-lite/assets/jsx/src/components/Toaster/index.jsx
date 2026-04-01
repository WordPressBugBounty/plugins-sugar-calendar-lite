import { Toaster as VendorToaster } from 'react-hot-toast';

import styles from './index.module.scss';

/**
 * Toaster component.
 *
 * @since {VERSION}
 *
 * @return {JSX.Element} Toaster component.
 */
const Toaster = () => (
	<VendorToaster
		containerStyle={ { bottom: 75 } }
		position="bottom-right"
		toastOptions={ { className: styles.toaster } }
	/>
);

export default Toaster;
