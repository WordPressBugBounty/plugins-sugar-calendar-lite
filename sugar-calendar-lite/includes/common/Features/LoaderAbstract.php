<?php

namespace Sugar_Calendar\Common\Features;

/**
 * Class LoaderAbstract.
 *
 * Addons loader.
 *
 * @since 3.0.0
 */
abstract class LoaderAbstract {

	/**
	 * Array containing the loaded Features.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	private $loaded_features = [];

	/**
	 * Array containing the Features.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	abstract protected function get_features();

	/**
	 * Get the Features path.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	abstract protected function get_features_path();

	/**
	 * Get the Features namespace.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	abstract protected function get_namespace();

	/**
	 * Load and initialize the Features.
	 *
	 * @since 3.0.0
	 */
	public function load_features() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$features = $this->get_features();

		if ( empty( $features ) ) {
			return;
		}

		$features_path = $this->get_features_path();
		$namespace     = $this->get_namespace();

		foreach ( $features as $feature_namespace ) {

			$feature_file = $features_path . $feature_namespace . '/Feature.php';

			if ( ! file_exists( $feature_file ) ) {
				continue;
			}

			require_once $feature_file;

			$feature_class = $namespace . '\\' . $feature_namespace . '\\' . 'Feature';

			if ( ! class_exists( $feature_class ) ) {
				continue;
			}

			$feature = new $feature_class();

			if ( ! $feature instanceof FeatureAbstract ) {
				continue;
			}

			$this->loaded_features[ $feature_namespace ] = $feature;

			if ( $feature->met_requirements() ) {
				$feature->init();
			}
		}
	}

	/**
	 * Get a loaded Feature.
	 *
	 * @since 3.0.0
	 *
	 * @param string $feature The Feature to get.
	 *
	 * @return FeatureAbstract|false
	 */
	public function get_feature( $feature ) {

		if ( isset( $this->loaded_features[ $feature ] ) ) {
			return $this->loaded_features[ $feature ];
		}

		return false;
	}
}
