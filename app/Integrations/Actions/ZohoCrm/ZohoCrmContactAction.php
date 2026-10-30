<?php
/**
 * Creates a Zoho CRM Contact from each submission.
 *
 * @package WPIntegrations\ZohoCrmConnector
 */

namespace WPIntegrations\ZohoCrmConnector\Integrations\Actions\ZohoCrm;

defined( 'ABSPATH' ) || exit;

final class ZohoCrmContactAction extends ZohoCrmRecordAction {

	public function key(): string {
		return 'zoho_crm_contact';
	}

	public function label(): string {
		return __( 'Zoho CRM Contact', 'wpintegrations-connector-for-zoho-crm' );
	}

	protected function module(): string {
		return 'Contacts';
	}

	protected function record_label(): string {
		return __( 'Contact', 'wpintegrations-connector-for-zoho-crm' );
	}

	public function target_fields(): array {
		return array_merge( $this->person_fields(), $this->closing_fields() );
	}
}
