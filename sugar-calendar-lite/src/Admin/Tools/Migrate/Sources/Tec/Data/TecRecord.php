<?php

namespace Sugar_Calendar\Admin\Tools\Migrate\Sources\Tec\Data;

/**
 * The post ID and field array a TEC record keeps, with the constructor and `get_id()`.
 *
 * @since 3.18.0
 */
trait TecRecord {

	/**
	 * The TEC post ID.
	 *
	 * @since 3.18.0
	 *
	 * @var int
	 */
	private $id;

	/**
	 * The fields read from the record's post and meta.
	 *
	 * @since 3.18.0
	 *
	 * @var array
	 */
	private $fields;

	/**
	 * Constructor.
	 *
	 * @since 3.18.0
	 *
	 * @param int   $id     The TEC post ID.
	 * @param array $fields The fields read from the post and its meta.
	 */
	public function __construct( $id, $fields ) {

		$this->id     = (int) $id;
		$this->fields = $fields;
	}

	/**
	 * The TEC post ID.
	 *
	 * @since 3.18.0
	 *
	 * @return int
	 */
	public function get_id() {

		return $this->id;
	}
}
