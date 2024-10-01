<?php

namespace Sugar_Calendar\Admin\Tools\Importers;

class TheEventCalendar extends Importer {

	/**
	 * {@inheritDoc}.
	 *
	 * @since 3.2.0
	 */
	public function get_name() {

		return 'The Events Calendar';
	}

	/**
	 * {@inheritDoc}.
	 *
	 * @since 3.2.0
	 */
	public function get_slug() {

		return 'the-events-calendar';
	}

	/**
	 * {@inheritDoc}.
	 *
	 * @since 3.2.0
	 */
	public function get_path() {

		return 'the-events-calendar/the-events-calendar.php';
	}
}
