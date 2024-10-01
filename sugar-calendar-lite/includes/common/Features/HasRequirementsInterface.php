<?php

namespace Sugar_Calendar\Common\Features;

/**
 * Interface HasRequirementsInterface.
 *
 * @since 3.0.0
 */
interface HasRequirementsInterface {

	/**
	 * Get the requirements to run the Feature.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function get_requirements();

	/**
	 * Check if the requirements are met.
	 *
	 * @since 3.0.0
	 */
	public function met_requirements();
}
