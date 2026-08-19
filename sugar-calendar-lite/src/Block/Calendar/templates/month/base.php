<?php
use Sugar_Calendar\Block\Common\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * @var \Sugar_Calendar\Block\Calendar\CalendarView\Month\Month $context
 */
?>
<div class="sugar-calendar-block__calendar-month">

	<?php Template::load( 'month.header' ); ?>

	<div class="sugar-calendar-block__calendar-month__body">
		<?php $context->render(); ?>
	</div>
</div>
