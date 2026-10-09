<?php

namespace Sugar_Calendar\PartnerPlugins;

/**
 * What the shared installer needs to know about one plugin: identity, package and how to read its state.
 *
 * Partners get one from Catalog::get_target(); Pro builds one per add-on after its license checks.
 *
 * @since 3.15.0
 */
class InstallTarget {

	/**
	 * Package to install.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	private $package_url;

	/**
	 * Accepted installed basenames; activation uses the first one that is installed.
	 *
	 * @since 3.15.0
	 *
	 * @var string[]
	 */
	private $basenames;

	/**
	 * Live state reader.
	 *
	 * @since 3.15.0
	 *
	 * @var callable
	 */
	private $read_state;

	/**
	 * Constructor.
	 *
	 * @since 3.15.0
	 *
	 * @param string   $package_url Package to install.
	 * @param string[] $basenames   Accepted installed basenames, in activation preference order.
	 * @param callable $read_state  Returns the live state: `missing`, `installed` or `active`.
	 */
	public function __construct( $package_url, array $basenames, callable $read_state ) {

		$this->package_url = (string) $package_url;
		$this->basenames   = array_values( $basenames );
		$this->read_state  = $read_state;
	}

	/**
	 * Get the package to install.
	 *
	 * @since 3.15.0
	 *
	 * @return string
	 */
	public function get_package_url() {

		return $this->package_url;
	}

	/**
	 * Get the accepted installed basenames.
	 *
	 * @since 3.15.0
	 *
	 * @return string[]
	 */
	public function get_basenames() {

		return $this->basenames;
	}

	/**
	 * Read the live state.
	 *
	 * @since 3.15.0
	 *
	 * @return string `missing`, `installed` or `active`.
	 */
	public function get_state() {

		return (string) call_user_func( $this->read_state );
	}
}
