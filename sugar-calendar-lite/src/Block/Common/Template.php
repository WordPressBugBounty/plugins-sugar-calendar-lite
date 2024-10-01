<?php

namespace Sugar_Calendar\Block\Common;

use Sugar_Calendar\Block\EventList\EventListView\Block as EventListBlock;

/**
 * Class Template.
 *
 * @since 3.1.0
 */
class Template {

	/**
	 * Load a template.
	 *
	 * @since 3.1.0
	 *
	 * @param string $template  The template to load.
	 * @param mixed  $context   The context to pass to the template.
	 * @param string $block_key The block key.
	 */
	public static function load( $template, $context = false, $block_key = 'calendar' ) {

		$template_path = plugin_dir_path( SC_PLUGIN_FILE ) . 'src/Block/';

		switch ( $block_key ) {
			case EventListBlock::KEY:
				$template_path .= 'EventList';
				break;

			case 'common':
				$template_path .= 'Common';
				break;

			default:
				$template_path .= 'Calendar';
				break;
		}

		$template_path .= '/templates';
		$template       = str_replace( '.', DIRECTORY_SEPARATOR, $template );

		if ( ! file_exists( $template_path . "/{$template}.php" ) ) {
			return;
		}

		include $template_path . "/{$template}.php";
	}
}
