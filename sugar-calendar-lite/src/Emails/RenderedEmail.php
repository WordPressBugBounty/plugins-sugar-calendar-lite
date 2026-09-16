<?php

namespace Sugar_Calendar\Emails;

/**
 * One rendered email: the values `Emails::render()` returns plus the
 * route-final html flag `send()` needs for the wp_mail window.
 *
 * @since 3.14.0
 */
class RenderedEmail {

	/**
	 * Final single-line subject.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	private $subject;

	/**
	 * Final message body. Normally a string; a third-party `sc_email_message` filter may return anything.
	 *
	 * @since 3.14.0
	 *
	 * @var mixed
	 */
	private $message;

	/**
	 * Effective filtered content type.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	private $content_type;

	/**
	 * Calendar action for the sender, or null.
	 *
	 * @since 3.14.0
	 *
	 * @var array|null
	 */
	private $calendar_action;

	/**
	 * Route-final html value.
	 *
	 * @since 3.14.0
	 *
	 * @var mixed
	 */
	private $html;

	/**
	 * Create one rendered email result.
	 *
	 * @since 3.14.0
	 *
	 * @param string     $subject         Final single-line subject.
	 * @param mixed      $message         Final message body.
	 * @param string     $content_type    Effective filtered content type.
	 * @param array|null $calendar_action Calendar action for the sender.
	 * @param mixed      $html            Route-final html value.
	 */
	public function __construct( string $subject, $message, string $content_type, ?array $calendar_action, $html ) {

		$this->subject         = $subject;
		$this->message         = $message;
		$this->content_type    = $content_type;
		$this->calendar_action = $calendar_action;
		$this->html            = $html;
	}

	/**
	 * Final single-line subject.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	public function subject(): string {

		return $this->subject;
	}

	/**
	 * Final message body.
	 *
	 * @since 3.14.0
	 *
	 * @return mixed
	 */
	public function message() {

		return $this->message;
	}

	/**
	 * Effective filtered content type.
	 *
	 * @since 3.14.0
	 *
	 * @return string
	 */
	public function content_type(): string {

		return $this->content_type;
	}

	/**
	 * Calendar action for the sender, or null.
	 *
	 * @since 3.14.0
	 *
	 * @return array|null
	 */
	public function calendar_action(): ?array {

		return $this->calendar_action;
	}

	/**
	 * Route-final html value. Not part of to_array(); only send() reads it.
	 *
	 * @since 3.14.0
	 *
	 * @return mixed
	 */
	public function html() {

		return $this->html;
	}

	/**
	 * The public `Emails::render()` shape.
	 *
	 * @since 3.14.0
	 *
	 * @return array { @type string $subject @type string $message @type string $content_type @type array|null $calendar_action }
	 */
	public function to_array(): array {

		return [
			'subject'         => $this->subject,
			'message'         => $this->message,
			'content_type'    => $this->content_type,
			'calendar_action' => $this->calendar_action,
		];
	}
}
