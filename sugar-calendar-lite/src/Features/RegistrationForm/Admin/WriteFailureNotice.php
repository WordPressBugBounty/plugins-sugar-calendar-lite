<?php

namespace Sugar_Calendar\Features\RegistrationForm\Admin;

use Sugar_Calendar\Features\RegistrationForm\ResponsesTableMigration;
use Sugar_Calendar\Features\RegistrationForm\WriteFailureLog;
use Sugar_Calendar\Integrations\Admin\PrintsDismissScript;

/**
 * Admin notice: registration answers could not be stored.
 *
 * Reads two channels nothing else surfaces: WriteFailureLog (rows dropped
 * after the charge, so nothing can retry them) and the responses-table
 * migration's error option, since a stalled table is a likely cause of the
 * failures. Dismissing is safe either way; a failed migration retries on the
 * next admin_init and rewrites the option if the problem persists.
 *
 * @since 3.13.0
 */
class WriteFailureNotice {

	use PrintsDismissScript;

	/**
	 * Nonce action for the dismiss request.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const DISMISS_NONCE = 'sc_dismiss_registration_write_failures';

	/**
	 * AJAX action for the dismiss request.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const DISMISS_ACTION = 'sc_dismiss_registration_write_failures';

	/**
	 * Register hooks.
	 *
	 * @since 3.13.0
	 *
	 * @return void
	 */
	public function hooks() {

		add_action( 'admin_notices', [ $this, 'maybe_render' ] );
		add_action( 'wp_ajax_' . self::DISMISS_ACTION, [ $this, 'ajax_dismiss' ] );
	}

	/**
	 * Render the notice when there is something to report.
	 *
	 * @since 3.13.0
	 *
	 * @return void
	 */
	public function maybe_render() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! sugar_calendar()->get_admin()->is_admin_page() ) {
			return;
		}

		$count           = WriteFailureLog::count();
		$migration_error = (string) get_option( ResponsesTableMigration::ERROR_OPTION_NAME, '' );

		if ( $count === 0 && $migration_error === '' ) {
			return;
		}

		$dismiss_url = add_query_arg(
			[
				'action' => self::DISMISS_ACTION,
				'nonce'  => wp_create_nonce( self::DISMISS_NONCE ),
			],
			admin_url( 'admin-ajax.php' )
		);

		printf(
			'<div class="notice notice-error is-dismissible" data-sc-registration-write-failures="1" data-sc-dismiss-url="%1$s">%2$s</div>',
			esc_url( $dismiss_url ),
			// Each paragraph is escaped as it is built below.
			$this->body( $count, $migration_error ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);

		$this->print_dismiss_script( '.notice[data-sc-registration-write-failures]' );
	}

	/**
	 * The notice's paragraphs.
	 *
	 * @since 3.13.0
	 *
	 * @param int    $count           Recorded write failures.
	 * @param string $migration_error The responses-table migration error, or ''.
	 *
	 * @return string
	 */
	private function body( $count, $migration_error ) {

		$out = $count > 0 ? $this->failure_paragraphs( $count ) : '';

		if ( $migration_error !== '' ) {
			$out .= '<p>' . esc_html(
				sprintf(
					/* translators: %s: database error reported by the migration. */
					__( 'The registration responses table did not finish upgrading: %s', 'sugar-calendar-lite' ),
					$migration_error
				)
			) . '</p>';
		}

		$out .= '<p>' . esc_html__(
			'Those answers were not stored and cannot be recovered automatically — contact the attendees if you need the information. Ask your host to check the database if this repeats.',
			'sugar-calendar-lite'
		) . '</p>';

		return $out;
	}

	/**
	 * The how-many paragraph, plus the most recent failure's detail.
	 *
	 * The detail is the only place the cause is ever stated — nothing on this path
	 * writes to a log — so it carries the database's own message, not just a code.
	 *
	 * @since 3.13.0
	 *
	 * @param int $count Recorded write failures. Always > 0 here.
	 *
	 * @return string
	 */
	private function failure_paragraphs( $count ) {

		$out = '<p>' . esc_html(
			sprintf(
				/* translators: %d: number of registration answers that could not be saved. */
				_n(
					'Sugar Calendar could not save %d registration answer submitted by an attendee.',
					'Sugar Calendar could not save %d registration answers submitted by attendees.',
					$count,
					'sugar-calendar-lite'
				),
				$count
			)
		) . '</p>';

		$latest = WriteFailureLog::latest();

		if ( $latest === null ) {
			return $out;
		}

		return $out . '<p>' . esc_html(
			sprintf(
				/* translators: %1$s: host type, "order" or "rsvp". %2$d: host id. %3$s: UTC date and time. %4$s: error reported by the database. */
				__( 'Most recent: %1$s #%2$d at %3$s UTC — %4$s', 'sugar-calendar-lite' ),
				isset( $latest['context'] ) ? (string) $latest['context'] : '',
				isset( $latest['context_id'] ) ? (int) $latest['context_id'] : 0,
				isset( $latest['at'] ) ? (string) $latest['at'] : '',
				$this->reason( $latest )
			)
		) . '</p>';
	}

	/**
	 * The human-facing cause of one failure.
	 *
	 * Prefers the database's own message and falls back to the error code, which is
	 * all an older recorded entry carries.
	 *
	 * @since 3.13.0
	 *
	 * @param array $entry One recorded failure.
	 *
	 * @return string
	 */
	private function reason( array $entry ) {

		if ( ! empty( $entry['message'] ) ) {
			return (string) $entry['message'];
		}

		return isset( $entry['code'] ) ? (string) $entry['code'] : '';
	}

	/**
	 * Clear both records when an administrator dismisses the notice.
	 *
	 * @since 3.13.0
	 *
	 * @return void
	 */
	public function ajax_dismiss() {

		if ( ! check_ajax_referer( self::DISMISS_NONCE, 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'bad_nonce' ], 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}

		WriteFailureLog::clear();
		delete_option( ResponsesTableMigration::ERROR_OPTION_NAME );

		wp_send_json_success();
	}
}
