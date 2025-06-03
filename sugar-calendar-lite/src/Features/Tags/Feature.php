<?php

namespace Sugar_Calendar\Features\Tags;

// Base Sugar Calendar feature class.
use Sugar_Calendar\Common\Features\FeatureAbstract;

// Tags taxonomy.
use Sugar_Calendar\Features\Tags\Common\Taxonomy;

// Admin area.
use Sugar_Calendar\Features\Tags\Admin\Area;

// Frontend blocks.
use Sugar_Calendar\Features\Tags\Frontend\Blocks;

/**
 * Feature class for the Tags feature.
 *
 * @since 3.7.0
 */
class Feature extends FeatureAbstract {

	/**
	 * Feature name.
	 *
	 * @var string
	 *
	 * @since 3.7.0
	 */
	public $name = 'sugar-calendar-tags';

	/**
	 * The taxonomy instance.
	 *
	 * @var Taxonomy
	 * @since 3.7.0
	 */
	private $taxonomy;

	/**
	 * The admin area instance.
	 *
	 * @var Area
	 * @since 3.7.0
	 */
	public $admin_area;

	/**
	 * The block helper instance.
	 *
	 * @var Blocks
	 * @since 3.7.0
	 */
	public $blocks;

	/**
	 * Setup the feature.
	 *
	 * @since 3.7.0
	 */
	public function setup() {

		// Load Tags taxonomy.
		$this->taxonomy = new Taxonomy();

		if ( is_admin() ) {

			// Load Admin area.
			$this->admin_area = new Area();

			$this->admin_area->init();

		} else {

			// Load Block helper.
			$this->blocks = new Blocks();
		}
	}

	/**
	 * Initialize admin pages.
	 *
	 * @since 3.7.0
	 */
	public function hooks() {

		// Taxonomy hooks.
		$this->taxonomy->hooks();

		// Only register admin hooks if in admin area.
		if ( is_admin() ) {

			$this->admin_area->hooks();

		} else {

			// Block hooks.
			$this->blocks->hooks();
		}
	}

	/**
	 * Get feature requirements.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	public function get_requirements() {

		return [
			'php' => [
				'minimum' => '7.2',
				'name'    => 'PHP',
				'current' => false,
			],
			'wp'  => [
				'minimum' => '5.7',
				'name'    => 'WordPress',
				'current' => false,
			],
			'sc'  => [
				'minimum' => '3.6.0',
				'name'    => 'Sugar Calendar',
				'current' => false,
			],
		];
	}
}
