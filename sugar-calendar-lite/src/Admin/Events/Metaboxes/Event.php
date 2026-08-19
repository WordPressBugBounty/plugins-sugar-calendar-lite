<?php

namespace Sugar_Calendar\Admin\Events\Metaboxes;

use Sugar_Calendar\Admin\Events\MetaboxInterface;
use Sugar_Calendar\Event as EventRow;
use Sugar_Calendar\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Helpers\Helpers as ScHelpers;
use Sugar_Calendar\Common\Editor;

/**
 * Event metabox.
 *
 * @since 3.0.0
 */
class Event implements MetaboxInterface {

	/**
	 * Sections.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	public $sections = [];

	/**
	 * ID of the currently selected section.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $current_section = 'duration';

	/**
	 * The event for this meta box.
	 *
	 * @since 2.0.0
	 *
	 * @var Event
	 */
	public $event = false;

	/**
	 * Metabox ID.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_id() {

		return 'sugar_calendar_editor_event_details';
	}

	/**
	 * Metabox title.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_title() {

		return esc_html__( 'Sugar Calendar Event Settings', 'sugar-calendar-lite' );
	}

	/**
	 * Metabox screen.
	 *
	 * @since 3.0.0
	 *
	 * @return string|array|WP_Screen
	 */
	public function get_screen() {

		return get_post_types_by_support( [ 'events' ] );
	}

	/**
	 * Metabox context.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_context() {

		return 'normal';
	}

	/**
	 * Metabox priority.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_priority() {

		return 'high';
	}

	/**
	 * Metabox constructor.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_Post $post Current post.
	 */
	public function __construct( $post ) {

		$this->setup_post( $post );
		$this->setup_sections();

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.0.0
	 */
	private function hooks() {

		add_filter( 'sugar_calendar_event_to_save', [ $this, 'save_post' ] );

		add_action( 'sugar_calendar_admin_area_enqueue_assets', [ $this, 'set_script_translations' ] );
	}

	/**
	 * Setup default sections.
	 *
	 * @since 2.0.3
	 */
	public function setup_sections() {

		// Duration.
		$this->add_section(
			[
				'id'       => 'duration',
				'label'    => esc_html__( 'Duration', 'sugar-calendar-lite' ),
				'icon'     => 'clock',
				'order'    => 10,
				'callback' => [ $this, 'section_duration' ],
			]
		);

		// Location.
		$this->add_section(
			[
				'id'       => 'location',
				'label'    => esc_html__( 'Location', 'sugar-calendar-lite' ),
				'icon'     => 'location',
				'order'    => 50,
				'callback' => [ $this, 'section_location' ],
			]
		);

		// Legacy support.
		if ( has_action( 'sc_event_meta_box_before' ) || has_action( 'sc_event_meta_box_after' ) ) {

			// Legacy.
			$this->add_section(
				[
					'id'       => 'legacy',
					'label'    => esc_html__( 'Other', 'sugar-calendar-lite' ),
					'icon'     => 'admin-settings',
					'order'    => 200,
					'callback' => [ $this, 'section_legacy' ],
				]
			);
		}

		/**
		 * Fires after metabox default sections are being registered.
		 *
		 * @since 3.0.0
		 *
		 * @param MetaboxInterface $metabox Metabox instance.
		 */
		do_action( 'sugar_calendar_admin_meta_box_setup_sections', $this ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Add a section.
	 *
	 * @since 2.0.0
	 *
	 * @param array $section Section data.
	 */
	public function add_section( $section = [] ) {

		// Bail if empty or not array.
		if ( empty( $section ) || ! is_array( $section ) ) {
			return;
		}

		// Construct the section.
		$section = new EventSection( $section );

		// Bail if section was not created.
		if ( empty( $section->id ) ) {
			return;
		}

		// Add the section.
		$this->sections[ $section->id ] = $section;

		// Always resort after adding.
		$this->sort_sections();
	}

	/**
	 * Remove a section.
	 *
	 * @since 3.5.0
	 *
	 * @param string $section_id Section ID.
	 *
	 * @return void
	 */
	public function remove_section( $section_id = '' ) {

		// Bail if empty.
		if ( empty( $section_id ) ) {
			return;
		}

		// Check if the section exists.
		if ( ! isset( $this->sections[ $section_id ] ) ) {
			return;
		}

		// Unset the section.
		unset( $this->sections[ $section_id ] );

		// Always resort after removing.
		$this->sort_sections();
	}

	/**
	 * Sort sections.
	 *
	 * @since 2.0.18
	 *
	 * @param string $orderby What to sort sections on.
	 * @param string $order   Order direction.
	 */
	public function sort_sections( $orderby = 'order', $order = 'ASC' ) {

		$this->sections = wp_list_sort( $this->sections, $orderby, $order, true );
	}

	/**
	 * Get all sections, and filter them.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	private function get_all_sections() {

		/**
		 * Filter metabox registered sections.
		 *
		 * @since 3.0.0
		 *
		 * @param array            $sections Registered sections.
		 * @param MetaboxInterface $metabox  Metabox instance.
		 */
		return (array) apply_filters( 'sugar_calendar_admin_meta_box_sections', $this->sections, $this ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Is a section the current section?
	 *
	 * @since 2.0.0
	 *
	 * @param string $section_id Section ID.
	 *
	 * @return bool
	 */
	private function is_current_section( $section_id = '' ) {

		return ( $section_id === $this->current_section );
	}

	/**
	 * Output the nonce field for the meta box.
	 *
	 * @since 2.0.0
	 */
	private function nonce_field() {

		wp_nonce_field( 'sugar_calendar_nonce', 'sc_mb_nonce', true );
	}

	/**
	 * Display links to all sections.
	 *
	 * @since 2.0.0
	 *
	 * @param array $tabs Section tabs.
	 */
	private function display_all_section_links( $tabs = [] ) {
		?>
		<div class="sugar-calendar-metabox__navigation">
			<?php echo $this->get_all_section_links( $tabs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
	}

	/**
	 * Get event data for a post.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Post $post Current post.
	 *
	 * @return array
	 */
	private function get_post_event_data( $post = 0 ) {

		/**
		 * Filter the event data for a post.
		 *
		 * @since 3.6.0
		 *
		 * @param \Sugar_Calendar\Event $event Event object.
		 * @param WP_Post               $post  Post object.
		 */
		return apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_admin_event_metabox_get_post_event_data',
			sugar_calendar_get_event_by_object( $post->ID ),
			$post
		);
	}

	/**
	 * Display all section contents.
	 *
	 * @since 2.0.0
	 *
	 * @param array $tabs Section tabs.
	 */
	private function display_all_section_contents( $tabs = [] ) {

		echo $this->get_all_section_contents( $tabs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Setup the meta box for the current post.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Post $post Current post.
	 */
	public function setup_post( $post = null ) {

		$this->event = $this->get_post_event_data( $post );
	}

	/**
	 * Get the contents of all links as HTML.
	 *
	 * @since 2.0.0
	 *
	 * @param array $sections Metabox sections.
	 *
	 * @return string
	 */
	private function get_all_section_links( $sections = [] ) {

		ob_start();

		// Loop through sections.
		foreach ( $sections as $section ) :
			$selected = $this->is_current_section( $section->id ) ? ' selected' : '';
			?>
			<button type="button"
				id="sugar-calendar-metabox__navigation__button-<?php echo esc_attr( $section->id ); ?>"
				class="sugar-calendar-metabox__navigation__button<?php echo esc_attr( $selected ); ?>"
				data-id="<?php echo esc_attr( $section->id ); ?>">
					<i class="dashicons dashicons-<?php echo esc_attr( $section->icon ); ?>"></i>
					<span class="label" id="sc-label-<?php echo esc_attr( $section->id ); ?>"><?php echo esc_attr( $section->label ); ?></span>
			</button>
		<?php
		endforeach;

		// Return output buffer.
		return ob_get_clean();
	}

	/**
	 * Get the contents of all sections as HTML.
	 *
	 * @since 2.0.0
	 * @since 3.6.0 Added DOM ID on each section.
	 * @since 3.8.0 Added filterable classes variable.
	 *
	 * @param array $sections Metabox sections.
	 *
	 * @return string HTML for all section contents.
	 */
	private function get_all_section_contents( $sections = [] ) {

		ob_start();

		// Loop through sections.
		foreach ( $sections as $section ) :
			$selected = $this->is_current_section( $section->id ) ? ' selected' : '';

			// Build section classes.
			$classes = [
				'sugar-calendar-metabox__section',
			];

			// Add selected class if current section.
			if ( $selected ) {
				$classes[] = 'selected';
			}

			/**
			 * Filter the CSS classes for a metabox section.
			 *
			 * @since 3.8.0
			 *
			 * @param array        $classes CSS classes array.
			 * @param EventSection $section Section object.
			 * @param Event        $metabox Metabox instance.
			 */
			$classes = apply_filters( 'sugar_calendar_admin_events_metaboxes_event_section_classes', $classes, $section, $this );

			// Convert classes array to string.
			$classes = implode( ' ', array_unique( array_filter( $classes ) ) );
			?>

			<div id="sugar-calendar-metabox__section__<?php echo esc_attr( $section->id ); ?>" data-id="<?php echo esc_attr( $section->id ); ?>"
				class="<?php echo esc_attr( $classes ); ?>">

				<?php $this->get_section_contents( $section ); ?>

			</div>

		<?php
		endforeach;

		// Return output buffer.
		return ob_get_clean();
	}

	/**
	 * Get the contents for a specific section.
	 *
	 * @since 2.0.18
	 *
	 * @param EventSection $section Section object.
	 */
	private function get_section_contents( $section = '' ) {

		// Setup the hook name.
		$hook = 'sugar_calendar_' . $section->id . 'meta_box_contents';

		// Callback.
		if ( ! empty( $section->callback ) && is_callable( $section->callback ) ) {
			call_user_func( $section->callback, $this->event );

			// Action.
		} elseif ( has_action( $hook ) ) {
			/**
			 * Fires when a metabox section has no callback.
			 *
			 * @since 3.0.0
			 *
			 * @param MetaboxInterface $metabox Metabox instance.
			 */
			do_action( $hook, $this ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName,WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Dynamic hook name resolved at runtime.
		}
	}

	/**
	 * Display metabox contents.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function display() {

		$this->enqueue_assets();

		$sections = $this->get_all_sections();
		$event_id = $this->event->id;

		// Start an output buffer.
		ob_start();
		?>

        <div class="sugar-calendar-event-details-metabox">

			<?php
			$this->display_all_section_links( $sections );
			$this->display_all_section_contents( $sections );
			?>

			<?php $this->nonce_field(); ?>
            <input type="hidden" name="sc-event-id" value="<?php echo esc_attr( $event_id ); ?>"/>
        </div>

		<?php

		// Output buffer.
		echo ob_get_clean(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Output the event duration meta-box section.
	 *
	 * @since  2.0.0
	 *
	 * @param Event $event The event object.
	 */
	public function section_duration( $event = null ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		// Get clock type, hours, and minutes.
		$tztype   = sugar_calendar_get_timezone_type();
		$timezone = sugar_calendar_get_timezone();
		$clock    = sugar_calendar_get_clock_type();
		$hours    = sugar_calendar_get_hours();
		$minutes  = sugar_calendar_get_minutes();

		// Get the hour format based on the clock type.
		$hour_format = ( $clock === '12' )
			? 'h'
			: 'H';

		// Setup empty Event if malformed.
		if ( ! is_object( $event ) ) {
			$event = new EventRow();
		}

		// Default dates & times.
		$date       = '';
		$hour       = '';
		$minute     = '';
		$end_date   = '';
		$end_hour   = '';
		$end_minute = '';

		// Default AM/PM.
		$am_pm     = '';
		$end_am_pm = '';

		// Default time zones.
		$start_tz = '';
		$end_tz   = '';

		// Default time zone UI.
		$show_multi_tz  = false;
		$show_single_tz = false;

		// All Day.
		$all_day = ! empty( $event->all_day ) && (bool) $event->all_day;

		// Ends.

		// Get date_time.
		$end_date_time = ! $event->is_empty_date( $event->end ) && ( $event->start !== $event->end )
			? strtotime( $event->end )
			: null;

		// Only if end isn't empty.
		if ( ! empty( $end_date_time ) ) {

			// Date.
			$end_date = gmdate( 'Y-m-d', $end_date_time );

			// Only if not all-day.
			if ( empty( $all_day ) ) {

				// Hour.
				$end_hour = gmdate( $hour_format, $end_date_time );

				if ( empty( $end_hour ) ) {
					$end_hour = '';
				}

				// Minute.
				$end_minute = gmdate( 'i', $end_date_time );

				if ( empty( $end_hour ) || empty( $end_minute ) ) {
					$end_minute = '';
				}

				// Day/night.
				$end_am_pm = gmdate( 'a', $end_date_time );

				if ( empty( $end_hour ) && empty( $end_minute ) ) {
					$end_am_pm = '';
				}
			}
		}

		// Starts.

		// Get date_time.
		if ( ! empty( $_GET['sce_start_date'] ) ) { // phpcs:disable WordPress.Security.NonceVerification.Recommended
			$start_date_raw = sanitize_text_field( wp_unslash( $_GET['sce_start_date'] ) );
			$start_hour     = 0;

			if ( ! empty( $_GET['sce_all_day'] ) ) {
				$all_day = true;
			} else {
				$start_hour = isset( $_GET['sce_start_hour'] ) ? absint( wp_unslash( $_GET['sce_start_hour'] ) ) : 0;
			}

			$date_time = $this->validate_start_date_param( $start_date_raw, $start_hour );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
		} elseif ( ! empty( $_GET['start_day'] ) ) {
			$date_time = (int) $_GET['start_day'];
		} else {
			$date_time = ! $event->is_empty_date( $event->start )
				? strtotime( $event->start )
				: null;
		}

		// Date.
		if ( ! empty( $date_time ) ) {
			$date = gmdate( 'Y-m-d', $date_time );

			// Only if not all-day.
			if ( empty( $all_day ) ) {

				// Hour.
				$hour = gmdate( $hour_format, $date_time );

				if ( empty( $hour ) ) {
					$hour = '';
				}

				// Minute.
				$minute = gmdate( 'i', $date_time );

				if ( empty( $hour ) || empty( $minute ) ) {
					$minute = '';
				}

				// Day/night.
				$am_pm = gmdate( 'a', $date_time );

				if ( empty( $hour ) && empty( $minute ) ) {
					$am_pm = '';
				}

				// All day.
			} elseif ( $date === $end_date ) {
				$end_date = '';
			}
		}

		// Time Zones.

		// Default time zone on "Add New".
		if ( empty( $event->end_tz ) && ( $tztype !== 'off' ) && ! $event->exists() ) {
			$end_tz = $timezone;

			// Event end time zone.
		} elseif ( ! empty( $end_date_time ) || ( $date_time !== $end_date_time ) ) {
			$end_tz = $event->end_tz;
		}

		// Default time zone on "Add New".
		if ( empty( $event->start_tz ) && ( $tztype !== 'off' ) && ! $event->exists() ) {
			$start_tz = $timezone;

			// Event start time zone.
		} elseif ( ! empty( $date_time ) ) {
			$start_tz = $event->start_tz;
		}

		// All day Events have no time zone data.
		if ( ! empty( $all_day ) ) {
			$start_tz = '';
			$end_tz   = '';
		}

		// Show multi time zone UI.
		if ( ( $tztype === 'multi' )
		     || (
			     ! empty( $end_tz )
			     && ( $date_time !== $end_date_time )
			     && ( $start_tz !== $end_tz )
		     )
		) {
			$show_multi_tz = true;

			// Show single time zone UI.
		} elseif ( ( $tztype === 'single' ) || ! empty( $start_tz ) ) {
			$show_single_tz = true;
		}

		$hidden = ( $all_day === true )
			? 'display: none;'
			: '';

		// Start an output buffer.
		ob_start();
		?>

        <div class="sugar-calendar-metabox__field-row">
            <label for="all_day"><?php esc_html_e( 'All Day', 'sugar-calendar-lite' ); ?></label>
            <div class="sugar-calendar-metabox__field">
				<?php
				UI::toggle_control(
					[
						'name'          => 'all_day',
						'id'            => 'all_day',
						'value'         => $all_day,
						'toggle_labels' => [
							esc_html__( 'YES', 'sugar-calendar-lite' ),
							esc_html__( 'NO', 'sugar-calendar-lite' ),
						],
					],
					true
				);
				?>
            </div>
        </div>
        <div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--start_date">
            <label for="start_date"><?php esc_html_e( 'Start', 'sugar-calendar-lite' ); ?></label>
            <div class="sugar-calendar-metabox__field">
                <div class="event-date">
                    <input type="text"
                           name="start_date"
                           id="start_date"
                           value="<?php echo esc_attr( $date ); ?>"
                           placeholder="yyyy-mm-dd"
                           autocomplete="off"
                           data-datepicker/>
                </div>
                <div class="event-time" style="<?php echo esc_attr( $hidden ); ?>">
                    <span class="sc-time-separator"><?php esc_html_e( 'at', 'sugar-calendar-lite' ); ?></span>

					<?php
					// Start Hour.
					sugar_calendar_time_dropdown(
						[
							'first'    => '&nbsp;',
							'id'       => 'start_time_hour',
							'name'     => 'start_time_hour',
							'items'    => $hours,
							'selected' => $hour,
						]
					);
					?>

                    <span class="sc-time-separator">:</span>

					<?php
					// Start Minute.
					sugar_calendar_time_dropdown(
						[
							'first'    => '&nbsp;',
							'id'       => 'start_time_minute',
							'name'     => 'start_time_minute',
							'items'    => $minutes,
							'selected' => $minute,
						]
					);

					// Start AM/PM.
					if ( $clock === '12' ) :
						?>

                        <select id="start_time_am_pm" name="start_time_am_pm" class="sc-select-chosen sc-time">
                            <option value="">&nbsp;</option>
                            <option value="am" <?php selected( $am_pm, 'am' ); ?>><?php esc_html_e( 'AM', 'sugar-calendar-lite' ); ?></option>
                            <option value="pm" <?php selected( $am_pm, 'pm' ); ?>><?php esc_html_e( 'PM', 'sugar-calendar-lite' ); ?></option>
                        </select>

					<?php endif; ?>
                </div>

				<?php
				// Start Time Zone.
				if ( $show_multi_tz === true ) :
					?>

                    <div class="event-time-zone">

						<?php
						UI::timezone_dropdown_control(
							[
								'name'    => 'start_tz',
								'id'      => 'start_tz',
								'current' => $start_tz,
							],
							true
						);
						?>

                    </div>
				<?php endif; ?>
            </div>
        </div>
        <div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--end_date">
            <label for="end_date"><?php esc_html_e( 'End', 'sugar-calendar-lite' ); ?></label>
            <div class="sugar-calendar-metabox__field">
                <div class="event-date">
                    <input type="text"
                           name="end_date"
                           id="end_date"
                           value="<?php echo esc_attr( $end_date ); ?>"
                           placeholder="yyyy-mm-dd"
						   autocomplete="off"
                           data-datepicker/>
                </div>
                <div class="event-time" style="<?php echo esc_attr( $hidden ); ?>">
                    <span class="sc-time-separator"><?php esc_html_e( 'at', 'sugar-calendar-lite' ); ?></span>

					<?php
					// End Hour.
					sugar_calendar_time_dropdown(
						[
							'first'    => '&nbsp;',
							'id'       => 'end_time_hour',
							'name'     => 'end_time_hour',
							'items'    => $hours,
							'selected' => $end_hour,
						]
					);
					?>

                    <span class="sc-time-separator">:</span>

					<?php
					// End Minute.
					sugar_calendar_time_dropdown(
						[
							'first'    => '&nbsp;',
							'id'       => 'end_time_minute',
							'name'     => 'end_time_minute',
							'items'    => $minutes,
							'selected' => $end_minute,
						]
					);

					// Start AM/PM.
					if ( $clock === '12' ) :
						?>

                        <select id="end_time_am_pm" name="end_time_am_pm" class="sc-select-chosen sc-time">
                            <option value="">&nbsp;</option>
                            <option value="am" <?php selected( $end_am_pm, 'am' ); ?>><?php esc_html_e( 'AM', 'sugar-calendar-lite' ); ?></option>
                            <option value="pm" <?php selected( $end_am_pm, 'pm' ); ?>><?php esc_html_e( 'PM', 'sugar-calendar-lite' ); ?></option>
                        </select>

					<?php endif; ?>
                </div>

				<?php
				// End Time Zone.
				if ( $show_multi_tz === true ) :
					?>

                    <div class="event-time-zone">

						<?php
						UI::timezone_dropdown_control(
							[
								'name'    => 'end_tz',
								'id'      => 'end_tz',
								'current' => $end_tz,
							],
							true
						);
						?>

                    </div>
				<?php endif; ?>
            </div>
        </div>

		<?php
		// Start & end time zones.
		if ( $show_single_tz === true ) :
			?>

            <div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--time-zone" style="<?php echo esc_attr( $hidden ); ?>">
                <label for="start_tz"><?php esc_html_e( 'Time Zone', 'sugar-calendar-lite' ); ?></label>
                <div class="sugar-calendar-metabox__field">
					<?php
					UI::timezone_dropdown_control(
						[
							'name'    => 'start_tz',
							'id'      => 'start_tz',
							'current' => $start_tz,
						],
						true
					);
					?>
                </div>
            </div>

		<?php
		endif;

		echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Output the event location meta-box section.
	 *
	 * @since 2.0.0
	 * @since 3.5.0 Added the venue education.
	 * @since 3.12.0 Unified Location/Venue tab: gate the Address field behind a
	 *                  filter and expose an action hook for feature UI (Venue, Online).
	 *
	 * @param Event $event The event object.
	 */
	public function section_location( $event = null ) {

		// Setup empty Event if malformed.
		if ( ! is_object( $event ) ) {
			$event = new Sugar_Calendar\Event();
		}

		// Location.
		$location = $event->location;

		/**
		 * Filter whether the Address field renders inside the Location section.
		 *
		 * Pro (Venues) uses this to hide the Address field when a venue is
		 * selected or when no address was ever saved. Default true (Lite always
		 * shows the Address field).
		 *
		 * @since 3.12.0
		 *
		 * @param bool  $show_address Whether to render the Address field.
		 * @param Event $event        The event being edited.
		 */
		$show_address = (bool) apply_filters( 'sugar_calendar_admin_meta_box_show_location_address', true, $event ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		// Start an output buffer.
		ob_start();

		if ( $show_address ) :
			?>

			<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--location">
				<label for="location"><?php esc_html_e( 'Address', 'sugar-calendar-lite' ); ?></label>
				<div class="sugar-calendar-metabox__field">
					<textarea name="location"
							  id="location"><?php echo esc_textarea( $location ); ?></textarea>
				</div>
			</div>

			<?php
		endif;

		/**
		 * Fires inside the Location section, after the Address field.
		 *
		 * Features append their Location UI here — the Venue selector (Pro), the
		 * Lite venue product-education block, and (later) the Online meeting
		 * provider dropdown. Fires unconditionally, even when the Address field
		 * is gated off, so the venue selector always renders.
		 *
		 * @since 3.12.0
		 *
		 * @param Event $event The event being edited.
		 */
		do_action( 'sugar_calendar_admin_meta_box_location_section', $event ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		// End & flush the output buffer.
		echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Output the event legacy meta-box section.
	 *
	 * @since  2.0.17
	 */
	public function section_legacy() {

		// Start an output buffer.
		ob_start();
		?>

        <table class="form-table rowfat">
            <tbody>

			<?php

			/**
			 * Fires before a legacy metabox content.
			 *
			 * @since 3.0.0
			 */
			do_action( 'sc_event_meta_box_before' ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Established public hook; renaming would break backward compatibility.

			/**
			 * Fires after a legacy metabox content.
			 *
			 * @since 3.0.0
			 */
			do_action( 'sc_event_meta_box_after' ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Established public hook; renaming would break backward compatibility.
			?>

            </tbody>
        </table>

		<?php

		// End & flush the output buffer.
		echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Save post data.
	 *
	 * @since 3.0.0
	 *
	 * @param array $data Post data.
	 *
	 * @return array
	 */
	public function save_post( $data ) {

		return array_merge( EventDateTimeRequest::from_request(), $data );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style( 'sugar-calendar-admin-event-meta-box' );

		wp_enqueue_script( 'sugar-calendar-admin-event-meta-box' );

		// jQuery-Confirm powers the online-meeting "Remove" danger-zone modal.
		// Enqueue only the vendor SCRIPT + SC's branded `admin-confirm` theme —
		// NOT the vendor stylesheet, whose default `.btn` styles (uppercase, small)
		// would override the SC button theme. This mirrors what the Integrations
		// page loads (admin-alerts only), so the modal matches there.
		wp_enqueue_script( 'sugar-calendar-vendor-jquery-confirm' );
		wp_enqueue_style( 'sugar-calendar-admin-confirm' );

		/**
		 * Filter the localize script for the admin event meta box.
		 *
		 * @since 3.7.0
		 * @since 3.8.0 Added Help URL context.
		 *
		 * @param array $localize_script Localize script.
		 */
		$admin_event_meta_box_localize_script = apply_filters(
			'sugar_calendar_admin_events_metaboxes_event_localize_script',
			[
				'start_of_week' => sugar_calendar_get_user_preference( 'start_of_week' ),
				'date_format'   => sugar_calendar_get_user_preference( 'date_format' ),
				'time_format'   => sugar_calendar_get_user_preference( 'time_format' ),
				'timezone'      => sugar_calendar_get_user_preference( 'timezone' ),
				'timezone_type' => sugar_calendar_get_user_preference( 'timezone_type' ),
				'clock_type'    => sugar_calendar_get_clock_type(),
				'help_url'      => [
					'duration'       => 'Duration',
					'adv-recurrence' => 'Recurrence',
					'venue'          => 'Venue',
					'url'            => 'Link',
					'tickets'        => 'Tickets',
				],
				'post_type'     => sugar_calendar_get_event_post_type_id(),
				'editor'        => [
					'type'               => Editor\current(),
					'taxonomies_to_hide' => [ ScHelpers::get_tags_slug() ],
				],
			]
		);

		wp_localize_script(
			'sugar-calendar-admin-event-meta-box',
			'sugar_calendar_admin_event_meta_box',
			$admin_event_meta_box_localize_script
		);
	}

	/**
	 * Add translation support to scripts.
	 *
	 * @since 3.3.0
	 *
	 * @return void
	 */
	public function set_script_translations() {

		// Support admin-event-meta-box script.
		wp_set_script_translations(
			'sugar-calendar-admin-event-meta-box',
			'sugar-calendar',
			SC_PLUGIN_DIR . 'languages'
		);
	}

	/**
	 * Validate a date string and return it's timestamp.
	 *
	 * @since 3.10.0
	 *
	 * @param string $date_string The date string to validate (expected format: Y-m-d).
	 * @param int    $start_hour  The start hour to set.
	 *
	 * @return int|null Unix timestamp if valid, null otherwise.
	 */
	private function validate_start_date_param( $date_string, $start_hour = 0 ) {

		// Bail early if empty.
		if ( empty( $date_string ) ) {
			return false;
		}

		try {
			$new_date = new \DateTime( $date_string );
		} catch ( \Exception $e ) {
			return false;
		}

		if ( empty( $new_date ) ) {
			return false;
		}

		$start_hour = empty( $start_hour ) ? 7 : absint( $start_hour );

		$new_date->setTime( $start_hour, 0, 0 );

		return $new_date->getTimestamp();
	}
}
