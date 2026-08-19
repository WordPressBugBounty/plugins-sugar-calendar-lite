<?php

namespace Sugar_Calendar\Features\RegistrationForm;

use Sugar_Calendar\Common\Features\FeatureAbstract;
use Sugar_Calendar\Features\RegistrationForm\Abandonment\ReminderTask;
use Sugar_Calendar\Features\RegistrationForm\Admin\AnswersConfirmationEmailConfig;
use Sugar_Calendar\Features\RegistrationForm\Admin\LiteEducationSection;
use Sugar_Calendar\Features\RegistrationForm\Admin\MetaboxSection;
use Sugar_Calendar\Features\RegistrationForm\Admin\OrderPage;
use Sugar_Calendar\Features\RegistrationForm\Admin\ReminderEmailConfig;
use Sugar_Calendar\Features\RegistrationForm\Admin\RsvpPage;
use Sugar_Calendar\Features\RegistrationForm\Admin\WriteFailureNotice;
use Sugar_Calendar\Features\RegistrationForm\Cleanup;
use Sugar_Calendar\Features\RegistrationForm\Frontend\RsvpAfterCheckout;
use Sugar_Calendar\Features\RegistrationForm\Frontend\RsvpCheckout;
use Sugar_Calendar\Features\RegistrationForm\Frontend\SubmitEndpoint;
use Sugar_Calendar\Features\RegistrationForm\Frontend\TicketingCheckout;
use Sugar_Calendar\Features\RegistrationForm\Frontend\TicketingReceipt;
use Sugar_Calendar\Features\RegistrationForm\Frontend\TokenResume;

/**
 * The Registration Form feature (issue #601).
 *
 * @since 3.13.0
 */
class Feature extends FeatureAbstract {

	/**
	 * Feature name.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	public $name = 'sugar-calendar-registration-form';

	/**
	 * Feature requirements.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	public function get_requirements() {

		return [
			'php' => [
				'minimum' => '7.4',
				'name'    => 'PHP',
				'current' => false,
			],
			'wp'  => [
				'minimum' => '5.9',
				'name'    => 'WordPress',
				'current' => false,
			],
		];
	}

	/**
	 * Setup the feature.
	 *
	 * @since 3.13.0
	 */
	protected function setup() {}

	/**
	 * Register hooks.
	 *
	 * @since 3.13.0
	 */
	protected function hooks() {

		add_filter( 'sugar_calendar_migrations_get_migrations', [ $this, 'register_migrations' ] );
		add_filter( 'sugar_calendar_meta_data', [ $this, 'register_meta' ] );

		// Booted on every request: checkout validation runs through admin-ajax,
		// where is_admin() is also true.
		( new TicketingCheckout() )->hooks();

		// Same reasoning as TicketingCheckout above.
		( new RsvpCheckout() )->hooks();

		// Booted unconditionally so deletes from admin, WP-CLI, or code all clean
		// up orphaned rows.
		( new Cleanup() )->hooks();

		// Pro-only, like every read path; registered on priv and nopriv since a
		// ticket buyer is usually not logged in.
		if ( sugar_calendar()->is_pro() ) {
			( new SubmitEndpoint() )->hooks();
			( new TicketingReceipt() )->hooks();
			( new RsvpAfterCheckout() )->hooks();
			( new TokenResume() )->hooks();

			// Outside is_admin(): Action Scheduler and WP-CLI/cron run with
			// is_admin() false.
			add_filter( 'sugar_calendar_tasks_get_tasks', [ $this, 'register_tasks' ] );
		}

		if ( is_admin() ) {
			if ( sugar_calendar()->is_pro() ) {
				( new MetaboxSection() )->hooks();
				( new WriteFailureNotice() )->hooks();
				( new OrderPage() )->hooks();

				// No add-on presence check: this only registers hook callbacks,
				// and sc-rsvp's hooks simply never fire when it isn't active.
				( new RsvpPage() )->hooks();

				( new ReminderEmailConfig() )->hooks();
				( new AnswersConfirmationEmailConfig() )->hooks();
			} else {
				// Lite gets a picture of the editor plus an upgrade path.
				( new LiteEducationSection() )->hooks();
			}
		}
	}

	/**
	 * Add the responses-table migration to the migration list.
	 *
	 * @since 3.13.0
	 *
	 * @param array $migrations Migration class names.
	 *
	 * @return array
	 */
	public function register_migrations( $migrations ) {

		$migrations[] = ResponsesTableMigration::class;

		return $migrations;
	}

	/**
	 * Register the `registration_form` event-meta key.
	 *
	 * SCE's event store persists only registered meta keys from the
	 * `sugar_calendar_event_to_save` array. Our write path is
	 * SchemaRepository::save() (direct update_event_meta), but the key is
	 * registered regardless so it is a known, queryable key.
	 *
	 * @since 3.13.0
	 *
	 * @param array $schema Event meta-data schema (key => register_meta args).
	 *
	 * @return array
	 */
	public function register_meta( $schema ) {

		$schema['registration_form'] = [
			'type'              => 'string',
			'description'       => '',
			'single'            => true,
			'sanitize_callback' => null,
			'auth_callback'     => null,
			'show_in_rest'      => false,
		];

		return $schema;
	}

	/**
	 * Add the abandonment reminder to the task list.
	 *
	 * @since 3.13.0
	 *
	 * @param array $tasks Task class names.
	 *
	 * @return array
	 */
	public function register_tasks( $tasks ) {

		$tasks[] = ReminderTask::class;

		return $tasks;
	}
}
