<?php
/**
 * Plugin Name:       WPIntegrations Form Connector for Zoho CRM
 * Description:       Send Contact Form 7, Elementor Pro, WPForms, Fluent Forms and Gravity Forms submissions to Zoho CRM as leads or contacts, with field mapping and delivery logs. Not affiliated with Zoho or any form plugin.
 * Version:           1.0.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            WPIntegrations
 * Author URI:        https://wpintegrations.net/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpintegrations-connector-for-zoho-crm
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pro shares this plugin's namespace, constants and tables, so only one may run
 * and Pro wins. Decided before any constant is defined and without relying on
 * load order; the option is the only check available this early, since
 * is_plugin_active() lives in an admin-only file.
 */
$wpi_zcrm_pro       = 'wpintegrations-connector-for-zoho-crm-pro/wpintegrations-connector-for-zoho-crm-pro.php';
$wpi_zcrm_pro_alive = in_array( $wpi_zcrm_pro, (array) get_option( 'active_plugins', array() ), true )
	|| ( is_multisite() && array_key_exists( $wpi_zcrm_pro, (array) get_site_option( 'active_sitewide_plugins', array() ) ) );

if ( $wpi_zcrm_pro_alive ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-warning"><p>' . esc_html__(
				'The Pro version is active, so this free copy is doing nothing. You can safely delete it — your integrations and connection stay with Pro.',
				'wpintegrations-connector-for-zoho-crm'
			) . '</p></div>';
		}
	);
	return;
}

define( 'WPI_ZCRM_VERSION', '1.0.1' );
define( 'WPI_ZCRM_FILE', __FILE__ );
define( 'WPI_ZCRM_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPI_ZCRM_URL', plugin_dir_url( __FILE__ ) );
define( 'WPI_ZCRM_SLUG', 'wpintegrations-connector-for-zoho-crm' );
define( 'WPI_ZCRM_REST_NS', 'wpi_zcrm/v1' );

// PSR-4 autoloader for app/. No Composer needed at runtime.
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'WPIntegrations\\ZohoCrmConnector\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = WPI_ZCRM_PATH . 'app/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook( __FILE__, array( WPIntegrations\ZohoCrmConnector\Core\Application::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( WPIntegrations\ZohoCrmConnector\Core\Application::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		WPIntegrations\ZohoCrmConnector\Core\Application::instance()->boot();
	}
);
