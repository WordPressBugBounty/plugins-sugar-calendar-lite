<?php

namespace Sugar_Calendar\Common\Features;

/**
 * Class Loader.
 *
 * Load and initialize the common Features.
 *
 * @since 3.0.0
 */
class Loader extends LoaderAbstract {

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 */
	public function init() {

		$this->load_features();
	}

	/**
	 * Get the common Features.
	 *
	 * @since 3.0.0
	 * @since 3.1.0 Added Event Ticketing feature.
	 *
	 * @return string[]
	 */
	protected function get_features() {

		return [
			'GoogleMaps',
			'EventTicketing',
		];
	}

	/**
	 * The common Features path.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	protected function get_features_path() {

		return SC_PLUGIN_DIR . 'includes/common/Features/';
	}

	/**
	 * The common Features namespace.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	protected function get_namespace() {

		return __NAMESPACE__;
	}
}
