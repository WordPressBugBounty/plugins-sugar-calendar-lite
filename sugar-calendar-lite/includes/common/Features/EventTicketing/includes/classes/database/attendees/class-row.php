<?php
/**
 * Attendees Row Class.
 *
 * @package     Sugar Calendar
 * @subpackage  Database\Schemas
 * @since       1.0
 */
namespace Sugar_Calendar\AddOn\Ticketing\Database;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Sugar_Calendar\Database\Row;

/**
 * Attendee Class
 *
 * @since 1.0.0
 */
final class Attendee extends Row {

	/**
	 * Attendee ID.
	 *
	 * @since 1.0.0
	 * @access public
	 * @var int
	 */
	public $id;

}