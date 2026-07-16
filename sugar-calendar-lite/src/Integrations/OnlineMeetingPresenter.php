<?php

namespace Sugar_Calendar\Integrations;

/**
 * Renders the "Join Link" block for front-end and email surfaces.
 *
 * Pure presentation — callers decide WHETHER to show the block (via
 * OnlineMeeting::is_public() or their own attendee gate); this class only
 * decides HOW it looks. Front-end markup mirrors the single-event details
 * rows so it sits naturally next to Date / Time / Location.
 *
 * @since 3.12.0
 */
class OnlineMeetingPresenter {

	/**
	 * Front-end details-row block (single event page, RSVP box).
	 *
	 * @since 3.12.0
	 *
	 * @param array $details Output of OnlineMeeting::for_event().
	 *
	 * @return string Escaped HTML.
	 */
	public static function front_end_html( array $details ) {

		$out  = '<div class="sc-frontend-single-event__details__online sc-frontend-single-event__details-row">';
		$out .= '<div class="sc-frontend-single-event__details__label">';
		$out .= esc_html__( 'Online:', 'sugar-calendar-lite' );
		$out .= '</div>';
		$out .= '<div class="sc-frontend-single-event__details__val">';
		$out .= '<a href="' . esc_url( $details['join_url'] ) . '" target="_blank" rel="noopener noreferrer">';

		if ( $details['provider_name'] !== '' ) {
			/* translators: %s - online meeting provider name (e.g. Zoom). */
			$out .= sprintf( esc_html__( 'Join %s meeting', 'sugar-calendar-lite' ), esc_html( $details['provider_name'] ) );
		} else {
			$out .= esc_html__( 'Join meeting', 'sugar-calendar-lite' );
		}

		$out .= '</a>';

		if ( $details['password'] !== '' ) {
			/* translators: %s - meeting password. */
			$out .= ' <span class="sc-frontend-single-event__details__online-password">'
				. sprintf( esc_html__( '(Password: %s)', 'sugar-calendar-lite' ), esc_html( $details['password'] ) )
				. '</span>';
		}

		$out .= '</div></div>';

		return $out;
	}

	/**
	 * HTML email block (ticketing + RSVP HTML attendee emails).
	 *
	 * @since 3.12.0
	 *
	 * @param array $details Output of OnlineMeeting::for_event().
	 *
	 * @return string Escaped HTML.
	 */
	public static function email_html( array $details ) {

		$out = '<div class="sc-online-meeting-email" style="margin:16px 0;padding:12px 16px;background:#f6f7f7;border-radius:4px;">';

		if ( $details['provider_name'] !== '' ) {
			/* translators: %s - online meeting provider name (e.g. Zoom). */
			$heading = sprintf( esc_html__( 'Join the %s meeting', 'sugar-calendar-lite' ), esc_html( $details['provider_name'] ) );
		} else {
			$heading = esc_html__( 'Join the meeting', 'sugar-calendar-lite' );
		}

		$out .= '<p style="margin:0 0 8px;font-weight:bold;">' . $heading . '</p>';
		$out .= '<p style="margin:0;"><a href="' . esc_url( $details['join_url'] ) . '">'
			. esc_html( $details['join_url'] ) . '</a></p>';

		if ( $details['password'] !== '' ) {
			/* translators: %s - meeting password. */
			$out .= '<p style="margin:8px 0 0;">'
				. sprintf( esc_html__( 'Password: %s', 'sugar-calendar-lite' ), esc_html( $details['password'] ) )
				. '</p>';
		}

		$out .= '</div>';

		return $out;
	}

	/**
	 * Plain-text email block (RSVP plain-text attendee emails).
	 *
	 * @since 3.12.0
	 *
	 * @param array $details Output of OnlineMeeting::for_event().
	 *
	 * @return string Plain text (caller is responsible for context).
	 */
	public static function email_text( array $details ) {

		if ( $details['provider_name'] !== '' ) {
			/* translators: %s - online meeting provider name (e.g. Zoom). */
			$heading = sprintf( __( 'Join the %s meeting:', 'sugar-calendar-lite' ), $details['provider_name'] );
		} else {
			$heading = __( 'Join the meeting:', 'sugar-calendar-lite' );
		}

		$out  = "\n\n" . $heading . "\n";
		$out .= $details['join_url'];

		if ( $details['password'] !== '' ) {
			/* translators: %s - meeting password. */
			$out .= "\n" . sprintf( __( 'Password: %s', 'sugar-calendar-lite' ), $details['password'] );
		}

		return $out;
	}
}
