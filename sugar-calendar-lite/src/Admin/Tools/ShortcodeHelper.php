<?php

namespace Sugar_Calendar\Admin\Tools;

use Sugar_Calendar\Helpers as BaseHelpers;
use Sugar_Calendar\Helpers\WP;
use Sugar_Calendar\Shortcodes\ModernShortcodes;

/**
 * Sugar Calendar Shortcode Helper.
 *
 * @since 3.9.0
 */
class ShortcodeHelper {

	/**
	 * Shortcode config.
	 *
	 * @since 3.9.0
	 *
	 * @var array
	 */
	public $config = [];

	/**
	 * Initialize the shortcode helper.
	 *
	 * @since 3.9.0
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 3.9.0
	 */
	public function hooks() {

		// Set shortcode config.
		add_action( 'admin_init', [ $this, 'set_shortcode_config' ] );

		// Add a button to the Classic Editor toolbar next to "Add Media".
		add_action( 'media_buttons', [ $this, 'render_classic_editor_button' ], 20 );

		// Enqueue scripts.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// Render modal container in admin footer.
		add_action( 'admin_footer', [ $this, 'render_modal_container' ] );
	}

	/**
	 * Get shortcode config.
	 *
	 * @since 3.9.0
	 */
	public function set_shortcode_config() {

		$modern_shortcodes = new ModernShortcodes();

		$config = $modern_shortcodes->get_shortcodes_config();

		$shortcode_config_helper = [
			'sc_events_calendar' => [
				'attributes' => [
 					'calendars'                 => [
 						'label'    => __( 'Select Calendar to display', 'sugar-calendar-lite' ),
 						'input_id' => 'sc-sh-calendars',
 						// Options populated from taxonomy terms.
 						'options'  => $this->get_calendars_options(),
 					],
					'display'                   => [
						'label'          => __( 'Display', 'sugar-calendar-lite' ),
						'input_id'       => 'sc-sh-sc_events_calendar-display',
						'options_labels' => [
							'month' => __( 'Month', 'sugar-calendar-lite' ),
							'week'  => __( 'Week', 'sugar-calendar-lite' ),
							'day'   => __( 'Day', 'sugar-calendar-lite' ),
						],
					],
					'show_block_header'         => [
						'label'    => __( 'Show Block Header', 'sugar-calendar-lite' ),
						'input_id' => 'sc-sh-sc_events_calendar-show-block-header',
					],
					'allow_user_change_display' => [
						'label'    => __( 'Allow Users to Change Display', 'sugar-calendar-lite' ),
						'input_id' => 'sc-sh-sc_events_calendar-allow-user-change-display',
					],
					'show_filters'              => [
						'label'    => __( 'Show Filters', 'sugar-calendar-lite' ),
						'input_id' => 'sc-sh-sc_events_calendar-show-filters',
					],
					'show_search'               => [
						'label'    => __( 'Show Search', 'sugar-calendar-lite' ),
						'input_id' => 'sc-sh-sc_events_calendar-show-search',
					],
				],
			],
			'sc_events_list'     => [
				'attributes' => [
 					'calendars'                 => [
 						'label'    => __( 'Select Calendar to display', 'sugar-calendar-lite' ),
 						'input_id' => 'sc-sh-calendars',
 						// Options populated from taxonomy terms.
 						'options'  => $this->get_calendars_options(),
						'choices'  => true,
 					],
					'display'                   => [
						'label'          => __( 'Display', 'sugar-calendar-lite' ),
						'input_id'       => 'sc-sh-sc_events_list-display',
						'options_labels' => [
							'list'  => __( 'List', 'sugar-calendar-lite' ),
							'grid'  => __( 'Grid', 'sugar-calendar-lite' ),
							'plain' => __( 'Plain', 'sugar-calendar-lite' ),
						],
					],
					'group_events_by_week'      => [
						'label'    => __( 'Group events per week', 'sugar-calendar-lite' ),
						'input_id' => 'sc-sh-sc_events_list-group-events-by-week',
					],
					'events_per_page'           => [
						'label'    => __( 'Events per page', 'sugar-calendar-lite' ),
						'input_id' => 'sc-sh-sc_events_list-events-per-page',
					],
					'maximum_events_to_show'    => [
						'label'    => __( 'Maximum events to show', 'sugar-calendar-lite' ),
						'input_id' => 'sc-sh-sc_events_list-maximum-events-to-show',
					],
					'show_block_header'         => [
						'label'    => __( 'Show Block Header', 'sugar-calendar-lite' ),
						'input_id' => 'sc-sh-show-block-header',
					],
					'allow_user_change_display' => [
						'label'    => __( 'Allow Users to Change Display', 'sugar-calendar-lite' ),
						'input_id' => 'sc-sh-sc_events_list-allow-user-change-display',
					],
					'show_filters'              => [
						'label'    => __( 'Show Filters', 'sugar-calendar-lite' ),
						'input_id' => 'sc-sh-sc_events_list-show-filters',
					],
					'show_search'               => [
						'label'    => __( 'Show Search', 'sugar-calendar-lite' ),
						'input_id' => 'sc-sh-sc_events_list-show-search',
					],
					'show_date_cards'           => [
						'label'    => __( 'Show Date Cards', 'sugar-calendar-lite' ),
						'input_id' => 'sc-sh-sc_events_list-show-date-cards',
					],
					'show_descriptions'         => [
						'label'    => __( 'Show Descriptions', 'sugar-calendar-lite' ),
						'input_id' => 'sc-sh-sc_events_list-show-descriptions',
					],
					'show_featured_images'      => [
						'label'    => __( 'Show Featured Images', 'sugar-calendar-lite' ),
						'input_id' => 'sc-sh-sc_events_list-show-featured-images',
					],
					'image_position'            => [
						'label'          => __( 'Image Position', 'sugar-calendar-lite' ),
						'input_id'       => 'sc-sh-sc_events_list-image-position',
						'options_labels' => [
							'left'  => __( 'Left', 'sugar-calendar-lite' ),
							'right' => __( 'Right', 'sugar-calendar-lite' ),
						],
					],
				],
			],
		];

		$this->config = array_replace_recursive( $shortcode_config_helper, $config );
	}

	/**
	 * Get calendars options for multiselect field.
	 *
	 * @since 3.9.0
	 *
	 * @return array key => label pairs for <option> rendering
	 */
	private function get_calendars_options() {

		$terms = get_terms(
			[
				'taxonomy'   => 'sc_event_category',
				'hide_empty' => false,
			]
		);

		$options = [];

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return $options;
		}

		foreach ( $terms as $term ) {
			$options[ (string) $term->term_id ] = $term->name;
		}

		return $options;
	}

	/**
	 * Get modal config.
	 *
	 * @since 3.9.0
	 *
	 * @return array
	 */
	public function get_modal_config() {

		return [
			'title'      => __( 'Insert Sugar Calendar', 'sugar-calendar-lite' ),
			'width'      => 720,
			'height'     => 640,
			'inline_id'  => 'sc-shortcode-helper-modal',
			'identifier' => 'shortcode-helper',
		];
	}

	/**
	 * Registers assets.
	 *
	 * @since 3.9.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script(
			'sugar-calendar-admin-shortcode-helper',
			SC_PLUGIN_ASSETS_URL . 'js/admin-shortcode-helper' . WP::asset_min() . '.js',
			[ 'jquery', 'thickbox', 'sugar-calendar-vendor-choices' ],
			BaseHelpers::get_asset_version(),
			true
		);

		wp_enqueue_style(
			'sugar-calendar-admin-shortcode-helper',
			SC_PLUGIN_ASSETS_URL . 'css/admin-shortcode-helper' . WP::asset_min() . '.css',
			[ 'thickbox' ],
			BaseHelpers::get_asset_version()
		);

		// Static variables for initial implementation.
		wp_localize_script(
			'sugar-calendar-admin-shortcode-helper',
			'scShortcodeHelper',
			[
				'title'      => $this->get_modal_config()['title'],
				'width'      => $this->get_modal_config()['width'],
				'height'     => $this->get_modal_config()['height'],
				'inlineId'   => $this->get_modal_config()['inline_id'],
				'identifier' => $this->get_modal_config()['identifier'],
				'config'     => $this->config,
			]
		);
	}

	/**
	 * Renders hidden ThickBox modal container.
	 *
	 * This outputs a hidden div that ThickBox will display when requested.
	 *
	 * @since 3.9.0
	 *
	 * @return void
	 */
	public function render_modal_container() {

		// Only print once.
		static $printed = false;

		if ( $printed ) {
			return;
		}

		$printed = true;

		?>
		<div id="<?php echo esc_attr( $this->get_modal_config()['inline_id'] ); ?>" style="display:none;">
			<div class="sc-shortcode-helper-modal-wrap" role="dialog" aria-labelledby="sc-sh-title">
				<div class="sc-sh-content" type-selected="sc_events_calendar">

					<div class="sc-sh-section sc-sh-type-selection">
						<div class="sc-sh-section-title"><?php echo esc_html__( 'Select what you would like to insert', 'sugar-calendar-lite' ); ?></div>
						<div class="sc-sh-options" role="group" aria-label="<?php echo esc_attr__( 'Insert options', 'sugar-calendar-lite' ); ?>">
							<label class="sc-sh-option"><input type="radio" name="sc_sh_type" value="sc_events_calendar" checked> <?php echo esc_html__( 'Events Calendar', 'sugar-calendar-lite' ); ?></label>
							<label class="sc-sh-option"><input type="radio" name="sc_sh_type" value="sc_events_list"> <?php echo esc_html__( 'Events List', 'sugar-calendar-lite' ); ?></label>
						</div>
					</div>
					<hr class="sc-sh-separator" />

					<div class="sc-sh-section sc-sh-query-options">
						<?php
						// Calendars multiselect (shared for both shortcodes).
						$this->render_field(
							'boolean',
							[
								'input_id' => 'sc-sh-all-calendars',
								'label'    => __( 'Display events from all calendars', 'sugar-calendar-lite' ),
								'default'  => true,
							]
						);
						$this->render_field( 'array_int', $this->config['sc_events_calendar']['attributes']['calendars'] );

						// Group events by week.
						$this->render_field( 'boolean', $this->config['sc_events_list']['attributes']['group_events_by_week'] );
						$this->render_field( 'int', $this->config['sc_events_list']['attributes']['events_per_page'] );
						$this->render_field( 'int', $this->config['sc_events_list']['attributes']['maximum_events_to_show'] );
						?>
					</div>
					<hr class="sc-sh-separator" />

					<div class="sc-sh-section sc-sh-display-options" data-shortcode="sc_events_calendar">

						<?php
						$this->render_field( 'options', $this->config['sc_events_calendar']['attributes']['display'] );
						$this->render_field( 'boolean', $this->config['sc_events_calendar']['attributes']['show_block_header'] );
						$this->render_field( 'boolean', $this->config['sc_events_calendar']['attributes']['allow_user_change_display'] );
						$this->render_field( 'boolean', $this->config['sc_events_calendar']['attributes']['show_filters'] );
						$this->render_field( 'boolean', $this->config['sc_events_calendar']['attributes']['show_search'] );
						?>
					</div>

					<div class="sc-sh-section sc-sh-display-options" data-shortcode="sc_events_list">

						<?php
						$this->render_field( 'options', $this->config['sc_events_list']['attributes']['display'] );
						$this->render_field( 'boolean', $this->config['sc_events_list']['attributes']['show_block_header'] );
						$this->render_field( 'boolean', $this->config['sc_events_list']['attributes']['allow_user_change_display'] );
						$this->render_field( 'boolean', $this->config['sc_events_list']['attributes']['show_filters'] );
						$this->render_field( 'boolean', $this->config['sc_events_list']['attributes']['show_search'] );
						$this->render_field( 'boolean', $this->config['sc_events_list']['attributes']['show_date_cards'] );
						$this->render_field( 'boolean', $this->config['sc_events_list']['attributes']['show_descriptions'] );
						$this->render_field( 'boolean', $this->config['sc_events_list']['attributes']['show_featured_images'] );
						$this->render_field( 'options', $this->config['sc_events_list']['attributes']['image_position'] );
						?>
					</div>
				</div>
				<div class="sc-sh-footer">
					<button type="button" class="button button-primary sc-insert-shortcode-confirm"><?php echo esc_html__( 'Add Shortcode', 'sugar-calendar-lite' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the Classic Editor toolbar button.
	 *
	 * Appears to the right of the core "Add Media" button. This method only
	 * outputs markup; behavior (e.g., opening a modal or inserting shortcodes)
	 * can be attached separately via JS if needed.
	 *
	 * @since 3.9.0
	 *
	 * @param string $editor_id The editor instance ID.
	 *
	 * @return void
	 */
	public function render_classic_editor_button( $editor_id ) {

		// Add Shortcode button label.
		$label = __( 'Add Sugar Calendar', 'sugar-calendar-lite' );

		// Button class.
		$button_class = 'button sc-insert-shortcode-button';

		// Button icon.
		$button_icon = '<span class="sc-insert-shortcode-button-icon">
			<svg xmlns="http://www.w3.org/2000/svg" width="17" height="16" viewBox="0 0 17 16" fill="none">
				<path fill-rule="evenodd" clip-rule="evenodd" d="M3.58844 0.795376C3.58844 0.591908 3.68092 0.388439 3.8289 0.240462C3.99538 0.0924855 4.19884 0 4.42081 0C4.64277 0 4.86474 0.0924855 5.01272 0.240462C5.16069 0.388439 5.25318 0.57341 5.25318 0.795376L10.7653 0.795376C10.7653 0.591908 10.8578 0.388439 11.0058 0.240462C11.1538 0.0924855 11.3757 0 11.5977 0C11.8197 0 12.0416 0.0924855 12.1896 0.240462C12.3376 0.388439 12.4301 0.57341 12.4301 0.795376H12.7075C14.5387 0.795376 16.0185 2.27514 16.0185 4.10636L16.0185 12.689C16.0185 14.5202 14.5387 16 12.7075 16L3.31098 16C1.47977 16 0 14.5202 0 12.689L0 4.10636C0 2.27514 1.47977 0.795376 3.31098 0.795376H3.58844ZM13.9838 11.9121C13.9838 12.4855 13.7618 13.022 13.3549 13.4289C12.948 13.8358 12.4116 14.0578 11.8382 14.0578L4.18035 14.0578C2.99653 14.0578 2.03468 13.096 2.03468 11.9121V11.5237C2.03468 11.5237 2.09017 11.3942 2.14566 11.3942L9.91445 11.3942C10.3954 11.3942 10.7653 11.0243 10.7653 10.5434C10.7653 10.0809 10.3769 9.69249 9.91445 9.69249L2.99653 9.69249C2.73757 9.69249 2.49711 9.6 2.31214 9.41503C2.12717 9.23006 2.03468 8.9896 2.03468 8.74913L2.03468 4.25434C2.03468 3.8474 2.20116 3.45896 2.47861 3.1815C2.75607 2.90405 3.14451 2.73757 3.55145 2.73757C3.55145 2.73757 3.55145 2.73757 3.56994 2.73757C3.56994 2.73757 3.56994 2.73757 3.56994 2.75607C3.56994 3.16301 3.90289 3.49595 4.30983 3.49595H4.5133C4.92023 3.49595 5.25318 3.16301 5.25318 2.75607C5.25318 2.75607 5.25318 2.71908 5.29017 2.71908L10.7283 2.71908C10.7283 2.71908 10.7653 2.71908 10.7653 2.75607C10.7653 3.16301 11.0983 3.49595 11.5052 3.49595H11.7087C12.1156 3.49595 12.4486 3.16301 12.4486 2.75607V2.73757C12.4486 2.73757 12.4486 2.73757 12.4671 2.73757C13.3179 2.73757 13.9838 3.42197 13.9838 4.25434V5.27168C13.9838 5.27168 13.9283 5.40116 13.8728 5.40116L6.12254 5.40116C5.66012 5.40116 5.27168 5.7711 5.27168 6.25202C5.27168 6.71445 5.66012 7.10289 6.12254 7.10289L13.0405 7.10289C13.2994 7.10289 13.5399 7.19538 13.7249 7.38035C13.9098 7.56532 14.0023 7.80578 14.0023 8.04624V11.9121H13.9838Z" fill="#2271B1"/>
			</svg>
		</span>';

		// Button markup with wp_kses_post escaping for post-like content.
		echo wp_sprintf(
			'<button type="button" class="%1$s" data-editor="%2$s" aria-label="%3$s" title="%4$s">%6$s%5$s</button>',
			esc_attr( $button_class ),
			esc_attr( $editor_id ),
			esc_attr( $label ),
			esc_attr( $label ),
			esc_html( $label ),
			wp_kses(
				$button_icon,
				[
					'svg'  => [
						'xmlns'   => true,
						'width'   => true,
						'height'  => true,
						'viewBox' => true,
						'fill'    => true,
					],
					'path' => [
						'fill-rule' => true,
						'clip-rule' => true,
						'd'         => true,
						'fill'      => true,
					],
					'span' => [
						'class' => true,
					],
				]
			)
		);
	}

	/**
	 * Renders a form field based on type and configuration.
	 *
	 * @since 3.9.0
	 *
	 * @param string $type The field type (options, boolean, string, int, array_int).
	 * @param array  $args The field configuration array.
	 *
	 * @return void
	 */
	private function render_field( $type, $args ) {

		// Ensure required keys exist.
		if ( ! isset( $args['input_id'], $args['label'] ) ) {
			return;
		}

		$input_id      = $args['input_id'];
		$default_value = $args['default'] ?? '';
		$wrapper_class = '';

		ob_start();

		switch ( $type ) {
			case 'options':
				$wrapper_class = 'sc-sh-field-options';

				$this->render_select_field( $input_id, $default_value, $args );
				break;

			case 'boolean':
				$wrapper_class = 'sc-sh-field-boolean';

				$this->render_checkbox_field( $input_id, $default_value, $args );
				break;

			case 'string':
				$wrapper_class = 'sc-sh-field-string';

				$this->render_text_field( $input_id, $default_value, $args );
				break;

			case 'int':
				$wrapper_class = 'sc-sh-field-int';

				$this->render_number_field( $input_id, $default_value, $args );
				break;

			case 'array_int':
				$wrapper_class = 'sc-sh-field-array_int';

				$this->render_multiselect_field( $input_id, $default_value, $args );
				break;

			default:
				$wrapper_class = 'sc-sh-field-text';

				$this->render_text_field( $input_id, $default_value, $args );
				break;
		}

		$input = ob_get_clean();

		?>
		<div class="sc-sh-field <?php echo esc_attr( $wrapper_class ); ?>">
			<?php echo $input; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
	}

	/**
	 * Renders a select dropdown field.
	 *
	 * @since 3.9.0
	 *
	 * @param string $input_id      The input ID.
	 * @param mixed  $default_value The default value.
	 * @param array  $args          The field configuration.
	 *
	 * @return void
	 */
	private function render_select_field( $input_id, $default_value, $args ) {

		?>
		<label for="<?php echo esc_attr( $input_id ); ?>" class="sc-sh-field-label"><?php echo esc_html( $args['label'] ); ?></label>
		<select id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $input_id ); ?>" class="sc-sh-field-input">
			<?php
			if ( isset( $args['options_labels'] ) ) {
				foreach ( $args['options_labels'] as $value => $label ) {
					?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $default_value, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php
				}
			}
			?>
		</select>
		<?php
	}

	/**
	 * Renders a checkbox field.
	 *
	 * @since 3.9.0
	 *
	 * @param string $input_id      The input ID.
	 * @param mixed  $default_value The default value.
	 * @param array  $args          The field configuration.
	 *
	 * @return void
	 */
	private function render_checkbox_field( $input_id, $default_value, $args ) {

		?>
		<input
			type="checkbox"
			id="<?php echo esc_attr( $input_id ); ?>"
			name="<?php echo esc_attr( $input_id ); ?>"
			class="sc-sh-field-input"
			<?php checked( $default_value, true ); ?>
		>
		<label for="<?php echo esc_attr( $input_id ); ?>" class="sc-sh-field-label"><?php echo esc_html( $args['label'] ); ?></label>
		<?php
	}

	/**
	 * Renders a text input field.
	 *
	 * @since 3.9.0
	 *
	 * @param string $input_id      The input ID.
	 * @param mixed  $default_value The default value.
	 * @param array  $args          The field configuration.
	 *
	 * @return void
	 */
	private function render_text_field( $input_id, $default_value, $args ) {

		?>
		<label for="<?php echo esc_attr( $input_id ); ?>" class="sc-sh-field-label"><?php echo esc_html( $args['label'] ); ?></label>
		<input
			type="text"
			id="<?php echo esc_attr( $input_id ); ?>"
			name="<?php echo esc_attr( $input_id ); ?>"
			class="sc-sh-field-input"
			value="<?php echo esc_attr( $default_value ); ?>"
		>
		<?php
	}

	/**
	 * Renders a number input field.
	 *
	 * @since 3.9.0
	 *
	 * @param string $input_id      The input ID.
	 * @param mixed  $default_value The default value.
	 * @param array  $args          The field configuration.
	 *
	 * @return void
	 */
	private function render_number_field( $input_id, $default_value, $args ) {

		?>
		<label for="<?php echo esc_attr( $input_id ); ?>" class="sc-sh-field-label"><?php echo esc_html( $args['label'] ); ?></label>
		<input
			type="number"
			id="<?php echo esc_attr( $input_id ); ?>"
			name="<?php echo esc_attr( $input_id ); ?>"
			class="sc-sh-field-input"
			value="<?php echo esc_attr( $default_value ); ?>"
		>
		<?php
	}

	/**
	 * Renders a multiselect field (for array_int type).
	 *
	 * @since 3.9.0
	 *
	 * @param string $input_id      The input ID.
	 * @param mixed  $default_value The default value.
	 * @param array  $args          The field configuration.
	 *
	 * @return void
	 */
	private function render_multiselect_field( $input_id, $default_value, $args ) {

		$options = $args['options'] ?? [];
		?>
		<label for="<?php echo esc_attr( $input_id ); ?>" class="sc-sh-field-label"><?php echo esc_html( $args['label'] ); ?></label>
		<span class="choicesjs-select-wrap">
			<select
				id="<?php echo esc_attr( $input_id ); ?>"
				name="<?php echo esc_attr( $input_id ); ?>[]"
				class="sc-sh-field-input choicesjs-select"
				multiple
			>
				<?php foreach ( $options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php echo in_array( (string) $value, array_map( 'strval', (array) $default_value ), true ) ? 'selected' : ''; ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</span>
		<?php
	}
}
