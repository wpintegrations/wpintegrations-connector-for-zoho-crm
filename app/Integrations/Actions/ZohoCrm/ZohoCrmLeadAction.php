<?php
/**
 * Creates a Zoho CRM Lead from each submission.
 *
 * @package WPIntegrations\ZohoCrmConnector
 */

namespace WPIntegrations\ZohoCrmConnector\Integrations\Actions\ZohoCrm;

defined( 'ABSPATH' ) || exit;

final class ZohoCrmLeadAction extends ZohoCrmRecordAction {

	public function key(): string {
		return 'zoho_crm_lead';
	}

	public function label(): string {
		return __( 'Zoho CRM Lead', 'wpintegrations-connector-for-zoho-crm' );
	}

	protected function module(): string {
		return 'Leads';
	}

	protected function record_label(): string {
		return __( 'Lead', 'wpintegrations-connector-for-zoho-crm' );
	}

	public function target_fields(): array {
		return array_merge(
			$this->person_fields(),
			array(
				array(
					'key'   => 'Company',
					'label' => __( 'Company', 'wpintegrations-connector-for-zoho-crm' ),
				),
			),
			$this->closing_fields()
		);
	}
}
