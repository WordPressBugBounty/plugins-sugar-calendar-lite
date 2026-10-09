<?php

namespace Sugar_Calendar\Admin\SetupChecklist;

/**
 * Composes checklist groups and page layout.
 *
 * @since 3.15.0
 */
class ChecklistRenderer {

	/**
	 * Task row presentation.
	 *
	 * @since 3.15.0
	 *
	 * @var ItemRenderer
	 */
	private $item_renderer;

	/**
	 * Promotional group contents.
	 *
	 * @since 3.15.0
	 *
	 * @var PromotionsRenderer
	 */
	private $promotions_renderer;

	/**
	 * Initialize rendering collaborators.
	 *
	 * @since 3.15.0
	 */
	public function __construct() {

		$this->item_renderer       = new ItemRenderer();
		$this->promotions_renderer = new PromotionsRenderer();
	}

	/**
	 * Render the checklist page body.
	 *
	 * @since 3.15.0
	 *
	 * @param array  $data  Checklist view data.
	 * @param string $label Page label.
	 */
	public function render( $data, $label ) {

		?>
		<div id="sugar-calendar-setup-checklist" class="wrap sugar-calendar-admin-wrap">
			<div class="sugar-calendar-admin-content">
				<h1 class="screen-reader-text"><?php echo esc_html( $label ); ?></h1>

				<div class="sugar-calendar-checklist">

					<?php
					foreach ( $data['groups'] as $group_id => $group ) {
						$this->render_group( $group_id, $group, $data );
					}

					if ( ! empty( $data['upsell_cards'] ) ) {
						$this->render_upsell( $data['upsell_cards'] );
					}

					if ( ! empty( $data['growth_tools'] ) ) {
						$this->render_growth_tools( $data['growth_tools'] );
					}

					$this->render_footer();
					?>

				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render one checklist group.
	 *
	 * @since 3.15.0
	 *
	 * @param string $group_id Stable group id.
	 * @param array  $group    Normalized group.
	 * @param array  $data     Whole view data, for the controls that need it.
	 */
	private function render_group( $group_id, $group, $data ) {

		$this->render_group_open(
			[
				'id'        => $group_id,
				'title'     => $group['title'],
				'collapsed' => ! empty( $group['collapsed'] ),
				'badge'     => ! empty( $group['is_complete'] ),
			]
		);
		?>
		<ul class="sugar-calendar-checklist__items">
			<?php foreach ( $group['items'] as $item_id => $item ) : ?>
				<?php $this->item_renderer->render( $item_id, $item, $data['email'] ?? '' ); ?>
			<?php endforeach; ?>
		</ul>
		<?php
		$this->render_group_close();
	}

	/**
	 * Open a group card and its collapsible body.
	 *
	 * @since 3.15.0
	 *
	 * @param array $args Group id, title and collapsed flag, plus either `badge` (bool,
	 *                    for task groups) or `tagline` (string, for the promotional ones).
	 */
	private function render_group_open( $args ) {

		$body_id   = 'sugar-calendar-checklist-group-' . sanitize_html_class( $args['id'] );
		$collapsed = ! empty( $args['collapsed'] );
		?>
		<section class="sugar-calendar-checklist__group" data-group="<?php echo esc_attr( $args['id'] ); ?>">
			<h2 class="sugar-calendar-checklist__group-heading">
				<button type="button"
						class="sugar-calendar-checklist__group-toggle"
						aria-expanded="<?php echo $collapsed ? 'false' : 'true'; ?>"
						aria-controls="<?php echo esc_attr( $body_id ); ?>">
					<span class="sugar-calendar-checklist__group-title"><?php echo esc_html( $args['title'] ); ?></span>

					<?php if ( isset( $args['badge'] ) ) : ?>
						<?php $this->render_group_badge( ! empty( $args['badge'] ) ); ?>
					<?php endif; ?>

					<?php if ( ! empty( $args['tagline'] ) ) : ?>
						<span class="sugar-calendar-checklist__group-separator" aria-hidden="true">&mdash;</span>
						<span class="sugar-calendar-checklist__group-tagline"><?php echo esc_html( $args['tagline'] ); ?></span>
					<?php endif; ?>

					<span class="sugar-calendar-checklist__group-chevron sc:icon-[fa6-solid--chevron-down]" aria-hidden="true"></span>
				</button>
			</h2>
			<div class="sugar-calendar-checklist__group-body"
					id="<?php echo esc_attr( $body_id ); ?>"
					<?php echo $collapsed ? 'hidden' : ''; ?>>
		<?php
	}

	/**
	 * Render a task group's completion marker.
	 *
	 * @since 3.15.0
	 *
	 * @param bool $is_complete Whether every visible row in the group is done.
	 */
	private function render_group_badge( $is_complete ) {

		$variant = $is_complete ? 'sc-badge--green' : 'sc-badge--platinum';
		$label   = $is_complete
			? __( 'Complete', 'sugar-calendar-lite' )
			: __( 'Incomplete', 'sugar-calendar-lite' );
		?>
		<span class="sc-badge sc-badge-lg <?php echo esc_attr( $variant ); ?>"><?php echo esc_html( $label ); ?></span>
		<?php
	}

	/**
	 * Close a group card opened by render_group_open().
	 *
	 * @since 3.15.0
	 */
	private function render_group_close() {

		?>
			</div>
		</section>
		<?php
	}

	/**
	 * Render the Lite upsell group.
	 *
	 * @since 3.15.0
	 *
	 * @param array $cards Upsell cards.
	 */
	private function render_upsell( $cards ) {

		$this->render_group_open(
			[
				'id'    => 'upsell',
				'title' => __( 'Take Your Events to the Next Level', 'sugar-calendar-lite' ),
			]
		);

		$this->promotions_renderer->render_upsell( $cards );
		$this->render_group_close();
	}

	/**
	 * Render the Growth Tools group.
	 *
	 * @since 3.15.0
	 *
	 * @param array $tools Growth tool cards.
	 */
	private function render_growth_tools( $tools ) {

		$this->render_group_open(
			[
				'id'      => 'growth-tools',
				'title'   => __( 'Set Up Recommended Growth Tools', 'sugar-calendar-lite' ),
				'tagline' => __( 'Take your website to the next level with our sister plugins.', 'sugar-calendar-lite' ),
			]
		);

		$this->promotions_renderer->render_growth_tools( $tools );
		$this->render_group_close();
	}

	/**
	 * Render the dismissal trigger under the groups.
	 *
	 * @since 3.15.0
	 */
	private function render_footer() {

		?>
		<div class="sugar-calendar-checklist__footer">
			<button type="button" class="sugar-calendar-checklist__dismiss" data-checklist-task="dismiss">
				<?php esc_html_e( 'Complete Setup Checklist', 'sugar-calendar-lite' ); ?>
			</button>
		</div>
		<?php
	}
}
