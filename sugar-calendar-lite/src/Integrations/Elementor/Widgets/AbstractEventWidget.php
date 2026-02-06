<?php

namespace Sugar_Calendar\Integrations\Elementor\Widgets;

use Elementor\Widget_Base;
use Sugar_Calendar\Integrations\Elementor\Helpers;
use Elementor\Plugin as ElementorPlugin;
use Sugar_Calendar\AddOn\Ticketing\Helpers\Helpers as TicketingHelpers;
use Sugar_Calendar\AddOn\Ticketing\Common\Functions as TicketingFunctions;

/**
 * Abstract base class for Sugar Calendar Event widgets.
 *
 * Provides common functionality for widgets that display event-specific data.
 *
 * @since 3.10.0
 */
abstract class AbstractEventWidget extends Widget_Base {

	/**
	 * The Event object.
	 *
	 * @since 3.10.0
	 *
	 * @var \Sugar_Calendar\Event|false
	 */
	protected $event = null;

	/**
	 * The Event venue (Pro).
	 *
	 * @since 3.10.0
	 *
	 * @var array|false
	 */
	protected $event_venue = null;

	/**
	 * Only show Sugar Calendar widgets in Event post type or Loop Grid templates.
	 *
	 * @since 3.10.0
	 *
	 * @return bool
	 */
	public function show_in_panel() {

		if ( $this->should_display_placeholder() ) {
			return true;
		}

		$event = $this->get_event();

		return ! empty( $event );
	}

	/**
	 * Get the Event object.
	 *
	 * @since 3.10.0
	 *
	 * @return \Sugar_Calendar\Event|false
	 */
	protected function get_event() {

		if ( ! is_null( $this->event ) ) {
			return $this->event;
		}

		$post_id = get_the_ID();

		/**
		 * Filters the event object for the Elementor widget.
		 *
		 * @since 3.10.0
		 *
		 * @param \Sugar_Calendar\Event $event The event object.
		 * @param int                   $post_id The post ID.
		 */
		$event = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_integrations_elementor_widgets_get_event',
			sugar_calendar_get_event_by_object( $post_id ),
			$post_id
		);

		if ( empty( $event ) || empty( $event->id ) ) {
			$this->event = false;

			return $this->event;
		}

		$this->event = $event;

		return $this->event;
	}

	/**
	 * Get the event title.
	 *
	 * @since 3.10.0
	 *
	 * @return string
	 */
	protected function get_event_title() {

		return empty( $this->get_event() ) ? '' : $this->get_event()->title;
	}

	/**
	 * Whether the widget returns dynamic content.
	 *
	 * @since 3.10.0
	 *
	 * @return bool
	 */
	protected function is_dynamic_content(): bool {

		return true;
	}

	/**
	 * Get widget categories.
	 *
	 * @since 3.10.0
	 *
	 * @return array Widget categories.
	 */
	public function get_categories() {

		return [ 'sugar-calendar-event-elements-single' ];
	}

	/**
	 * Whether the buy ticket widget should be displayed.
	 *
	 * @since 3.10.0
	 *
	 * @return bool
	 */
	protected function should_display_buy_ticket_widget() {

		$event = $this->get_event();

		if ( empty( $event ) ) {
			return false;
		}

		if (
			! ElementorPlugin::$instance->editor->is_edit_mode() &&
			! is_singular( sugar_calendar_get_event_post_type_id() )
		) {
			return false;
		}

		if (
			empty( TicketingHelpers::get_event_remaining_tickets( $event ) ) ||
			! TicketingFunctions\should_display_tickets( $event )
		) {
			return false;
		}

		return true;
	}

	/**
	 * Get the event venue data.
	 *
	 * @since 3.10.0
	 *
	 * @return array|false
	 */
	protected function get_event_venue() {

		if ( ! is_null( $this->event_venue ) ) {
			return $this->event_venue;
		}

		$this->event_venue = false;
		$event             = $this->get_event();

		if ( empty( $event ) ) {
			return false;
		}

		$event_venue_data = sc_get_venue_data( $event->venue_id );

		if ( empty( $event_venue_data ) ) {
			return false;
		}

		$this->event_venue = $event_venue_data;

		return $this->event_venue;
	}

	/**
	 * Whether the widget is in editor mode.
	 *
	 * @since 3.10.0
	 *
	 * @return bool
	 */
	protected function is_in_editor_mode() {

		return ElementorPlugin::$instance->editor->is_edit_mode() || ElementorPlugin::$instance->preview->is_preview_mode();
	}

	/**
	 * Whether to display the placeholder.
	 *
	 * @since 3.10.0
	 *
	 * @return bool
	 */
	protected function should_display_placeholder() {

		return $this->is_in_loop_template() || $this->is_in_event_template_builder();
	}

	/**
	 * Whether the widget is in loop template context.
	 *
	 * @since 3.10.0
	 *
	 * @return bool
	 */
	protected function is_in_loop_template() {

		$document = ElementorPlugin::$instance->documents->get_current();

		return $this->is_in_editor_mode() && $document->get_template_type() === 'loop-item';
	}

	/**
	 * Whether the widget is in "event" template builder context.
	 *
	 * @since 3.10.0
	 *
	 * @return bool
	 */
	protected function is_in_event_template_builder() {

		$document = ElementorPlugin::$instance->documents->get_current();

		return $document->get_template_type() === sugar_calendar_get_event_post_type_id() && $this->is_in_editor_mode();
	}

	/**
	 * Whether to display the location widget.
	 *
	 * @since 3.10.0
	 *
	 * @return bool
	 */
	protected function should_display_location_widget() {

		$display = true;

		// Pro has "Venue".
		if ( sugar_calendar()->is_pro() ) {
			$display = false;
		}

		/**
		 * Filters whether to display the location widget.
		 *
		 * @since 3.10.0
		 *
		 * @param bool                                                               $display Whether to display the location widget.
		 * @param \Sugar_Calendar\Event                                              $event   The event object.
		 * @param \Sugar_Calendar\Integrations\Elementor\Widgets\AbstractEventWidget $this    The widget instance.
		 */
		$display = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_integrations_elementor_widgets_should_display_location_widget',
			$display,
			$this->get_event(),
			$this
		);

		return boolval( $display );
	}
}
