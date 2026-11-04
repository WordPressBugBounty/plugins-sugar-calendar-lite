<?php

namespace Sugar_Calendar\Integrations\Polylang;

use PLL_Base;

/**
 * Polylang integration.
 *
 * @since 3.17.0
 */
class Polylang {

	/**
	 * Admin list table support.
	 *
	 * @since 3.17.0
	 *
	 * @var PolylangAdmin
	 */
	private $admin;

	/**
	 * Query support.
	 *
	 * @since 3.17.0
	 *
	 * @var PolylangQuery
	 */
	private $query;

	/**
	 * Translation sync support.
	 *
	 * @since 3.17.0
	 *
	 * @var PolylangSync
	 */
	private $sync;

	/**
	 * Front-end support.
	 *
	 * @since 3.17.0
	 *
	 * @var PolylangFrontend
	 */
	private $frontend;

	/**
	 * Calendar term support.
	 *
	 * @since 3.17.0
	 *
	 * @var PolylangTerms
	 */
	private $terms;

	/**
	 * Event meta support.
	 *
	 * @since 3.17.0
	 *
	 * @var PolylangMeta
	 */
	private $meta;

	/**
	 * Ticket, RSVP and online meeting support.
	 *
	 * @since 3.17.0
	 *
	 * @var PolylangSales
	 */
	private $sales;

	/**
	 * Google Calendar push support.
	 *
	 * @since 3.17.0
	 *
	 * @var PolylangGoogle
	 */
	private $google;

	/**
	 * Initialize the Polylang integration.
	 *
	 * @since 3.17.0
	 */
	public function init() {

		if ( ! self::is_polylang_active() ) {
			return;
		}

		$this->admin    = $this->get_admin();
		$this->query    = $this->get_query();
		$this->sync     = $this->get_sync();
		$this->frontend = $this->get_frontend();
		$this->terms    = $this->get_terms();
		$this->meta     = $this->get_meta();
		$this->sales    = $this->get_sales();
		$this->google   = $this->get_google();

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.17.0
	 *
	 * @phpstan-impure
	 */
	protected function hooks() {

		add_action( 'admin_init', [ $this, 'admin_hooks' ] );

		// Saves also come from REST and WP-CLI, and front-end requests never reach admin_init.
		$this->sync->hooks();
		$this->frontend->hooks();
		$this->terms->hooks();
		$this->meta->hooks();
		$this->sales->hooks();
		$this->google->hooks();
	}

	/**
	 * Register admin-only hooks.
	 *
	 * @since 3.17.0
	 */
	public function admin_hooks() {

		$this->admin->hooks();
		$this->query->hooks();
	}

	/**
	 * Detect whether a supported Polylang version (3.4+) is active.
	 *
	 * @since 3.17.0
	 *
	 * @return bool
	 */
	public static function is_polylang_active() {

		return function_exists( 'PLL' )
			&& PLL() instanceof PLL_Base
			&& defined( 'POLYLANG_VERSION' )
			&& version_compare( constant( 'POLYLANG_VERSION' ), '3.4', '>=' );
	}

	/**
	 * Get the admin list table support.
	 *
	 * @since 3.17.0
	 *
	 * @return PolylangAdmin
	 */
	protected function get_admin() {

		return new PolylangAdmin();
	}

	/**
	 * Get the query support.
	 *
	 * @since 3.17.0
	 *
	 * @return PolylangQuery
	 */
	protected function get_query() {

		return new PolylangQuery();
	}

	/**
	 * Get the translation sync support.
	 *
	 * @since 3.17.0
	 *
	 * @return PolylangSync
	 */
	protected function get_sync() {

		return new PolylangSync();
	}

	/**
	 * Get the front-end support.
	 *
	 * @since 3.17.0
	 *
	 * @return PolylangFrontend
	 */
	protected function get_frontend() {

		return new PolylangFrontend();
	}

	/**
	 * Get the calendar term support.
	 *
	 * @since 3.17.0
	 *
	 * @return PolylangTerms
	 */
	protected function get_terms() {

		return new PolylangTerms();
	}

	/**
	 * Get the event meta support.
	 *
	 * @since 3.17.0
	 *
	 * @return PolylangMeta
	 */
	protected function get_meta() {

		return new PolylangMeta();
	}

	/**
	 * Get the ticket, RSVP and online meeting support.
	 *
	 * @since 3.17.0
	 *
	 * @return PolylangSales
	 */
	protected function get_sales() {

		return new PolylangSales();
	}

	/**
	 * Get the Google Calendar push support.
	 *
	 * @since 3.17.0
	 *
	 * @return PolylangGoogle
	 */
	protected function get_google() {

		return new PolylangGoogle();
	}
}
