<?php

namespace Sugar_Calendar\Admin\Pages;

use Sugar_Calendar\Admin\Tools\Importers;

/**
 * Calendar Import Tools tab.
 *
 * @since 3.3.0
 */
class ToolsImportTab extends Tools {

	/**
	 * Register Export tab hooks.
	 *
	 * @since 3.3.0
	 */
	public function hooks() {

		parent::hooks();

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @since 3.3.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( 'sugar-calendar-admin-importers' );
	}

	/**
	 * Initialize import page.
	 *
	 * @since 3.3.0
	 */
	public function init() {

		parent::init();

		if (
			! empty( $_POST['action'] ) &&
			$_POST['action'] === 'import_form' &&
			! empty( $_POST['import_src'] )
		) {
			if (
				! empty( $_POST['_nonce'] ) &&
				wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ), Importers::IMPORT_NONCE_ACTION )
			) {
				$this->attempt_perform_import( sanitize_text_field( wp_unslash( $_POST['import_src'] ) ) );
			} else {
				wp_die( esc_html__( 'Invalid request.', 'sugar-calendar-lite' ) );
			}
		}
	}

	/**
	 * Attempt to perform an import.
	 *
	 * @since 3.3.0
	 *
	 * @param string $import_src Import source.
	 */
	private function attempt_perform_import( $import_src ) {

		$import_src = strtolower( $import_src );

		if ( ! array_key_exists( $import_src, sugar_calendar()->get_importers()->get_loaded_importers() ) ) {
			wp_die(
				sprintf(
					/* translators: %s: import source. */
					esc_html__( 'Importer not found for: %s', 'sugar-calendar-lite' ),
					esc_html( $import_src )
				)
			);
		}

		sugar_calendar()->get_importers()->get_loaded_importers()[ $import_src ]->run();
	}

	/**
	 * Page tab slug.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	public static function get_tab_slug() {

		return 'import';
	}

	/**
	 * Page label.
	 *
	 * @since 3.3.0
	 *
	 * @return string
	 */
	public static function get_label() {

		return esc_html__( 'Import', 'sugar-calendar-lite' );
	}

	/**
	 * Output the tab.
	 *
	 * @since 3.3.0
	 */
	protected function display_tab() {

		$this->display_importer_tab();
	}
}
