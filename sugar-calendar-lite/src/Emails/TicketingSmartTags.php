<?php

namespace Sugar_Calendar\Emails;

use Sugar_Calendar\AddOn\Ticketing\Emails;
use Sugar_Calendar\SmartTags\SmartTag;
use Sugar_Calendar\SmartTags\SmartTagInterface;
use Sugar_Calendar\SmartTags\SmartTagRegistry;
use Sugar_Calendar\SmartTags\SmartTagResolver;
use Sugar_Calendar\SmartTags\Ticketing\CallbackSmartTag;
use Sugar_Calendar\SmartTags\Ticketing\DefaultTags;

/**
 * Ticketing Smart Tag registry for one sender.
 *
 * Adapts the registry's registered ticketing definitions to the released
 * `sc_email_tags` row shape, and owns that filter, the per-render override
 * state, and `{tag}` parsing. One instance serves one render frame or one
 * out-of-render lookup; the sender is passed through to the filter and the
 * callbacks unchanged.
 *
 * @since 3.14.0
 */
class TicketingSmartTags {

	/**
	 * The sender every `sc_email_tags` callback and tag callback receives.
	 *
	 * @since 3.14.0
	 *
	 * @var Emails
	 */
	private $sender;

	/**
	 * Object id the tags resolve against.
	 *
	 * @since 3.14.0
	 *
	 * @var mixed
	 */
	private $object_id;

	/**
	 * Object type the tags resolve against (`order` or `ticket`).
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	private $object_type;

	/**
	 * Resolved values that replace registered callbacks for this render.
	 *
	 * @since 3.14.0
	 *
	 * @var array<string, string>
	 */
	private $overrides;

	/**
	 * Definitions appended to the defaults (the ticket-sale admin tags).
	 *
	 * @since 3.14.0
	 *
	 * @var CallbackSmartTag[]
	 */
	private $admin_definitions;

	/**
	 * Registered tags keyed by slug; null until setup().
	 *
	 * @since 3.14.0
	 *
	 * @var CallbackSmartTag[]|null
	 */
	private $tags;

	/**
	 * Create a registry for one sender.
	 *
	 * @since 3.14.0
	 *
	 * @param Emails             $sender            Sender passed to the filter and callbacks.
	 * @param mixed              $object_id         Object id.
	 * @param string             $object_type       Object type.
	 * @param array              $overrides         Slug => resolved string. Already filtered to string keys and values.
	 * @param CallbackSmartTag[] $admin_definitions Extra tag definitions appended to the defaults.
	 */
	public function __construct( Emails $sender, $object_id, $object_type, array $overrides = [], array $admin_definitions = [] ) {

		$this->sender            = $sender;
		$this->object_id         = $object_id;
		$this->object_type       = $object_type;
		$this->overrides         = $overrides;
		$this->admin_definitions = $admin_definitions;
	}

	/**
	 * Retrieve all registered email tags.
	 *
	 * @since 3.14.0
	 *
	 * @param string $object_type The tag type to return; order, ticket, event, attendee, or all.
	 *
	 * @return array
	 */
	public function get_tags( $object_type = 'order' ) {

		$email_tags = [];

		foreach ( SmartTagRegistry::for_scopes( [ DefaultTags::SCOPE ] ) as $tag ) {
			$email_tags[] = self::to_row( $tag );
		}

		foreach ( $this->admin_definitions as $tag ) {
			$email_tags[] = $tag->to_row();
		}

		if ( $object_type !== 'all' ) {
			$email_tags = wp_list_filter( $email_tags, [ 'type' => $object_type ] );
		}

		/**
		 * Filters the supported email tags and their attributes.
		 *
		 * @since 1.0
		 *
		 * @param array  $email_tags  {
		 *                            Email tags and their attributes.
		 *
		 * @type string   $tag         Email tag slug.
		 * @type string   $description Translatable description for what the email tag represents.
		 * @type callable $function    Callback function for rendering the email tag.
		 * @type string   $type        The object type this tag is for.
		 *                            }
		 * @param string $object_type The object type filter, or 'all'.
		 * @param Emails $sender      Email sender instance.
		 */
		return apply_filters( 'sc_email_tags', $email_tags, $object_type, $this->sender ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * The released row for one registered definition.
	 *
	 * A `CallbackSmartTag` round-trips byte for byte, which is what keeps the
	 * native-callback identity check meaningful. Any other implementation the
	 * registration action supplied is wrapped in the legacy callback signature:
	 * `to_row()` is not on the interface, and calling it unconditionally would
	 * fatal inside a checkout receipt send. The wrapped closure cannot decline:
	 * the legacy row shape has no way to express "leave `{tag}` literal", so a
	 * null `resolve()` becomes `''`, same as the released callback contract.
	 *
	 * @since 3.14.0
	 *
	 * @param SmartTagInterface $definition Registered definition.
	 *
	 * @return array
	 */
	private static function to_row( SmartTagInterface $definition ): array {

		if ( $definition instanceof CallbackSmartTag ) {
			return $definition->to_row();
		}

		return [
			'tag'         => $definition->tag(),
			'description' => $definition->label(),
			'function'    => static function ( $object_id, $object_type, $tag ) use ( $definition ) {

				$value = $definition->resolve(
					[
						'object_id'   => $object_id,
						'object_type' => $object_type,
					]
				);

				return $value === null ? '' : $value;
			},
			'type'        => $definition->group(),
		];
	}

	/**
	 * The tags one email's editor may offer, keyed by slug.
	 *
	 * Builds one throwaway sender — the old `from_ticketing()` built one per
	 * object type and unbound none of them — so the admin id still picks up its
	 * admin definitions through the facade's per-instance resolver and
	 * `sc_email_tags` still sees a sender.
	 *
	 * @since 3.14.0
	 *
	 * @param string $email_id Email id the sender reports its tags for.
	 *
	 * @return CallbackSmartTag[]
	 */
	public static function definitions_for( string $email_id ): array { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks -- The Emails constructor registers send hooks; this removes those exact callbacks in finally for a throwaway instance.

		$emails           = new Emails();
		$emails->email_id = $email_id;
		$definitions      = [];

		try {
			foreach ( $emails->get_tags( 'all' ) as $row ) {
				$tag = is_array( $row ) ? CallbackSmartTag::from_row( $row ) : null;

				if ( $tag !== null ) {
					$definitions[ $tag->tag() ] = $tag;
				}
			}
		} finally {
			// The Emails constructor binds these two to the throwaway instance, and
			// send_before() would then add wp_mail_from and content-type filters for
			// a sender with no object. PreviewDocument::render() unbinds the same way.
			remove_action( 'sc_email_send_before', [ $emails, 'send_before' ] );
			remove_action( 'sc_email_send_after', [ $emails, 'send_after' ] );
		}

		return $definitions;
	}

	/**
	 * Register every callable tag for this render.
	 *
	 * @since 3.14.0
	 */
	public function setup(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound -- Sugar Calendar requires PHP 7.4+.

		$this->tags = [];
		$rows       = $this->get_tags( 'all' );

		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$tag = is_array( $row ) ? CallbackSmartTag::from_row( $row ) : null;

			if ( $tag !== null ) {
				$this->tags[ $tag->tag() ] = $tag;
			}
		}
	}

	/**
	 * Search content for email tags and filter them through their callbacks.
	 *
	 * @since 3.14.0
	 *
	 * @param string $content Content to search for email tags.
	 * @param array  $skip    Tag slugs to leave unparsed.
	 *
	 * @return string
	 */
	public function parse( $content, array $skip = [] ) {

		// Deliberately not lazy: a parse before setup() returns the content
		// untouched, exactly as it does today.
		if ( empty( $this->tags ) || ! is_array( $this->tags ) ) {
			return $content;
		}

		$tags = [];

		foreach ( $this->tags as $slug => $tag ) {
			if ( in_array( $slug, $skip, true ) ) {
				continue;
			}

			$override = $this->override( $slug );

			$tags[ $slug ] = $override === null
				? $tag
				: new SmartTag(
					$slug,
					$tag->label(),
					$tag->group(),
					static function () use ( $override ) {

						return $override;
					}
				);
		}

		return SmartTagResolver::resolve(
			(string) $content,
			$tags,
			[
				'object_id'   => $this->object_id,
				'object_type' => $this->object_type,
			]
		);
	}

	/**
	 * Whether a slug is a registered email tag; sets up lazily.
	 *
	 * @since 3.14.0
	 *
	 * @param string $tag Tag slug.
	 *
	 * @return bool
	 */
	public function exists( $tag ): bool {

		if ( $this->tags === null ) {
			$this->setup();
		}

		return array_key_exists( $tag, $this->tags );
	}

	/**
	 * Whether a slug was registered by setup() and its callback is still callable.
	 *
	 * @since 3.14.0
	 *
	 * @param string $tag Tag slug.
	 *
	 * @return bool
	 */
	public function is_registered( string $tag ): bool {

		return isset( $this->tags[ $tag ] ) && is_callable( $this->tags[ $tag ]->callback() );
	}

	/**
	 * The registered callback for a slug, or null when absent or not callable.
	 *
	 * @since 3.14.0
	 *
	 * @param string $tag Tag slug.
	 *
	 * @return callable|null
	 */
	public function callback( string $tag ) {

		return isset( $this->tags[ $tag ] ) && is_callable( $this->tags[ $tag ]->callback() )
			? $this->tags[ $tag ]->callback()
			: null;
	}

	/**
	 * Whether an override was supplied for a slug, registered or not.
	 *
	 * @since 3.14.0
	 *
	 * @param string $tag Tag slug.
	 *
	 * @return bool
	 */
	public function has_override( string $tag ): bool {

		return array_key_exists( $tag, $this->overrides );
	}

	/**
	 * The override for a slug, only when one was supplied AND setup() registered the slug.
	 *
	 * @since 3.14.0
	 *
	 * @param string $tag Tag slug.
	 *
	 * @return string|null
	 */
	public function override( string $tag ): ?string { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound -- Sugar Calendar requires PHP 7.4+.

		return array_key_exists( $tag, $this->overrides ) && isset( $this->tags[ $tag ] )
			? $this->overrides[ $tag ]
			: null;
	}
}
