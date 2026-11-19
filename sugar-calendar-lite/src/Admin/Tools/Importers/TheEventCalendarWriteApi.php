<?php

namespace Sugar_Calendar\Admin\Tools\Importers;

/**
 * Public entry points to the protected write methods of `Importer`, for the TEC migration steps.
 *
 * `Importer` keeps its methods protected, as released: a subclass can widen visibility, the base cannot take it back.
 *
 * @since 3.18.0
 */
trait TheEventCalendarWriteApi {

	/**
	 * Create a new Sugar Calendar event.
	 *
	 * @since 3.18.0
	 *
	 * @param array $data The event data.
	 *
	 * @return false|array Returns `false` if unable to create Sugar Calendar event.
	 */
	public function create_sc_event( $data ) { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- Widens the visibility.

		return parent::create_sc_event( $data );
	}

	/**
	 * Get or create an attendee.
	 *
	 * @since 3.18.0
	 *
	 * @param string $email      The email of the attendee.
	 * @param string $first_name The first name of the attendee.
	 * @param string $last_name  The last name of the attendee.
	 *
	 * @return int
	 */
	public function get_or_create_sc_attendee( $email, $first_name, $last_name ) { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- Widens the visibility.

		return parent::get_or_create_sc_attendee( $email, $first_name, $last_name );
	}

	/**
	 * Log errors.
	 *
	 * @since 3.18.0
	 *
	 * @param string $context The context of the error.
	 * @param mixed  $data    The data of the error.
	 */
	public function log_errors( $context, $data ) { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- Widens the visibility.

		parent::log_errors( $context, $data );
	}

	/**
	 * Get the HTML display of the errors.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_error_html_display() { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- Widens the visibility.

		return parent::get_error_html_display();
	}

	/**
	 * Get the errors transient key.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_errors_transient_key() { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- Widens the visibility.

		return parent::get_errors_transient_key();
	}
}
