<?php
/**
 * Recommended Growth Tools.
 *
 * @since 3.18.0
 *
 * @var array $tiles Tiles for `widgets/growth-tool`.
 */

use Sugar_Calendar\Admin\Dashboard\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<ul class="sc-dashboard-recommended-growth-tools sc-reset-deep sc:max-phone:grid-cols-1">
	<?php foreach ( $tiles as $tile ) : ?>
		<?php echo Templates::get_html( 'widgets/growth-tool', [ 'tile' => $tile ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes its own output. ?>
	<?php endforeach; ?>
</ul>
