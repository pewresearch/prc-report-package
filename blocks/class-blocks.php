<?php
/**
 * The report package blocks class.
 *
 * @package PRC\Platform\Report_Package
 */

namespace PRC\Platform\Report_Package;

/**
 * The report package blocks class.
 */
class Blocks {
	/**
	 * The loader object.
	 *
	 * @var object
	 */
	protected $loader;

	/**
	 * Constructor.
	 *
	 * @param object $loader The loader object.
	 */
	public function __construct( $loader ) {
		$this->loader = $loader;

		require_once PRC_REPORT_PACKAGE_BLOCKS_DIR . '/build/report-materials/class-report-materials.php';
		require_once PRC_REPORT_PACKAGE_BLOCKS_DIR . '/build/report-pagination/class-report-pagination.php';

		$this->init();
	}

	/**
	 * Initialize the class.
	 */
	public function init() {
		$this->loader->add_action( 'init', $this, 'block_init' );

		new Report_Materials( $this->loader );
		new Report_Pagination( $this->loader );
	}

	/**
	 * Register the dataset description block.
	 *
	 * @hook init
	 */
	public function block_init() {
		wp_register_block_metadata_collection(
			PRC_REPORT_PACKAGE_BLOCKS_DIR . '/build',
			PRC_REPORT_PACKAGE_BLOCKS_DIR . '/build/blocks-manifest.php'
		);
	}
}
