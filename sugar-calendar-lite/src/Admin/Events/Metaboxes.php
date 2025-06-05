<?php

namespace Sugar_Calendar\Admin\Events;

use Sugar_Calendar\Admin\Events\Metaboxes\Details;
use Sugar_Calendar\Admin\Events\Metaboxes\Event;
use Sugar_Calendar\Admin\Events\Metaboxes\WalkerCategoryCheckbox;
use Sugar_Calendar\Common\Editor;
use Sugar_Calendar\Helpers\Helpers;
use Sugar_Calendar\Helpers\UI;
use Sugar_Calendar\Plugin;

/**
 * Metaboxes class.
 *
 * @since 3.0.0
 */
class Metaboxes {

	/**
	 * Register hooks.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function hooks() {

		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ], 10, 2 );
		add_filter( 'register_taxonomy_args', [ $this, 'taxonomy_args' ], 10, 2 );
		add_filter( 'wp_terms_checklist_args', [ $this, 'checklist_args' ] );
		add_filter( 'sc_event_supports', [ $this, 'custom_fields' ] );
		add_action( 'save_post', [ $this, 'save' ], 10, 2 );

		// Education.
		add_action( 'sugar_calendar_admin_meta_box_setup_sections', [ $this, 'event_metabox_education' ] );
	}

	/**
	 * Returns a list of registered metaboxes.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_Post $post Current post.
	 *
	 * @return MetaboxInterface[]
	 */
	private function get_meta_boxes( $post ) {

		static $metaboxes;

		if ( $metaboxes === null ) {
			$metaboxes = [
				Event::class,
			];

			$event_post_type = sugar_calendar_get_event_post_type_id();

			if ( ! post_type_supports( $event_post_type, 'editor' ) ) {
				$metaboxes[] = Details::class;
			}

			$metaboxes = array_map( fn( $metabox ) => new $metabox( $post ), $metaboxes );
		}

		return $metaboxes;
	}

	/**
	 * Register metaboxes.
	 *
	 * @since 3.0.0
	 *
	 * @param string  $post_type Current post type.
	 * @param WP_POST $post      Current post.
	 *
	 * @return void
	 */
	public function add_meta_boxes( $post_type, $post ) {

		// Bail if not in new/edit Event screen.
		if ( $post_type !== sugar_calendar_get_event_post_type_id() ) {
			return;
		}

		foreach ( $this->get_meta_boxes( $post ) as $metabox ) {

			add_meta_box(
				$metabox->get_id(),
				$metabox->get_title(),
				[ $metabox, 'display' ],
				$metabox->get_screen(),
				$metabox->get_context(),
				$metabox->get_priority()
			);
		}
	}

	/**
	 * Event Types Meta-box.
	 * Output custom checkboxes instead of the default WordPress mechanism.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $args     Taxonomy arguments.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return array
	 */
	public function taxonomy_args( $args = [], $taxonomy = '' ) {

		if ( sugar_calendar_get_calendar_taxonomy_id() === $taxonomy ) {

			/**
			 * Filter event taxonomy arguments.
			 *
			 * @since 3.0.0
			 *
			 * @param array $taxonomy_args Taxonomy arguments.
			 * @param array $original_args Original taxonomy arguments.
			 */
			$r = apply_filters(
				'sugar_calendar_admin_events_metaboxes_taxonomy_args',
				[
					'meta_box_cb' => 'post_categories_meta_box',
				],
				$args
			);

			$args = wp_parse_args( $args, $r );
		}

		return $args;
	}

	/**
	 * Use the custom walker for checkboxes.
	 *
	 * @since 2.0.0
	 * @since 3.2.0 Replaced radio buttons with checkboxes.
	 *
	 * @param array $args Checklist arguments.
	 *
	 * @return array
	 */
	public function checklist_args( $args = [] ) {

		if ( ! empty( $args['taxonomy'] ) && ( sugar_calendar_get_calendar_taxonomy_id() === $args['taxonomy'] ) ) {

			/**
			 * Filter event checklist arguments.
			 *
			 * @since 3.0.0
			 * @since 3.2.0 Changed the walker to WalkerCategoryCheckbox.
			 *
			 * @param array $checklist_args Checklist arguments.
			 * @param array $original_args  Original checklist arguments.
			 */
			$r = apply_filters(
				'sugar_calendar_admin_events_metaboxes_checklist_args',
				[
					'walker' => new WalkerCategoryCheckbox(),
				],
				$args
			);

			// Re-parse the arguments.
			$args = wp_parse_args( $args, $r );
		}

		return $args;
	}

	/**
	 * Maybe add custom fields support to supported post types.
	 *
	 * @since 2.1.0
	 *
	 * @param array $supports List of supported features.
	 *
	 * @return array
	 */
	public function custom_fields( $supports = [] ) {

		// Get the custom fields setting.
		$supported = Editor\custom_fields();

		// Add custom fields support.
		if ( ! empty( $supported ) ) {
			$supports[] = 'custom-fields';
		}

		// Return supported.
		return $supports;
	}

	/**
	 * Determine whether the meta-box contents can be saved.
	 *
	 * This checks a number of specific things, like nonces, autosave, ajax, bulk,
	 * and also checks caps based on the object type.
	 *
	 * @since 2.0
	 *
	 * @param int    $object_id Current object ID.
	 * @param object $object    Current object.
	 *
	 * @return bool
	 */
	private function can_save_meta_box( $object_id = 0, $object = null ) {

		// Default return value.
		$retval = false;

		// Bail if no nonce or nonce check fails.
		if ( empty( $_POST['sc_mb_nonce'] ) || ! wp_verify_nonce( $_POST['sc_mb_nonce'], 'sugar_calendar_nonce' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return $retval;
		}

		// Bail on autosave, ajax, or bulk.
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || isset( $_REQUEST['bulk_edit'] ) ) {
			return $retval;
		}

		if ( is_a( $object, 'WP_Post' ) ) {

			// Get the post type.
			$post_type = get_post_type( $object_id );

			// Only save event metadata to supported post types.
			if ( ! post_type_supports( $post_type, 'events' ) ) {
				return $retval;
			}

			// Bail if revision.
			if ( wp_is_post_revision( $object_id ) ) {
				return $retval;
			}

			// Get post type object.
			$post_type_object = get_post_type_object( $post_type );

			// Bail if user cannot edit this event.
			if ( current_user_can( $post_type_object->cap->edit_post, $object_id ) ) {
				$retval = true;
			}
		}

		// Return whether the meta-box can be saved.
		return (bool) $retval;
	}

	/**
	 * Meta-box save.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $object_id ID of the connected object.
	 * @param object $object    Connected object data.
	 *
	 * @return int|void
	 */
	public function save( $object_id = 0, $object = null ) {

		// Bail if meta-box cannot be saved.
		if ( ! $this->can_save_meta_box( $object_id, $object ) ) {
			return $object_id;
		}

		$this->get_meta_boxes( $object );

		// Shim these for now (need to make functions for them).
		$title   = $object->post_title;
		$content = $object->post_content;
		$subtype = $object->post_type;
		$status  = $object->post_status;

		// Get an event.
		$event = sugar_calendar_get_event_by_object( $object_id );
		$type  = ! empty( $event->object_type )
			? $event->object_type
			: 'post';

		/**
		 * Filter event data before saving.
		 *
		 * @since 3.0.0
		 * @since 3.6.0 Pass the event object.
		 *
		 * @param array $data  Data to save.
		 * @param Event $event Event object.
		 */
		$to_save = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'sugar_calendar_event_to_save',
			[
				'object_id'      => $object_id,
				'object_type'    => $type,
				'object_subtype' => $subtype,
				'title'          => $title,
				'content'        => $content,
				'status'         => $status,
			],
			$event
		);

		// Update or Add New.
		if ( ! empty( $event->id ) ) {
			$success = sugar_calendar_update_event( $event->id, $to_save, $event );
		} else {
			$success = sugar_calendar_add_event( $to_save );
		}

		// Return the results of the update/add event.
		return $success;
	}

	/**
	 * Add product education metabox sections.
	 *
	 * @since 3.0.0
	 * @since 3.1.0 Remove event ticketing education.
	 * @since 3.5.0 Add event venue education.
	 * @since 3.7.0 Added RSVP education.
	 *
	 * @param Event $box
	 *
	 * @return void
	 */
	public function event_metabox_education( $box = false ) {

		if ( Plugin::instance()->is_pro() ) {
			return;
		}

		// Recurrence.
		$box->add_section(
			[
				'id'       => 'adv-recurrence',
				'label'    => esc_html__( 'Recurrence', 'sugar-calendar-lite' ),
				'icon'     => 'controls-repeat',
				'order'    => 20,
				'callback' => [ $this, 'event_metabox_recurring_education' ],
			]
		);

		// Link.
		$box->add_section(
			[
				'id'       => 'url',
				'label'    => esc_html__( 'Link', 'sugar-calendar-lite' ),
				'icon'     => 'admin-links',
				'order'    => 70,
				'callback' => [ $this, 'event_metabox_link_education' ],
			]
		);

		// Venue.
		$box->add_section(
			[
				'id'       => 'venue',
				'label'    => esc_html__( 'Venue', 'sugar-calendar-lite' ),
				'icon'     => 'location',
				'order'    => 50,
				'callback' => [ $this, 'event_metabox_venue_education' ],
			]
		);

		// Speakers.
		$box->add_section(
			[
				'id'       => 'speakers',
				'label'    => esc_html__( 'Speakers', 'sugar-calendar-lite' ),
				'icon'     => 'admin-users',
				'order'    => 60,
				'callback' => [ $this, 'event_metabox_speakers_education' ],
			]
		);

		// RSVP.
		$box->add_section(
			[
				'id'       => 'rsvp',
				'label'    => esc_html__( 'RSVP', 'sugar-calendar' ),
				'icon'     => 'yes-alt',
				'order'    => 50,
				'callback' => [ $this, 'event_metabox_rsvp_education' ],
			]
		);
	}

	/**
	 * Event metabox recurrence section education.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function event_metabox_recurring_education() {

		?>
		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--education">
			<label for="recurrence"><?php esc_html_e( 'Repeat', 'sugar-calendar-lite' ); ?></label>
			<div class="sugar-calendar-metabox__field">
				<select id="recurrence" class="recurrence" disabled>
					<option><?php esc_html_e( 'Never', 'sugar-calendar-lite' ); ?></option>
				</select>
			</div>
		</div>

		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--education sugar-calendar-metabox__field-row--education--recurrence-interval repeat-advanced">
			<label for="recurrence_interval"><?php esc_html_e( 'Every', 'sugar-calendar-lite' ); ?></label>
			<div class="sugar-calendar-metabox__field">
				<input type="number" min="1" max="999" disabled/>
			</div>
		</div>

		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--education sugar-calendar-metabox__field-row--education--recurrence-end-type repeat-advanced">
			<label for="recurrence_end_type"><?php esc_html_e( 'Ends', 'sugar-calendar-lite' ); ?></label>
			<div class="sugar-calendar-metabox__field">
				<div class="sugar-calendar-metabox__field__wrapper end-repeat-type">
					<input type="radio" id="recurrence_end_type_never" checked disabled/>
					<label for="recurrence_end_type_never">
						<span class="end-repeat-label"><?php esc_html_e( 'Never', 'sugar-calendar-lite' ); ?></span>
					</label>
				</div>

				<div class="sugar-calendar-metabox__field__wrapper end-repeat-type">
					<input type="radio" id="recurrence_end_type_date" disabled/>
					<label for="recurrence_end_type_date">
						<span class="end-repeat-label"><?php esc_html_e( 'On', 'sugar-calendar-lite' ); ?></span>
					</label>
					<input type="text" id="recurrence_end_date" placeholder="<?php esc_html_e( 'Date', 'sugar-calendar-lite' ); ?>" disabled/>
				</div>

				<div class="sugar-calendar-metabox__field__wrapper end-repeat-type">
					<input type="radio" id="recurrence_end_type_count" disabled/>
					<label for="recurrence_end_type_count">
						<span class="end-repeat-label"><?php esc_html_e( 'After', 'sugar-calendar-lite' ); ?></span>
					</label>
					<input type="number" min="1" max="999" id="recurrence_end_count" placeholder="1" disabled/>
					<label for="recurrence_end_type_count">
						<span id="repeat-occurrence"><?php esc_html_e( 'time', 'sugar-calendar-lite' ); ?></span>
					</label>
				</div>
			</div>
		</div>

		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--upgrade">
			<p class="desc">
				<?php
				echo wp_kses(
					sprintf( /* translators: %1$s - SugarCalendar.com documentation URL; %2$s - link text; %2$3 - paragraph text. */
						'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a> %3$s',
						Helpers::get_upgrade_link( [ 'medium' => 'lite-event-recurrence', 'content' => 'Upgrade to Sugar Calendar Pro' ] ),
						esc_html__( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ),
						esc_html__( 'to access this feature and a lot more!', 'sugar-calendar-lite' )
					),
					[
						'a' => [
							'href'   => [],
							'rel'    => [],
							'target' => [],
						],
					]
				);
				?>
			</p>
		</div>

		<?php
	}

	/**
	 * Event metabox link section education.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function event_metabox_link_education() {

		?>

		<div class="sugar-calendar-metabox__field-row">
			<p class="desc"><?php esc_html_e( 'Add a custom URL which will be displayed in the event details or redirect visitors to a different page.', 'sugar-calendar-lite' ); ?></p>
		</div>

		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--education sugar-calendar-metabox__field-row--education--sc-event-url-redirect">
			<label for="sc-event-url-redirect"><?php esc_html_e( 'Redirect', 'sugar-calendar-lite' ); ?></label>
			<div class="sugar-calendar-metabox__field">
				<?php
				UI::toggle_control(
					[
						'id'            => 'sc-event-url-redirect',
						'value'         => false,
						'disabled'      => true,
						'toggle_labels' => [
							esc_html__( 'ON', 'sugar-calendar-lite' ),
							esc_html__( 'OFF', 'sugar-calendar-lite' ),
						],
						'description'   => esc_html__( 'Automatically send visitors here. The Event page on your site will be inaccessible.', 'sugar-calendar-lite' ),
					],
					true
				);
				?>
			</div>
		</div>

		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--education sugar-calendar-metabox__field-row--education--sc-event-url">
			<label for="sc-event-url"><?php esc_html_e( 'URL', 'sugar-calendar-lite' ); ?></label>
			<div class="sugar-calendar-metabox__field">
				<input type="text" id="sc-event-url" disabled/>
				<p class="desc">
					<?php esc_html_e( 'Paste the full URL starting with https://', 'sugar-calendar-lite' ); ?>
				</p>
			</div>
		</div>

		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--education sugar-calendar-metabox__field-row--education--sc-event-url-target">
			<label for="sc-event-url-target"><?php esc_html_e( 'Target', 'sugar-calendar-lite' ); ?></label>
			<div class="sugar-calendar-metabox__field">
				<input type="checkbox" id="sc-event-url-target" disabled/>
				<label for="sc-event-url-target"><?php esc_html_e( 'Open link in a new tab', 'sugar-calendar-lite' ); ?></label>
			</div>
		</div>

		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--education sugar-calendar-metabox__field-row--education--sc-event-url-text">
			<label for="sc-event-url-text"><?php esc_html_e( 'Text', 'sugar-calendar-lite' ); ?></label>
			<div class="sugar-calendar-metabox__field">
				<input type="text" id="sc-event-url-text" disabled/>
				<p class="desc">
					<?php esc_html_e( 'Use this text instead of showing the URL.', 'sugar-calendar-lite' ); ?>
				</p>
			</div>
		</div>

		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--upgrade">
			<p class="desc">
				<?php
				echo wp_kses(
					sprintf( /* translators: %1$s - SugarCalendar.com documentation URL; %2$s - link text; %2$3 - paragraph text. */
						'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a> %3$s',
						Helpers::get_upgrade_link( [ 'medium' => 'lite-event-link', 'content' => 'Upgrade to Sugar Calendar Pro' ] ),
						esc_html__( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ),
						esc_html__( 'to access this feature and a lot more!', 'sugar-calendar-lite' )
					),
					[
						'a' => [
							'href'   => [],
							'rel'    => [],
							'target' => [],
						],
					]
				);
				?>
			</p>
		</div>

		<?php
	}

	/**
	 * Event metabox venue section education.
	 *
	 * @since 3.5.0
	 *
	 * @return void
	 */
	public function event_metabox_venue_education() {

		?>
		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--venue sugar-calendar-metabox__field-row--education">
			<label for="venue"><?php esc_html_e( 'Venue', 'sugar-calendar-lite' ); ?></label>
			<div class="sugar-calendar-metabox__field sugar-calendar-event-venue-selection">
				<div id="sugar-calendar-setting-row-venue" class="sugar-calendar-setting-row sugar-calendar-clear sugar-calendar-setting-row-select">
					<span class="sugar-calendar-setting-field choicesjs-select-wrap">
						<div class="choices" data-type="select-one" tabindex="0" role="combobox" aria-autocomplete="list" aria-haspopup="true" aria-expanded="false">
							<div class="choices__inner">
								<div class="choices__list choices__list--single">
									<div class="choices__item choices__item--selectable" aria-selected="true">
										<?php esc_html_e( 'Existing Venue', 'sugar-calendar-lite' ); ?>
									</div>
								</div>
							</div>
						</div>
						<p class="desc"><?php esc_html_e( 'Select an existing venue or create a new one.', 'sugar-calendar-lite' ); ?></p>
					</span>
				</div>
			</div>
			<span id="venue-add-new"><?php esc_html_e( 'Add New Venue', 'sugar-calendar-lite' ); ?></span>
			<div class="sugar-calendar-event-venue-summary">
				<div class="sugar-calendar-event-venue-info-card active">
					<div class="venue-info-card-display">
						<h4>The Roxy</h4>
						<p>9009 W Sunset Blvd</p>
						<p>West Hollywood, CA, 90069</p>
						<p>United States</p>
						<p>310-278-9457</p>
					</div>
					<span id="venue-edit-open" aria-label="Edit Venue"></span>
				</div>
			</div>
		</div>

		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--venue_show_map sugar-calendar-metabox__field-row--education">
			<label for="recurrence"><?php esc_html_e( 'Show Map', 'sugar-calendar-lite' ); ?></label>
			<div class="sugar-calendar-metabox__field">
				<div id="sugar-calendar-setting-row-show_map" class="sugar-calendar-setting-row sugar-calendar-clear sugar-calendar-setting-row-toggle">
					<span class="sugar-calendar-setting-field">
						<span class="sugar-calendar-toggle-control">
							<label class="sugar-calendar-toggle-control-icon" for="sugar-calendar-setting-show_map"></label>
							<label
								class="sugar-calendar-toggle-control-status sugar-calendar-toggle-control-status-off"
								for="sugar-calendar-setting-show_map"
								style="display:block;"
							><?php esc_html_e( 'Off', 'sugar-calendar-lite' ); ?></label>
						</span>
						<p class="desc">
							<?php
								echo wp_sprintf(
									esc_html__( 'You need to configure Google API in %s to enable this feature.', 'sugar-calendar-lite' ),
									'<a href="#" target="_blank">' . esc_html__( 'settings', 'sugar-calendar-lite' ) . '</a>'
								);
							?>
						</p>
					</span>
				</div>
			</div>
		</div>

		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--upgrade">
			<p class="desc">
				<?php
				echo wp_kses(
					sprintf( /* translators: %1$s - SugarCalendar.com documentation URL; %2$s - link text; %2$3 - paragraph text. */
						'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a> %3$s',
						esc_url(
							Helpers::get_upgrade_link(
								[
									'medium'  => 'lite-event-venue',
									'content' => 'Upgrade to Sugar Calendar Pro',
								]
							)
						),
						esc_html__( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ),
						esc_html__( 'to access this feature and a lot more!', 'sugar-calendar-lite' )
					),
					[
						'a' => [
							'href'   => [],
							'rel'    => [],
							'target' => [],
						],
					]
				);
				?>
            </p>
        </div>
		<?php
	}

	/**
	 * Event metabox RSVP section education.
	 *
	 * @since 3.7.0
	 */
	public function event_metabox_rsvp_education() {

		$toggles = [
			[
				'label'     => __( 'RSVP', 'sugar-calendar-lite' ),
				'separator' => true,
			],
			[
				'label'     => __( 'Limit Capacity', 'sugar-calendar-lite' ),
				'separator' => true,
			],
			[
				'label' => __( 'Phone Required', 'sugar-calendar-lite' ),
			],
			[
				'label' => __( 'Show Attendee List', 'sugar-calendar-lite' ),
			],
			[
				'label'       => __( 'Allow "Not Going"', 'sugar-calendar-lite' ),
				'description' => __( 'Enabling this will allow users to submit “Not Going” as a response', 'sugar-calendar-lite'),
			],
		];

		foreach ( $toggles as $toggle ) {
			?>
			<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--education">
				<label><?php echo esc_html( $toggle['label'] ); ?></label>

				<div class="sugar-calendar-metabox__field">
					<span class="sugar-calendar-toggle-control">
						<input type="checkbox" name="" value="1" disabled="disabled">
						<label class="sugar-calendar-toggle-control-icon"></label>
						<label class="sugar-calendar-toggle-control-status sugar-calendar-toggle-control-status-on">ON</label>
						<label class="sugar-calendar-toggle-control-status sugar-calendar-toggle-control-status-off">OFF</label>
					</span>

					<?php
					if ( ! empty( $toggle['description'] ) ) {
						?>
						<p class="desc"><?php echo esc_html( $toggle['description'] ); ?></p>
					<?php
					}
					?>
				</div>
			</div>
			<?php
			if ( ! empty( $toggle['separator'] ) && $toggle['separator'] ) {
				?>
				<div class="sugar-calendar-metabox__field-row__sep"></div>
				<?php
			}
		}
		?>
		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--upgrade">
			<p class="desc">
				<?php
				echo wp_kses(
					sprintf( /* translators: %1$s - SugarCalendar.com documentation URL; %2$s - link text; %2$3 - paragraph text. */
						'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a> %3$s',
						esc_url(
							Helpers::get_upgrade_link(
								[
									'medium'  => 'lite-event-rsvp',
									'content' => 'Upgrade to Sugar Calendar Pro',
								]
							)
						),
						esc_html__( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ),
						esc_html__( 'to access this feature and a lot more!', 'sugar-calendar-lite' )
					),
					[
						'a' => [
							'href'   => [],
							'rel'    => [],
							'target' => [],
						],
					]
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Event metabox venue section education.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function event_metabox_speakers_education() {

		?>
		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--speaker sugar-calendar-metabox__field-row--education">
			<label for="speakers"><?php esc_html_e( 'Speakers', 'sugar-calendar-lite' ); ?></label>
			<div class="sugar-calendar-metabox__field sugar-calendar-event-speaker-selection">

				<div id="sugar-calendar-setting-row-speakers" class="sugar-calendar-setting-row sugar-calendar-clear sugar-calendar-field--event-speaker-selection sugar-calendar-setting-row-select">
					<span class="sugar-calendar-setting-field">
						<span class="choicesjs-select-wrap">
							<div class="choices" data-type="select-multiple" role="combobox" aria-autocomplete="list" aria-haspopup="true" aria-expanded="false">
								<div class="choices__inner">
									<select id="sugar-calendar-setting-speakers" class="sugar-calendar-field--event-speaker-selection choices__input" multiple hidden tabindex="-1" data-choice="active">
										<option>Mia Harper</option>
									</select>
									<div class="choices__list choices__list--multiple">
										<div class="choices__item choices__item--selectable" aria-selected="true" data-deletable="">
											Mia Harper
											<button type="button" class="choices__button" data-button=""></button>
										</div>
									</div>
								</div>
							</div>
						</span>
						<p class="desc"><?php esc_html_e( 'Search an existing speaker or create a new one.', 'sugar-calendar-lite' ); ?></p>
					</span>
				</div>
			</div>
			<span id="speaker-add-new"><?php esc_html_e( 'Add New Speaker', 'sugar-calendar-lite' ); ?></span>
		</div>

		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--upgrade">
			<p class="desc">
				<?php
				echo wp_kses(
					sprintf( /* translators: %1$s - SugarCalendar.com documentation URL; %2$s - link text; %2$3 - paragraph text. */
						'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a> %3$s',
						esc_url(
							Helpers::get_upgrade_link(
								[
									'medium'  => 'lite-event-speakers',
									'content' => 'Upgrade to Sugar Calendar Pro',
								]
							)
						),
						esc_html__( 'Upgrade to Sugar Calendar Pro', 'sugar-calendar-lite' ),
						esc_html__( 'to access this feature and a lot more!', 'sugar-calendar-lite' )
					),
					[
						'a' => [
							'href'   => [],
							'rel'    => [],
							'target' => [],
						],
					]
				);
				?>
			</p>
		</div>
		<?php
	}
}
