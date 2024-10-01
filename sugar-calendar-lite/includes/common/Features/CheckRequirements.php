<?php

namespace Sugar_Calendar\Common\Features;

/**
 * Trait CheckRequirements.
 *
 * @since 3.0.0
 */
trait CheckRequirements {

	/**
	 * Requirements to run the Feature.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	protected $requirements = [];

	/**
	 * Whether the requirements are met.
	 *
	 * @since 3.0.0
	 *
	 * @var bool
	 */
	private $is_met = false;

	/**
	 * Whether the requirements have been checked.
	 *
	 * @since 3.0.0
	 *
	 * @var bool
	 */
	private $is_checked = false;

	/**
	 * Get whether the requirements are met.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function met_requirements() {

		if ( empty( $this->requirements ) ) {
			$this->is_met = true;

			return true;
		}

		if ( $this->is_checked ) {
			return $this->is_met;
		}

		$this->check_requirements();

		$requirements_to_meet = wp_list_pluck( $this->requirements, 'met' );

		foreach ( $requirements_to_meet as $to_meet ) {
			// if any of the requirements are not met, return `false`.
			if ( empty( $to_meet ) ) {
				$this->is_met = false;

				return false;
			}
		}

		$this->is_met = true;

		return true;
	}

	/**
	 * Check the requirements.
	 *
	 * @since 3.0.0
	 */
	protected function check_requirements() {

		if ( $this->is_checked ) {
			return;
		}

		$this->is_checked = true;

		if ( empty( $this->requirements ) ) {
			return;
		}

		foreach ( $this->requirements as $dependency => $properties ) {

			$this->requirements[ $dependency ] = array_merge(
				[
					'checked' => true,
					'exists'  => true,
					'met'     => false,
				],
				$this->check_dependency( $dependency, $properties )
			);
		}
	}

	/**
	 * Check if a dependency is met.
	 *
	 * @since 3.0.0
	 *
	 * @param string $dependency The dependency.
	 * @param array  $properties Properties of the dependency to check.
	 *
	 * @return array
	 */
	private function check_dependency( $dependency, $properties ) {

		switch ( $dependency ) {

			// phpcs:disable WPForms.Formatting.Switch.AddEmptyLineBefore, WPForms.Formatting.EmptyLineBeforeReturn.AddEmptyLineBeforeReturnStatement
			case 'php':
				return [
					'met'     => is_php_version_compatible( $properties['minimum'] ),
					'version' => phpversion(),
				];

			case 'wp':
				return [
					'met'     => is_wp_version_compatible( $properties['minimum'] ),
					'version' => get_bloginfo( 'version' ),
				];

			case 'sc':
				$version = defined( 'SC_PLUGIN_VERSION' ) ? SC_PLUGIN_VERSION : false;

				return [
					'exists'  => ! empty( $version ),
					'met'     => version_compare( $version, $properties['minimum'], '>=' ),
					'version' => $version,
				];

			default:
				return [
					'met'     => false,
					'version' => false,
				];
			// phpcs:enable WPForms.Formatting.Switch.AddEmptyLineBefore, WPForms.Formatting.EmptyLineBeforeReturn.AddEmptyLineBeforeReturnStatement
		}
	}
}
