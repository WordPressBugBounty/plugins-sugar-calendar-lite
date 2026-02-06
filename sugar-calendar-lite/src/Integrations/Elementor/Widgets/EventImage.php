<?php
namespace Sugar_Calendar\Integrations\Elementor\Widgets;

use Elementor\Widget_Image;
use Elementor\Utils;
use Elementor\Group_Control_Image_Size;
use Sugar_Calendar\Integrations\Elementor\Helpers;
use Elementor\Controls_Manager;
use Elementor\Plugin as ElementorPlugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Event Image widget for Elementor.
 *
 * @since 3.10.0
 */
class EventImage extends Widget_Image {

	/**
	 * Get widget name.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget name.
	 */
	public function get_name() {

		return 'sugar-calendar-event-image';
	}

	/**
	 * Get widget title.
	 *
	 * @since 3.10.0
	 *
	 * @return string Widget title.
	 */
	public function get_title() {

		return esc_html__( 'SC Event Image', 'sugar-calendar-lite' );
	}

	/**
	 * Register widget controls.
	 *
	 * @since 3.10.0
	 */
	protected function register_controls() {

		parent::register_controls();

		$this->remove_control( 'caption_source' );
		$this->remove_control( 'caption' );
		$this->remove_control( 'link_to' );
		$this->remove_control( 'link' );

		$featured_image = $this->get_event_image_info();

		$this->update_control(
			'image',
			[
				'type'    => Controls_Manager::HIDDEN,
				'dynamic' => [
					'active' => false,
				],
				'default' => [
					'id'  => $featured_image['id'],
					'url' => $featured_image['url'],
				],
			],
			[
				'recursive' => true,
			]
		);
	}

	/**
	 * Get widget keywords.
	 *
	 * @since 3.10.0
	 *
	 * @return string[] Widget keywords.
	 */
	public function get_keywords() {

		return [ 'sugar calendar', 'event', 'image' ];
	}

	/**
	 * Get the event image information.
	 *
	 * @since 3.10.0
	 *
	 * @return []
	 */
	private function get_event_image_info() {

		$featured_image_id  = get_post_thumbnail_id();
		$featured_image_url = '';

		if ( ! empty( $featured_image_id ) ) {
			$featured_image_url = get_the_post_thumbnail_url();
		} elseif ( ElementorPlugin::$instance->editor->is_edit_mode() ) {
			$featured_image_url = Utils::get_placeholder_image_src();
		}

		return [
			'id'  => $featured_image_id,
			'url' => $featured_image_url,
		];
	}

	/**
	 * Render image widget output on the frontend.
	 *
	 * @since 3.10.0
	 */
	protected function render() {

		$settings = $this->get_settings_for_display();
		$document = ElementorPlugin::$instance->documents->get_current();

		if ( $document->get_name() === 'loop-item' || $document->get_name() === sugar_calendar_get_event_post_type_id() ) {
			$featured_image = $this->get_event_image_info();

			if ( empty( $settings['image'] ) ) {
				$settings['image'] = [
					'id'  => $featured_image['id'],
					'url' => $featured_image['url'],
				];
			} else {
				$settings['image']['id']  = $featured_image['id'];
				$settings['image']['url'] = $featured_image['url'];
			}
		}

		if ( empty( $settings['image']['url'] ) ) {
			return;
		}

		Group_Control_Image_Size::print_attachment_image_html( $settings );
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
	 * Only show Sugar Calendar widgets in Event post type or Loop Grid templates.
	 *
	 * @since 3.10.0
	 *
	 * @return bool
	 */
	public function show_in_panel() {

		return Helpers::should_show_widget();
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
}
