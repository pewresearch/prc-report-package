<?php
/**
 * Plugin class.
 *
 * @package    PRC\Platform\Report_Package
 */

namespace PRC\Platform\Report_Package;

use WP_Error;

/**
 * Plugin class.
 *
 * @package    PRC\Platform\Report_Package
 */
class Plugin {
	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the platform as initialized by hooks.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->version     = '1.0.0';
		$this->plugin_name = 'prc-report-package';

		$this->load_dependencies();
		$this->init_dependencies();
	}


	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		// Load plugin loading class.
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-loader.php';

		// Initialize the loader.
		$this->loader = new Loader();

		require_once plugin_dir_path( __DIR__ ) . '/includes/class-rest-api.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-relationship-manager.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-wp-admin.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-distributor.php';
	}

	/**
	 * Initialize the dependencies.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function init_dependencies() {
		new Rest_API( $this->get_loader() );
		new Relationship_Manager( $this->get_loader() );
		new WP_Admin( $this->get_loader() );
		new Distributor( $this->get_loader() );

		// Initialize blocks.
		wp_register_block_metadata_collection(
			plugin_dir_path( __DIR__ ) . 'build',
			plugin_dir_path( __DIR__ ) . 'build/blocks-manifest.php'
		);

		// Load block classes if the function exists.
		if ( ! function_exists( '\PRC\Platform\Block_Utils\load_blocks' ) ) {
			return;
		}
		$blocks_loaded = \PRC\Platform\Block_Utils\load_blocks( PRC_REPORT_PACKAGE_DIR );
		if ( ! is_wp_error( $blocks_loaded ) ) {
			new Report_Materials( $this->get_loader() );
			new Report_Pagination( $this->get_loader() );
		}

		$this->register_schema_seo_filters();
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    PRC\Platform\Report_Package\Loader
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Register schema SEO filters.
	 */
	public function register_schema_seo_filters() {
		// Register schema SEO filter for report packages.
		// $this->loader->add_filter( 'prc_schema_seo_schema_type_default', $this, 'set_report_package_schema_type', 20, 3 );
	}

	/**
	 * Set multi-section report posts to use Report schema type.
	 *
	 * @hook prc_schema_seo_schema_type_default
	 *
	 * @param string $schema_type The default schema type.
	 * @param string $post_type   The post type.
	 * @param int    $post_id     The post ID.
	 * @return string The schema type.
	 */
	public function set_report_package_schema_type( $schema_type, $post_type, $post_id ) {
		if ( 'post' === $post_type && $post_id ) {
			if ( is_report_package( $post_id ) ) {
				return 'Report';
			}
		}
		return $schema_type;
	}
}
