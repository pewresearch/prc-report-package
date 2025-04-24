<?php
/**
 * WP Admin
 *
 * @package PRC\Platform\Report_Package
 */

namespace PRC\Platform\Report_Package;

use WP_Error;

/**
 * Handles registering and enqueing admin assets and interface elements.
 *
 * @package PRC\Platform\Report_Package
 */
class WP_Admin {

	/**
	 * The handle for the admin assets.
	 *
	 * @var string
	 */
	public static $handle = 'prc-report-package-interface';

	/**
	 * The constructor.
	 *
	 * @param mixed $loader The loader.
	 */
	public function __construct( $loader ) {
		$this->init( $loader );
	}

	/**
	 * Initialize the hooks.
	 *
	 * @param mixed $loader The loader.
	 */
	public function init( $loader = null ) {
		if ( null !== $loader ) {
			$loader->add_filter( 'the_title', $this, 'indicate_chapter_post', 10, 2 );
			$loader->add_action( 'enqueue_block_editor_assets', $this, 'enqueue_panel_assets' );
		}
	}

	/**
	 * Modify the post title to include an em dash before the title if this post is a child and part of a post-package.
	 *
	 * @hook the_title
	 *
	 * @param string $title The title.
	 * @param int    $post_id The post ID.
	 * @return string The modified title.
	 */
	public function indicate_chapter_post( $title, $post_id = null ) {
		// Sanity check.
		if ( ! function_exists( 'get_current_screen' ) ) {
			return $title;
		}

		// If we're not in admin or if our post_id isn't set return title.
		if ( ! is_admin() || null === $post_id ) {
			return $title;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'edit' !== $screen->parent_base ) {
			return $title;
		}

		if ( ! in_array( get_post_type( $post_id ), PRC_REPORT_PACKAGE_ENABLED_POST_TYPES ) ) {
			return $title;
		}

		// Add an em dash to the title if this post is a child and part of a post-package.
		if ( 0 !== wp_get_post_parent_id( $post_id ) && true === is_chapter_part_of_report_package( $post_id ) ) {
			$title = '&mdash; ' . $title;
		}

		return $title;
	}

	/**
	 * Register the UI panel assets for this block editor plugin.
	 *
	 * @hook enqueue_block_editor_assets
	 * @return WP_Error|true
	 */
	public function register_panel_assets() {
		$asset_file = include plugin_dir_path( __FILE__ ) . 'inspector-sidebar-panel/build/index.asset.php';
		$asset_slug = self::$handle;
		$script_src = plugin_dir_url( __FILE__ ) . 'inspector-sidebar-panel/build/index.js';

		$script = wp_register_script(
			$asset_slug,
			$script_src,
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);
		if ( ! $script ) {
			return new WP_Error( self::$handle, 'Failed to register all assets' );
		}

		return true;
	}

	/**
	 * Enqueue the assets for this block editor plugin.
	 *
	 * @hook enqueue_block_editor_assets
	 * @return void
	 */
	public function enqueue_panel_assets() {
		$registered = $this->register_panel_assets();
		if ( is_admin() && ! is_wp_error( $registered ) ) {
			$screen = get_current_screen();
			if ( in_array( $screen->post_type, PRC_REPORT_PACKAGE_ENABLED_POST_TYPES ) ) {
				wp_enqueue_script( self::$handle );
			}
		}
	}
}
