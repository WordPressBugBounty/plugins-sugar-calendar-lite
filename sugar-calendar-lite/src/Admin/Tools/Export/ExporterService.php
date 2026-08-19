<?php

namespace Sugar_Calendar\Admin\Tools\Export;

/**
 * Tools export orchestrator.
 *
 * The single place a requested format resolves to a concrete exporter. Callers
 * check has_items() then output() without knowing which format is active.
 *
 * @since 3.13.0
 */
class ExporterService {

	/**
	 * Known exporters, each declaring the format slug it handles via its
	 * FORMAT constant. Add a format by adding its class here — no branching.
	 * The first entry is the default when the requested format is unknown.
	 *
	 * @since 3.13.0
	 *
	 * @var string[]
	 */
	const EXPORTERS = [
		JsonExporter::class,
		CsvExporter::class,
	];

	/**
	 * The resolved concrete exporter.
	 *
	 * @since 3.13.0
	 *
	 * @var AbstractExporter
	 */
	private $exporter;

	/**
	 * Constructor.
	 *
	 * @since 3.13.0
	 *
	 * @param array $args Export args ( keys, format, date_start, date_end ).
	 */
	public function __construct( array $args ) {

		$this->exporter = $this->resolve( $args );
	}

	/**
	 * Resolve the concrete exporter for the requested format.
	 *
	 * @since 3.13.0
	 *
	 * @param array $args Export args.
	 *
	 * @return AbstractExporter
	 */
	private function resolve( array $args ): AbstractExporter {

		$format = $args['format'] ?? '';

		foreach ( self::EXPORTERS as $exporter ) {
			if ( $format === $exporter::FORMAT ) {
				return new $exporter( $args );
			}
		}

		// Unknown/empty format: fall back to the first (default) exporter.
		$default = self::EXPORTERS[0];

		return new $default( $args );
	}

	/**
	 * Whether there is anything to export for the current selection.
	 *
	 * @since 3.13.0
	 *
	 * @return bool
	 */
	public function has_items(): bool {

		return $this->exporter->has_items();
	}

	/**
	 * Stream the export to the browser and exit.
	 *
	 * @since 3.13.0
	 */
	public function output() {

		$this->exporter->output();
	}
}
