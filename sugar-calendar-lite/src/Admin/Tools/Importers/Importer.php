<?php

namespace Sugar_Calendar\Admin\Tools\Importers;

abstract class Importer implements ImporterInterface {

	/**
	 * If the importer plugin source is active.
	 *
	 * @since 3.2.0
	 *
	 * @return bool
	 */
	protected function is_active() {

		return is_plugin_active( $this->get_path() );
	}
}
