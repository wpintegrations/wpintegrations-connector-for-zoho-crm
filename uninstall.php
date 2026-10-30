<?php
/**
 * Removes tables, options and cron events when the plugin is deleted.
 *
 * @package WPIntegrations\ZohoCrmConnector
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Both editions share this data. Bail on an installed Pro copy, not just an
 * active one: a bulk delete can remove this plugin while Pro is momentarily
 * deactivated, taking Pro's tokens and integrations with it.
 */
$wpi_zcrm_pro = 'wpintegrations-connector-for-zoho-crm-pro/wpintegrations-connector-for-zoho-crm-pro.php';

$wpi_zcrm_active = (array) get_option( 'active_plugins', array() );
$wpi_zcrm_shared = in_array( $wpi_zcrm_pro, $wpi_zcrm_active, true )
	|| ( is_multisite() && array_key_exists( $wpi_zcrm_pro, (array) get_site_option( 'active_sitewide_plugins', array() ) ) )
	|| file_exists( WP_PLUGIN_DIR . '/' . $wpi_zcrm_pro );

if ( $wpi_zcrm_shared ) {
	return;
}

global $wpdb;

// Plugin tables. Names are constants, not user input.
foreach ( array( 'wpi_zcrm_flows', 'wpi_zcrm_logs', 'wpi_zcrm_jobs' ) as $wpi_zcrm_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup of plugin-owned tables.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $wpi_zcrm_table ) );
}

// Options.
delete_option( 'wpi_zcrm_settings' );
delete_option( 'wpi_zcrm_version' );
delete_option( 'wpi_zcrm_migrations' );
delete_option( 'wpi_zcrm_recent_shapes' );

// Transients: each form plugin's scan cache, the OAuth state, plus Zoho
// field caches swept by prefix.
foreach ( array( 'elementor', 'cf7', 'wpforms', 'fluentform', 'gravityforms' ) as $wpi_zcrm_trigger ) {
	delete_transient( 'wpi_zcrm_' . $wpi_zcrm_trigger . '_forms' );
}
delete_transient( 'wpi_zcrm_oauth_state' );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup.
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_wpi\_zcrm\_api\_%'
	    OR option_name LIKE '\_transient\_timeout\_wpi\_zcrm\_api\_%'"
);

// Cron events.
wp_clear_scheduled_hook( 'wpi_zcrm_queue_tick' );
wp_clear_scheduled_hook( 'wpi_zcrm_daily_purge' );
