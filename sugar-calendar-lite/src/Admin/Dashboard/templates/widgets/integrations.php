<?php
/**
 * Integrations and add-ons grid.
 *
 * @since 3.18.0
 *
 * @var array $cards Cards for `widgets/integration-card`.
 */

use Sugar_Calendar\Admin\Dashboard\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<ul class="sc-dashboard-integrations-wide sc-reset-deep sc:max-tablet:grid-cols-2">
	<?php foreach ( $cards as $card ) : ?>
		<li class="sc-dashboard-integrations-wide__cell">
			<?php echo Templates::get_html( 'widgets/integration-card', [ 'card' => $card ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes its own output. ?>
		</li>
	<?php endforeach; ?>
</ul>
