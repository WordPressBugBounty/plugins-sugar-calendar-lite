<?php

namespace Sugar_Calendar\ConflictFixes;

/**
 * Conflict fixes Loader.
 *
 * @since 3.8.2
 */
class Loader {

	/**
	 * Conflict fixes classes.
	 *
	 * @since 3.8.2
	 *
	 * @var array
	 */
	private $conflict_fixes_classes = [
		'GiveWP\\GiveWP',
	];

	/**
	 * Initialize the conflict fixes loader.
	 *
	 * @since 3.8.2
	 */
	public function init() {

		$this->load_conflict_fixes();
	}

	/**
	 * Load conflict fixes.
	 *
	 * @since 3.8.2
	 */
	private function load_conflict_fixes() {

		/**
		 * Filters the conflict fixes classes.
		 *
		 * @since 3.8.2
		 *
		 * @param array $class_names Array of conflict fixes classes.
		 */
		$class_names = (array) apply_filters( 'sugar_calendar_conflict_fixes_classes', $this->conflict_fixes_classes ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

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


