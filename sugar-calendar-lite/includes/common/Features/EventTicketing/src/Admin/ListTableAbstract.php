<?php

namespace Sugar_Calendar\AddOn\Ticketing\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Sugar_Calendar\Event_Query;
use Sugar_Calendar\Helper;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Shared base of the Tickets, Orders and Coupons list tables.
 *
 * @since 3.17.0
 */
abstract class ListTableAbstract extends \WP_List_Table {

	/**
	 * User saved preferences (the screen options).
	 *
	 * @since 3.17.0
	 *
	 * @var array|false
	 */
	public $user_saved_pref = false;

	/**
	 * Rows per page until the user saves another number.
	 *
	 * @since 3.17.0
	 *
	 * @var int
	 */
	public $per_page = 20;

	/**
	 * Number of items across every view, set by the list query.
	 *
	 * @since 3.17.0
	 *
	 * @var int
	 */
	public $total_count = 0;

	/**
	 * Bulk actions, cached by the parent class.
	 *
	 * @since 3.17.0
	 *
	 * @var array|null
	 */
	protected $_actions; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Whether the Events filter takes several events, sent as `event_id[]`.
	 *
	 * @since 3.17.0
	 *
	 * @var bool
	 */
	protected $many_events = false;

	/**
	 * Value of the hidden `tab` input in the list form.
	 *
	 * @since 3.17.0
	 *
	 * @return string
	 */
	abstract protected function get_hidden_tab();

	/**
	 * Placeholder of the search input.
	 *
	 * @since 3.17.0
	 *
	 * @return string
	 */
	abstract protected function get_search_placeholder();

	/**
	 * Display the search reset block.
	 *
	 * @since 3.17.0
	 */
	abstract public function display_search_reset();

	/**
	 * Display the search reset block of this list's tab.
	 *
	 * @since 3.17.0
	 *
	 * @param string $single Translated singular item name.
	 * @param string $plural Translated plural item name.
	 * @param string $all    Translated heading of the unfiltered list.
	 */
	protected function display_tab_search_reset( $single, $plural, $all ) {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['s'] ) || $_GET['s'] === '' ) {
			return;
		}

		Helper::display_search_reset(
			$this->total_count,
			strtolower( $single ),
			strtolower( $plural ),
			$all,
			admin_url( 'admin.php?page=sc-event-ticketing&tab=' . $this->get_hidden_tab() )
		);
	}

	/**
	 * Display the bulk actions dropdown.
	 *
	 * @since 3.17.0
	 *
	 * @param string $which The location of the bulk actions: 'top' or 'bottom'.
	 */
	protected function bulk_actions( $which = '' ) {

		if ( is_null( $this->_actions ) ) {
			$this->_actions = $this->get_bulk_actions();
		}

		if ( empty( $this->_actions ) ) {
			return;
		}

		$this->display_bulk_select( $which );

		submit_button(
			__( 'Apply', 'sugar-calendar-lite' ),
			'action',
			'',
			false,
			[
				'id' => 'doaction' . ( $which === 'bottom' ? '2' : '' ),
			]
		);
	}

	/**
	 * Display the bulk action select and the hidden tab input.
	 *
	 * @since 3.17.0
	 *
	 * @param string $which The location of the bulk actions: 'top' or 'bottom'.
	 */
	private function display_bulk_select( $which ) {

		?>
		<label for="bulk-action-selector-<?php echo esc_attr( $which ); ?>" class="screen-reader-text"><?php echo esc_html__( 'Select bulk action', 'sugar-calendar-lite' ); ?></label>
		<select name="action<?php echo $which === 'bottom' ? '2' : ''; ?>" id="bulk-action-selector-<?php echo esc_attr( $which ); ?>">
			<option value="-1"><?php echo esc_html__( 'Bulk actions', 'sugar-calendar-lite' ); ?></option>
			<?php foreach ( $this->_actions as $key => $value ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="hidden" name="tab" value="<?php echo esc_attr( $this->get_hidden_tab() ); ?>" />
		<?php
	}

	/**
	 * Displays extra controls between bulk actions and pagination.
	 *
	 * @since 3.17.0
	 *
	 * @param string $which Whether top or bottom.
	 */
	protected function extra_tablenav( $which ) {

		if ( $which !== 'top' ) {
			return;
		}

		?>
		<div class="sugar-calendar-ticketing__admin__list__actions alignleft actions">
			<span class="sugar-calendar-ticketing__admin__list__actions__choices-events choicesjs-select-wrap<?php echo $this->many_events ? ' sugar-calendar-ticketing__admin__list__actions__choices-events--many' : ''; ?>">
				<?php $this->display_event_select(); ?>
			</span>
			<?php
			printf(
				'<input type="submit" class="button" value="%1$s">',
				esc_attr__( 'Filter', 'sugar-calendar-lite' )
			);
			?>
		</div>
		<?php
	}

	/**
	 * Display the Events filter select (enhanced by the Choices script).
	 *
	 * @since 3.17.0
	 */
	private function display_event_select() {

		?>
		<select
			id="sugar-calendar-ticketing-event"
			name="<?php echo esc_attr( $this->many_events ? 'event_id[]' : 'event_id' ); ?>"
			class="choicesjs-select"
			data-max-items="<?php echo esc_attr( $this->many_events ? '-1' : '1' ); ?>"
			multiple
		>
			<?php
			echo wp_kses(
				$this->get_selected_event_options(),
				[
					'option' => [
						'selected' => [],
						'value'    => [],
					],
				]
			);
			?>
		</select>
		<?php
	}

	/**
	 * The `<option>`s of the events picked in the Events filter, if any.
	 *
	 * @since 3.17.0
	 *
	 * @return string
	 */
	protected function get_selected_event_options() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$event_ids = isset( $_GET['event_id'] ) ? array_values( array_filter( wp_parse_id_list( wp_unslash( $_GET['event_id'] ) ) ) ) : [];

		if ( $event_ids === [] ) {
			return '';
		}

		$query   = new Event_Query(
			[
				'id__in' => $event_ids,
				'number' => count( $event_ids ),
			]
		);
		$options = '';

		foreach ( $query->items as $event ) {
			$options .= sprintf(
				'<option selected value="%1$d">%2$s</option>',
				(int) $event->id,
				esc_html( $event->title )
			);
		}

		return $options;
	}

	/**
	 * Show the search field.
	 *
	 * @since 3.17.0
	 *
	 * @param string $text     Label for the search box.
	 * @param string $input_id ID of the search box.
	 */
	public function search_box( $text, $input_id ) {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$has_search = isset( $_REQUEST['s'] ) && $_REQUEST['s'] !== '';

		if ( ! $has_search && ! $this->has_items() ) {
			return;
		}

		$input_id = $input_id . '-search-input';

		$this->display_hidden_sort_fields();

		?>
		<p class="search-box">
			<?php $this->display_search_input( $text, $input_id ); ?>
			<input type="hidden" name="tab" value="<?php echo esc_attr( $this->get_hidden_tab() ); ?>" />
			<input type="submit" id="search-submit" class="button" value="<?php esc_attr_e( 'Search', 'sugar-calendar-lite' ); ?>">
		</p>
		<?php
	}

	/**
	 * Display the search label and input.
	 *
	 * @since 3.17.0
	 *
	 * @param string $text     Label for the search box.
	 * @param string $input_id ID of the search input.
	 */
	private function display_search_input( $text, $input_id ) {

		/**
		 * Fires at the start of the Tickets, Orders and Coupons search box.
		 *
		 * @since 3.17.0
		 */
		do_action( 'sc_event_tickets_list_table_search_box' ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		?>
		<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $text ); ?>:</label>
		<input
			type="search"
			id="<?php echo esc_attr( $input_id ); ?>"
			name="s"
			value="<?php _admin_search_query(); ?>"
			placeholder="<?php echo esc_attr( $this->get_search_placeholder() ); ?>" />
		<?php
	}

	/**
	 * Print the current sort as hidden inputs, so a search keeps it.
	 *
	 * @since 3.17.0
	 */
	private function display_hidden_sort_fields() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		foreach ( [ 'orderby', 'order' ] as $name ) {
			if ( isset( $_REQUEST[ $name ] ) && $_REQUEST[ $name ] !== '' ) {
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( sanitize_text_field( wp_unslash( $_REQUEST[ $name ] ) ) ) . '" />';
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Generates the tbody element for the list table.
	 *
	 * @since 3.17.0
	 */
	public function display_rows_or_placeholder() {

		if ( $this->has_items() ) {
			$this->display_rows();
		} else {
			$this->no_items();
		}
	}

	/**
	 * Set the column headers from the user's saved column choices (the gear).
	 *
	 * @since 3.17.0
	 *
	 * @param string   $table          Table name used in the user meta key.
	 * @param string[] $required       Columns that are never hidden.
	 * @param string[] $default_active Columns shown until the user saves a choice. Empty means every column.
	 */
	protected function set_column_headers( $table, array $required, array $default_active = [] ) {

		$columns = $this->get_columns();

		$active = get_user_meta( get_current_user_id(), 'sugar_calendar_table_' . $table . '_active_columns', true );

		if ( ! is_array( $active ) ) {
			$active = empty( $default_active ) ? array_keys( $columns ) : $default_active;
		}

		$active = array_unique( array_merge( $required, $active ) );
		$hidden = array_diff( array_keys( $columns ), $active, $required );

		$this->_column_headers = [ $columns, $hidden, $this->get_sortable_columns() ];
	}

	/**
	 * Apply the per page value saved in the screen options.
	 *
	 * @since 3.17.0
	 */
	protected function apply_saved_per_page() {

		if ( empty( $this->user_saved_pref['pagination_per_page'] ) ) {
			return;
		}

		$per_page = absint( $this->user_saved_pref['pagination_per_page'] );

		if ( ! empty( $per_page ) ) {
			$this->per_page = $per_page;
		}
	}
}
