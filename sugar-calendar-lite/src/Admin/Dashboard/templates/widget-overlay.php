<?php
/**
 * Product Education Popup centred over a Registrations & Orders panel.
 *
 * @since 3.18.0
 *
 * @var string $title      Popup heading.
 * @var string $text       Popup copy.
 * @var array  $cta        `label` and `url`. An empty `url` renders an inert button; an empty array renders none.
 * @var string $disclaimer Footer note. Empty renders none.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="sc-dashboard-registrations-orders__overlay">
	<div class="sc-dashboard-registrations-orders__overlay-card">
		<div class="sc-dashboard-product-education-popup">
			<div class="sc-dashboard-product-education-popup__content">
				<div class="sc-dashboard-product-education-popup__text">
					<h3 class="sc-dashboard-product-education-popup__title sc-title-bar"><?php echo esc_html( $title ); ?></h3>
					<p class="sc-dashboard-product-education-popup__description sc-field-description"><?php echo esc_html( $text ); ?></p>
				</div>

				<?php if ( ! empty( $cta ) ) : ?>
					<div class="sc-dashboard-product-education-popup__buttons">
						<?php if ( $cta['url'] !== '' ) : ?>
							<a class="sc-button sc-button--primary" href="<?php echo esc_url( $cta['url'] ); ?>"><?php echo esc_html( $cta['label'] ); ?></a>
						<?php else : ?>
							<button type="button" class="sc-button sc-button--primary"><?php echo esc_html( $cta['label'] ); ?></button>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $disclaimer !== '' ) : ?>
				<p class="sc-dashboard-product-education-popup__disclaimer sc-field-description-small"><?php echo esc_html( $disclaimer ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>
