<?php

namespace Sugar_Calendar\Admin\SetupChecklist;

use Sugar_Calendar\Helpers\WP;

/**
 * Renders checklist action controls.
 *
 * @since 3.15.0
 */
class ActionRenderer {

	/**
	 * Id shared by the page-selector trigger, its popover and the embed button.
	 *
	 * @since 3.15.0
	 *
	 * @var string
	 */
	const PAGE_SELECTOR_ID = 'sugar-calendar-checklist-page-selector';

	/**
	 * Render a row's action column.
	 *
	 * @since 3.15.0
	 *
	 * @param string   $item_id Stable item id.
	 * @param array    $action  Action definition.
	 * @param string   $email   Newsletter email address.
	 * @param string[] $input   The row's declared `input` fields, which the control's click sends.
	 */
	public function render( $item_id, $action, $email, $input = [] ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh -- one arm per action type; splitting the dispatch would only hide it.

		switch ( $action['type'] ?? '' ) {
			case 'link':
				$this->render_link_action( $item_id, $action );
				break;

			case 'page-selector':
				$this->render_page_selector_action( $item_id, $action, $input );
				break;

			case 'email':
				$this->render_email_action( $item_id, $action, $email, $input );
				break;

			case 'stripe':
				$this->render_stripe_action( $item_id, $action );
				break;

			case 'notice':
				$this->render_notice_action( $action );
				break;

			default:
				$this->render_button_action( $item_id, $action );
		}
	}

	/**
	 * Render a destination-only link styled as a button.
	 *
	 * @since 3.15.0
	 *
	 * @param string $item_id Stable item id.
	 * @param array  $action  Action definition.
	 */
	private function render_link_action( $item_id, $action ) {

		$external = $this->is_external_url( $action['url'] );
		?>
		<a class="sc-button <?php echo esc_attr( $this->get_button_variant_class( $action ) ); ?>"
				href="<?php echo esc_url( $action['url'] ); ?>"
				<?php $this->render_action_attributes( $item_id, $action ); ?>
			<?php echo $external ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
			<?php echo esc_html( $action['label'] ); ?>
			<?php $this->render_action_icon( $action ); ?>
		</a>
		<?php
	}

	/**
	 * Render a message in place of a control, for a row the site cannot act on yet.
	 *
	 * @since 3.15.0
	 *
	 * @param array $action Action definition: `label` (the lead sentence) and optional `message`.
	 */
	private function render_notice_action( $action ) {

		?>
		<p class="sugar-calendar-checklist__notice">
			<strong><?php echo esc_html( $action['label'] ?? '' ); ?></strong>
			<?php echo esc_html( $action['message'] ?? '' ); ?>
		</p>
		<?php
	}

	/**
	 * Render an action button.
	 *
	 * @since 3.15.0
	 *
	 * @param string $item_id Stable item id.
	 * @param array  $action  Action definition.
	 */
	private function render_button_action( $item_id, $action ) {

		?>
		<button type="button"
				class="sc-button <?php echo esc_attr( $this->get_button_variant_class( $action ) ); ?>"
				<?php $this->render_action_attributes( $item_id, $action ); ?>>
			<?php echo esc_html( $action['label'] ); ?>
			<?php $this->render_action_icon( $action ); ?>
		</button>
		<?php
	}

	/**
	 * Render the page selector and its embed button.
	 *
	 * @since 3.15.0
	 *
	 * @param string   $item_id Stable item id.
	 * @param array    $action  Action definition.
	 * @param string[] $input   Declared input fields.
	 */
	private function render_page_selector_action( $item_id, $action, $input ) {

		$id = self::PAGE_SELECTOR_ID;
		?>
		<div class="sugar-calendar-checklist__page-selector">
			<button type="button"
					class="sc-select"
					id="<?php echo esc_attr( $id ); ?>"
					data-state="placeholder"
					aria-haspopup="listbox"
					aria-expanded="false"
					aria-controls="<?php echo esc_attr( $id ); ?>-menu">
				<span class="sugar-calendar-checklist__page-selector-label">
					<?php esc_html_e( 'Select a Page', 'sugar-calendar-lite' ); ?>
				</span>
				<span class="sc-select__chevron sc:icon-[fa6-solid--chevron-down]" aria-hidden="true"></span>
			</button>

			<div class="sc-dropdown-menu sugar-calendar-checklist__page-selector-menu"
					id="<?php echo esc_attr( $id ); ?>-menu"
					hidden>
				<label class="sugar-calendar-checklist__sr" for="<?php echo esc_attr( $id ); ?>-search">
					<?php esc_html_e( 'Search pages', 'sugar-calendar-lite' ); ?>
				</label>
				<input type="search"
						class="sc-input sugar-calendar-checklist__page-search"
						id="<?php echo esc_attr( $id ); ?>-search"
						placeholder="<?php esc_attr_e( 'Search pages', 'sugar-calendar-lite' ); ?>"
						autocomplete="off">
				<p class="sugar-calendar-checklist__page-status" role="status" aria-live="polite"></p>
				<div class="sc-dropdown-list" role="listbox" aria-labelledby="<?php echo esc_attr( $id ); ?>"></div>
				<p class="sugar-calendar-checklist__page-empty" hidden>
					<span><?php esc_html_e( 'No editable pages found.', 'sugar-calendar-lite' ); ?></span>
					<a href="<?php echo esc_url( WP::admin_url( 'edit.php?post_type=page' ) ); ?>">
						<?php esc_html_e( 'Open Pages to create a page, then return here.', 'sugar-calendar-lite' ); ?>
					</a>
				</p>
				<button type="button" class="sugar-calendar-checklist__page-more" hidden>
					<?php esc_html_e( 'Load more pages', 'sugar-calendar-lite' ); ?>
				</button>
			</div>

			<button type="button"
					class="sc-button sc-button--tertiary"
					id="<?php echo esc_attr( $id ); ?>-submit"
					<?php $this->render_action_attributes( $item_id, $action, $input ); ?>
					disabled>
				<?php echo esc_html( $action['label'] ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render the newsletter field and its signup button.
	 *
	 * @since 3.15.0
	 *
	 * @param string   $item_id Stable item id.
	 * @param array    $action  Action definition.
	 * @param string   $email   Address the field starts with.
	 * @param string[] $input   Declared input fields.
	 */
	private function render_email_action( $item_id, $action, $email, $input ) {

		$id = 'sugar-calendar-checklist-newsletter';
		?>
		<div class="sugar-calendar-checklist__newsletter">
			<label class="sugar-calendar-checklist__sr" for="<?php echo esc_attr( $id ); ?>">
				<?php esc_html_e( 'Email address', 'sugar-calendar-lite' ); ?>
			</label>
			<input type="email"
					class="sc-input"
					id="<?php echo esc_attr( $id ); ?>"
					value="<?php echo esc_attr( $email ); ?>"
					placeholder="<?php esc_attr_e( 'you@example.com', 'sugar-calendar-lite' ); ?>">
			<button type="button"
					class="sc-button sc-button--tertiary"
					<?php $this->render_action_attributes( $item_id, $action, $input ); ?>>
				<?php echo esc_html( $action['label'] ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render the Stripe connect button.
	 *
	 * @since 3.15.0
	 *
	 * @param string $item_id Stable item id.
	 * @param array  $action  Action definition.
	 */
	private function render_stripe_action( $item_id, $action ) {

		?>
		<button type="button"
				class="sugar-calendar-checklist__stripe-button"
				<?php $this->render_action_attributes( $item_id, $action ); ?>>
			<img src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/setup-checklist/stripe-button-glyph.svg' ); ?>"
					width="12" height="16" alt="">
			<span class="sugar-calendar-checklist__stripe-button-rule" aria-hidden="true"></span>
			<span><?php echo esc_html( $action['label'] ); ?></span>
		</button>
		<?php
	}

	/**
	 * Render an action's trailing icon.
	 *
	 * @since 3.15.0
	 *
	 * @param array $action Action definition.
	 */
	private function render_action_icon( $action ) {

		if ( empty( $action['icon'] ) ) {
			return;
		}

		printf(
			'<span class="sc-button__icon %s" aria-hidden="true"></span>',
			esc_attr( $action['icon'] )
		);
	}

	/**
	 * Render the data attributes that tell the server which item action to run.
	 *
	 * @since 3.15.0
	 *
	 * @param string   $item_id Stable item id.
	 * @param array    $action  Action definition.
	 * @param string[] $input   Declared input fields, space-separated in `data-checklist-input`.
	 */
	private function render_action_attributes( $item_id, $action, $input = [] ) {

		printf(
			'data-checklist-task="item_action" data-item="%1$s" data-checklist-action="%2$s"',
			esc_attr( $item_id ),
			esc_attr( $action['action'] ?? '' )
		);

		if ( ! empty( $input ) ) {
			printf( ' data-checklist-input="%s"', esc_attr( implode( ' ', (array) $input ) ) );
		}

		if ( ! empty( $action['plugin_labels'] ) ) {
			printf( ' data-plugin-labels="%s"', esc_attr( wp_json_encode( $action['plugin_labels'] ) ) );
		}

		if ( ! empty( $action['failed_message'] ) ) {
			printf( ' data-failed-message="%s"', esc_attr( $action['failed_message'] ) );
		}
	}

	/**
	 * Button variant class for an action.
	 *
	 * @since 3.15.0
	 *
	 * @param array $action Action definition.
	 *
	 * @return string
	 */
	private function get_button_variant_class( $action ) {

		$variant = $action['variant'] ?? 'tertiary';

		return 'sc-button--' . sanitize_html_class( $variant );
	}

	/**
	 * Whether a URL leaves the admin and so deserves a new tab.
	 *
	 * @since 3.15.0
	 *
	 * @param string $url Destination.
	 *
	 * @return bool
	 */
	private function is_external_url( $url ) {

		return strpos( (string) $url, admin_url() ) !== 0;
	}
}
