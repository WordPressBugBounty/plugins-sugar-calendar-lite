<?php

namespace Sugar_Calendar\Admin\Tools\Importers;

use Sugar_Calendar\Admin\Tools\Importers;
use Sugar_Calendar\Helpers;
use Sugar_Calendar\Features\Tags\Common\Helpers as TagHelpers;

/**
 * Sugar Calendar importer.
 *
 * @since 3.3.0
 */
class SugarCalendar extends Importer {

	/**
	 * Contains the imported data of the finished import.
	 *
	 * @since 3.3.0
	 *
	 * @var null|array
	 */
	private $importer_data = null;

	/**
	 * Imported event ids to record old and new event ids.
	 *
	 * @since 3.6.0
	 *
	 * @var array
	 */
	public $imported_event_ids = [];

	/**
	 * Imported tag ids to record old and new tag ids.
	 *
	 * @since 3.7.0
	 *
	 * @var array
	 */
	public $imported_tag_ids = [];

	/**
	 * Display the default import display.
	 *
	 * @since 3.3.0
	 * @since 3.6.0 Adjust separator when other importers are present.
	 */
	public function display() {

		/**
		 * Fires before the importer tab is displayed.
		 *
		 * @since 3.6.0
		 *
		 * @param Importers\ImporterInterface $importer The importer.
		 */
		do_action( 'sugar_calendar_admin_pages_tools_default_importer_before' );

		if ( ! is_null( $this->importer_data ) ) {
			$this->display_finished_import_summary();

			return;
		}
		?>
		<p>
			<?php esc_html_e( 'Select a Sugar Calendar JSON file to import.', 'sugar-calendar-lite' ); ?>
		</p>

		<?php
		if ( ! sugar_calendar()->is_pro() ) {
			$this->display_recurring_notice();
		}
		?>

		<form id="sc-admin-tools-import-form" method="post" enctype="multipart/form-data">
			<div class="sc-admin-tools-form-content">
				<div id="sc-admin-tools-import-file-upload-wrap">
					<input type="file" name="file" id="sc-admin-tools-form-import" class="inputfile" accept=".json" />
					<label for="sc-admin-tools-form-import">
						<span id="sc-admin-tools-form-import-file-btn">
							<?php esc_html_e( 'Choose File', 'sugar-calendar-lite' ); ?>
						</span>
						<span id="sc-admin-tools-form-import-file-info">
							<?php esc_html_e( 'No file chosen', 'sugar-calendar-lite' ); ?>
						</span>
					</label>
				</div>
			</div>
			<input type="hidden" name="action" value="import_form">
			<input type="hidden" name="import_src" value="sugar-calendar" />
			<?php wp_nonce_field( Importers::IMPORT_NONCE_ACTION, '_nonce' ); ?>

				<?php if ( ! has_action( 'sugar_calendar_admin_pages_tools_default_importer_after' ) ) : ?>
					<div class="sc-admin-tools-divider"></div>
				<?php endif; ?>

				<button id="sc-admin-tools-sc-import-btn" name="submit-import"
					class="sc-admin-tools-disabled sc-admin-tools-sc-import-btn-disabled sugar-calendar-btn sugar-calendar-btn-primary sugar-calendar-btn-md">
				<span class="sc-admin-tools-sc-import-btn__text"><?php esc_html_e( 'Import', 'sugar-calendar-lite' ); ?></span>
			</button>
		</form>
		<?php

		/**
		 * Fires after the importer tab is displayed.
		 *
		 * @since 3.6.0
		 *
		 * @param Importers\ImporterInterface $importer The importer.
		 */
		do_action( 'sugar_calendar_admin_pages_tools_default_importer_after' );
	}

	/**
	 * Display the finished import summary.
	 *
	 * @since 3.3.0
	 */
	private function display_finished_import_summary() {
		?>
		<p>
			<?php esc_html_e( 'Select a Sugar Calendar JSON file to import.', 'sugar-calendar-lite' ); ?>
		</p>
		<div class="sc-admin-tools-divider"></div>
		<div class="sc-admin-tools-import-summary">
			<p>
				<span class="sc-admin-tools-import-summary__title"><?php esc_html_e( 'Import Completed!', 'sugar-calendar-lite' ); ?></span>
			</p>
			<div class="sc-admin-tools-import-summary__wrap">
				<?php
				foreach ( [ 'events', 'venues', 'speakers', 'calendars', 'tickets', 'orders', 'attendees', 'categories', 'tags' ] as $context ) {
					if ( ! array_key_exists( $context, $this->importer_data ) ) {
						continue;
					}
					?>
					<div class="sc-admin-tools-import-summary__item">
						<svg width="14" height="15" viewBox="0 0 14 15" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M7 0.96875C10.7188 0.96875 13.7812 4.03125 13.7812 7.75C13.7812 11.4961 10.7188 14.5312 7 14.5312C3.25391 14.5312 0.21875 11.4961 0.21875 7.75C0.21875 4.03125 3.25391 0.96875 7 0.96875ZM7 2.28125C3.96484 2.28125 1.53125 4.74219 1.53125 7.75C1.53125 10.7852 3.96484 13.2188 7 13.2188C10.0078 13.2188 12.4688 10.7852 12.4688 7.75C12.4688 4.74219 10.0078 2.28125 7 2.28125ZM10.8281 5.86328C10.9375 5.97266 10.9375 6.19141 10.8281 6.32812L6.09766 11.0039C5.96094 11.1406 5.76953 11.1406 5.63281 11.0039L3.14453 8.48828C3.03516 8.37891 3.03516 8.16016 3.14453 8.02344L3.77344 7.42188C3.91016 7.28516 4.10156 7.28516 4.23828 7.42188L5.87891 9.0625L9.73438 5.23438C9.87109 5.09766 10.0898 5.09766 10.1992 5.23438L10.8281 5.86328Z" fill="#00BA37"/>
						</svg>
						<span>
							<?php
							printf(
								/* translators: %1$s: number of imported items, %2$s: item type. */
								esc_html__( '%1$s imported: %2$s', 'sugar-calendar-lite' ),
								esc_html( ucfirst( $context ) ),
								absint( $this->importer_data[ $context ] )
							);
							?>
						</span>
					</div>
					<?php
				}
				?>
			</div>
		</div>
		<?php
		$error_html = $this->get_error_html_display();

		if ( ! empty( $error_html ) ) {
			echo wp_kses_post( $error_html );
		}
	}

	/**
	 * Display the recurring notice.
	 *
	 * @since 3.3.0
	 */
	private function display_recurring_notice() {
		?>
		<div id="sc-admin-tools-manual-import-recur-notice" class="sc-admin-tools-import-notice sc-admin-tools-import-notice__info">
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: Sugar Calendar Pro pricing page URL. */
						__(
							'If you are importing recurring events, please <a target="_blank" href="%1$s">upgrade to Sugar Calendar Pro</a>. If you import recurring events on Sugar Calendar Lite, then the recurring events will be converted to normal non-recurring events. Are you sure you want to continue?',
							'sugar-calendar-lite'
						),
						esc_url(
							Helpers\Helpers::get_utm_url(
								'https://sugarcalendar.com/lite-upgrade/',
								[
									'medium'  => 'tools-import',
									'content' => 'recurring-events-upgrade',
								]
							)
						)
					),
					[
						'a' => [
							'href'   => [],
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
	 * Validate the import action.
	 *
	 * @since 3.3.0
	 */
	private function validate_import_action() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		// Add filter of the link rel attr to avoid JSON damage.
		add_filter( 'wp_targeted_link_rel', '__return_empty_string', 50, 1 );

		$ext = '';

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( isset( $_FILES['file']['name'] ) ) {
			$ext = strtolower( pathinfo( sanitize_text_field( wp_unslash( $_FILES['file']['name'] ) ), PATHINFO_EXTENSION ) );
		}

		if ( $ext !== 'json' ) {
			wp_die(
				esc_html__( 'Please upload a valid .json Sugar Calendar export file.', 'sugar-calendar-lite' ),
				esc_html__( 'Error', 'sugar-calendar-lite' ),
				[
					'response' => 400,
				]
			);
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			wp_die(
				esc_html__( 'The unfiltered HTML permissions are required to import.', 'sugar-calendar-lite' ),
				esc_html__( 'Error', 'sugar-calendar-lite' ),
				[
					'response' => 400,
				]
			);
		}
	}

	/**
	 * {@inheritDoc}.
	 *
	 * @since 3.3.0
	 */
	public function run( $total_number_to_import = [] ) {

		$this->validate_import_action();

		// The wp_unslash() function breaks upload on Windows.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.NonceVerification.Missing
		$filename = isset( $_FILES['file']['tmp_name'] ) ? sanitize_text_field( $_FILES['file']['tmp_name'] ) : '';

		$data = json_decode( Helpers::remove_utf8_bom( file_get_contents( $filename ) ), true );

		if ( empty( $data ) ) {
			wp_die(
				esc_html( json_last_error_msg() ),
				esc_html__( 'Error', 'sugar-calendar-lite' ),
				[
					'response' => 400,
				]
			);
		}

		$this->importer_data = $this->import( $data );
	}

	/**
	 * Import data.
	 *
	 * @since 3.3.0
	 *
	 * @param array $data The data to import.
	 *
	 * @return array Returns the number of imported items per context.
	 */
	private function import( $data ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		$imported_data = [];

		/**
		 * Pre process import action.
		 *
		 * @since 3.6.0
		 *
		 * @param array         $imported_data The imported data.
		 * @param array         $data          The data to import.
		 * @param SugarCalendar $this          The importer object.
		 *
		 * @return array Returns the number of imported items per context.
		 */
		$imported_data = apply_filters(
			'sugar_calendar_admin_tools_importers_sugar_calendar_imported_data_before_import',
			$imported_data,
			$data,
			$this
		);

		/*
		 * Importing should be done in specific order.
		 * Calendars.
		 */
		if ( array_key_exists( 'calendars', $data ) ) {
			$this->import_calendars( $data['calendars'] );
			$imported_data['calendars'] = absint( $this->imported_calendars_count );
		}

		// Tags.
		if ( array_key_exists( 'tags', $data ) ) {
			$imported_data['tags'] = absint( $this->import_tags( $data['tags'] ) );
		}

		// Attendees.
		if ( array_key_exists( 'attendees', $data ) ) {
			$this->import_attendees( $data['attendees'] );
			$imported_data['attendees'] = absint( $this->imported_attendees_count );
		}

		// Events.
		if ( array_key_exists( 'events', $data ) ) {
			$imported_data['events'] = $this->import_events( $data['events'] );
		}

		// Events tags relationship - process directly from events data.
		if ( array_key_exists( 'events', $data ) && array_key_exists( 'tags', $data ) ) {
			$imported_data['events_tags_related'] = $this->relate_events_to_imported_tags( $data, $this );
		}

		// Orders.
		if ( array_key_exists( 'orders', $data ) ) {
			// If we have this then it means that the SC export was exported with "events" data.
			// So we import these orders without associating them to any events.
			foreach ( $data['orders'] as $order ) {
				$this->import_order( 0, $order );
			}
		}

		// Extra orders.
		if ( array_key_exists( 'extra_orders', $data ) ) {
			// The orders data here isn't associated to any events on the SC export source.
			foreach ( $data['extra_orders'] as $order ) {
				$this->import_order( 0, $order );
			}
		}

		// Extra tickets.
		if ( array_key_exists( 'extra_tickets', $data ) ) {
			// The orders data here isn't associated to any events or orders on the SC export source.
			foreach ( $data['extra_tickets'] as $ticket ) {
				$this->import_ticket(
					$ticket,
					0,
					0,
					$ticket['event_date']
				);
			}
		}

		if ( ! is_null( $this->imported_orders_count ) ) {
			$imported_data['orders'] = $this->imported_orders_count;
		}

		if ( ! is_null( $this->imported_tickets_count ) ) {
			$imported_data['tickets'] = $this->imported_tickets_count;
		}

		/**
		 * Post process import action.
		 *
		 * @since 3.6.0
		 *
		 * @param array         $imported_data The imported data.
		 * @param array         $data          The data to import.
		 * @param SugarCalendar $this          The importer object.
		 *
		 * @return array Returns the number of imported items per context.
		 */
		$imported_data = apply_filters(
			'sugar_calendar_admin_tools_importers_sugar_calendar_imported_data_after_import',
			$imported_data,
			$data,
			$this
		);

		/**
		 * Fires after the import is done.
		 *
		 * @since 3.6.0
		 *
		 * @param array $imported_data The imported data.
		 * @param array $data          The data to import.
		 */
		do_action(
			'sugar_calendar_admin_tools_importers_sugar_calendar_after_import',
			$imported_data,
			$data
		);

		return $imported_data;
	}

	/**
	 * Import calendars.
	 *
	 * @since 3.3.0
	 *
	 * @param array $calendars_data The calendars data to import.
	 */
	private function import_calendars( $calendars_data ) {

		// Separate the calendars with parent to those without.
		$parent_calendars   = [];
		$children_calendars = [];

		foreach ( $calendars_data as $calendar ) {

			if ( empty( $calendar['parent_slug'] ) ) {
				$parent_calendars[] = $calendar;
			} else {
				$children_calendars[] = $calendar;
			}
		}

		// Let's import the parent calendars first.
		foreach ( $parent_calendars as $calendar ) {
			$this->import_calendar( $calendar );
		}

		// Then the children calendars.
		foreach ( $children_calendars as $calendar ) {
			$this->import_calendar( $calendar );
		}
	}

	/**
	 * Import attendees.
	 *
	 * @since 3.3.0
	 *
	 * @param array $attendees_data The attendees data to import.
	 */
	private function import_attendees( $attendees_data ) {

		foreach ( $attendees_data as $attendee ) {
			$this->import_attendee( $attendee );
		}
	}

	/**
	 * Import events and record old and new event ids.
	 *
	 * @since 3.3.0
	 * @since 3.6.0 Add temporary record of imported event ids.
	 *
	 * @param array $events_data Array containing the events data to import.
	 *
	 * @return int The number of imported events.
	 */
	private function import_events( $events_data ) {

		$imported_events_count = 0;

		foreach ( $events_data as $event ) {

			$imported_sc_event = $this->create_sc_event( $event );

			// If the event is imported, add it to the imported events array.
			if (
				isset( $imported_sc_event['sc_event_id'] )
				&&
				isset( $imported_sc_event['sc_event_post_id'] )
			) {

				// Record the old and new event ids.
				$this->imported_event_ids[ $event['id'] ] = $imported_sc_event;

				++$imported_events_count;
			}
		}

		return $imported_events_count;
	}

	/**
	 * {@inheritDoc}.
	 *
	 * @since 3.3.0
	 */
	public function get_slug() {

		return 'sugar-calendar';
	}

	/**
	 * {@inheritDoc}.
	 *
	 * @since 3.3.0
	 */
	public function is_ajax() {

		return false;
	}

	/**
	 * Import tags.
	 *
	 * @since 3.7.0
	 *
	 * @param array $tags_data The tags data.
	 *
	 * @return int Returns the number of tags imported.
	 */
	private function import_tags( $tags_data ) {

		$imported_count = 0;

		$taxonomy = TagHelpers::get_tags_taxonomy_id();

		foreach ( $tags_data as $tag ) {

			// Check if tag already exists.
			$existing_term = get_term_by( 'slug', $tag['slug'], $taxonomy );

			if ( $existing_term && ! is_wp_error( $existing_term ) ) {

				// Use existing term.
				$this->imported_tag_ids[ $tag['id'] ] = $existing_term->term_id;

				++$imported_count;

				continue;
			}

			// Create new tag.
			$term = wp_insert_term(
				$tag['name'],
				$taxonomy,
				[
					'slug'        => $tag['slug'],
					'description' => isset( $tag['description'] ) ? $tag['description'] : '',
				]
			);

			// If tag creation failed, skip.
			if ( is_wp_error( $term ) ) {
				continue;
			}

			// Record the imported tag ID.
			$this->imported_tag_ids[ $tag['id'] ] = $term['term_id'];

			++$imported_count;
		}

		// Return number of imported tags.
		return $imported_count;
	}

	/**
	 * Relate events to imported tags.
	 *
	 * @since 3.7.0
	 *
	 * @param array                                              $data     The data to import.
	 * @param Sugar_Calendar\Admin\Tools\Importers\SugarCalendar $importer The importer object.
	 *
	 * @return int Number of events related to imported tags.
	 */
	private function relate_events_to_imported_tags( $data, $importer ) {

		// Count how many relationships were created.
		$events_tags_related = 0;

		// Map to collect tag IDs for each event.
		$event_tag_map = [];

		// Process events with tags.
		foreach ( $data['events'] as $event ) {

			// Skip if the event has no tags or wasn't imported successfully.
			if (
				empty( $event['tags'] ) ||
				empty( $importer->imported_event_ids[ $event['id'] ] )
			) {
				continue;
			}

			$old_event_id = intval( $event['id'] );

			$new_event_post_id = $importer->imported_event_ids[ $old_event_id ]['sc_event_post_id'];

			// Initialize the array for this event if not exists.
			if ( ! isset( $event_tag_map[ $new_event_post_id ] ) ) {
				$event_tag_map[ $new_event_post_id ] = [];
			}

			// Process each tag ID for this event.
			foreach ( $event['tags'] as $old_tag_id ) {

				// Skip if we don't have mapping for the tag.
				if ( empty( $this->imported_tag_ids[ $old_tag_id ] ) ) {
					continue;
				}

				// Include only valid tag IDs.
				if ( TagHelpers::is_valid_tags_term_id( $this->imported_tag_ids[ $old_tag_id ] ) ) {
					$event_tag_map[ $new_event_post_id ][] = $this->imported_tag_ids[ $old_tag_id ];
				}
			}
		}

		// Create all the relationships.
		foreach ( $event_tag_map as $new_event_post_id => $new_tag_ids ) {

			// Skip if no tags for this event.
			if ( empty( $new_tag_ids ) ) {
				continue;
			}

			// Set the terms for this post.
			$result = wp_set_object_terms(
				$new_event_post_id,
				$new_tag_ids,
				TagHelpers::get_tags_taxonomy_id(),
				true // Append to existing tags.
			);

			// If successful, increment the counter.
			if ( ! is_wp_error( $result ) && ! empty( $result ) ) {
				$events_tags_related += count( $new_tag_ids );
			}
		}

		return $events_tags_related;
	}
}
