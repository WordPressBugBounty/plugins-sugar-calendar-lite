<?php

namespace Sugar_Calendar\Admin\Tools\Migrate;

/**
 * A step with work to do once every step has run.
 *
 * @since 3.18.0
 */
interface FinishableStep extends MigrationStep {

	/**
	 * Run the step's post-run work.
	 *
	 * @since 3.18.0
	 */
	public function finish();
}
