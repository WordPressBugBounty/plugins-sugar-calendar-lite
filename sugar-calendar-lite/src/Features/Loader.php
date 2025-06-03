<?php

namespace Sugar_Calendar\Features;

// Base Sugar Calendar feature class.
use Sugar_Calendar\Common\Features\FeatureAbstract;

// Tags feature.
use Sugar_Calendar\Features\Tags\Feature as TagsFeature;

/**
 * Loader class for the Features.
 *
 * @since 3.7.0
 */
class Loader {


	/**
	 * Loaded features.
	 *
	 * @since 3.7.0
	 *
	 * @var array
	 */
	private $loaded_features = [];

	/**
	 * Constructor.
	 *
	 * @since 3.7.0
	 */
	public function init() {

		// Load the features.
		$this->load_features();

		// Initialize the features.
		$this->initialize_features();
	}

	/**
	 * Get the features.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	public function get_features() {

		/**
		 * Filters the features to load.
		 *
		 * @since 3.7.0
		 *
		 * @param array $features The features to load.
		 */
		return apply_filters(
			'sugar_calendar_features_loader_get_features',
			[
				TagsFeature::class,
			]
		);
	}

	/**
	 * Get loaded features.
	 *
	 * @since 3.7.0
	 *
	 * @param string|null $name The name of the feature to get.
	 *
	 * @return array
	 */
	public function get_loaded_features( $name = null ) {

		if ( $name ) {
			return $this->loaded_features[ $name ];
		}

		return $this->loaded_features;
	}

	/**
	 * Load the features.
	 *
	 * @since 3.7.0
	 */
	public function load_features() {

		$features = $this->get_features();

		if ( empty( $features ) ) {
			return;
		}

		foreach ( $features as $feature ) {

			$loaded_feature = $this->load_feature( $feature );

			if ( $loaded_feature ) {

				$this->loaded_features[ $loaded_feature->name ] = $loaded_feature;
			}
		}
	}

	/**
	 * Load a feature.
	 *
	 * @since 3.7.0
	 *
	 * @param string $feature The feature class name.
	 *
	 * @return FeatureAbstract|null The loaded feature or null if it failed to load.
	 */
	public function load_feature( $feature ) {

		// Make sure the class exists.
		if ( ! class_exists( $feature ) ) {
			return;
		}

		// Check if the class extends FeatureAbstract.
		if ( ! is_subclass_of( $feature, FeatureAbstract::class ) ) {
			return;
		}

		// Create an instance of the feature.
		return new $feature();
	}

	/**
	 * Initialize the features.
	 *
	 * @since 3.7.0
	 */
	public function initialize_features() {

		foreach ( $this->loaded_features as $feature_name => $feature ) {

			// Check if the feature has an init method.
			if ( ! method_exists( $feature, 'init' ) ) {
				continue;
			}

			// Initialize the feature.
			$feature->init();
		}
	}
}
