<?php

namespace Sugar_Calendar\Emails;

use Sugar_Calendar\Emails\Ticketing\TicketSaleAdminEmail;
use Sugar_Calendar\SmartTags\SmartTagResolver;
use Sugar_Calendar\SmartTags\Ticketing\CallbackSmartTag;
use Sugar_Calendar\SmartTags\Ticketing\DefaultTags;

/**
 * Ticket-sale admin Smart Tag pipeline for one sender.
 *
 * Owns the admin-only tag definitions, the bounded one-entry order-record
 * cache, and the opaque subject/body position tokens the legacy route
 * substitutes after `sc_email_message`. One instance per render frame, plus
 * one lazily kept per `Emails` instance for callbacks obtained outside a render.
 *
 * @since 3.14.0
 */
class AdminOrderTagResolver {

	/**
	 * The only email id whose sender carries this resolver.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public const EMAIL_ID = TicketSaleAdminEmail::ID;

	/**
	 * Order id of the active render, or null outside a frame.
	 *
	 * @since 3.14.0
	 *
	 * @var mixed
	 */
	private $object_id;

	/**
	 * Object type of the active render.
	 *
	 * @since 3.14.0
	 *
	 * @var mixed
	 */
	private $object_type;

	/**
	 * The render's tag registry, attached before initialize().
	 *
	 * @since 3.14.0
	 *
	 * @var TicketingSmartTags|null
	 */
	private $tags;

	/**
	 * Complete administrator order configuration for the active render.
	 *
	 * @since 3.14.0
	 *
	 * @var array|null
	 */
	private $render_config;

	/**
	 * Object-scoped cache for one administrator order configuration.
	 *
	 * @since 3.14.0
	 *
	 * @var array
	 */
	private $cache = [];

	/**
	 * Per-render state set by initialize() and prepare_subject().
	 *
	 * @since 3.14.0
	 *
	 * @var array
	 */
	private $state = [
		'active'            => false,
		'preview'           => false,
		'saved'             => false,
		'fictional'         => false,
		'event_available'   => true,
		'subject_positions' => [
			'immediate' => [],
			'deferred'  => [],
		],
	];

	/**
	 * Create a resolver for one sender.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $object_id   Order id, or null outside a render frame.
	 * @param mixed $object_type Object type, or null outside a render frame.
	 */
	public function __construct( $object_id = null, $object_type = null ) {

		$this->object_id   = $object_id;
		$this->object_type = $object_type;
	}

	/**
	 * Attach the render's tag registry.
	 *
	 * @since 3.14.0
	 *
	 * @param TicketingSmartTags $tags Registry for this render.
	 *
	 * @return self
	 */
	public function attach( TicketingSmartTags $tags ): self {

		$this->tags = $tags;

		return $this;
	}

	/**
	 * Get Smart Tags specific to ticket-sale administrator emails.
	 *
	 * @since 3.14.0
	 *
	 * @return CallbackSmartTag[]
	 */
	public function tag_definitions(): array {

		return [
			new CallbackSmartTag( 'order_time', esc_html__( 'Order Time', 'sugar-calendar-lite' ), 'order', [ $this, 'resolve_admin_order_tag' ] ),
			new CallbackSmartTag( 'transaction_id', esc_html__( 'Transaction ID', 'sugar-calendar-lite' ), 'order', [ $this, 'resolve_admin_order_tag' ] ),
			new CallbackSmartTag( 'order_admin_url', esc_html__( 'Order Admin URL', 'sugar-calendar-lite' ), 'order', [ $this, 'resolve_admin_order_tag' ] ),
			new CallbackSmartTag( 'ticket_count', esc_html__( 'Ticket Count', 'sugar-calendar-lite' ), 'order', [ $this, 'resolve_admin_order_tag' ] ),
			new CallbackSmartTag( 'admin_tickets_info', esc_html__( 'Admin Ticket Information', 'sugar-calendar-lite' ), 'order', [ $this, 'resolve_admin_order_tag' ] ),
			new CallbackSmartTag( 'event_end_date', esc_html__( 'Event End Date', 'sugar-calendar-lite' ), 'event', [ $this, 'resolve_admin_order_tag' ] ),
			new CallbackSmartTag( 'event_location', esc_html__( 'Event Location', 'sugar-calendar-lite' ), 'event', [ $this, 'resolve_admin_order_tag' ] ),
		];
	}

	/**
	 * Initialize administrator order state without building ticket records.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $source  Saved-order marker or fictional context.
	 * @param bool  $preview Whether this is a saved preview.
	 */
	public function initialize( $source, bool $preview ): void {

		$this->state['preview'] = $preview;

		if ( is_array( $source ) ) {
			$config = AdminOrderRecord::render_config_from_context( $source, true );

			if ( is_array( $config ) ) {
				$this->render_config            = $config;
				$this->state['active']          = true;
				$this->state['fictional']       = true;
				$this->state['event_available'] = $config['event_available'];
			}
		} elseif ( $source === true ) {
			$source_state = AdminOrderRecord::source_state( (int) $this->object_id );

			if ( is_array( $source_state ) ) {
				$this->state['active']          = true;
				$this->state['saved']           = true;
				$this->state['event_available'] = ! empty( $source_state['event_available'] );
			}
		}
	}

	/**
	 * Whether this render carries an administrator order.
	 *
	 * @since 3.14.0
	 *
	 * @return bool
	 */
	public function is_active(): bool {

		return $this->state['active'];
	}

	/**
	 * Tokenize the authored subject; immediate positions resolve now, deferred ones in finish().
	 *
	 * @since 3.14.0
	 *
	 * @param string $subject Unslashed authored subject.
	 *
	 * @return string
	 */
	public function prepare_subject( string $subject ): string {

		$positions = $this->prepare_subject_positions( $subject, $this->state['fictional'] );

		$this->state['subject_positions'] = [
			'immediate' => $positions['immediate'],
			'deferred'  => $positions['deferred'],
		];

		return $positions['subject'];
	}

	/**
	 * Finish the legacy body: load the record if needed, resolve deferred subject
	 * positions, tokenize and substitute body positions, prune empty optional lines.
	 *
	 * @since 3.14.0
	 *
	 * @param string $subject Parsed subject carrying position tokens.
	 * @param mixed  $body    Filtered legacy body.
	 *
	 * @return array { @type string $subject @type string $body }
	 */
	public function finish( string $subject, $body ): array {

		$body                 = is_scalar( $body ) ? (string) $body : '';
		$body_needs_record    = $this->content_needs_record( $body );
		$subject_needs_record = $this->positions_need_record( $this->state['subject_positions']['deferred'] );
		$body_has_receipt     = AdminOrderRecord::contains_receipt( $body );
		$native_receipt       = $body_has_receipt
			&& ! $this->tags->has_override( 'receipt_url' )
			&& $this->is_active_tag( 'receipt_url' )
			&& $this->is_native_callback( 'receipt_url', $this->tags->callback( 'receipt_url' ) );

		if (
			$this->state['saved']
			&& ( $body_needs_record || $subject_needs_record || $native_receipt )
		) {
			$this->load_render_config(
				(int) $this->object_id,
				$this->state['preview'],
				$native_receipt
			);
		}

		$optional_tags     = is_array( $this->render_config )
			? $this->render_config['optional_smart_tags']
			: AdminOrderRecord::optional_tags( $this->state['event_available'] );
		$subject_positions = array_merge(
			$this->state['subject_positions']['immediate'],
			$this->resolve_deferred_subject_positions( $this->state['subject_positions']['deferred'] )
		);
		$subject           = $this->apply_subject_positions( $subject, $subject_positions );
		$body_positions    = $this->prepare_body_positions( $body, $optional_tags );
		$body              = $this->tags->parse( $body_positions['body'] );

		if ( ! empty( $body_positions['positions'] ) ) {
			$body = strtr( $body, $body_positions['positions'] );
		}

		return [
			'subject' => $subject,
			'body'    => $body,
		];
	}

	/**
	 * Resolve one registered administrator order Smart Tag.
	 *
	 * @since 3.14.0
	 *
	 * @param mixed $object_id   Object id.
	 * @param mixed $object_type Object type.
	 * @param mixed $tag         Tag slug.
	 *
	 * @return string
	 */
	public function resolve_admin_order_tag( $object_id, $object_type, $tag ): string { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh -- One dispatch boundary covers every registered admin tag's guard clauses.

		if ( $object_type !== 'order' || ! is_string( $tag ) || ! in_array( $tag, AdminOrderRecord::new_tags(), true ) ) {
			return '';
		}

		$order_id = (int) $object_id;

		if ( ! $this->state['active'] ) {
			$config = $this->load_render_config( $order_id, false, false );
		} elseif ( $order_id === (int) $this->object_id ) {
			$config = $this->render_config;

			if ( $config === null ) {
				$config = $this->load_render_config( $order_id, false, false );
			}
		} else {
			$active_config = $this->render_config;

			try {
				$config = $this->load_render_config( $order_id, false, false );
			} finally {
				$this->render_config = $active_config;
			}
		}

		if ( ! is_array( $config ) || ! isset( $config['admin_tag_values'][ $tag ] ) ) {
			return '';
		}

		return is_string( $config['admin_tag_values'][ $tag ] ) ? $config['admin_tag_values'][ $tag ] : '';
	}

	/**
	 * Load and cache one complete administrator order configuration.
	 *
	 * @since 3.14.0
	 *
	 * @param int  $order_id        Order id.
	 * @param bool $preview         Whether this is a preview.
	 * @param bool $include_receipt Whether to issue a receipt URL.
	 *
	 * @return array|null
	 */
	private function load_render_config( int $order_id, bool $preview, bool $include_receipt ): ?array {

		$key = get_current_blog_id() . ':' . $order_id . ':' . (int) $preview . ':' . (int) $include_receipt;

		if ( array_key_exists( $key, $this->cache ) ) {
			$config              = $this->cache[ $key ];
			$this->render_config = is_array( $config ) ? $config : null;

			return $this->render_config;
		}

		$config              = AdminOrderRecord::render_config( $order_id, $preview, $include_receipt );
		$this->cache         = [ $key => $config ];
		$this->render_config = is_array( $config ) ? $config : null;

		return $this->render_config;
	}

	/**
	 * Protect administrator subject positions until their intended resolution time.
	 *
	 * @since 3.14.0
	 *
	 * @param string $subject   Authored subject.
	 * @param bool   $fictional Whether this is a fictional preview.
	 *
	 * @return array
	 */
	private function prepare_subject_positions( string $subject, bool $fictional ): array {

		$immediate  = [];
		$deferred   = [];
		$occurrence = 0;
		$prepared   = preg_replace_callback(
			SmartTagResolver::PATTERN,
			function ( $matches ) use ( $subject, $fictional, &$immediate, &$deferred, &$occurrence ) {

				$tag = $matches[1];

				if (
					! AdminOrderRecord::tracks_tag( $tag )
					|| ! $this->is_active_tag( $tag )
					|| in_array( $tag, [ 'tickets', 'admin_tickets_info', 'receipt_url' ], true )
				) {
					return $matches[0];
				}

				$token = $this->position_token( 'SUBJECT', $tag, $subject, array_merge( $immediate, $deferred ), $occurrence );

				++$occurrence;

				$defer = $fictional || in_array( $tag, AdminOrderRecord::subject_tags(), true );

				if ( $defer ) {
					$deferred[ $token ] = $tag;
				} else {
					$immediate[ $token ] = $this->resolve_position_value( $tag );
				}

				return $token;
			},
			$subject
		);

		return [
			'subject'   => is_string( $prepared ) ? $prepared : $subject,
			'immediate' => $immediate,
			'deferred'  => $deferred,
		];
	}

	/**
	 * Apply already-resolved opaque subject positions without reparsing output.
	 *
	 * @since 3.14.0
	 *
	 * @param string $subject   Tokenized content.
	 * @param array  $positions Token-to-value map.
	 *
	 * @return string
	 */
	private function apply_subject_positions( string $subject, array $positions ): string {

		return empty( $positions ) ? $subject : strtr( $subject, $positions );
	}

	/**
	 * Resolve subject positions that require the complete order record.
	 *
	 * @since 3.14.0
	 *
	 * @param array $positions Token-to-tag map.
	 *
	 * @return array
	 */
	private function resolve_deferred_subject_positions( array $positions ): array {

		$resolved = [];

		foreach ( $positions as $token => $tag ) {
			if ( is_string( $token ) && is_string( $tag ) ) {
				$resolved[ $token ] = $this->resolve_position_value( $tag );
			}
		}

		return $resolved;
	}

	/**
	 * Resolve tracked administrator body positions behind opaque tokens.
	 *
	 * @since 3.14.0
	 *
	 * @param string $body          Filtered legacy body.
	 * @param array  $optional_tags Optional tag slugs.
	 *
	 * @return array
	 */
	private function prepare_body_positions( string $body, array $optional_tags ): array {

		$positions    = [];
		$empty_tokens = [];
		$occurrence   = 0;
		$optional     = array_fill_keys( $optional_tags, true );
		$prepared     = preg_replace_callback(
			SmartTagResolver::PATTERN,
			function ( $matches ) use ( $body, $optional, &$positions, &$empty_tokens, &$occurrence ) {

				$tag = $matches[1];

				if ( ! AdminOrderRecord::tracks_tag( $tag ) || ! $this->is_active_tag( $tag ) ) {
					return $matches[0];
				}

				$token               = $this->position_token( 'BODY', $tag, $body, $positions, $occurrence );
				$value               = $this->resolve_position_value( $tag );
				$positions[ $token ] = $value;

				++$occurrence;

				if ( isset( $optional[ $tag ] ) && $value === '' ) {
					$empty_tokens[] = $token;
				}

				return $token;
			},
			$body
		);

		$prepared = is_string( $prepared ) ? $prepared : $body;

		return [
			'body'      => $this->prune_empty_position_lines( $prepared, $empty_tokens ),
			'positions' => $positions,
		];
	}

	/**
	 * Resolve and sanitize one selected administrator Smart Tag callback.
	 *
	 * @since 3.14.0
	 *
	 * @param string $tag Tag slug.
	 *
	 * @return string
	 */
	private function resolve_position_value( string $tag ): string { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded -- Native exceptions and selected replacements share one sanitization boundary.

		$override = $this->tags->override( $tag );
		$callback = $this->tags->callback( $tag );

		if ( $override !== null ) {
			$value = $override;
		} elseif ( $callback === null ) {
			return '';
		} else {
			$native = $this->is_native_callback( $tag, $callback );

			if ( $native && $tag === 'receipt_url' ) {
				$value = is_array( $this->render_config )
					? ( $this->render_config['prepared_tag_values']['receipt_url'] ?? '' )
					: '';
			} elseif (
				$native
				&& is_array( $this->render_config )
				&& ! empty( $this->render_config['fictional'] )
				&& array_key_exists( $tag, $this->render_config['prepared_tag_values'] )
			) {
				$value = $this->render_config['prepared_tag_values'][ $tag ];
			} else {
				$value = call_user_func( $callback, $this->object_id, $this->object_type, $tag );
			}

			$value = $tag === 'admin_tickets_info'
				? AdminNotificationText::block( $value )
				: AdminNotificationText::value( $value );

			if ( $native && $tag === 'event_title' && $value === '' && ! $this->state['event_available'] ) {
				$value = AdminNotificationText::value( __( 'Event details unavailable.', 'sugar-calendar-lite' ) );
			}

			return $value;
		}

		return $tag === 'admin_tickets_info'
			? AdminNotificationText::block( $value )
			: AdminNotificationText::value( $value );
	}

	/**
	 * Determine whether a selected callback is the built-in callback for a tag.
	 *
	 * @since 3.14.0
	 *
	 * @param string $tag      Tag slug.
	 * @param mixed  $callback Selected callback.
	 *
	 * @return bool
	 */
	private function is_native_callback( string $tag, $callback ): bool {

		if ( in_array( $tag, AdminOrderRecord::new_tags(), true ) ) {
			return is_array( $callback )
				&& count( $callback ) === 2
				&& $callback[0] === $this
				&& $callback[1] === 'resolve_admin_order_tag';
		}

		if ( ! AdminOrderRecord::tracks_tag( $tag ) ) {
			return false;
		}

		return DefaultTags::is_default_callback( $tag, $callback );
	}

	/**
	 * Determine whether an administrator tag has an active callable registration.
	 *
	 * @since 3.14.0
	 *
	 * @param string $tag Tag slug.
	 *
	 * @return bool
	 */
	private function is_active_tag( string $tag ): bool {

		return $this->tags !== null && $this->tags->is_registered( $tag );
	}

	/**
	 * Determine whether a selected tag needs the complete administrator record.
	 *
	 * @since 3.14.0
	 *
	 * @param string $tag Tag slug.
	 *
	 * @return bool
	 */
	private function tag_needs_record( string $tag ): bool {

		if (
			! in_array( $tag, AdminOrderRecord::new_tags(), true )
			|| ! $this->is_active_tag( $tag )
			|| $this->tags->has_override( $tag )
		) {
			return false;
		}

		return $this->is_native_callback( $tag, $this->tags->callback( $tag ) );
	}

	/**
	 * Determine whether authored content selects a complete-record consumer.
	 *
	 * @since 3.14.0
	 *
	 * @param string $content Authored content.
	 *
	 * @return bool
	 */
	private function content_needs_record( string $content ): bool {

		$count = preg_match_all( SmartTagResolver::PATTERN, $content, $matches );

		if ( ! is_int( $count ) || $count < 1 || empty( $matches[1] ) ) {
			return false;
		}

		foreach ( $matches[1] as $tag ) {
			if ( is_string( $tag ) && $this->tag_needs_record( $tag ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether deferred positions select a complete-record consumer.
	 *
	 * @since 3.14.0
	 *
	 * @param array $positions Token-to-tag map.
	 *
	 * @return bool
	 */
	private function positions_need_record( array $positions ): bool {

		foreach ( $positions as $tag ) {
			if ( is_string( $tag ) && $this->tag_needs_record( $tag ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build a collision-free opaque administrator position token.
	 *
	 * @since 3.14.0
	 *
	 * @param string $kind       Token kind.
	 * @param string $tag        Tag slug.
	 * @param string $content    Authored content.
	 * @param array  $used       Previously allocated tokens.
	 * @param int    $occurrence Occurrence index.
	 *
	 * @return string
	 */
	private function position_token( string $kind, string $tag, string $content, array $used, int $occurrence ): string {

		for ( $attempt = 0; $attempt < 100; ++$attempt ) {
			$hash  = substr( hash( 'sha256', $kind . "\0" . $tag . "\0" . $occurrence . "\0" . $attempt . "\0" . $content ), 0, 32 );
			$token = '__SC_ADMIN_' . $kind . '_' . $hash . '__';

			if ( false === strpos( $content, $token ) && ! array_key_exists( $token, $used ) ) {
				return $token;
			}
		}

		// Longer than the complete source, so this bounded fallback cannot collide.
		return '__SC_ADMIN_' . $kind . '_' . str_repeat( '0', strlen( $content ) + 1 ) . dechex( $occurrence ) . '__';
	}

	/**
	 * Remove authored lines whose optional position resolved empty.
	 *
	 * @since 3.14.0
	 *
	 * @param string $body         Tokenized body.
	 * @param array  $empty_tokens Empty optional-position tokens.
	 *
	 * @return string
	 */
	private function prune_empty_position_lines( string $body, array $empty_tokens ): string { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh -- One pass must evaluate every optional-position case to prune correctly.

		if ( empty( $empty_tokens ) ) {
			return $body;
		}

		$lines = preg_split( '/\R/u', $body );

		if ( ! is_array( $lines ) ) {
			return $body;
		}

		$kept = [];

		foreach ( $lines as $line ) {
			$remove = false;

			foreach ( $empty_tokens as $token ) {
				if ( false !== strpos( $line, $token ) ) {
					$remove = true;

					break;
				}
			}

			if ( ! $remove ) {
				$kept[] = $line;
			}
		}

		$body = implode( "\n", $kept );

		return trim( (string) preg_replace( '/\n{3,}/', "\n\n", $body ) );
	}
}
