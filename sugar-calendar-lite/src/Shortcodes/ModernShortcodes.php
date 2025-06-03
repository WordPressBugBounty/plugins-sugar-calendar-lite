<?php

namespace Sugar_Calendar\Shortcodes;

/**
 * Loader for the shortcodes.
 *
 * @since 3.4.0
 */
class ModernShortcodes {

	/**
	 * Shortcode names and callbacks.
	 *
	 * @since 3.4.0
	 *
	 * @var array
	 */
	public $shortcodes = [];

	/**
	 * Initialize the shortcodes loader.
	 *
	 * @since 3.4.0
	 * @since 3.7.0 Removed venues attribute.
	 */
	public function init() {

		// Set up shortcodes.
		$this->shortcodes = [
			'sc_events_calendar' => [
				'name'       => 'sugarcalendar_events_calendar',
				'callback'   => [ $this, 'shortcode_render_block' ],
				'block'      => 'sugar-calendar/block',
				'attributes' => [
					// Fallback block ID if not set by the user.
					'_id'                       => [
						'block_attribute' => 'calendarId',
						'default'         => '',
						'type'            => 'string',
					],
					// Below are the optional shortcode attributes.
					'id'                        => [
						'block_attribute' => 'user_generated_dom_id',
						'default'         => '',
						'type'            => 'string',
					],
					'calendars'                 => [
						'block_attribute' => 'calendars',
						'default'         => [],
						'type'            => 'array_int',
					],
					'tags'                      => [
						'block_attribute' => 'tags',
						'default'         => [],
						'type'            => 'array_int',
					],
					'display'                   => [
						'block_attribute' => 'display',
						'default'         => 'month',
						'type'            => 'options',
						'options'         => [
							'month',
							'week',
							'day',
						],
					],
					'show_block_header'         => [
						'block_attribute' => 'showBlockHeader',
						'default'         => true,
						'type'            => 'boolean',
					],
					'allow_user_change_display' => [
						'block_attribute' => 'allowUserChangeDisplay',
						'default'         => true,
						'type'            => 'boolean',
					],
					'show_filters'              => [
						'block_attribute' => 'showFilters',
						'default'         => true,
						'type'            => 'boolean',
					],
					'show_search'               => [
						'block_attribute' => 'showSearch',
						'default'         => true,
						'type'            => 'boolean',
					],
					'appearance'                => [
						'block_attribute' => 'appearance',
						'default'         => 'light',
						'type'            => 'options',
						'options'         => [
							'light',
							'dark',
						],
					],
					'accent_color'              => [
						'block_attribute' => 'accentColor',
						'default'         => '#5685BD',
						'type'            => 'string',
					],
				],
			],
			'sc_events_list'     => [
				'name'       => 'sugarcalendar_events_list',
				'callback'   => [ $this, 'shortcode_render_block' ],
				'block'      => 'sugar-calendar/event-list-block',
				'attributes' => [
					// Fallback block ID if not set by the user.
					'_id'                       => [
						'block_attribute' => 'blockId',
						'default'         => '',
						'type'            => 'string',
					],
					// Below are the optional shortcode attributes.
					'id'                        => [
						'block_attribute' => 'user_generated_dom_id',
						'default'         => '',
						'type'            => 'string',
					],
					'calendars'                 => [
						'block_attribute' => 'calendars',
						'default'         => [],
						'type'            => 'array_int',
					],
					'tags'                      => [
						'block_attribute' => 'tags',
						'default'         => [],
						'type'            => 'array_int',
					],
					'group_events_by_week'      => [
						'block_attribute' => 'groupEventsByWeek',
						'default'         => true,
						'type'            => 'boolean',
					],
					'events_per_page'           => [
						'block_attribute' => 'eventsPerPage',
						'default'         => 10,
						'type'            => 'int',
					],
					'maximum_events_to_show'    => [
						'block_attribute' => 'maximumEventsToShow',
						'default'         => 10,
						'type'            => 'int',
					],
					'display'                   => [
						'block_attribute' => 'display',
						'default'         => 'list',
						'type'            => 'options',
						'options'         => [
							'list',
							'grid',
							'plain',
						],
					],
					'show_block_header'         => [
						'block_attribute' => 'showBlockHeader',
						'default'         => true,
						'type'            => 'boolean',
					],
					'allow_user_change_display' => [
						'block_attribute' => 'allowUserChangeDisplay',
						'default'         => true,
						'type'            => 'boolean',
					],
					'show_filters'              => [
						'block_attribute' => 'showFilters',
						'default'         => true,
						'type'            => 'boolean',
					],
					'show_search'               => [
						'block_attribute' => 'showSearch',
						'default'         => true,
						'type'            => 'boolean',
					],
					'show_date_cards'           => [
						'block_attribute' => 'showDateCards',
						'default'         => true,
						'type'            => 'boolean',
					],
					'show_descriptions'         => [
						'block_attribute' => 'showDescriptions',
						'default'         => true,
						'type'            => 'boolean',
					],
					'show_featured_images'      => [
						'block_attribute' => 'showFeaturedImages',
						'default'         => true,
						'type'            => 'boolean',
					],
					'image_position'            => [
						'block_attribute' => 'imagePosition',
						'default'         => 'right',
						'type'            => 'options',
						'options'         => [
							'right',
							'left',
						],
					],
					'appearance'                => [
						'block_attribute' => 'appearance',
						'default'         => 'light',
						'type'            => 'options',
						'options'         => [
							'light',
							'dark',
						],
					],
					'accent_color'              => [
						'block_attribute' => 'accentColor',
						'default'         => '#5685BD',
						'type'            => 'string',
					],
					'links_color'               => [
						'block_attribute' => 'linksColor',
						'default'         => '#000000D9',
						'type'            => 'string',
					],
				],
			],
		];

		/**
		 * Filters the modern shortcodes configuration.
		 *
		 * @since 3.7.0
		 *
		 * @param array             $shortcodes Array of shortcode configurations.
		 * @param ModernShortcodes  $this       Instance of ModernShortcodes.
		 */
		$this->shortcodes = apply_filters( 'sugar_calendar_shortcodes_modern_shortcodes', $this->shortcodes, $this );

		// Register hooks.
		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.4.0
	 */
	public function hooks() {

		// Register shortcodes.
		add_action( 'init', [ $this, 'add_shortcodes' ] );

		// Load block assets only if the shortcode is present in the content.
		add_filter( 'sugar_calendar_block_calendar_should_load_assets', [ $this, 'should_load_assets' ] );
		add_filter( 'sugar_calendar_block_list_should_load_assets', [ $this, 'should_load_assets' ] );

		// Add shortcode attributes filters.
		add_filter( 'sugar_calendar_shortcodes_modern_shortcodes_attributes_sugarcalendar_events_list', [ $this, 'apply_sugarcalendar_events_list_attributes' ], 10, 2 );

		// Handler to avoid shortcode block autop.
		add_filter( 'block_type_metadata_settings', [ $this, 'block_type_metadata_settings' ], 10, 2 );
	}

	/**
	 * Register shortcodes.
	 *
	 * @since 3.4.0
	 */
	public function add_shortcodes() {

		foreach ( $this->shortcodes as $shortcode ) {
			add_shortcode( $shortcode['name'], $shortcode['callback'] );
		}
	}

	/**
	 * Check if the Sugar Calendar block calendar assets should be loaded.
	 *
	 * @since 3.4.0
	 *
	 * @param bool $should_load Whether the assets should be loaded.
	 *
	 * @return bool
	 */
	public function should_load_assets( $should_load ) {

		// Check if the content has the shortcode.
		if (
			$this->has_shortcode_in_content( $this->shortcodes['sc_events_calendar']['name'] )
			||
			$this->has_shortcode_in_content( $this->shortcodes['sc_events_list']['name'] )
			||
			! is_singular()
		) {

			$should_load = true;
		}

		return $should_load;
	}

	/**
	 * Check if the content has the calendar shortcode.
	 *
	 * @since 3.4.0
	 *
	 * @param string $name Shortcode tag.
	 *
	 * @return bool
	 */
	private function has_shortcode_in_content( $name ) {

		return has_shortcode(
			get_post_field( 'post_content', get_the_ID() ),
			$name
		);
	}

	/**
	 * Get shortcode identifier by tag.
	 *
	 * @since 3.4.0
	 *
	 * @param string $tag Shortcode tag.
	 *
	 * @return string|null
	 */
	private function get_shortcode_id_by_tag( $tag ) {

		$id = null;

		foreach ( $this->shortcodes as $identifier => $shortcode ) {

			if ( $shortcode['name'] === $tag ) {

				$id = $identifier;

				break;
			}
		}

		return $id;
	}

	/**
	 * Get block attributes.
	 *
	 * @since 3.4.0
	 *
	 * @param string $identifier           Shortcode identifier.
	 * @param array  $shortcode_attributes Shortcode attributes.
	 *
	 * @return array
	 */
	private function get_block_attributes( $identifier, $shortcode_attributes ) {

		// Get default attributes for the shortcode.
		$defaults = $this->get_shortcode_defaults( $identifier );

		// Parse and sanitize the provided attributes.
		$attributes = $this->parse_shortcode_attributes( $identifier, $shortcode_attributes );

		if ( empty( $attributes['user_generated_dom_id'] ) ) {

			// Define block ID keys.
			$block_id_keys = [
				'sc_events_calendar' => 'calendarId',
				'sc_events_list'     => 'blockId',
			];

			// Set unique block ID.
			$attributes[ $block_id_keys[ $identifier ] ] = wp_unique_id( 'code-' );
		}

		return shortcode_atts( $defaults, $attributes );
	}

	/**
	 * Get default attributes for the shortcode.
	 *
	 * @since 3.4.0
	 *
	 * @param string $identifier Shortcode identifier.
	 *
	 * @return array
	 */
	private function get_shortcode_defaults( $identifier ) {

		$defaults = [];

		foreach ( $this->shortcodes[ $identifier ]['attributes'] as $value ) {

			$defaults[ $value['block_attribute'] ] = $value['default'];
		}

		$defaults['user_generated_dom_id'] = '';

		return $defaults;
	}

	/**
	 * Parse and sanitize shortcode attributes.
	 *
	 * @since 3.4.0
	 *
	 * @param string $identifier           Shortcode identifier.
	 * @param array  $shortcode_attributes WP shortcode attributes.
	 *
	 * @return array
	 */
	private function parse_shortcode_attributes( $identifier, $shortcode_attributes ) {

		$attributes = [];

		// If the shortcode attributes are empty, return an empty array.
		if ( empty( $shortcode_attributes ) ) {
			return $attributes;
		}

		foreach ( $shortcode_attributes as $key => $value ) {

			if ( $this->is_valid_shortcode_attribute( $identifier, $key ) ) {

				$block_attribute = $this->shortcodes[ $identifier ]['attributes'][ $key ]['block_attribute'];

				$type = $this->shortcodes[ $identifier ]['attributes'][ $key ]['type'];

				$value = $this->sanitize_shortcode_value( $key, $value, $type, $identifier );

				if ( ! is_null( $value ) ) {
					$attributes[ $block_attribute ] = $value;
				}
			}
		}

		return $attributes;
	}

	/**
	 * Check if an attribute is valid for the shortcode.
	 *
	 * @since 3.4.0
	 *
	 * @param string $identifier Shortcode identifier.
	 * @param string $key        Attribute key.
	 *
	 * @return bool
	 */
	private function is_valid_shortcode_attribute( $identifier, $key ) {

		return isset( $this->shortcodes[ $identifier ]['attributes'][ $key ] );
	}

	/**
	 * Sanitize the shortcode value based on type.
	 *
	 * @since 3.4.0
	 *
	 * @param string $key        Attribute key.
	 * @param mixed  $value      Value to sanitize.
	 * @param string $type       Attribute type.
	 * @param string $identifier Shortcode identifier.
	 *
	 * @return mixed
	 */
	private function sanitize_shortcode_value( $key, $value, $type, $identifier ) {

		// Get shortcode parameters.
		$shortcode_parameters = $this->shortcodes[ $identifier ]['attributes'][ $key ];

		// Default sanitized value.
		$sanitized_value = null;

		// Determine the sanitization method based on type.
		switch ( $type ) {
			case 'boolean':
				$sanitized_value = $this->sanitize_boolean( $value, $shortcode_parameters['default'] );
				break;

			case 'array_int':
				$sanitized_value = $this->sanitize_array_int( $value );
				break;

			case 'string':
				$sanitized_value = sanitize_text_field( $value );
				break;

			case 'options':
				$sanitized_value = $this->sanitize_options( $value, $shortcode_parameters );
				break;

			case 'int':
				$sanitized_value = $this->sanitize_int( $value, $shortcode_parameters );
				break;
		}

		return $sanitized_value;
	}

	/**
	 * Helper method for boolean sanitization.
	 *
	 * @since 3.4.0
	 *
	 * @param mixed $value         Value to sanitize.
	 * @param mixed $default_value Default value.
	 *
	 * @return bool
	 */
	private function sanitize_boolean( $value, $default_value ) {

		$value = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

		return is_null( $value ) ? $default_value : $value;
	}

	/**
	 * Helper method for array of integers sanitization.
	 *
	 * @since 3.4.0
	 *
	 * @param mixed $value Value to sanitize.
	 *
	 * @return array
	 */
	private function sanitize_array_int( $value ) {

		return array_filter( array_map( 'absint', explode( ',', $value ) ) );
	}

	/**
	 * Helper method for options sanitization.
	 *
	 * @since 3.4.0
	 *
	 * @param mixed $value      Value to sanitize.
	 * @param array $parameters Sanitization parameters.
	 *
	 * @return mixed
	 */
	private function sanitize_options( $value, $parameters ) {

		$value = sanitize_title( $value );

		return in_array( $value, $parameters['options'], true ) ? $value : $parameters['default'];
	}

	/**
	 * Helper method for integer sanitization.
	 *
	 * @since 3.4.0
	 *
	 * @param mixed $value      Value to sanitize.
	 * @param array $parameters Sanitization parameters.
	 *
	 * @return int
	 */
	private function sanitize_int( $value, $parameters ) {

		$value = absint( $value );

		return ! $value ? $parameters['default'] : $value;
	}

	/**
	 * Sugar Calendar Events Calendar shortcode callback.
	 *
	 * @since 3.4.0
	 *
	 * @param array  $shortcode_attributes Shortcode attributes.
	 * @param string $content              Shortcode content.
	 * @param string $tag                  Shortcode tag.
	 *
	 * @return string
	 */
	public function shortcode_render_block( $shortcode_attributes, $content, $tag ) {

		// Standardize the shortcode attributes.
		if ( empty( $shortcode_attributes ) ) {
			$shortcode_attributes = [];
		}

		// Get shortcode identifier.
		$identifier = $this->get_shortcode_id_by_tag( $tag );

		// If the block is not registered, return an empty string.
		if ( ! $identifier ) {
			return '';
		}

		// Parse block attributes.
		$block_attributes = $this->get_block_attributes( $identifier, $shortcode_attributes );

		/**
		 * Filter the shortcode attributes.
		 *
		 * @since 3.4.0
		 *
		 * @param array  $block_attributes     Block attributes.
		 * @param array  $shortcode_attributes Shortcode attributes.
		 * @param string $content              Shortcode content.
		 *
		 * @hook sugar_calendar_shortcodes_modern_shortcodes_attributes_sugarcalendar_events_calendar
		 * @hook sugar_calendar_shortcodes_modern_shortcodes_attributes_sugarcalendar_events_list
		 */
		$attributes = apply_filters( 'sugar_calendar_shortcodes_modern_shortcodes_attributes_' . sanitize_title( $tag ), $block_attributes, $shortcode_attributes, $content );

		// Fetch the block instance.
		$block_content = render_block(
			[
				'blockName' => $this->shortcodes[ $identifier ]['block'],
				'attrs'     => $attributes,
			]
		);

		return $block_content;
	}

	/**
	 * Apply additional logic to Sugar Calendar Events List shortcode attributes.
	 *
	 * @since 3.4.0
	 * @since 3.5.0 Updated so that `eventsPerPage` is never greater than `maximumEventsToShow`.
	 *
	 * @param array $block_attributes     Block attributes.
	 * @param array $shortcode_attributes Shortcode attributes.
	 *
	 * @return array
	 */
	public function apply_sugarcalendar_events_list_attributes( $block_attributes, $shortcode_attributes ) {

		// If dark mode is set in the shortcode and the accent color is not set,
		// set the accent color to white.
		if (
			isset( $shortcode_attributes['appearance'] )
			&&
			sanitize_title( $shortcode_attributes['appearance'] ) === 'dark'
			&&
			empty( $shortcode_attributes['links_color'] )
		) {
			$block_attributes['linksColor'] = '#FFFFFF';
		}

		// If group events by week is used and set to false.
		if (
			isset( $shortcode_attributes['group_events_by_week'] ) &&
			! $block_attributes['groupEventsByWeek'] &&
			$block_attributes['maximumEventsToShow'] < $block_attributes['eventsPerPage']
		) {
			$block_attributes['eventsPerPage'] = $block_attributes['maximumEventsToShow'];
		}

		return $block_attributes;
	}

	/**
	 * Handler for the block type metadata settings filter.
	 *
	 * @since 3.4.0
	 *
	 * @param array $settings Block type settings.
	 * @param array $metadata Block type metadata.
	 *
	 * @return array
	 */
	public function block_type_metadata_settings( $settings, $metadata ) {

		// Bail early if this is not the shortcode block.
		if (
			! isset( $metadata['name'], $settings['render_callback'] )
			||
			$metadata['name'] !== 'core/shortcode'
		) {
			return $settings;
		}

		// Store the original render callback.
		$settings['original_render_callback'] = $settings['render_callback'];

		// Override the render callback.
		$settings['render_callback'] = function ( $attributes, $content ) use ( $settings ) {

			// Check for either of the Sugar Calendar block.
			if ( strstr( $content, 'sugar-calendar-block' ) ) {
				return $content;
			}

			// Not Sugar Calendar shortcode, return the original callback.
			$render_callback = $settings['original_render_callback'];

			return $render_callback( $attributes, $content );
		};

		return $settings;
	}
}
