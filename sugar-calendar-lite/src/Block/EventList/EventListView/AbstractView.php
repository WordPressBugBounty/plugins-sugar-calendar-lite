<?php

namespace Sugar_Calendar\Block\EventList\EventListView;

use DateInterval;
use Sugar_Calendar\Block\Common\InterfaceBaseView;
use Sugar_Calendar\Block\Common\Template;

abstract class AbstractView implements InterfaceBaseView {

	/**
	 * Block object.
	 *
	 * @since 3.1.0
	 *
	 * @var Block
	 */
	protected $block;

	/**
	 * Constructor.
	 *
	 * @since 3.1.0
	 *
	 * @param Block $block Block object.
	 */
	public function __construct( $block ) {

		$this->block = $block;
	}

	/**
	 * Get the heading.
	 *
	 * @since 3.1.0
	 * @since 3.4.0
	 *
	 * @param bool $use_abbreviated_month Whether to use abbreviated month or not.
	 *
	 * @return string
	 */
	public function get_heading( $use_abbreviated_month = false ) {

		global $wp_locale;

		$start_date = $wp_locale->get_month( $this->get_block()->get_week_period()->start->format( 'm' ) );
		$end_date   = $wp_locale->get_month( $this->get_block()->get_week_period()->end->format( 'm' ) );

		if ( $use_abbreviated_month ) {
			$start_date = $wp_locale->get_month_abbrev( $start_date );
			$end_date   = $wp_locale->get_month_abbrev( $end_date );
		}

		return sprintf(
			'%1$s %2$d - %3$s %4$d',
			$start_date,
			$this->get_block()->get_week_period()->getStartDate()->format( 'd' ),
			$end_date,
			$this->get_block()->get_week_period()->getEndDate()->format( 'd' )
		);
	}

	/**
	 * Get the block object.
	 *
	 * @since 3.1.0
	 *
	 * @return Block
	 */
	public function get_block() {

		return $this->block;
	}

	/**
	 * Render the view.
	 *
	 * @since 3.1.0
	 * @since 3.4.0 Add case for Upcoming Events display.
	 */
	public function render_base() {
		/*
		 * If events are not to be loaded, we don't display the no-events message since
		 * we need to immediately refresh via JS.
		 */
		if ( $this->block->should_group_events_by_week() && $this->block->get_events() && $this->block->has_events_in_week() ) {

			// Handles the case where the block is used to show default Event List display.
			Template::load( static::DISPLAY_MODE . 'view.base', $this, Block::KEY );

		} elseif ( ! $this->block->should_group_events_by_week() && $this->block->get_events() ) {

			// Handles the case where the block is used to show Upcoming Events display.
			Template::load( static::DISPLAY_MODE . 'view.base', $this, Block::KEY );

		} elseif ( ! $this->block->should_not_load_events() ) {

			// Handles the case where the block is used to show no events message.
			Template::load( 'no-events', $this, Block::KEY );
		}
	}
}
