<?php
/**
 * Application configuration.
 *
 * @package WPIntegrations\ZohoCrmConnector
 */

defined( 'ABSPATH' ) || exit;

return array(
	'providers' => array(
		WPIntegrations\ZohoCrmConnector\Providers\AppServiceProvider::class,
		WPIntegrations\ZohoCrmConnector\Providers\DatabaseServiceProvider::class,
		WPIntegrations\ZohoCrmConnector\Providers\IntegrationServiceProvider::class,
		WPIntegrations\ZohoCrmConnector\Providers\RetentionServiceProvider::class,
		WPIntegrations\ZohoCrmConnector\Providers\RestServiceProvider::class,
		WPIntegrations\ZohoCrmConnector\Providers\AdminServiceProvider::class,
	),
);
