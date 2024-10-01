<?php
/**
 * Base integration
 */
namespace Sugar_Calendar\AddOn\Ticketing\Integrations;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\AddOn\Ticketing\Common\Functions as Functions;

/**
 * Base integration class.
 *
 * @since 1.1.0
 */
class Base {

	public function __construct() {
		$this->init();
	}

	public function init() {}

}