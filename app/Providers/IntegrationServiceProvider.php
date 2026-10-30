<?php
/**
 * Registers built-in triggers/actions and boots available triggers.
 *
 * @package WPIntegrations\ZohoCrmConnector
 */

namespace WPIntegrations\ZohoCrmConnector\Providers;

use WPIntegrations\ZohoCrmConnector\Core\ServiceProvider;
use WPIntegrations\ZohoCrmConnector\Integrations\Actions\ZohoCrm\ZohoCrmContactAction;
use WPIntegrations\ZohoCrmConnector\Integrations\Actions\ZohoCrm\ZohoCrmLeadAction;
use WPIntegrations\ZohoCrmConnector\Integrations\Registry;
use WPIntegrations\ZohoCrmConnector\Integrations\Triggers\ContactForm7\ContactForm7Trigger;
use WPIntegrations\ZohoCrmConnector\Integrations\Triggers\Elementor\ElementorTrigger;
use WPIntegrations\ZohoCrmConnector\Integrations\Triggers\FluentForms\FluentFormsTrigger;
use WPIntegrations\ZohoCrmConnector\Integrations\Triggers\GravityForms\GravityFormsTrigger;
use WPIntegrations\ZohoCrmConnector\Integrations\Triggers\WPForms\WPFormsTrigger;

defined( 'ABSPATH' ) || exit;

final class IntegrationServiceProvider extends ServiceProvider {

	public function boot(): void {
		$registry = $this->app->make( Registry::class );

		// Every supported form plugin is registered; only the active ones hook
		// in, and the editor offers only those.
		$registry->add_trigger( $this->app->make( ContactForm7Trigger::class ) );
		$registry->add_trigger( $this->app->make( ElementorTrigger::class ) );
		$registry->add_trigger( $this->app->make( WPFormsTrigger::class ) );
		$registry->add_trigger( $this->app->make( FluentFormsTrigger::class ) );
		$registry->add_trigger( $this->app->make( GravityFormsTrigger::class ) );
		$registry->add_action( $this->app->make( ZohoCrmLeadAction::class ) );
		$registry->add_action( $this->app->make( ZohoCrmContactAction::class ) );

		/**
		 * Add-ons register additional triggers and actions here.
		 *
		 * @param Registry $registry The integration registry.
		 */
		do_action( 'wpi_zcrm_register_integrations', $registry );

		add_action(
			'init',
			static function () use ( $registry ) {
				foreach ( $registry->triggers() as $trigger ) {
					if ( $trigger->available() ) {
						$trigger->register();
					}
				}
			}
		);
	}
}
