<?php

namespace Sugar_Calendar\Emails;

use Sugar_Calendar\Emails\Ticketing\AttendeeTicketEmail;
use Sugar_Calendar\Emails\Ticketing\OrderReceiptEmail;
use Sugar_Calendar\Emails\Ticketing\TicketSaleAdminEmail;

/**
 * Every configurable email, resolved one way for every consumer.
 *
 * Two views. `definitions()` is what core and add-ons registered, read by
 * delivery and routing. `all()` is the same set after the released 3.7.0
 * listing filter, read by the admin screens. The filter shapes the listing
 * and never runs on a send path.
 *
 * Rebuilt on every read: there is no static store, so a filter or action
 * added mid-request is visible on the next call.
 *
 * @since 3.14.0
 */
class EmailRegistry {

	/**
	 * Group id for emails delivered to attendees / purchasers.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const GROUP_ATTENDEES = 'attendees';

	/**
	 * Group id for emails delivered to the site administrator.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	const GROUP_ADMIN = 'admin';

	/**
	 * Registered emails, keyed by id.
	 *
	 * @since 3.14.0
	 *
	 * @var EmailInterface[]
	 */
	private $definitions = [];

	/**
	 * The instance under construction while the registration action runs, else null.
	 *
	 * @since 3.14.0
	 *
	 * @var self|null
	 */
	private static $building = null;

	/**
	 * Only build() creates an instance, so a stray `new EmailRegistry()` cannot
	 * register into a registry nothing reads.
	 *
	 * @since 3.14.0
	 */
	private function __construct() {}

	/**
	 * Register an email. A second registration with the same id replaces the first.
	 *
	 * @since 3.14.0
	 *
	 * @param EmailInterface $email Email to register.
	 */
	public function register( EmailInterface $email ): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound -- Sugar Calendar requires PHP 7.4+.

		$this->definitions[ $email->id() ] = $email;
	}

	/**
	 * Whether an id is registered so far. For register-unless-present callbacks.
	 *
	 * @since 3.14.0
	 *
	 * @param string $id Email id.
	 *
	 * @return bool
	 */
	public function has( string $id ): bool {

		return isset( $this->definitions[ $id ] );
	}

	/**
	 * Every registered email, before the listing filter, keyed by id.
	 *
	 * @since 3.14.0
	 *
	 * @return EmailInterface[]
	 */
	public static function definitions(): array {

		return self::build()->definitions;
	}

	/**
	 * One registered email, before the listing filter.
	 *
	 * @since 3.14.0
	 *
	 * @param string $id Email id.
	 *
	 * @return EmailInterface|null
	 */
	public static function definition( string $id ): ?EmailInterface { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound -- Sugar Calendar requires PHP 7.4+.

		$definitions = self::definitions();

		return isset( $definitions[ $id ] ) ? $definitions[ $id ] : null;
	}

	/**
	 * Ids that route to an edit screen: every definition with option slugs.
	 *
	 * Reads `option_slugs()`, a pure value, never `editor()`, which builds
	 * values and applies the settings and styling filters.
	 *
	 * @since 3.14.0
	 *
	 * @return string[]
	 */
	public static function editable_ids(): array {

		$ids = [];

		foreach ( self::definitions() as $id => $email ) {
			if ( ! empty( $email->option_slugs() ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Every listed email after the released filter, keyed by id.
	 *
	 * @since 3.14.0
	 *
	 * @return EmailInterface[]
	 */
	public static function all(): array {

		$definitions = self::definitions();
		$rows        = [];

		foreach ( $definitions as $id => $email ) {
			$rows[ $id ] = self::to_row( $email );
		}

		/**
		 * Filters the emails to configure.
		 *
		 * @since 3.7.0
		 * @since 3.14.0 Entries may carry a `group` key (`attendees` or
		 *                  `admin`). A missing `group` is treated as
		 *                  `attendees` for backward compatibility. The filter
		 *                  shapes the Settings → Emails listing only; it cannot
		 *                  register or unregister an email.
		 *
		 * @param array $emails_to_configure {
		 *     Array containing the emails that can be configured, keyed by id.
		 *
		 *     @type string $title       Title of the email configuration.
		 *     @type string $description Description of the email configuration.
		 *     @type string $group       Card group: `attendees` or `admin`.
		 *     @type bool   $pro_only    Whether the row needs an inactive add-on.
		 *     @type bool   $sendable    Whether a sender exists for this email.
		 *                               Defaults to true; false renders the enable
		 *                               toggle disabled.
		 *     @type bool   $status_managed Whether the installed code enforces
		 *                               EmailStatus on an automatic send path for
		 *                               this email. Defaults to FALSE. Set it true
		 *                               only in the release where your sender
		 *                               actually reads EmailStatus::is_enabled() —
		 *                               the toggle is writable only when this and
		 *                               `sendable` are true and `pro_only` is false.
		 * }
		 */
		$rows = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_admin_settings_emails_tab_emails_to_configure',
			$rows
		);

		$emails = [];

		foreach ( (array) $rows as $id => $row ) {
			$id            = (string) $id;
			$emails[ $id ] = new ArrayEmail(
				$id,
				self::normalize( $row ),
				isset( $definitions[ $id ] ) ? $definitions[ $id ] : null
			);
		}

		return $emails;
	}

	/**
	 * Every listed email split into its card groups.
	 *
	 * Both group keys are always present, even when a group is empty, so
	 * consumers never have to guard on isset().
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public static function by_group(): array {

		$grouped = [
			self::GROUP_ADMIN     => [],
			self::GROUP_ATTENDEES => [],
		];

		foreach ( self::all() as $id => $email ) {
			$grouped[ $email->group() ][ $id ] = $email;
		}

		return $grouped;
	}

	/**
	 * One listed email after the released filter, or null when the filter
	 * removed its row. No fallback: a hidden row keeps its disabled toggle.
	 *
	 * @since 3.14.0
	 *
	 * @param string $id Email id.
	 *
	 * @return EmailInterface|null
	 */
	public static function get( string $id ): ?EmailInterface { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound -- Sugar Calendar requires PHP 7.4+.

		$emails = self::all();

		return isset( $emails[ $id ] ) ? $emails[ $id ] : null;
	}

	/**
	 * Whether an email's status toggle may be written by a save.
	 *
	 * Three predicates, and all three are load-bearing:
	 *
	 * - `sendable` — a sender exists at all.
	 * - `status_managed` — the installed code enforces the flag on an automatic
	 *   send. A sender with only manual callers has nothing to switch off.
	 * - `! pro_only` — the servicing add-on is active. A `pro_only` row renders an
	 *   upgrade prompt instead of a toggle, so it never posts a value.
	 *
	 * Defined once and shared by both render sites and the save path, so a control
	 * can never be rendered writable by one and rejected by the other — or worse,
	 * rendered disabled and written anyway.
	 *
	 * @since 3.14.0
	 *
	 * @param EmailInterface|null $email Listed email, or null for an unknown id.
	 *
	 * @return bool
	 */
	public static function is_status_writable( ?EmailInterface $email ): bool { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.typeDeclarationFound -- Sugar Calendar requires PHP 7.4+.

		return $email !== null
			&& $email->is_sendable()
			&& $email->is_status_managed()
			&& ! $email->is_pro_only();
	}

	/**
	 * Build the registry: core defaults, then the registration action.
	 *
	 * A read from inside the action returns the registry as built so far with
	 * a `_doing_it_wrong()` notice; a real rebuild would fire the action again
	 * without end.
	 *
	 * @since 3.14.0
	 *
	 * @return self
	 */
	private static function build(): self {

		if ( self::$building !== null ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'A sugar_calendar_emails_register callback read the registry while it was being built. Call has() on the registry the action passes instead.', 'sugar-calendar-lite' ),
				'3.14.0'
			);

			return self::$building;
		}

		$registry       = new self();
		self::$building = $registry;

		try {
			$upsell = self::upsell_rows();

			// This sequence reproduces the released registry's declaration order, which
			// the Settings → Emails card list and the stored `sugar_calendar_email_status`
			// option both surface. Core's classes are interleaved with the upsell rows on
			// purpose; grouping them ahead would reorder both.
			$registry->register( new OrderReceiptEmail() );
			$registry->register( new AttendeeTicketEmail() );
			$registry->register( self::upsell_row( $upsell, 'sc_rsvp_going_email_to_attendee' ) );
			$registry->register( self::upsell_row( $upsell, 'sc_rsvp_not_going_email_to_attendee' ) );
			$registry->register( new TicketSaleAdminEmail() );
			$registry->register( self::upsell_row( $upsell, 'sc_rsvp_going_email_to_admin' ) );
			$registry->register( self::upsell_row( $upsell, 'sc_rsvp_not_going_email_to_admin' ) );

			/**
			 * Register configurable emails.
			 *
			 * Fires on every registry read, on every request type. Callbacks call
			 * `$registry->register()` and may call `$registry->has()`. A callback
			 * that reads the registry through the static API instead gets the
			 * registry as built so far and a `_doing_it_wrong()` notice.
			 *
			 * @since 3.14.0
			 *
			 * @param EmailRegistry $registry The registry being built.
			 */
			do_action( 'sugar_calendar_emails_register', $registry ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		} finally {
			self::$building = null;
		}

		return $registry;
	}

	/**
	 * Listing rows for the RSVP add-on's emails when the add-on is not active.
	 *
	 * The add-on registers real objects with the same ids and replaces these.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	private static function upsell_rows(): array {

		return [
			// Core declares these false; the sc-rsvp release that makes
			// Rsvp::send_email() read EmailStatus opts them in through the filter.
			// That ordering is what keeps a new-core/old-add-on site from showing a
			// switch the installed add-on does not honour.
			'sc_rsvp_going_email_to_attendee'     => [
				'title'          => __( 'RSVP “Going” Email To Attendee', 'sugar-calendar-lite' ),
				'description'    => __( 'Confirmation email to attendees who RSVP as "going."', 'sugar-calendar-lite' ),
				'group'          => self::GROUP_ATTENDEES,
				'pro_only'       => true,
				'status_managed' => false,
			],
			'sc_rsvp_not_going_email_to_attendee' => [
				'title'          => __( 'RSVP “Not Going” Email To Attendee', 'sugar-calendar-lite' ),
				'description'    => __( 'Confirmation email to attendees who RSVP as "not going."', 'sugar-calendar-lite' ),
				'group'          => self::GROUP_ATTENDEES,
				'pro_only'       => true,
				'status_managed' => false,
			],
			// Core declares the two RSVP admin rows `sendable => false` /
			// `status_managed => false` even though sc-rsvp ships senders for
			// them. That is the version-skew guard, not a statement that no
			// sender exists: the add-on opts each row in from
			// EmailsConfig::add_emails_to_configure(), so new core paired with an
			// OLDER add-on renders a disabled toggle instead of a switch the
			// installed code ignores.
			'sc_rsvp_going_email_to_admin'        => [
				'title'          => __( 'RSVP “Going” Email To Admin', 'sugar-calendar-lite' ),
				'description'    => __( 'Notification email to admin when an attendee RSVP\'s as "going"', 'sugar-calendar-lite' ),
				'group'          => self::GROUP_ADMIN,
				'pro_only'       => true,
				'sendable'       => false,
				'status_managed' => false,
			],
			'sc_rsvp_not_going_email_to_admin'    => [
				'title'          => __( 'RSVP “Not Going” Email To Admin', 'sugar-calendar-lite' ),
				'description'    => __( 'Notification email to admin when an attendee RSVP\'s as "not going"', 'sugar-calendar-lite' ),
				'group'          => self::GROUP_ADMIN,
				'pro_only'       => true,
				'sendable'       => false,
				'status_managed' => false,
			],
		];
	}

	/**
	 * One upsell row as a listed email.
	 *
	 * @since 3.14.0
	 *
	 * @param array  $rows Rows from upsell_rows().
	 * @param string $id   Row id.
	 *
	 * @return ArrayEmail
	 */
	private static function upsell_row( array $rows, string $id ): ArrayEmail {

		return new ArrayEmail( $id, self::normalize( $rows[ $id ] ) );
	}

	/**
	 * The released filter row for one email.
	 *
	 * @since 3.14.0
	 *
	 * @param EmailInterface $email Email.
	 *
	 * @return array
	 */
	private static function to_row( EmailInterface $email ): array {

		return [
			'title'          => $email->title(),
			'description'    => $email->description(),
			'group'          => $email->group(),
			'pro_only'       => $email->is_pro_only(),
			'sendable'       => $email->is_sendable(),
			'status_managed' => $email->is_status_managed(),
		];
	}

	/**
	 * Fill in defaults for a single row.
	 *
	 * Third-party entries added through the filter may omit `group` and
	 * `pro_only`; an unrecognised group falls back to attendees rather than
	 * creating a phantom card group.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $email Row from the filter.
	 *
	 * @return array
	 */
	private static function normalize( $email ): array {

		$email = (array) $email;

		$email['title']       = isset( $email['title'] ) ? $email['title'] : '';
		$email['description'] = isset( $email['description'] ) ? $email['description'] : '';
		$email['pro_only']    = ! empty( $email['pro_only'] );

		// Defaults to TRUE, unlike pro_only. Absence has to mean "this email
		// sends", or every existing row — and every third-party row added through
		// the filter — would render a disabled toggle the moment this key landed.
		$email['sendable'] = ! isset( $email['sendable'] ) || (bool) $email['sendable'];

		// Defaults to FALSE, unlike sendable — and the asymmetry is the point.
		// `sendable` asks "does a sender exist"; this asks "does the installed code
		// enforce EmailStatus on an automatic send". Defaulting it true would hand
		// every third-party row a working-looking switch its sender never reads,
		// which is exactly the lie the toggles were rewired to stop telling. An
		// extension opts in only in the release where its sender honours the flag.
		$email['status_managed'] = ! empty( $email['status_managed'] );

		if ( empty( $email['group'] ) || ! in_array( $email['group'], [ self::GROUP_ADMIN, self::GROUP_ATTENDEES ], true ) ) {
			$email['group'] = self::GROUP_ATTENDEES;
		}

		return $email;
	}
}
