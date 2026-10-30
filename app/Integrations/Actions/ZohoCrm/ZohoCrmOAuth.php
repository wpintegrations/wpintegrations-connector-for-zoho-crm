<?php
/**
 * Site-wide Zoho CRM OAuth2 connection.
 *
 * Owns the client credentials, the consent redirect, the code exchange and
 * transparent refreshing. Each site registers its own client in the Zoho API
 * Console, so no credentials ship with the plugin and no broker sees a token.
 *
 * Zoho runs separate data centres (US, EU, India, Australia, Japan, Canada…).
 * Consent always starts at accounts.zoho.com; Zoho sends the browser back with
 * the account's own accounts server, and the token response names the API
 * domain to call. Both are stored and every later call uses them.
 *
 * @package WPIntegrations\ZohoCrmConnector
 */

namespace WPIntegrations\ZohoCrmConnector\Integrations\Actions\ZohoCrm;

use WPIntegrations\ZohoCrmConnector\Http\Controllers\SettingsController;
use WPIntegrations\ZohoCrmConnector\Services\Crypto;
use WPIntegrations\ZohoCrmConnector\Services\Http;

defined( 'ABSPATH' ) || exit;

final class ZohoCrmOAuth {

	/** Where consent starts for every data centre that allows multi-DC clients. */
	const CONSENT_SERVER = 'https://accounts.zoho.com';

	/** Used until a connection names the account's own API domain. */
	const DEFAULT_API_DOMAIN = 'https://www.zohoapis.com';

	/** Refresh this many seconds before the token actually expires. */
	const EXPIRY_MARGIN = 120;

	/** Lifetime of the CSRF state token issued on Connect. */
	const STATE_TTL = 900;

	const STATE_TRANSIENT = 'wpi_zcrm_oauth_state';

	/** @var Http */
	private $http;

	/** @var Crypto */
	private $crypto;

	/** @var string Why the last refresh failed, or ''. */
	private $last_error = '';

	public function __construct( Http $http, Crypto $crypto ) {
		$this->http   = $http;
		$this->crypto = $crypto;
	}

	/**
	 * Redirect URI to register on the Zoho client.
	 */
	public function redirect_uri(): string {
		return rest_url( WPI_ZCRM_REST_NS . '/oauth/callback' );
	}

	/**
	 * Permissions asked for at consent: create the records this plugin writes,
	 * and read the organization name for the connection test.
	 *
	 * @return string[]
	 */
	public function scopes(): array {
		/**
		 * Filters the Zoho OAuth scopes requested on Connect.
		 *
		 * @param string[] $scopes Zoho scope names.
		 */
		return (array) apply_filters(
			'wpi_zcrm_oauth_scopes',
			array(
				'ZohoCRM.modules.leads.CREATE',
				'ZohoCRM.modules.contacts.CREATE',
				'ZohoCRM.org.READ',
			)
		);
	}

	public function client_id(): string {
		return (string) ( $this->config()['client_id'] ?? '' );
	}

	public function client_secret(): string {
		$stored = (string) ( $this->config()['client_secret'] ?? '' );
		return '' === $stored ? '' : $this->crypto->decrypt( $stored );
	}

	public function has_credentials(): bool {
		return '' !== $this->client_id() && '' !== $this->client_secret();
	}

	public function connected(): bool {
		return '' !== (string) ( $this->config()['refresh_token'] ?? '' );
	}

	/**
	 * The account's API domain, e.g. https://www.zohoapis.eu.
	 */
	public function api_domain(): string {
		$domain = (string) ( $this->config()['api_domain'] ?? '' );
		return '' === $domain ? self::DEFAULT_API_DOMAIN : $domain;
	}

	/**
	 * Store the client credentials. An empty secret keeps the stored one, so
	 * the browser can send back a mask instead of the real value.
	 */
	public function save_credentials( string $client_id, string $client_secret ): void {
		$config              = $this->config();
		$config['client_id'] = $client_id;
		if ( '' !== $client_secret ) {
			$config['client_secret'] = $this->crypto->encrypt( $client_secret );
		}
		$this->save( $config );
	}

	/**
	 * Mint a single-use state token and build the consent URL. Offline access
	 * with a forced consent screen is what makes Zoho return a refresh token.
	 */
	public function authorize_url(): string {
		$state = wp_generate_uuid4();
		set_transient( self::STATE_TRANSIENT, $state, self::STATE_TTL );

		return add_query_arg(
			array(
				'scope'         => rawurlencode( implode( ',', $this->scopes() ) ),
				'client_id'     => rawurlencode( $this->client_id() ),
				'response_type' => 'code',
				'access_type'   => 'offline',
				'prompt'        => 'consent',
				'redirect_uri'  => rawurlencode( $this->redirect_uri() ),
				'state'         => rawurlencode( $state ),
			),
			self::CONSENT_SERVER . '/oauth/v2/auth'
		);
	}

	/**
	 * Check the returned state against the issued one. Single use, so it is
	 * consumed whatever the outcome.
	 */
	public function verify_state( string $state ): bool {
		$expected = (string) get_transient( self::STATE_TRANSIENT );
		delete_transient( self::STATE_TRANSIENT );
		return '' !== $expected && hash_equals( $expected, $state );
	}

	/**
	 * Exchange an authorization code for tokens at the account's own data
	 * centre.
	 *
	 * @param string $code           Authorization code returned by Zoho.
	 * @param string $accounts_server The accounts-server Zoho returned, or ''.
	 * @return string Empty on success, otherwise an error message.
	 */
	public function exchange_code( string $code, string $accounts_server ): string {
		$server = '' === $accounts_server ? self::CONSENT_SERVER : $accounts_server;
		if ( ! self::is_zoho_accounts_server( $server ) ) {
			// The client secret goes to this host, so it must be Zoho's.
			return __( 'Zoho returned an unexpected sign-in server, so the connection was not completed.', 'wpintegrations-connector-for-zoho-crm' );
		}

		$response = $this->http->post_form(
			$server . '/oauth/v2/token',
			array(
				'grant_type'    => 'authorization_code',
				'client_id'     => $this->client_id(),
				'client_secret' => $this->client_secret(),
				'redirect_uri'  => $this->redirect_uri(),
				'code'          => $code,
			)
		);

		return $this->store_tokens( $response, $server );
	}

	/**
	 * Trade the refresh token for a new access token.
	 *
	 * @return string Empty on success, otherwise an error message.
	 */
	public function refresh(): string {
		$refresh_token = $this->refresh_token();
		if ( '' === $refresh_token ) {
			$this->last_error = __( 'Zoho CRM is not connected — connect it under Settings.', 'wpintegrations-connector-for-zoho-crm' );
			return $this->last_error;
		}

		$server   = (string) ( $this->config()['accounts_server'] ?? self::CONSENT_SERVER );
		$response = $this->http->post_form(
			$server . '/oauth/v2/token',
			array(
				'grant_type'    => 'refresh_token',
				'client_id'     => $this->client_id(),
				'client_secret' => $this->client_secret(),
				'refresh_token' => $refresh_token,
			)
		);

		$this->last_error = $this->store_tokens( $response, $server );
		return $this->last_error;
	}

	/**
	 * Why the last token refresh failed, for a request that got no token.
	 */
	public function last_error(): string {
		return '' !== $this->last_error ? $this->last_error : __( 'Zoho CRM is not connected — connect it under Settings.', 'wpintegrations-connector-for-zoho-crm' );
	}

	/**
	 * A usable access token, refreshing first if it is expired or near it.
	 */
	public function access_token(): string {
		$config  = $this->config();
		$token   = (string) ( $config['access_token'] ?? '' );
		$expires = (int) ( $config['expires_at'] ?? 0 );

		if ( '' === $token || $expires <= time() + self::EXPIRY_MARGIN ) {
			if ( '' !== $this->refresh() ) {
				return '';
			}
			$token = (string) ( $this->config()['access_token'] ?? '' );
		}

		return '' === $token ? '' : $this->crypto->decrypt( $token );
	}

	/**
	 * Authorized GET against the API domain. A 401 means a stale token:
	 * refresh once, then retry.
	 *
	 * @param string $path Path under the API domain, e.g. /crm/v8/org.
	 * @return object|null Normalized Http response, or null when not connected.
	 */
	public function get( string $path ) {
		return $this->call(
			function ( string $token ) use ( $path ) {
				return $this->http->get( $this->api_domain() . $path, $this->headers( $token ) );
			}
		);
	}

	/**
	 * Authorized JSON POST, with the same refresh-once-on-401 behaviour.
	 *
	 * @param string $path Path under the API domain.
	 * @param array  $body JSON-encodable body.
	 * @return object|null Normalized Http response, or null when not connected.
	 */
	public function post( string $path, array $body ) {
		return $this->call(
			function ( string $token ) use ( $path, $body ) {
				return $this->http->post_json( $this->api_domain() . $path, $body, $this->headers( $token ) );
			}
		);
	}

	/**
	 * Drop the tokens but keep the client credentials, so reconnecting does
	 * not mean re-entering them.
	 */
	public function disconnect(): void {
		$config = $this->config();
		unset( $config['access_token'], $config['refresh_token'], $config['expires_at'], $config['connected_at'], $config['accounts_server'], $config['api_domain'] );
		$this->save( $config );
	}

	/**
	 * Connection state for the admin UI. Never exposes a secret: only its
	 * last characters, so the owner can tell which one is saved.
	 *
	 * @return array<string, mixed>
	 */
	public function status(): array {
		$config = $this->config();
		$secret = (string) ( $config['client_secret'] ?? '' );

		return array(
			'has_credentials' => $this->has_credentials(),
			'connected'       => $this->connected(),
			'client_id'       => $this->client_id(),
			'secret_mask'     => '' !== $secret ? SettingsController::mask( $this->client_secret() ) : '',
			'redirect_uri'    => $this->redirect_uri(),
			'api_domain'      => $this->connected() ? $this->api_domain() : '',
			'connected_at'    => (string) ( $config['connected_at'] ?? '' ),
		);
	}

	/**
	 * Whether a URL is one of Zoho's accounts servers, e.g.
	 * https://accounts.zoho.eu or https://accounts.zohocloud.ca.
	 */
	public static function is_zoho_accounts_server( string $url ): bool {
		return 1 === preg_match( '#^https://accounts\.zoho(cloud)?\.[a-z]{2,3}(\.[a-z]{2})?$#', $url );
	}

	/**
	 * Whether a URL is one of Zoho's API domains, e.g. https://www.zohoapis.in.
	 */
	public static function is_zoho_api_domain( string $url ): bool {
		return 1 === preg_match( '#^https://(www\.)?zohoapis\.[a-z]{2,3}(\.[a-z]{2})?$#', $url );
	}

	/**
	 * Run an authorized request, refreshing once when Zoho answers 401.
	 *
	 * @param callable $send Receives the access token, returns a response.
	 * @return object|null
	 */
	private function call( callable $send ) {
		$token = $this->access_token();
		if ( '' === $token ) {
			return null;
		}

		$response = $send( $token );

		if ( 401 === $response->code && '' === $this->refresh() ) {
			$token = $this->access_token();
			if ( '' !== $token ) {
				$response = $send( $token );
			}
		}

		return $response;
	}

	/**
	 * @return array<string, string>
	 */
	private function headers( string $token ): array {
		return array(
			'Authorization' => 'Zoho-oauthtoken ' . $token,
			'Accept'        => 'application/json',
		);
	}

	private function refresh_token(): string {
		$stored = (string) ( $this->config()['refresh_token'] ?? '' );
		return '' === $stored ? '' : $this->crypto->decrypt( $stored );
	}

	/**
	 * Persist tokens from a token-endpoint response. Zoho answers some failures
	 * with HTTP 200 and an "error" field, so both are checked.
	 *
	 * @param object $response Normalized Http response.
	 * @param string $server   The accounts server the tokens came from.
	 * @return string Empty on success, otherwise an error message.
	 */
	private function store_tokens( object $response, string $server ): string {
		$body  = is_array( $response->body ) ? $response->body : array();
		$error = (string) ( $body['error'] ?? '' );

		if ( ! $response->ok || '' !== $error ) {
			if ( 0 === $response->code ) {
				/* translators: %s: transport error message. */
				return sprintf( __( 'Could not reach Zoho: %s', 'wpintegrations-connector-for-zoho-crm' ), $response->error );
			}
			if ( 'invalid_client' === $error ) {
				return __( 'Zoho did not accept the Client ID or Client Secret. If your Zoho account is outside the US data centre, turn on Multi-DC for this client in the Zoho API Console.', 'wpintegrations-connector-for-zoho-crm' );
			}
			if ( 'invalid_code' === $error ) {
				return __( 'Zoho no longer accepts this authorization. Connect again, or reconnect if it was revoked in Zoho.', 'wpintegrations-connector-for-zoho-crm' );
			}
			$detail = '' !== $error ? $error : ( is_array( $response->body ) ? wp_json_encode( $response->body ) : (string) $response->body );
			/* translators: 1: HTTP status code, 2: error detail. */
			return sprintf( __( 'Zoho rejected the connection (HTTP %1$d): %2$s', 'wpintegrations-connector-for-zoho-crm' ), $response->code, mb_substr( sanitize_text_field( (string) $detail ), 0, 300 ) );
		}

		$access = (string) ( $body['access_token'] ?? '' );
		if ( '' === $access ) {
			return __( 'Zoho did not return an access token.', 'wpintegrations-connector-for-zoho-crm' );
		}

		$config                    = $this->config();
		$config['access_token']    = $this->crypto->encrypt( $access );
		$config['expires_at']      = time() + (int) ( $body['expires_in'] ?? HOUR_IN_SECONDS );
		$config['accounts_server'] = $server;

		$api_domain = untrailingslashit( (string) ( $body['api_domain'] ?? '' ) );
		if ( self::is_zoho_api_domain( $api_domain ) ) {
			$config['api_domain'] = $api_domain;
		}

		// Only the code exchange returns a refresh token; a refresh keeps the
		// stored one.
		$refresh = (string) ( $body['refresh_token'] ?? '' );
		if ( '' !== $refresh ) {
			$config['refresh_token'] = $this->crypto->encrypt( $refresh );
			$config['connected_at']  = current_time( 'mysql', true );
		}

		$this->save( $config );
		return '';
	}

	/**
	 * @return array<string, mixed>
	 */
	private function config(): array {
		$settings = (array) get_option( 'wpi_zcrm_settings', array() );
		return (array) ( $settings['oauth'] ?? array() );
	}

	/**
	 * @param array<string, mixed> $config OAuth config block.
	 */
	private function save( array $config ): void {
		$settings          = (array) get_option( 'wpi_zcrm_settings', array() );
		$settings['oauth'] = $config;
		update_option( 'wpi_zcrm_settings', $settings, false );
	}
}
