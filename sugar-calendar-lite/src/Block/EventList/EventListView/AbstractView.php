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
	 *
	 * @return string
	 */
	public function get_heading() {

		global $wp_locale;

		return sprintf(
			'%1$s %2$d - %3$s %4$d',
			$wp_locale->get_month( $this->get_block()->get_week_period()->start->format( 'm' ) ),
			$this->get_block()->get_week_period()->getStartDate()->format( 'd' ),
			$wp_locale->get_month( $this->get_block()->get_week_period()->end->format( 'm' ) ),
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
	 */
	public function render_base() {
		/*
		 * If events are not to be loaded, we don't display the no-events message since
		 * we need to immediately refresh via JS.
		 */
		if ( $this->block->get_events() && $this->block->has_events_in_week() ) {
			Template::load( static::DISPLAY_MODE . 'view.base', $this, Block::KEY );
		} elseif ( ! $this->block->should_not_load_events() ) {
			Template::load( 'no-events', $this, Block::KEY );
		}
	}
}
