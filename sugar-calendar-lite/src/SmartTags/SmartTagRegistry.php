<?php

namespace Sugar_Calendar\SmartTags;

use Sugar_Calendar\SmartTags\Ticketing\DefaultTags;

/**
 * Every Smart Tag definition, grouped by the scope its owner registered it under.
 *
 * A scope is the audience: which senders may resolve this tag. It is not the
 * dropdown heading, which is `SmartTagInterface::group()`. RSVP's admin and
 * attendee emails resolve the same slugs differently under one heading, and
 * ticketing's admin-only tags sit under customer headings but must never reach a
 * customer email, so the two cannot be one value.
 *
 * Rebuilt on every read: there is no static store, so a tag registered mid-request
 * is visible on the next call.
 *
 * @since 3.14.0
 */
class SmartTagRegistry {

	/**
	 * Registered tags: scope => [ slug => SmartTagInterface ].
	 *
	 * @since 3.14.0
	 *
	 * @var array
	 */
	private $tags = [];

	/**
	 * The instance under construction while the registration action runs, else null.
	 *
	 * @since 3.14.0
	 *
	 * @var self|null
	 */
	private static $building = null;

	/**
	 * Only build() creates an instance, so a stray `new SmartTagRegistry()` cannot
	 * register into a registry nothing reads.
	 *
	 * @since 3.14.0
	 */
	private function __construct() {}

	/**
	 * Register a tag in a scope. A second registration of the same slug in the same
	 * scope replaces the first and keeps its position.
	 *
	 * @since 3.14.0
	 *
	 * @param string            $scope Audience scope.
	 * @param SmartTagInterface $tag   Tag to register.
	 */
	public function register( string $scope, SmartTagInterface $tag ): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound -- Sugar Calendar requires PHP 7.4+.

		$this->tags[ $scope ][ $tag->tag() ] = $tag;
	}

	/**
	 * Whether a slug is registered in a scope so far. For register-unless-present callbacks.
	 *
	 * @since 3.14.0
	 *
	 * @param string $scope Audience scope.
	 * @param string $tag   Slug.
	 *
	 * @return bool
	 */
	public function has( string $scope, string $tag ): bool {

		return isset( $this->tags[ $scope ][ $tag ] );
	}

	/**
	 * Every tag in the given scopes, keyed by slug, in scope order.
	 *
	 * A slug that appears in more than one scope keeps the position of its first
	 * appearance and takes the value of its last, which is what array assignment
	 * does and what the RSVP admin editors rely on when a record scope and an
	 * event scope both define one.
	 *
	 * @since 3.14.0
	 *
	 * @param array $scopes Scope names, in the order the caller wants them.
	 *
	 * @return SmartTagInterface[]
	 */
	public static function for_scopes( array $scopes ): array {

		$registry = self::build();
		$tags     = [];

		foreach ( $scopes as $scope ) {
			if ( ! is_string( $scope ) || ! isset( $registry->tags[ $scope ] ) ) {
				continue;
			}

			foreach ( $registry->tags[ $scope ] as $slug => $tag ) {
				$tags[ $slug ] = $tag;
			}
		}

		return $tags;
	}

	/**
	 * Build the registry: core's ticketing defaults, then the registration action.
	 *
	 * A read from inside the action returns the registry as built so far with a
	 * `_doing_it_wrong()` notice; a real rebuild would fire the action again
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
				esc_html__( 'A sugar_calendar_smart_tags_register callback read the registry while it was being built. Call has() on the registry the action passes instead.', 'sugar-calendar-lite' ),
				'3.14.0'
			);

			return self::$building;
		}

		$registry       = new self();
		self::$building = $registry;

		try {
			foreach ( DefaultTags::all() as $tag ) {
				$registry->register( DefaultTags::SCOPE, $tag );
			}

			/**
			 * Register Smart Tags.
			 *
			 * Fires on every registry read, on every request type. Callbacks call
			 * `$registry->register( $scope, $tag )` and may call `$registry->has()`.
			 * A callback that reads the registry through the static API instead gets
			 * the registry as built so far and a `_doing_it_wrong()` notice.
			 *
			 * A `Ticketing\CallbackSmartTag` in the `ticketing` scope keeps its
			 * exact released row, including the callable identity the native
			 * receipt check compares. Any other implementation is wrapped in the
			 * legacy callback signature when the ticketing adapter builds its rows.
			 *
			 * @since 3.14.0
			 *
			 * @param SmartTagRegistry $registry The registry being built.
			 */
			do_action( 'sugar_calendar_smart_tags_register', $registry ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		} finally {
			self::$building = null;
		}

		return $registry;
	}
}
