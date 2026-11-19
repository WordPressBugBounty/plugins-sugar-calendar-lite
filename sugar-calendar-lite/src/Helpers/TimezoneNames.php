<?php

namespace Sugar_Calendar\Helpers;

/**
 * Timezone names tzdata has retired, and their current names.
 *
 * @since 3.18.0
 */
class TimezoneNames {

	/**
	 * Timezones tzdata renamed or merged into another, mapped to the current name.
	 *
	 * An importer keeps the name a site chose years ago; PHP lists only current names, so the old
	 * one would fail Sugar Calendar's validation and leave the event without a timezone.
	 *
	 * @since 3.18.0
	 *
	 * @var string[]
	 */
	const RENAMED = [
		'America/Buenos_Aires' => 'America/Argentina/Buenos_Aires',
		'America/Godthab'      => 'America/Nuuk',
		'America/Indianapolis' => 'America/Indiana/Indianapolis',
		'America/Montreal'     => 'America/Toronto',
		'America/Nipigon'      => 'America/Toronto',
		'America/Pangnirtung'  => 'America/Iqaluit',
		'America/Rainy_River'  => 'America/Winnipeg',
		'America/Thunder_Bay'  => 'America/Toronto',
		'America/Yellowknife'  => 'America/Edmonton',
		'Asia/Calcutta'        => 'Asia/Kolkata',
		'Asia/Choibalsan'      => 'Asia/Ulaanbaatar',
		'Asia/Dacca'           => 'Asia/Dhaka',
		'Asia/Katmandu'        => 'Asia/Kathmandu',
		'Asia/Rangoon'         => 'Asia/Yangon',
		'Asia/Saigon'          => 'Asia/Ho_Chi_Minh',
		'Asia/Thimbu'          => 'Asia/Thimphu',
		'Asia/Ujung_Pandang'   => 'Asia/Makassar',
		'Asia/Ulan_Bator'      => 'Asia/Ulaanbaatar',
		'Atlantic/Faeroe'      => 'Atlantic/Faroe',
		'Australia/Currie'     => 'Australia/Hobart',
		'Europe/Belfast'       => 'Europe/London',
		'Europe/Kiev'          => 'Europe/Kyiv',
		'Europe/Uzhgorod'      => 'Europe/Kyiv',
		'Europe/Zaporozhye'    => 'Europe/Kyiv',
		'Pacific/Enderbury'    => 'Pacific/Kanton',
		'Pacific/Johnston'     => 'Pacific/Honolulu',
		'Pacific/Ponape'       => 'Pacific/Pohnpei',
		'Pacific/Truk'         => 'Pacific/Chuuk',
	];

	/**
	 * The current name of a timezone stored under a name tzdata has since retired.
	 *
	 * @since 3.18.0
	 *
	 * @param string $timezone A timezone name.
	 *
	 * @return string
	 */
	public static function get_current( $timezone ) {

		$current = self::RENAMED[ $timezone ] ?? '';

		// Older PHP still lists the old name and may not know the new one.
		if ( $current === '' || in_array( $timezone, timezone_identifiers_list(), true ) || ! in_array( $current, timezone_identifiers_list(), true ) ) {
			return $timezone;
		}

		return $current;
	}
}
