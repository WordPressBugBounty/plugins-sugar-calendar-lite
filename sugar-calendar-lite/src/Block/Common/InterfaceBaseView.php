<?php

namespace Sugar_Calendar\Block\Common;

/**
 * Interface InterfaceBaseView.
 *
 * Use in each of the base calendar views.
 *
 * @since 3.0.0
 */
interface InterfaceBaseView {

	/**
	 * Render the base view.
	 *
	 * @since 3.0.0
	 */
	public function render_base();

	/**
	 * Get the heading of the view.
	 *
	 * This method is mostly used for AJAX requests.
	 *
	 * @since 3.0.0
	 * @since 3.4.0
	 *
	 * @param bool $use_abbreviated_month Whether to use abbreviated month or not.
	 *
	 * @return string
	 */
	public function get_heading( $use_abbreviated_month = false );
}
