<?php

namespace Sugar_Calendar\Admin\Tools\Migrate;

/**
 * What one migrated item came to: migrated, or not, with the line the summary lists for it.
 *
 * @since 3.18.0
 */
final class StepResult {

	/**
	 * Whether the item was migrated.
	 *
	 * @since 3.18.0
	 *
	 * @var bool
	 */
	private $migrated;

	/**
	 * The summary group the item is listed under, empty when it is not listed.
	 *
	 * @since 3.18.0
	 *
	 * @var string
	 */
	private $issue_entity;

	/**
	 * The name the summary lists the item by.
	 *
	 * @since 3.18.0
	 *
	 * @var string
	 */
	private $issue_name;

	/**
	 * Constructor.
	 *
	 * @since 3.18.0
	 *
	 * @param bool   $migrated     Whether the item was migrated.
	 * @param string $issue_entity The summary group the item is listed under.
	 * @param string $issue_name   The name the summary lists the item by.
	 */
	private function __construct( $migrated, $issue_entity = '', $issue_name = '' ) {

		$this->migrated     = $migrated;
		$this->issue_entity = $issue_entity;
		$this->issue_name   = $issue_name;
	}

	/**
	 * The item was migrated.
	 *
	 * @since 3.18.0
	 *
	 * @return StepResult
	 */
	public static function migrated() {

		return new self( true );
	}

	/**
	 * The item was not migrated; with a group, the summary lists it.
	 *
	 * @since 3.18.0
	 *
	 * @param string $issue_entity The summary group the item is listed under, empty to not list it.
	 * @param string $issue_name   The name the summary lists the item by.
	 *
	 * @return StepResult
	 */
	public static function not_migrated( $issue_entity = '', $issue_name = '' ) {

		return new self( false, $issue_entity, $issue_name );
	}

	/**
	 * Whether the item was migrated.
	 *
	 * @since 3.18.0
	 *
	 * @return bool
	 */
	public function is_migrated() {

		return $this->migrated;
	}

	/**
	 * The summary group the item is listed under, empty when it is not listed.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_issue_entity() {

		return $this->issue_entity;
	}

	/**
	 * The name the summary lists the item by.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_issue_name() {

		return $this->issue_name;
	}
}
