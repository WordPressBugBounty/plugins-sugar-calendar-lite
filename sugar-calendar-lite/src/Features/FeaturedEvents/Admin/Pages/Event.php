<?php

namespace Sugar_Calendar\Features\FeaturedEvents\Admin\Pages;

use Sugar_Calendar\Features\FeaturedEvents\Common\Featured;
use Sugar_Calendar\Helpers\UI;

/**
 * Featured Events UI on the Event Edit admin page.
 *
 * @since 3.14.0
 */
class Event {

	/**
	 * Render the "Featured Event" toggle, below the date fields on the General tab.
	 *
	 * @since 3.14.0
	 *
	 * @param object $event The event object. May be an empty event on add-new.
	 */
	public function display_toggle( $event = null ) {

		$featured = Featured::is_featured( $event );
		?>

		<div class="sugar-calendar-metabox__field-row sugar-calendar-metabox__field-row--featured">
			<label for="featured"><?php esc_html_e( 'Featured Event', 'sugar-calendar-lite' ); ?></label>
			<div class="sugar-calendar-metabox__field">
				<?php
				UI::toggle_control(
					[
						'name'          => Featured::META_KEY,
						'id'            => Featured::META_KEY,
						'value'         => $featured,
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

		<?php
	}

	/**
	 * Add the posted featured flag to the event data being saved.
	 *
	 * An absent key means not featured — an unchecked toggle posts nothing.
	 *
	 * @since 3.14.0
	 *
	 * @param array $event Event data to save.
	 *
	 * @return array
	 */
	public function save( $event ) {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$event[ Featured::META_KEY ] = ! empty( $_POST[ Featured::META_KEY ] ) ? '1' : '';

		return $event;
	}
}
