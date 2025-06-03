<?php

namespace Sugar_Calendar\Common\Features;

/**
 * Class FeatureAbstract.
 *
 * Abstract class for Features.
 *
 * @since 3.0.0
 */
abstract class FeatureAbstract implements HasRequirementsInterface {

	use CheckRequirements;

	/**
	 * Feature name.
	 *
	 * @since 3.7.0
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {

		$this->requirements = $this->get_requirements();
	}

	/**
	 * Feature init.
	 *
	 * @since 3.0.0
	 */
	public function init() {

		$this->setup();
		$this->hooks();
	}

	/**
	 * Setup the Feature.
	 *
	 * Include files and pre-hook configurations here.
	 *
	 * @since 3.0.0
	 */
	abstract protected function setup();

	/**
	 * Feature hooks.
	 *
	 * @since 3.0.0
	 */
	abstract protected function hooks();
}
