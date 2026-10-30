<?php
/**
 * Creates one Zoho CRM record per form submission.
 *
 * Each module the plugin writes (Leads, Contacts) is its own action, so the
 * destination step offers them side by side and each declares only the fields
 * it sends. All of them deliver through the one site-wide OAuth connection.
 *
 * Every mapped target is sent as the Zoho field of that API name.
 *
 * @package WPIntegrations\ZohoCrmConnector
 */

namespace WPIntegrations\ZohoCrmConnector\Integrations\Actions\ZohoCrm;

use WPIntegrations\ZohoCrmConnector\Delivery\Result;
use WPIntegrations\ZohoCrmConnector\Integrations\Action;

defined( 'ABSPATH' ) || exit;

abstract class ZohoCrmRecordAction extends Action {

	/** Zoho CRM REST API version. */
	const API_VERSION = 'v8';

	/** Zoho's limit for a single-line text field. */
	const TEXT_MAX = 255;

	/** Zoho's limit for a multi-line field such as Description. */
	const TEXTAREA_MAX = 32000;

	/** @var ZohoCrmOAuth */
	protected $oauth;

	public function __construct( ZohoCrmOAuth $oauth ) {
		$this->oauth = $oauth;
	}

	/** The module's API name, e.g. 'Leads'. */
	abstract protected function module(): string;

	/** The record's name in log lines, e.g. 'Lead'. */
	abstract protected function record_label(): string;

	public function connected(): bool {
		return $this->oauth->connected();
	}

	/**
	 * Fields every person record shares. Last Name is the one Zoho requires.
	 *
	 * @return array<int, array{key:string, label:string, required?:bool}>
	 */
	protected function person_fields(): array {
		return array(
			array(
				'key'      => 'Last_Name',
				'label'    => __( 'Last Name', 'wpintegrations-connector-for-zoho-crm' ),
				'required' => true,
			),
			array(
				'key'   => 'First_Name',
				'label' => __( 'First Name', 'wpintegrations-connector-for-zoho-crm' ),
			),
			array(
				'key'   => 'Email',
				'label' => __( 'Email', 'wpintegrations-connector-for-zoho-crm' ),
			),
			array(
				'key'   => 'Phone',
				'label' => __( 'Phone', 'wpintegrations-connector-for-zoho-crm' ),
			),
			array(
				'key'   => 'Mobile',
				'label' => __( 'Mobile', 'wpintegrations-connector-for-zoho-crm' ),
			),
		);
	}

	/**
	 * Fields after the person's own.
	 *
	 * @return array<int, array{key:string, label:string}>
	 */
	protected function closing_fields(): array {
		return array(
			array(
				'key'   => 'Lead_Source',
				'label' => __( 'Lead Source', 'wpintegrations-connector-for-zoho-crm' ),
			),
			array(
				'key'   => 'Description',
				'label' => __( 'Description', 'wpintegrations-connector-for-zoho-crm' ),
			),
		);
	}

	public function flow_fields(): array {
		return array(
			array(
				'key'     => 'run_workflows',
				'label'   => __( 'Run Zoho CRM workflow rules for new records', 'wpintegrations-connector-for-zoho-crm' ),
				'help'    => __( 'Lets your Zoho workflows (alerts, tasks, field updates) act on records this form creates.', 'wpintegrations-connector-for-zoho-crm' ),
				'type'    => 'boolean',
				'default' => '1',
				'below'   => true,
			),
		);
	}

	/**
	 * Zoho refuses a person record without a last name.
	 */
	public function validate_flow( array $field_map, array $settings ): ?string {
		foreach ( $field_map as $pair ) {
			if ( 'Last_Name' === (string) $pair['target'] ) {
				return null;
			}
		}
		return __( 'Map a form field to Last Name — Zoho CRM requires it.', 'wpintegrations-connector-for-zoho-crm' );
	}

	/**
	 * Read the organization, which proves the token works without writing.
	 */
	public function test_connection( array $credentials ): Result {
		if ( ! $this->oauth->connected() ) {
			return Result::failure( __( 'Connect Zoho CRM first.', 'wpintegrations-connector-for-zoho-crm' ) );
		}

		$response = $this->oauth->get( '/crm/' . self::API_VERSION . '/org' );
		if ( null === $response ) {
			return Result::failure( $this->oauth->last_error() );
		}
		if ( ! $response->ok ) {
			return Result::failure( $this->error_message( $response ) );
		}

		$org  = is_array( $response->body ) ? (array) ( $response->body['org'][0] ?? array() ) : array();
		$name = sanitize_text_field( (string) ( $org['company_name'] ?? '' ) );

		return Result::success(
			'' !== $name
				/* translators: %s: Zoho CRM organization name. */
				? sprintf( __( 'Connected to the Zoho CRM organization “%s”.', 'wpintegrations-connector-for-zoho-crm' ), $name )
				: __( 'Connected to Zoho CRM.', 'wpintegrations-connector-for-zoho-crm' )
		);
	}

	public function execute( object $flow, array $payload ): Result {
		if ( ! $this->oauth->connected() ) {
			return Result::failure( __( 'Zoho CRM is not connected — connect it under Settings.', 'wpintegrations-connector-for-zoho-crm' ) );
		}

		$record = array();
		foreach ( $this->map_fields( $flow, $payload ) as $target => $value ) {
			$target = trim( (string) $target );
			$value  = trim( (string) $value );
			if ( '' === $target || '' === $value ) {
				continue;
			}
			$record[ $target ] = 'Description' === $target
				? mb_substr( sanitize_textarea_field( $value ), 0, self::TEXTAREA_MAX )
				: mb_substr( sanitize_text_field( $value ), 0, self::TEXT_MAX );
		}

		// The mapping has the last name, but the visitor may have left it empty.
		if ( empty( $record['Last_Name'] ) ) {
			return Result::failure( __( 'This submission has no last name, which Zoho CRM requires. Nothing was sent.', 'wpintegrations-connector-for-zoho-crm' ) );
		}
		if ( isset( $record['Email'] ) && ! is_email( $record['Email'] ) ) {
			/* translators: %s: the submitted email address. */
			return Result::failure( sprintf( __( '“%s” is not a valid email address, so the record was not sent.', 'wpintegrations-connector-for-zoho-crm' ), $record['Email'] ) );
		}

		$settings = (array) ( $flow->settings ?? array() );
		$body     = array(
			'data'    => array( $record ),
			// An empty list tells Zoho to skip workflow rules.
			'trigger' => ! empty( $settings['run_workflows'] ) ? array( 'workflow' ) : array(),
		);

		$path     = '/crm/' . self::API_VERSION . '/' . $this->module();
		$request  = array(
			'endpoint' => $this->oauth->api_domain() . $path,
			'payload'  => $body,
		);
		$response = $this->oauth->post( $path, $body );

		if ( null === $response ) {
			return Result::failure( $this->oauth->last_error(), $request );
		}

		$logged = array(
			'code' => $response->code,
			'body' => $response->body,
		);

		// Zoho reports each record on its own, even inside a 2xx.
		$row = is_array( $response->body ) ? (array) ( $response->body['data'][0] ?? array() ) : array();
		if ( $response->ok && 'success' === ( $row['status'] ?? '' ) ) {
			$id = sanitize_text_field( (string) ( $row['details']['id'] ?? '' ) );
			return Result::success(
				'' !== $id
					/* translators: 1: record type, e.g. Lead, 2: Zoho record id. */
					? sprintf( __( '%1$s created in Zoho CRM (ID %2$s).', 'wpintegrations-connector-for-zoho-crm' ), $this->record_label(), $id )
					/* translators: %s: record type, e.g. Lead. */
					: sprintf( __( '%s created in Zoho CRM.', 'wpintegrations-connector-for-zoho-crm' ), $this->record_label() ),
				$request,
				$logged
			);
		}

		return Result::failure( $this->error_message( $response ), $request, $logged );
	}

	/**
	 * Zoho's own reason, naming the offending field where it gives one.
	 *
	 * @param object $response Normalized response.
	 */
	protected function error_message( object $response ): string {
		if ( 0 === $response->code ) {
			/* translators: %s: transport error message. */
			return sprintf( __( 'Could not reach Zoho CRM: %s', 'wpintegrations-connector-for-zoho-crm' ), $response->error );
		}

		$body = is_array( $response->body ) ? $response->body : array();
		// Record-level errors sit in data[0]; request-level ones at the top.
		$error = isset( $body['data'][0] ) && is_array( $body['data'][0] ) ? $body['data'][0] : $body;
		$code  = sanitize_text_field( (string) ( $error['code'] ?? '' ) );
		$text  = sanitize_text_field( (string) ( $error['message'] ?? '' ) );
		$field = sanitize_text_field( (string) ( $error['details']['api_name'] ?? '' ) );

		if ( 'OAUTH_SCOPE_MISMATCH' === $code ) {
			return __( 'The Zoho connection lacks a permission this needs. Reconnect Zoho CRM under Settings.', 'wpintegrations-connector-for-zoho-crm' );
		}
		if ( 'INVALID_TOKEN' === $code || 401 === $response->code ) {
			return __( 'Zoho CRM no longer accepts this connection. Reconnect it under Settings.', 'wpintegrations-connector-for-zoho-crm' );
		}
		if ( 'DUPLICATE_DATA' === $code ) {
			/* translators: %s: Zoho field API name. */
			return sprintf( __( 'Zoho CRM already has a record with this %s, and that field must be unique. Nothing was created.', 'wpintegrations-connector-for-zoho-crm' ), '' !== $field ? $field : __( 'value', 'wpintegrations-connector-for-zoho-crm' ) );
		}
		if ( 'MANDATORY_NOT_FOUND' === $code && '' !== $field ) {
			/* translators: %s: Zoho field API name. */
			return sprintf( __( 'Zoho CRM requires the field %s, which this integration does not send.', 'wpintegrations-connector-for-zoho-crm' ), $field );
		}

		$detail = trim( $code . ( '' !== $text ? ': ' . $text : '' ) . ( '' !== $field ? ' (' . $field . ')' : '' ) );
		if ( '' === $detail ) {
			$detail = mb_substr( is_array( $response->body ) ? (string) wp_json_encode( $response->body ) : (string) $response->body, 0, 300 );
		}
		/* translators: 1: HTTP status code, 2: error detail from Zoho. */
		return sprintf( __( 'Zoho CRM error (HTTP %1$d): %2$s', 'wpintegrations-connector-for-zoho-crm' ), $response->code, $detail );
	}
}
