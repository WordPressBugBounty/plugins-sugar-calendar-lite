<?php

namespace Sugar_Calendar\Admin\Tools\Importers;

interface ImporterInterface {

	/**
	 * Get the name of the importer.
	 *
	 * @since 3.2.0
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Get the slug of the importer.
	 *
	 * @since 3.2.0
	 *
	 * @return string
	 */
	public function get_slug();

	/**
	 * Get the path of the plugin that the importer is for.
	 *
	 * @since 3.2.0
	 *
	 * @return string
	 */
	public function get_path();
}
