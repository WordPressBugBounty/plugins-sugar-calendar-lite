<?php

namespace Sugar_Calendar\Integrations;

/**
 * Integrations Loader.
 *
 * @since 3.2.0
 */
class Loader {

	/**
	 * Integrations classes.
	 *
	 * @since 3.2.0
	 *
	 * @var array
	 */
	private $integrations_classes = [
		'Elementor\Elementor',
	];

	/**
	 * Initialize the integrations loader.
	 *
	 * @since 3.2.0
	 */
	public function init() {

		$this->load_integrations();
	}

	/**
	 * Load integrations.
	 *
	 * @since 3.2.0
	 */
	private function load_integrations() {

		/**
		 * Filters the integrations classes.
		 *
		 * @since 3.2.0
		 *
		 * @param array $class_names Array of integrations classes.
		 */
		$class_names = (array) apply_filters( 'sugar_calendar_integrations_classes', $this->integrations_classes ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		foreach ( $class_names as $class_name ) {

			$fqcn = __NAMESPACE__ . '\\' . $class_name;

			if ( ! class_exists( $fqcn ) ) {
				continue;
			}

			$class = new $fqcn();

			if ( method_exists( $class, 'init' ) ) {
				$class->init();
			}
		}
	}
}
