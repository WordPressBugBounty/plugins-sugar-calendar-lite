<?php

namespace Sugar_Calendar\Admin\Tools\Migrate;

use Sugar_Calendar\Admin\Tools\Importers\Importer;

/**
 * The summary of a finished migration.
 *
 * @since 3.18.0
 */
final class Report {

	/**
	 * The items not migrated, by summary group.
	 *
	 * @since 3.18.0
	 *
	 * @var array
	 */
	private $issues;

	/**
	 * The summary as the Migrate tab shows it.
	 *
	 * @since 3.18.0
	 *
	 * @var string
	 */
	private $issues_html;

	/**
	 * Constructor.
	 *
	 * @since 3.18.0
	 *
	 * @param array  $issues      The items not migrated, by summary group.
	 * @param string $issues_html The summary as the Migrate tab shows it.
	 */
	public function __construct( $issues, $issues_html ) {

		$this->issues      = $issues;
		$this->issues_html = $issues_html;
	}

	/**
	 * The summary from what an issue log holds.
	 *
	 * @since 3.18.0
	 *
	 * @param IssueLog $issue_log The issue log.
	 *
	 * @return Report
	 */
	public static function from_issue_log( IssueLog $issue_log ) {

		return new self( $issue_log->get_issues(), $issue_log->render_html() );
	}

	/**
	 * The summary in the Migrate tab's response shape.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	public function to_array() {

		return [
			'status'     => Importer::AJAX_RETURN_STATUS_COMPLETE,
			'errors'     => $this->issues,
			'error_html' => wp_kses_post( $this->issues_html ),
		];
	}
}
