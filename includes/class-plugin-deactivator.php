<?php
/**
 * Plugin Deactivator
 *
 * @package PRC\Platform\Report_Package
 */

namespace PRC\Platform\Report_Package;

/**
 * Plugin Deactivator
 */
class Plugin_Deactivator {

	public static function deactivate() {
		flush_rewrite_rules();

		wp_mail(
			DEFAULT_TECHNICAL_CONTACT,
			'PRC Report Package Deactivated',
			'The PRC Report Package plugin has been deactivated on ' . get_site_url()
		);
	}
}
