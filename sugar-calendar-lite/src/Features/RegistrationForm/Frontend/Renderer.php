<?php

namespace Sugar_Calendar\Features\RegistrationForm\Frontend;

use Sugar_Calendar\Features\RegistrationForm\SchemaRepository;
use Sugar_Calendar\Features\RegistrationForm\SchemaValidator;
use Sugar_Calendar\Helpers as Sugar_Calendar_Helpers;
use Sugar_Calendar\Helpers\WP;

/**
 * Renders the registration step and owns the one applicability predicate.
 *
 * for_event() is the single gate for the whole front end; every other class is
 * null-guarded off it. Markup is emitted once as a <template> carrying __KEY__
 * placeholders that the controller clones per attendee. Errors are always painted
 * by JS into the empty error nodes, since both hosts are modals that never reload.
 *
 * @since 3.13.0
 */
class Renderer {

	/**
	 * Placeholder the controller replaces with each attendee's key.
	 *
	 * @since 3.13.0
	 *
	 * @var string
	 */
	const KEY_PLACEHOLDER = '__KEY__';

	/**
	 * Attributes that must be unique per attendee.
	 *
	 * A clone that keeps one `name` makes every attendee post under one key, so
	 * PHP keeps only the last; a duplicated id rebinds a label or error region to
	 * the wrong attendee's field.
	 *
	 * @since 3.13.0
	 *
	 * @var string[]
	 */
	const KEY_ATTRIBUTES = [ 'name', 'id', 'for', 'aria-controls', 'aria-describedby', 'data-attendee-key' ];

	/**
	 * Sugar Calendar event id.
	 *
	 * @since 3.13.0
	 *
	 * @var int
	 */
	private $event_id;

	/**
	 * Stored schema.
	 *
	 * @since 3.13.0
	 *
	 * @var array
	 */
	private $schema;

	/**
	 * Constructor. Use for_event().
	 *
	 * @since 3.13.0
	 *
	 * @param int   $event_id Sugar Calendar event id.
	 * @param array $schema   Stored schema.
	 */
	private function __construct( $event_id, array $schema ) {

		$this->event_id = (int) $event_id;
		$this->schema   = $schema;
	}

	/**
	 * Resolve a renderer for an event, or null when no form should render.
	 *
	 * @since 3.13.0
	 *
	 * @param int $event_id Sugar Calendar event id.
	 *
	 * @return self|null
	 */
	public static function for_event( $event_id ) {

		$event_id = (int) $event_id;

		if ( $event_id <= 0 ) {
			return null;
		}

		// Event meta survives a Pro -> Lite downgrade and SchemaRepository::get()
		// has no Lite gate of its own, so the read side needs one here.
		if ( ! sugar_calendar()->is_pro() ) {
			return null;
		}

		$schema = SchemaRepository::get( $event_id );

		if ( empty( $schema['enabled'] ) || empty( $schema['fields'] ) ) {
			return null;
		}

		// WooCommerce-mediated purchases go through
		// Integrations\WooCommerce::create_tickets(), which never calls
		// Checkout::complete(), so none of this feature's seams fire there.
		// Collecting answers we could never store would be worse than nothing.
		if ( get_event_meta( $event_id, 'woocommerce_checkout', true ) ) {
			return null;
		}

		return new self( $event_id, $schema );
	}

	/**
	 * Sugar Calendar event id.
	 *
	 * @since 3.13.0
	 *
	 * @return int
	 */
	public function event_id() {

		return $this->event_id;
	}

	/**
	 * Stored schema.
	 *
	 * @since 3.13.0
	 *
	 * @return array
	 */
	public function schema() {

		return $this->schema;
	}

	/**
	 * When the form is collected.
	 *
	 * @since 3.13.0
	 *
	 * @return string Either 'before' or 'after'.
	 */
	public function mode() {

		return ( $this->schema['show'] ?? 'before_checkout' ) === 'after_checkout' ? 'after' : 'before';
	}

	/**
	 * Whether the form is collected per attendee rather than once.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	public function collects_per_attendee() {

		return ( $this->schema['collect'] ?? 'main_attendee' ) === 'each_attendee';
	}

	/**
	 * The attendees this form applies to.
	 *
	 * The one applicability predicate: both the rendered attendee list and
	 * ResponseGate consume this output, so they cannot disagree. Keys are checked
	 * against AnswerRequest::KEY_PATTERN here because a bare numeric-string key
	 * would be coerced to an int, turning the encoded error map into a JSON array.
	 *
	 * @since 3.13.0
	 *
	 * @param array[] $attendees              Each entry: [ 'key' => 'a{n}', 'ticket_type' => int ].
	 * @param bool    $apply_ticket_targeting Whether ticket_types targeting applies. False for hosts
	 *                                        with no ticket types (RSVP), where it drops everyone.
	 *
	 * @return array[] The same shape, filtered.
	 */
	public function applicable_attendees( array $attendees, $apply_ticket_targeting = true ) {

		if ( ! $this->collects_per_attendee() ) {
			return [
				[
					'key'         => AnswerRequest::MAIN_KEY,
					'ticket_type' => 0,
				],
			];
		}

		$types = $this->schema['ticket_types'] ?? 'all';
		$out   = [];

		foreach ( $attendees as $attendee ) {

			$key = isset( $attendee['key'] ) ? (string) $attendee['key'] : '';

			if ( ! preg_match( AnswerRequest::KEY_PATTERN, $key ) ) {
				continue;
			}

			/*
			 * Client-supplied, and not what the purchase is priced from. On its own it
			 * would let a buyer retarget their attendee block at a type the form does
			 * not cover and skip the form while still paying correctly. What prevents
			 * that lives in sc-event-ticketing: Checkout::get_cart_attendee_mismatch_error()
			 * requires per-type quantity parity between $_POST['cart'] and
			 * $_POST['attendees'], so a disagreeing ticket_type fails checkout outright.
			 * If that parity check is ever relaxed, resolve the type from the validated
			 * cart instead.
			 */
			$type = isset( $attendee['ticket_type'] ) ? (int) $attendee['ticket_type'] : 0;

			// 'all' (the default, and what single-ticket events get) skips the check:
			// a single-ticket row posts no ticket_type, and that 0 must not be read
			// as "belongs to no targeted type".
			if ( $apply_ticket_targeting && is_array( $types ) && ! in_array( $type, $types, true ) ) {
				continue;
			}

			$out[] = [
				'key'         => $key,
				'ticket_type' => $type,
			];
		}

		return $out;
	}

	/**
	 * Render the whole step: root, header, empty attendee list, template.
	 *
	 * The controller fills the attendee list, since quantity and attendee rows
	 * change client-side after this markup is printed.
	 *
	 * @since 3.13.0
	 *
	 * @param string $context Either 'order' or 'rsvp'.
	 *
	 * @return string
	 */
	public function render_step( $context ) {

		ob_start();
		?>
		<div
			class="sc-regform sc-regform-step"
			data-context="<?php echo esc_attr( $context ); ?>"
			data-event-id="<?php echo absint( $this->event_id ); ?>"
			data-mode="<?php echo esc_attr( $this->mode() ); ?>"
			data-collect="<?php echo esc_attr( $this->collects_per_attendee() ? 'each_attendee' : 'main_attendee' ); ?>"
			data-ticket-types="<?php echo esc_attr( wp_json_encode( $this->schema['ticket_types'] ?? 'all' ) ); ?>"
			data-cta-label="<?php esc_attr_e( 'Continue', 'sugar-calendar-lite' ); ?>"
			hidden
		>
			<div class="sc-regform__intro">
				<h3 class="sc-regform__title"><?php esc_html_e( 'Additional Details for Attendees', 'sugar-calendar-lite' ); ?></h3>
				<p class="sc-regform__description"><?php esc_html_e( 'Enter the details of additional attendees that will attend this event with you', 'sugar-calendar-lite' ); ?></p>
			</div>

			<?php
			/*
			 * Emitted before the attendee blocks: max_input_vars truncation drops
			 * trailing POST vars first, so a counter placed after what it counts
			 * would be the first thing lost.
			 */
			?>
			<input type="hidden" name="<?php echo esc_attr( AnswerRequest::COUNT_FIELD ); ?>" value="" disabled />

			<div class="sc-regform__attendees"></div>

			<?php echo $this->render_template(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped at each field. ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Render the step's Back control, for the host's own footer.
	 *
	 * Separate from render_step() because Back belongs in the modal footer beside
	 * Cancel and the CTA, which the host prints itself through a footer-scoped seam.
	 *
	 * @since 3.13.0
	 *
	 * @return string
	 */
	public function render_footer_back() {

		return sprintf(
			'<button type="button" class="sc-regform__back" hidden>%1$s</button>',
			esc_html__( 'Back', 'sugar-calendar-lite' )
		);
	}

	/**
	 * Render the whole form with the attendee blocks already stamped.
	 *
	 * The after-checkout sibling of render_step(). Here the pending rows are the
	 * attendee list, so real keys are emitted directly and stored answers prefilled.
	 * `display_name` and `ticket_label` are resolved by the host, whose attendee
	 * storage differs; both are escaped on output, since any buyer can set a name.
	 *
	 * @since 3.13.0
	 *
	 * @param array[] $rows    Each entry: [ 'attendee_key' => string, 'display_name' => string,
	 *                         'ticket_label' => string, 'answers' => array ].
	 * @param array   $summary The host's recap for the summary column, in
	 *                         AfterCheckoutSummary's shape. Empty renders no column.
	 *
	 * @return string
	 */
	public function render_static( array $rows, array $summary = [] ) {

		if ( $rows === [] ) {
			return '';
		}

		$last = count( $rows ) - 1;

		ob_start();
		?>
		<div
			class="sc-regform sc-regform-static"
			data-event-id="<?php echo absint( $this->event_id ); ?>"
			data-mode="<?php echo esc_attr( $this->mode() ); ?>"
		>
			<div class="sc-regform__layout">
				<div class="sc-regform__main">
					<div class="sc-regform__intro">
						<h3 class="sc-regform__title"><?php esc_html_e( 'Additional Details for Attendees', 'sugar-calendar-lite' ); ?></h3>
						<p class="sc-regform__description"><?php esc_html_e( 'Enter the details of additional attendees that will attend this event with you', 'sugar-calendar-lite' ); ?></p>
					</div>

					<?php
					/*
					 * Emitted before the attendee blocks: max_input_vars truncation drops
					 * trailing POST vars first, so a counter placed after what it counts
					 * would be the first thing lost.
					 */
					?>
					<input
						type="hidden"
						name="<?php echo esc_attr( AnswerRequest::COUNT_FIELD ); ?>"
						value="<?php echo absint( $this->leaf_count( $rows ) ); ?>"
					/>

					<div class="sc-regform__attendees">
						<?php
						foreach ( array_values( $rows ) as $position => $row ) {
							$this->render_static_attendee( $row, $position === 0, $position < $last );
						}
						?>
					</div>
				</div>

				<?php echo AfterCheckoutSummary::render( $summary ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped per value inside render(). ?>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Render one already-stamped attendee block.
	 *
	 * Only the first block starts expanded, as in the design and in the
	 * before-checkout step (attendees.js). Every block but the last carries the
	 * Next Attendee control; on the last there is nothing to advance to, and
	 * step.js's goToNextAttendee() would simply collapse the form out of sight.
	 *
	 * @since 3.13.0
	 *
	 * @param array $row      One render_static() row.
	 * @param bool  $expanded Whether this block starts open.
	 * @param bool  $has_next Whether another block follows this one.
	 */
	private function render_static_attendee( array $row, $expanded = true, $has_next = false ) {

		$key   = isset( $row['attendee_key'] ) ? (string) $row['attendee_key'] : '';
		$name  = isset( $row['display_name'] ) ? (string) $row['display_name'] : '';
		$label = isset( $row['ticket_label'] ) ? (string) $row['ticket_label'] : '';

		if ( ! preg_match( AnswerRequest::KEY_PATTERN, $key ) ) {
			return;
		}

		$answers = isset( $row['answers'] ) && is_array( $row['answers'] ) ? $row['answers'] : [];
		$state   = $expanded ? 'true' : 'false';
		?>
		<div class="sc-regform__attendee" data-attendee-key="<?php echo esc_attr( $key ); ?>" data-expanded="<?php echo esc_attr( $state ); ?>">
			<?php $this->render_static_attendee_header( $key, $name, $label, $state ); ?>
			<div class="sc-regform__attendee-body" id="sc-regform-body-<?php echo esc_attr( $key ); ?>">
				<?php
				foreach ( (array) $this->schema['fields'] as $field ) {
					$this->render_field( $field, $key, $answers[ $field['id'] ] ?? null, false );
				}
				?>
				<?php if ( $has_next ) : ?>
					<button type="button" class="sc-regform__next"><?php esc_html_e( 'Next Attendee', 'sugar-calendar-lite' ); ?></button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render one attendee block's collapse header.
	 *
	 * @since 3.13.0
	 *
	 * @param string $key   Attendee key.
	 * @param string $name  Display name.
	 * @param string $label Ticket-type label, or '' when the host has none.
	 * @param string $state Either 'true' or 'false' — the block's expanded state.
	 */
	private function render_static_attendee_header( $key, $name, $label, $state ) {

		?>
		<button
			type="button"
			class="sc-regform__attendee-header"
			aria-expanded="<?php echo esc_attr( $state ); ?>"
			aria-controls="sc-regform-body-<?php echo esc_attr( $key ); ?>"
		>
			<span class="sc-regform__attendee-name"><?php echo esc_html( $name ); ?></span>
			<?php if ( $label !== '' ) : ?>
				<span class="sc-regform__attendee-sep" aria-hidden="true"></span>
				<span class="sc-regform__attendee-ticket"><?php echo esc_html( $label ); ?></span>
			<?php endif; ?>
			<span class="sc-regform__attendee-chevron" aria-hidden="true"></span>
		</button>
		<?php
	}

	/**
	 * The number of answer leaves this form will post when fully filled.
	 *
	 * Stamped into COUNT_FIELD so a POST truncated by max_input_vars reads as
	 * truncation instead of a validation error the visitor cannot clear.
	 *
	 * @since 3.13.0
	 *
	 * @param array[] $rows The render_static() rows.
	 *
	 * @return int
	 */
	private function leaf_count( array $rows ) {

		return count( $rows ) * count( (array) $this->schema['fields'] );
	}

	/**
	 * Render the per-attendee template.
	 *
	 * @since 3.13.0
	 *
	 * @return string
	 */
	public function render_template() {

		$key = self::KEY_PLACEHOLDER;

		ob_start();
		?>
		<template id="sc-regform-template">
			<div class="sc-regform__attendee" data-attendee-key="<?php echo esc_attr( $key ); ?>" data-expanded="false">
				<button
					type="button"
					class="sc-regform__attendee-header"
					aria-expanded="false"
					aria-controls="sc-regform-body-<?php echo esc_attr( $key ); ?>"
				>
					<span class="sc-regform__attendee-name"></span>
					<span class="sc-regform__attendee-sep" aria-hidden="true"></span>
					<span class="sc-regform__attendee-ticket"></span>
					<span class="sc-regform__attendee-chevron" aria-hidden="true"></span>
				</button>
				<div class="sc-regform__attendee-body" id="sc-regform-body-<?php echo esc_attr( $key ); ?>">
					<?php
					foreach ( (array) $this->schema['fields'] as $field ) {
						$this->render_field( $field, $key );
					}
					?>
					<button type="button" class="sc-regform__next"><?php esc_html_e( 'Next Attendee', 'sugar-calendar-lite' ); ?></button>
				</div>
			</div>
		</template>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Enqueue the step's stylesheet and controller.
	 *
	 * Called from the render seam, on the instance that already ran the gate, so
	 * assets and markup can never disagree. The ticketing modal renders on
	 * wp_footer:10 and WordPress prints footer assets at wp_footer:20, so
	 * enqueuing at render time is early enough.
	 *
	 * @since 3.13.0
	 */
	public function enqueue() {

		wp_enqueue_style(
			'sc-frontend-registration-form',
			SC_PLUGIN_ASSETS_URL . 'css/frontend/registration-form' . WP::asset_min() . '.css',
			[],
			Sugar_Calendar_Helpers::get_asset_version()
		);

		/*
		 * One handle, one file. The controller is developed as several scripts under
		 * `frontend/parts/` that `gulp js:bundles` concatenates in declared order
		 * into `frontend/general.js` (see gulpfile-config.json "jsBundles"), so the
		 * pieces are never enqueued individually.
		 */
		wp_enqueue_script(
			'sc-registration-form',
			SC_PLUGIN_ASSETS_URL . 'js/features/registration-form/frontend/general' . WP::asset_min() . '.js',
			[ 'jquery' ],
			Sugar_Calendar_Helpers::get_asset_version(),
			true
		);

		wp_localize_script(
			'sc-registration-form',
			'sc_registration_form_obj',
			[
				// Unescaped on purpose: errors.js and shared.js assign these to
				// textContent, which escapes them, so esc_html__() here would show the
				// entity in a translation that contains an apostrophe.
				'strings' => [
					'required'         => __( 'This field is required.', 'sugar-calendar-lite' ),
					'invalid_option'   => __( 'Please choose one of the available options.', 'sugar-calendar-lite' ),
					'generic'          => __( 'Please check this answer.', 'sugar-calendar-lite' ),
					'attendeeFallback' => __( 'Attendee', 'sugar-calendar-lite' ),
				],
			]
		);
	}

	/**
	 * Render one field.
	 *
	 * @since 3.13.0
	 *
	 * @param array             $field    Field definition.
	 * @param string            $key      Attendee key (or KEY_PLACEHOLDER).
	 * @param string|array|null $value    Stored answer to prefill, or null for an empty field.
	 * @param bool              $disabled Whether the control(s) carry the disabled attribute. The
	 *                                    template shell stays disabled so jQuery.serialize() skips
	 *                                    it; render_static()'s controls are the ones that post.
	 */
	private function render_field( array $field, $key, $value = null, $disabled = true ) {

		$id       = (string) $field['id'];
		$type     = (string) $field['type'];
		$input_id = 'sc-regform-' . $key . '-' . $id;
		$error_id = $input_id . '-error';

		?>
		<div class="sc-regform__field" data-field-id="<?php echo esc_attr( $id ); ?>" data-type="<?php echo esc_attr( $type ); ?>">
			<?php $this->render_label( $field, $input_id, in_array( $type, SchemaValidator::CHOICE_TYPES, true ) && $type !== 'dropdown' ); ?>
			<?php $this->render_control( $field, $key, $input_id, $error_id, $value, $disabled ); ?>
			<p class="sc-regform__error" id="<?php echo esc_attr( $error_id ); ?>" role="alert"></p>
		</div>
		<?php
	}

	/**
	 * Render a field's label.
	 *
	 * Radio and checkbox groups get a plain <span>: a label has no single control to
	 * bind to, and binding it to the first option would make clicking the group
	 * title select that option.
	 *
	 * @since 3.13.0
	 *
	 * @param array  $field    One field definition from the schema.
	 * @param string $input_id Control id.
	 * @param bool   $is_group Whether the control is a radio/checkbox group.
	 */
	private function render_label( array $field, $input_id, $is_group ) {

		$tag = $is_group ? 'span' : 'label';
		$for = $is_group ? '' : ' for="' . esc_attr( $input_id ) . '"';

		echo '<' . esc_html( $tag ) . ' class="sc-regform__label"' . $for . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $for is escaped above.
		echo esc_html( $field['label'] );

		if ( ! empty( $field['required'] ) ) {
			echo '<span class="sc-regform__required" aria-hidden="true">*</span>';
		}

		echo '</' . esc_html( $tag ) . '>';
	}

	/**
	 * Render a field's control(s).
	 *
	 * @since 3.13.0
	 *
	 * @param array             $field    One field definition from the schema.
	 * @param string            $key      Attendee-key placeholder.
	 * @param string            $input_id Control id.
	 * @param string            $error_id Error node id.
	 * @param string|array|null $value    Stored answer to prefill, or null for an empty field.
	 * @param bool              $disabled Whether the control(s) carry the disabled attribute.
	 */
	private function render_control( array $field, $key, $input_id, $error_id, $value = null, $disabled = true ) {

		$name     = 'registration[' . $key . '][' . $field['id'] . ']';
		$required = ! empty( $field['required'] );

		switch ( $field['type'] ) {
			case 'long_text':
				$this->render_textarea( $name, $input_id, $error_id, $required, $value, $disabled );
				break;

			case 'dropdown':
				$this->render_dropdown( $field, $name, $input_id, $error_id, $required, $value, $disabled );
				break;

			case 'radio':
			case 'checkbox':
				$this->render_choice_group( $field, $key, $name, $error_id, $value, $disabled );
				break;

			default: // short_text.
				$this->render_text_input( $name, $input_id, $error_id, $required, $value, $disabled );
				break;
		}
	}

	/**
	 * Render a long_text control.
	 *
	 * @since 3.13.0
	 *
	 * @param string            $name     Control name.
	 * @param string            $input_id Control id.
	 * @param string            $error_id Error node id.
	 * @param bool              $required Whether the field is required.
	 * @param string|array|null $value    Stored answer to prefill, or null for an empty field.
	 * @param bool              $disabled Whether the control carries the disabled attribute.
	 */
	private function render_textarea( $name, $input_id, $error_id, $required, $value = null, $disabled = true ) {

		printf(
			'<textarea id="%1$s" name="%2$s" class="sc-regform__input sc-regform__textarea" rows="3" aria-describedby="%3$s"%4$s%5$s>%6$s</textarea>',
			esc_attr( $input_id ),
			esc_attr( $name ),
			esc_attr( $error_id ),
			$required ? ' required' : '',
			$disabled ? ' disabled' : '',
			esc_textarea( is_scalar( $value ) ? (string) $value : '' )
		);
	}

	/**
	 * Render a short_text control.
	 *
	 * @since 3.13.0
	 *
	 * @param string            $name     Control name.
	 * @param string            $input_id Control id.
	 * @param string            $error_id Error node id.
	 * @param bool              $required Whether the field is required.
	 * @param string|array|null $value    Stored answer to prefill, or null for an empty field.
	 * @param bool              $disabled Whether the control carries the disabled attribute.
	 */
	private function render_text_input( $name, $input_id, $error_id, $required, $value = null, $disabled = true ) {

		printf(
			'<input type="text" id="%1$s" name="%2$s" class="sc-regform__input" aria-describedby="%3$s"%4$s%5$s%6$s />',
			esc_attr( $input_id ),
			esc_attr( $name ),
			esc_attr( $error_id ),
			$required ? ' required' : '',
			( $value !== null && is_scalar( $value ) ) ? ' value="' . esc_attr( (string) $value ) . '"' : '',
			$disabled ? ' disabled' : ''
		);
	}

	/**
	 * Render a dropdown.
	 *
	 * @since 3.13.0
	 *
	 * @param array             $field    One field definition from the schema.
	 * @param string            $name     Control name.
	 * @param string            $input_id Control id.
	 * @param string            $error_id Error node id.
	 * @param bool              $required Whether the field is required.
	 * @param string|array|null $value    Stored answer to prefill, or null for an empty field.
	 * @param bool              $disabled Whether the control carries the disabled attribute.
	 */
	private function render_dropdown( array $field, $name, $input_id, $error_id, $required, $value = null, $disabled = true ) {

		// The wrapper carries the caret: a native select's own arrow is UA-drawn, so
		// its size and inset cannot be styled to match the attendee-header chevron.
		?>
		<div class="sc-regform__select-wrap">
			<select
				id="<?php echo esc_attr( $input_id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				class="sc-regform__input sc-regform__select"
				aria-describedby="<?php echo esc_attr( $error_id ); ?>"
				<?php echo $required ? 'required' : ''; ?>
				<?php echo $disabled ? 'disabled' : ''; ?>
			>
				<option value=""><?php esc_html_e( 'Select an option', 'sugar-calendar-lite' ); ?></option>
				<?php foreach ( (array) $field['options'] as $option ) : ?>
					<option value="<?php echo esc_attr( $option ); ?>"<?php echo ( $value !== null && (string) $value === (string) $option ) ? ' selected' : ''; ?>><?php echo esc_html( $option ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	/**
	 * Render a radio or checkbox group.
	 *
	 * Native inputs styled to match the design; the design's exported radio and
	 * checkbox images would break keyboard and assistive-technology behaviour.
	 *
	 * @since 3.13.0
	 *
	 * @param array             $field    One field definition from the schema.
	 * @param string            $key      Attendee-key placeholder.
	 * @param string            $name     Base control name.
	 * @param string            $error_id Error node id.
	 * @param string|array|null $value    Stored answer(s) to prefill, or null for an empty field.
	 * @param bool              $disabled Whether the control(s) carry the disabled attribute.
	 */
	private function render_choice_group( array $field, $key, $name, $error_id, $value = null, $disabled = true ) {

		$is_checkbox = $field['type'] === 'checkbox';
		$input_name  = $is_checkbox ? $name . '[]' : $name;
		$values      = (array) $value;

		?>
		<div class="sc-regform__choices" role="group" aria-describedby="<?php echo esc_attr( $error_id ); ?>">
			<?php foreach ( (array) $field['options'] as $index => $option ) : ?>
				<?php $option_id = 'sc-regform-' . $key . '-' . $field['id'] . '-' . (int) $index; ?>
				<span class="sc-regform__choice">
					<input
						type="<?php echo $is_checkbox ? 'checkbox' : 'radio'; ?>"
						id="<?php echo esc_attr( $option_id ); ?>"
						name="<?php echo esc_attr( $input_name ); ?>"
						value="<?php echo esc_attr( $option ); ?>"
						<?php echo ( $value !== null && in_array( (string) $option, array_map( 'strval', $values ), true ) ) ? 'checked ' : ''; ?><?php echo $disabled ? 'disabled' : ''; ?>
					/>
					<label class="sc-regform__choice-label" for="<?php echo esc_attr( $option_id ); ?>"><?php echo esc_html( $option ); ?></label>
				</span>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
