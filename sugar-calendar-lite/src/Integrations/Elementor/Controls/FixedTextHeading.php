<?php
namespace Sugar_Calendar\Integrations\Elementor\Controls;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Sugar Calendar-custom Elementor fixed text heading control.
 *
 * @since 3.10.0
 */
class FixedTextHeading extends \Elementor\Base_Data_Control {

	/**
	 * Get text control type.
	 *
	 * @since 3.10.0
	 *
	 * @return string
	 */
	public function get_type() {

		return 'sugar-calendar-fixed-text-heading';
	}

	/**
	 * Render text control output in the editor.
	 *
	 * @since 3.10.0
	 */
	public function content_template() {
		?>
		<div class="elementor-control-field">
			<# if ( data.label ) {#>
				<label for="<?php $this->print_control_uid(); ?>" class="elementor-control-title">{{{ data.label }}}</label>
			<# } #>
			<div class="elementor-control-input-wrapper elementor-control-unit-5">
				<input disabled id="<?php $this->print_control_uid(); ?>" type="{{ data.input_type }}" class="tooltip-target elementor-control-tag-area" data-tooltip="{{ data.title }}" data-setting="{{ data.name }}" placeholder="{{ view.getControlPlaceholder() }}" />
			</div>
		</div>
		<# if ( data.description ) { #>
			<div class="elementor-control-field-description">{{{ data.description }}}</div>
		<# } #>
		<?php
	}

	/**
	 * Get fixed text control default settings.
	 *
	 * @since 3.10.0
	 *
	 * @return array Control default settings.
	 */
	protected function get_default_settings() {

		return [
			'ai'          => [
				'active' => false,
				'type'   => 'text',
			],
			'input_type'  => 'text',
			'placeholder' => '',
			'title'       => '',
		];
	}
}
