<?php
/**
 * Zoho CRM OAuth2 connection endpoints.
 *
 * The callback is the plugin's one public route: Zoho redirects the browser to
 * it, so it carries no nonce and is authenticated by the single-use state
 * token instead.
 *
 * @package WPIntegrations\ZohoCrmConnector
 */

namespace WPIntegrations\ZohoCrmConnector\Http\Controllers;

use WPIntegrations\ZohoCrmConnector\Integrations\Actions\ZohoCrm\ZohoCrmOAuth;

defined( 'ABSPATH' ) || exit;

final class OAuthController extends Controller {

	/** @var ZohoCrmOAuth */
	private $oauth;

	public function __construct( ZohoCrmOAuth $oauth ) {
		$this->oauth = $oauth;
	}

	public function status( \WP_REST_Request $request ): \WP_REST_Response {
		return $this->ok( $this->oauth->status() );
	}

	/**
	 * Save the client credentials created in the Zoho API Console.
	 */
	public function credentials( \WP_REST_Request $request ): \WP_REST_Response {
		$body      = (array) $request->get_json_params();
		$client_id = sanitize_text_field( (string) ( $body['client_id'] ?? '' ) );
		$secret    = sanitize_text_field( (string) ( $body['client_secret'] ?? '' ) );

		if ( '' === $client_id ) {
			return $this->fail( __( 'Enter the Client ID from your Zoho API Console client.', 'wpintegrations-connector-for-zoho-crm' ) );
		}

		// The mask the status call sent means "keep the stored secret".
		$stored = $this->oauth->client_secret();
		if ( '' !== $stored && SettingsController::mask( $stored ) === $secret ) {
			$secret = '';
		}

		$this->oauth->save_credentials( $client_id, $secret );

		if ( ! $this->oauth->has_credentials() ) {
			return $this->fail( __( 'Enter the Client Secret from your Zoho API Console client.', 'wpintegrations-connector-for-zoho-crm' ) );
		}

		return $this->ok( $this->oauth->status() );
	}

	/**
	 * Build the consent URL to send the browser to.
	 */
	public function authorize( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! $this->oauth->has_credentials() ) {
			return $this->fail( __( 'Save your Client ID and Client Secret before connecting.', 'wpintegrations-connector-for-zoho-crm' ) );
		}

		return $this->ok( array( 'url' => $this->oauth->authorize_url() ) );
	}

	public function disconnect( \WP_REST_Request $request ): \WP_REST_Response {
		$this->oauth->disconnect();
		return $this->ok( $this->oauth->status() );
	}

	/**
	 * OAuth redirect target: validate the state, exchange the code at the
	 * account's data centre, then send the browser back to Settings.
	 */
	public function callback( \WP_REST_Request $request ) {
		$state  = sanitize_text_field( (string) $request->get_param( 'state' ) );
		$code   = sanitize_text_field( (string) $request->get_param( 'code' ) );
		$error  = sanitize_text_field( (string) $request->get_param( 'error' ) );
		$server = esc_url_raw( (string) $request->get_param( 'accounts-server' ) );

		// Single use: consumed before the code is touched, so no outcome of
		// this trip can be replayed.
		if ( ! $this->oauth->verify_state( $state ) ) {
			return $this->redirect( __( 'The connection request expired or did not come from this site. Please try again.', 'wpintegrations-connector-for-zoho-crm' ) );
		}

		if ( '' !== $error ) {
			/* translators: %s: error returned by Zoho. */
			return $this->redirect( sprintf( __( 'Zoho did not allow the connection: %s', 'wpintegrations-connector-for-zoho-crm' ), $error ) );
		}

		if ( '' === $code ) {
			return $this->redirect( __( 'Zoho did not return an authorization code.', 'wpintegrations-connector-for-zoho-crm' ) );
		}

		return $this->redirect( $this->oauth->exchange_code( $code, untrailingslashit( $server ) ) );
	}

	/**
	 * Redirect back to the Settings tab, which shows the outcome.
	 *
	 * @param string $error Error message, or '' on success.
	 */
	private function redirect( string $error ) {
		$url = add_query_arg(
			'' === $error
				? array( 'wpi_zcrm_oauth' => 'connected' )
				: array(
					'wpi_zcrm_oauth'       => 'error',
					'wpi_zcrm_oauth_error' => rawurlencode( $error ),
				),
			admin_url( 'admin.php?page=' . WPI_ZCRM_SLUG . '-settings' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
