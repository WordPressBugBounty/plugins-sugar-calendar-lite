<?php

namespace Sugar_Calendar\SmartTags\Ticketing;

use Sugar_Calendar\SmartTags\SmartTagInterface;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_order;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_ticket;

/**
 * An Event Ticketing Smart Tag: a `( $object_id, $object_type, $tag )` callable
 * plus the object type it answers for.
 *
 * The released `sc_email_tags` filter exposes these as arrays and lets a site
 * swap any callback per sender, so the callable is the definition and this class
 * converts to and from that row shape rather than replacing it.
 *
 * @since 3.14.0
 */
class CallbackSmartTag implements SmartTagInterface {

	/**
	 * Slug, without braces.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	private $tag;

	/**
	 * Dropdown label, the row's `description`.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Object type this tag answers for: order, event, ticket or attendee.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	private $type;

	/**
	 * The row's `function`, as given.
	 *
	 * @since 3.14.0
	 *
	 * @var mixed
	 */
	private $callback;

	/**
	 * Constructor.
	 *
	 * @since 3.14.0
	 *
	 * @param string $tag      Slug, without braces.
	 * @param string $label    Dropdown label.
	 * @param string $type     Object type.
	 * @param mixed  $callback The row's `function`.
	 */
	public function __construct( string $tag, string $label, string $type, $callback ) {

		$this->tag      = $tag;
		$this->label    = $label;
		$this->type     = $type;
		$this->callback = $callback;
	}

	/**
	 * Build a tag from a `sc_email_tags` row, or null when the row cannot resolve.
	 *
	 * @since 3.14.0
	 *
	 * @param array $row Row with `tag`, `description`, `function` and `type`.
	 *
	 * @return self|null
	 */
	public static function from_row( array $row ): ?self { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound -- Sugar Calendar requires PHP 7.4+.

		// The `?? ''` defaults below are load-bearing: SmartTagSecurityTest's
		// filter rows carry no `description` key, and today's setup() reads it
		// without one only because it stores the row array whole.
		$tag = isset( $row['tag'] ) && is_string( $row['tag'] ) ? $row['tag'] : '';

		if ( $tag === '' || ! isset( $row['function'] ) || ! is_callable( $row['function'] ) ) {
			return null;
		}

		return new self(
			$tag,
			isset( $row['description'] ) && is_scalar( $row['description'] ) ? (string) $row['description'] : '',
			isset( $row['type'] ) && is_scalar( $row['type'] ) ? (string) $row['type'] : '',
			$row['function']
		);
	}

	/**
	 * The `sc_email_tags` row for this tag, in the released key order.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public function to_row(): array {

		return [
			'tag'         => $this->tag,
			'description' => $this->label,
			'function'    => $this->callback,
			'type'        => $this->type,
		];
	}

	/**
	 * The callable as given, for the released rows and for identity checks.
	 *
	 * @since 3.14.0
	 *
	 * @return mixed
	 */
	public function callback() {

		return $this->callback;
	}

	/**
	 * Slug as authored, without braces.
	 *
	 * @since 3.14.0
	 */
	public function tag(): string {

		return $this->tag;
	}

	/**
	 * Editor dropdown label.
	 *
	 * @since 3.14.0
	 */
	public function label(): string {

		return $this->label;
	}

	/**
	 * The object type doubles as the dropdown heading key.
	 *
	 * @since 3.14.0
	 */
	public function group(): string {

		return $this->type;
	}

	/**
	 * Resolve for one object, or null to leave the tag literal.
	 *
	 * @since 3.14.0
	 *
	 * @param array $context The `object_id` and `object_type` of the render.
	 *
	 * @return string|null
	 */
	public function resolve( array $context ): ?string { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh, PHPCompatibility.FunctionDeclarations.NewNullableTypes.returnTypeFound -- One ownership boundary guards every registered callback; Sugar Calendar requires PHP 7.4+.

		$object_id   = isset( $context['object_id'] ) ? $context['object_id'] : null;
		$object_type = isset( $context['object_type'] ) ? $context['object_type'] : null;

		// An order has many tickets, so its id cannot identify one attendee or
		// ticket. Leave those tags literal instead of querying an unrelated row.
		if ( in_array( $this->type, [ 'ticket', 'attendee' ], true ) && $object_type !== 'ticket' ) {
			return null;
		}

		if ( $this->type === 'order' && $object_type !== 'order' ) {
			if ( $object_type !== 'ticket' ) {
				return null;
			}

			$ticket = get_ticket( $object_id );

			if ( empty( $ticket->order_id ) || ! get_order( $ticket->order_id ) ) {
				return '';
			}

			$object_id   = $ticket->order_id;
			$object_type = 'order';
		}

		return (string) call_user_func( $this->callback, $object_id, $object_type, $this->tag );
	}
}
