<?php

namespace Sugar_Calendar\Admin\Tools\Migrate;

/**
 * Where a migration stands after one request: a batch ran, or the migration finished.
 *
 * @since 3.18.0
 */
final class Progress {

	/**
	 * The response the Migrate tab reads.
	 *
	 * @since 3.18.0
	 *
	 * @var array
	 */
	private $response;

	/**
	 * Constructor.
	 *
	 * @since 3.18.0
	 *
	 * @param array $response The response the Migrate tab reads.
	 */
	private function __construct( $response ) {

		$this->response = $response;
	}

	/**
	 * A batch ran.
	 *
	 * @since 3.18.0
	 *
	 * @param array $response The batch response.
	 *
	 * @return Progress
	 */
	public static function batch( $response ) {

		return new self( $response );
	}

	/**
	 * The migration finished.
	 *
	 * @since 3.18.0
	 *
	 * @param Report $report The summary.
	 *
	 * @return Progress
	 */
	public static function finished( Report $report ) {

		return new self( $report->to_array() );
	}

	/**
	 * The response the Migrate tab reads.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	public function to_array() {

		return $this->response;
	}
}
