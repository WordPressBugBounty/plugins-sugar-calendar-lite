<?php

namespace Sugar_Calendar\Admin\Tools\Importers;

interface ImporterInterface {

	/**
	 * Get the slug of the importer.
	 *
	 * @since 3.2.0
	 *
	 * @return string
	 */
	public function get_slug();

	/**
	 * Display the tab content.
	 *
	 * @since 3.3.0
	 */
	public function display();

	/**
	 * Run the importer.
	 *
	 * @since 3.3.0
	 *
	 * @param int[] $total_number_to_import Optional. The total number to import per context.
	 */
	public function run( $total_number_to_import = [] );

	/**
	 * Whether the importer runs in AJAX or not.
	 *
	 * @since 3.3.0
	 *
	 * @return bool
	 */
	public function is_ajax();

	/**
	 * Get the errors.
	 *
	 * @since 3.3.0
	 *
	 * @return array
	 */
	public function get_errors();
}
