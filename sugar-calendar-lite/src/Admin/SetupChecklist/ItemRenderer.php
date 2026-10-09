<?php

namespace Sugar_Calendar\Admin\SetupChecklist;

/**
 * Renders checklist task rows.
 *
 * @since 3.15.0
 */
class ItemRenderer {

	/**
	 * Task action controls.
	 *
	 * @since 3.15.0
	 *
	 * @var ActionRenderer
	 */
	private $action_renderer;

	/**
	 * Initialize rendering collaborators.
	 *
	 * @since 3.15.0
	 */
	public function __construct() {

		$this->action_renderer = new ActionRenderer();
	}

	/**
	 * Render one task row.
	 *
	 * A completed row keeps its place in the catalogue order and loses its whole action
	 * column; the status icon is never a control.
	 *
	 * @since 3.15.0
	 *
	 * @param string $item_id Stable item id.
	 * @param array  $item    Item definition.
	 * @param string $email   Newsletter email address.
	 */
	public function render( $item_id, $item, $email ) {

		$completed = ! empty( $item['completed'] );
		$is_stripe = ( $item['action']['type'] ?? 'button' ) === 'stripe';
		$classes   = $this->get_item_classes( $completed, $is_stripe );
		?>
		<li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-item="<?php echo esc_attr( $item_id ); ?>">

			<?php $this->render_status( $completed, $is_stripe ); ?>

			<?php if ( $is_stripe ) : ?>
				<?php $this->render_stripe_body( $item ); ?>
			<?php else : ?>
				<div class="sugar-calendar-checklist__text">
					<h3 class="sugar-calendar-checklist__item-title"><?php echo esc_html( $item['title'] ); ?></h3>
					<?php $this->render_description( $item ); ?>
				</div>
			<?php endif; ?>

			<?php if ( ! $completed ) : ?>
				<div class="sugar-calendar-checklist__action">
					<?php $this->action_renderer->render( $item_id, $item['action'] ?? [], $email, $item['input'] ?? [] ); ?>
					<p class="sugar-calendar-checklist__feedback" role="status" aria-live="polite" hidden></p>
				</div>
			<?php endif; ?>
		</li>
		<?php
	}

	/**
	 * Classes for a task row's completion and presentation states.
	 *
	 * @since 3.15.0
	 *
	 * @param bool $completed Whether the row is done.
	 * @param bool $is_stripe Whether this is the Stripe panel row.
	 *
	 * @return string[]
	 */
	private function get_item_classes( $completed, $is_stripe ) {

		$classes = [ 'sugar-calendar-checklist__item' ];

		if ( $completed ) {
			$classes[] = 'sugar-calendar-checklist__item--completed';
		}

		if ( $is_stripe ) {
			$classes[] = 'sugar-calendar-checklist__item--stripe';
		}

		return $classes;
	}

	/**
	 * Render a row's status marker and its text equivalent.
	 *
	 * The Stripe row shows only the complete check; its empty marker is omitted from the
	 * markup rather than hidden, because the icon class sets `display` with !important.
	 *
	 * @since 3.15.0
	 *
	 * @param bool $completed Whether the row is done.
	 * @param bool $is_stripe Whether this is the Stripe panel row.
	 */
	private function render_status( $completed, $is_stripe ) {

		if ( $completed ) {
			?>
			<span class="sugar-calendar-checklist__status sugar-calendar-checklist__status--completed sc:icon-[fa6-regular--circle-check]" aria-hidden="true"></span>
			<span class="sugar-calendar-checklist__sr"><?php esc_html_e( 'Completed', 'sugar-calendar-lite' ); ?></span>
			<?php

			return;
		}

		if ( ! $is_stripe ) {
			?>
			<span class="sugar-calendar-checklist__status sc:icon-[fa6-regular--circle]" aria-hidden="true"></span>
			<?php
		}
		?>
		<span class="sugar-calendar-checklist__sr"><?php esc_html_e( 'Not completed', 'sugar-calendar-lite' ); ?></span>
		<?php
	}

	/**
	 * Render an item's description and its optional trailing link.
	 *
	 * @since 3.15.0
	 *
	 * @param array $item Item definition.
	 */
	private function render_description( $item ) {

		if ( empty( $item['description'] ) ) {
			return;
		}
		?>
		<p class="sugar-calendar-checklist__item-description">
			<?php
			echo esc_html( $item['description'] );

			if ( ! empty( $item['description_link']['url'] ) ) {
				printf(
					' <a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
					esc_url( $item['description_link']['url'] ),
					esc_html( $item['description_link']['label'] )
				);
			}
			?>
		</p>
		<?php
	}

	/**
	 * Render the Stripe row's copy block.
	 *
	 * @since 3.15.0
	 *
	 * @param array $item Item definition.
	 */
	private function render_stripe_body( $item ) {

		?>
		<span class="sugar-calendar-checklist__stripe-icon">
			<img src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/setup-checklist/stripe-app-icon.svg' ); ?>"
					width="100" height="100" alt="">
		</span>
		<div class="sugar-calendar-checklist__text">
			<h3 class="sugar-calendar-checklist__stripe-heading">
				<img class="sugar-calendar-checklist__stripe-wordmark"
						src="<?php echo esc_url( SC_PLUGIN_ASSETS_URL . 'images/setup-checklist/stripe-wordmark.svg' ); ?>"
						width="96" height="40" alt="Stripe">
				<span class="sugar-calendar-checklist__group-separator" aria-hidden="true">&mdash;</span>
				<span><?php echo esc_html( $item['title'] ); ?></span>
			</h3>
			<p class="sugar-calendar-checklist__stripe-description"><?php echo esc_html( $item['description'] ); ?></p>
			<?php if ( ! empty( $item['note'] ) ) : ?>
			<p class="sugar-calendar-checklist__stripe-note">
				<?php
				echo esc_html( $item['note'] );

				if ( ! empty( $item['note_link']['url'] ) ) {
					printf(
						' <a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
						esc_url( $item['note_link']['url'] ),
						esc_html( $item['note_link']['label'] )
					);
				}
				?>
			</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
