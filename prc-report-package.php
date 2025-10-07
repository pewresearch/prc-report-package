<?php
/**
 * PRC Report Package
 *
 * @package           PRC_Report_Package
 * @author            Seth Rubenstein
 * @copyright         2024 Pew Research Center
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       PRC Report Package
 * Plugin URI:        https://github.com/pewresearch/prc-report-package
 * Description:       A plugin for PRC Platform that enables managing comprehensive multi-post research report packages and their associated materials. Provides a system and interface for associating child posts with a parent report post, and for displaying the report package and its associated materials.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Author:            Seth Rubenstein
 * Author URI:        https://pewresearch.org
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       prc-report-package
 * Requires Plugins:  prc-platform-core
 */

namespace PRC\Platform\Report_Package;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PRC_REPORT_PACKAGE_FILE', __FILE__ );
define( 'PRC_REPORT_PACKAGE_DIR', __DIR__ );
define( 'PRC_REPORT_PACKAGE_MANIFEST_FILE', __DIR__ . '/build/block-manifest.php' );
define( 'PRC_REPORT_PACKAGE_BLOCKS_DIR', __DIR__ . '/build' );
define( 'PRC_REPORT_PACKAGE_VERSION', '1.0.0' );
define( 'PRC_REPORT_PACKAGE_ENABLED_POST_TYPES', array( 'post' ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-plugin-activator.php
 */
function activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-plugin-activator.php';
	Plugin_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-plugin-deactivator.php
 */
function deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-plugin-deactivator.php';
	Plugin_Deactivator::deactivate();
}

register_activation_hook( __FILE__, '\PRC\Platform\Report_Package\activate' );
register_deactivation_hook( __FILE__, '\PRC\Platform\Report_Package\deactivate' );

/**
 * Helper utilities
 */
require plugin_dir_path( __FILE__ ) . 'includes/utils.php';

/**
 * The core plugin class that is used to define the hooks that initialize the various components.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-plugin.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_prc_report_package() {
	$plugin = new Plugin();
	$plugin->run();
}
run_prc_report_package();
