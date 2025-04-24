<?php
/**
 * Plugin Activator
 *
 * @package PRC\Platform\Report_Package
 */

namespace PRC\Platform\Report_Package;

/**
 * Plugin Activator
 *
 * @package PRC\Platform\Report_Package
 */
class Plugin_Activator {

	public static function activate() {
		flush_rewrite_rules();

		wp_mail(
			DEFAULT_TECHNICAL_CONTACT,
			'PRC Report Package Activated',
			'The PRC Report Package plugin has been activated on ' . get_site_url()
		);
	}
}
