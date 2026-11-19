<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data;

/**
 * A TEC ticket's price and capacity.
 *
 * @since 3.18.0
 */
final class TecTicket {

	/**
	 * The TEC ticket post ID.
	 *
	 * @since 3.18.0
	 *
	 * @var int
	 */
	private $id;

	/**
	 * The stored `_price`.
	 *
	 * @since 3.18.0
	 *
	 * @var mixed
	 */
	private $price;

	/**
	 * The stored `_tribe_ticket_capacity`, -1 or empty for unlimited.
	 *
	 * @since 3.18.0
	 *
	 * @var mixed
	 */
	private $capacity;

	/**
	 * Constructor.
	 *
	 * @since 3.18.0
	 *
	 * @param int   $id       The TEC ticket post ID.
	 * @param mixed $price    The stored price.
	 * @param mixed $capacity The stored capacity.
	 */
	public function __construct( $id, $price, $capacity ) {

		$this->id       = (int) $id;
		$this->price    = $price;
		$this->capacity = $capacity;
	}

	/**
	 * The TEC ticket post ID.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	public function get_id() {

		return $this->id;
	}

	/**
	 * The stored price.
	 *
	 * @since 3.18.0
	 *
	 * @return mixed
	 */
	public function get_price() {

		return $this->price;
	}

	/**
	 * The stored capacity, -1 or empty for unlimited.
	 *
	 * @since 3.18.0
	 *
	 * @return mixed
	 */
	public function get_capacity() {

		return $this->capacity;
	}

	/**
	 * The ticket's name, its post title.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_name() {

		return get_the_title( $this->id );
	}
}
