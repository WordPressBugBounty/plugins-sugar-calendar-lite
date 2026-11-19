<?php

namespace Sugar_Calendar\Admin\Dashboard\Widgets;

use Sugar_Calendar\Admin\Dashboard\AccessContext;
use Sugar_Calendar\Admin\Dashboard\Templates;
use Sugar_Calendar\Admin\Dashboard\WidgetState;
use Sugar_Calendar\PartnerPlugins\Catalog;
use Sugar_Calendar\SetupChecklist\Config;

/**
 * Recommended sister plugins.
 *
 * Tools the site already runs give way to the ones it does not.
 *
 * @since 3.18.0
 */
class GrowthTools extends AbstractWidget {

	/**
	 * Column.
	 *
	 * @since 3.18.0
	 */
	const COLUMN = 'sidebar';

	/**
	 * Sort position.
	 *
	 * @since 3.18.0
	 */
	const ORDER = 50;

	/**
	 * Tiles shown.
	 *
	 * @since 3.18.0
	 */
	const TILE_LIMIT = 4;

	/**
	 * Widget identifier.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_id(): string {

		return 'growth_tools';
	}

	/**
	 * Widget title.
	 *
	 * @since 3.18.0
	 *
	 * @return string
	 */
	public function get_title(): string {

		return __( 'Recommended Growth Tools', 'sugar-calendar-lite' );
	}

	/**
	 * Always visible.
	 *
	 * @since 3.18.0
	 *
	 * @param AccessContext $access Access context.
	 *
	 * @return WidgetState
	 */
	public function get_state( AccessContext $access ): WidgetState {

		return new WidgetState( true, 'data' );
	}

	/**
	 * Render the tiles.
	 *
	 * @since 3.18.0
	 *
	 * @param string        $variant Template variant.
	 * @param array         $data    Range data.
	 * @param AccessContext $access  Access context.
	 *
	 * @return string
	 */
	protected function render_body( string $variant, array $data, AccessContext $access ): string {

		return Templates::get_html( 'widgets/growth-tools', [ 'tiles' => $this->get_tiles() ] );
	}

	/**
	 * TILE_LIMIT sister plugins: the ones the site does not run yet, in the checklist's
	 * order, then active ones to fill the grid.
	 *
	 * @since 3.18.0
	 *
	 * @return array Tiles; see `get_tile()`.
	 */
	protected function get_tiles(): array {

		$catalog  = new Catalog();
		$inactive = [];
		$active   = [];

		foreach ( Config::get_growth_tools() as $tool ) {
			$tile = $this->get_tile( $tool, $catalog );

			if ( $tile['state'] === 'installed' ) {
				$active[] = $tile;
			} else {
				$inactive[] = $tile;
			}
		}

		return array_slice( array_merge( $inactive, $active ), 0, self::TILE_LIMIT );
	}

	/**
	 * One tool's tile drawn from its state now, for the install endpoint to send back.
	 *
	 * @since 3.18.0
	 *
	 * @param string $key Growth tool key.
	 *
	 * @return string Empty when the key is not one of the checklist's tools.
	 */
	public function get_tile_html( string $key ): string {

		$tools = Config::get_growth_tools();

		if ( ! isset( $tools[ $key ] ) ) {
			return '';
		}

		return Templates::get_html( 'widgets/growth-tool', [ 'tile' => $this->get_tile( $tools[ $key ], new Catalog() ) ] );
	}

	/**
	 * A tool's tile.
	 *
	 * @since 3.18.0
	 *
	 * @param array   $tool    Growth tool from `Config::get_growth_tools()`.
	 * @param Catalog $catalog Partner plugin catalogue.
	 *
	 * @return array `key`, `title`, `description`, `icon` (URL), `state` (`install`, `activate` or `installed`) and its `label`.
	 */
	private function get_tile( array $tool, Catalog $catalog ): array {

		$action = Config::get_growth_tool_action( $catalog->get_state( $tool['key'] ) );

		return [
			'key'         => $tool['key'],
			'title'       => $tool['title'],
			'description' => $this->get_description( $tool ),
			'icon'        => SC_PLUGIN_ASSETS_URL . 'images/setup-checklist/' . $tool['icon'] . '.svg',
			'state'       => $action['state'],
			'label'       => $action['label'],
		];
	}

	/**
	 * A tool's description: the design's copy where the checklist's runs a line longer, the checklist's otherwise.
	 *
	 * A fourth line makes the whole grid row taller than the design's tiles; the Setup Checklist keeps its own copy.
	 *
	 * @since 3.18.0
	 *
	 * @param array $tool Growth tool from `Config::get_growth_tools()`.
	 *
	 * @return string
	 */
	private function get_description( array $tool ): string {

		$design = [
			'universally'  => __( 'Translate your website into 110+ languages within minutes using AI.', 'sugar-calendar-lite' ),
			'reviews-feed' => __( 'Customer reviews from Google, Yelp, and more to boost sales.', 'sugar-calendar-lite' ),
		];

		return $design[ $tool['key'] ] ?? $tool['description'];
	}
}
