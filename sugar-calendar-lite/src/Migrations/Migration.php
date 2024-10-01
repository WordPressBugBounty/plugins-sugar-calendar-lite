<?php

namespace Sugar_Calendar\Migrations;

use Sugar_Calendar\Options;

/**
 * Class Migration helps migrate plugin options, DB tables and more.
 *
 * @since 3.0.0
 */
class Migration extends MigrationAbstract {

	/**
	 * Version of the latest migration.
	 *
	 * @since 3.0.0
	 *
	 * @var int
	 */
	const VERSION = 1;

	/**
	 * Option key where we save the current migration version.
	 *
	 * @since 3.0.0
	 *
	 * @var int
	 */
	const OPTION_NAME = 'sugar_calendar_migration_version';

	/**
	 * Current migration version, received from static::OPTION_NAME WP option.
	 *
	 * @since 3.0.0
	 *
	 * @var int
	 */
	protected $current_version;

	/**
	 * Mapping of legacy settings to new settings.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	protected $legacy_settings_map = [
		'sc_number_of_events'    => 'number_of_events',
		'sc_start_of_week'       => 'start_of_week',
		'sc_date_format'         => 'date_format',
		'sc_time_format'         => 'time_format',
		'sc_day_color_style'     => 'day_color_style',
		'sc_timezone_convert'    => 'timezone_convert',
		'sc_timezone_type'       => 'timezone_type',
		'sc_timezone'            => 'timezone',
		'sc_editor_type'         => 'editor_type',
		'sc_custom_fields'       => 'custom_fields',
		'sc_default_calendar'    => 'default_calendar',
		'sc_hide_announcements'  => 'hide_announcements',
		'sc_maps_google_api_key' => 'maps_google_api_key',
	];

	/**
	 * Migration from 0.x to 1.0.0.
	 * Move separate plugin WP options to one main plugin WP option setting.
	 *
	 * @since 3.0.0
	 */
	protected function migrate_to_1() {

		foreach ( $this->legacy_settings_map as $legacy_setting => $new_setting ) {
			$value = get_option( $legacy_setting, null );

			if ( $value !== null ) {
				Options::update( $new_setting, $value );
			}

			delete_option( $legacy_setting );
		}

		$this->update_db_ver( 1 );
	}
}
