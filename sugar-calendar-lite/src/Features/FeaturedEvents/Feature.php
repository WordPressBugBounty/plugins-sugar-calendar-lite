<?php

namespace Sugar_Calendar\Features\FeaturedEvents;

// Base Sugar Calendar feature class.
use Sugar_Calendar\Common\Features\FeatureAbstract;

// Event meta registration.
use Sugar_Calendar\Features\FeaturedEvents\Common\Meta;

// Admin area.
use Sugar_Calendar\Features\FeaturedEvents\Admin\Area;

// Public single event.
use Sugar_Calendar\Features\FeaturedEvents\Frontend\SingleEvent;

// Block event titles.
use Sugar_Calendar\Features\FeaturedEvents\Frontend\BlockEventTitle;
use Sugar_Calendar\Features\FeaturedEvents\Integrations\Abilities\FeaturedContributor;

/**
 * Feature class for the Featured Events feature.
 *
 * @since 3.14.0
 */
class Feature extends FeatureAbstract {

	/**
	 * Feature name.
	 *
	 * @since 3.14.0
	 *
	 * @var string
	 */
	public $name = 'sugar-calendar-featured-events';

	/**
	 * The event meta instance.
	 *
	 * @since 3.14.0
	 *
	 * @var Meta
	 */
	private $meta;

	/**
	 * The admin area instance.
	 *
	 * @since 3.14.0
	 *
	 * @var Area
	 */
	public $admin_area;

	/**
	 * The public single event instance.
	 *
	 * @since 3.14.0
	 *
	 * @var SingleEvent
	 */
	public $single_event;

	/**
	 * The block event title instance.
	 *
	 * @since 3.14.0
	 *
	 * @var BlockEventTitle
	 */
	public $block_event_title;

	/**
	 * Setup the feature.
	 *
	 * @since 3.14.0
	 */
	public function setup() {

		// Meta registration runs on every request — see Meta::hooks().
		$this->meta = new Meta();

		// The blocks also render over admin-ajax, so this is not admin-gated.
		$this->block_event_title = new BlockEventTitle();

		if ( is_admin() ) {

			$this->admin_area = new Area();

			$this->admin_area->init();

		} else {

			$this->single_event = new SingleEvent();
		}
	}

	/**
	 * Feature hooks.
	 *
	 * @since 3.14.0
	 */
	public function hooks() {

		$this->meta->hooks();
		$this->block_event_title->hooks();

		// Abilities are served over REST and WP-CLI, so this cannot sit behind is_admin().
		FeaturedContributor::hooks();

		if ( is_admin() ) {
			$this->admin_area->hooks();
		} else {
			$this->single_event->hooks();
		}
	}

	/**
	 * Get feature requirements.
	 *
	 * @since 3.14.0
	 *
	 * @return array
	 */
	public function get_requirements() {

		return [
			'php' => [
				'minimum' => '7.4',
				'name'    => 'PHP',
				'current' => false,
			],
			'wp'  => [
				'minimum' => '5.9',
				'name'    => 'WordPress',
				'current' => false,
			],
			'sc'  => [
				'minimum' => '3.0.0',
				'name'    => 'Sugar Calendar',
				'current' => false,
			],
		];
	}
}
