<?php
/**
 * Registers REST routes from routes/api.php.
 *
 * @package WPIntegrations\ZohoCrmConnector
 */

namespace WPIntegrations\ZohoCrmConnector\Providers;

use WPIntegrations\ZohoCrmConnector\Core\ServiceProvider;
use WPIntegrations\ZohoCrmConnector\Http\Router;

defined( 'ABSPATH' ) || exit;

final class RestServiceProvider extends ServiceProvider {

	public function boot(): void {
		add_action(
			'rest_api_init',
			function () {
				$routes = require WPI_ZCRM_PATH . 'routes/api.php';
				$routes( new Router( $this->app ) );
			}
		);
	}
}
