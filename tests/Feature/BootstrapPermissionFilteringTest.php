<?php

namespace Tests\Feature;

/**
 * The data plane must follow the permission plane: /bootstrap only ships the
 * collections a session is authorized to act on. A radiologist never receives
 * invoices, the staff directory, DICOM node configuration or audit logs; only
 * `user logs history` holders receive audit trails.
 */
class BootstrapPermissionFilteringTest extends ApiTestCase
{
    public function test_radiologist_bootstrap_excludes_financial_and_administrative_data(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $payload = $this->actingAs($radiologist)
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->json('data');

        // Clinical context a radiologist legitimately needs:
        $this->assertArrayHasKey('studies', $payload);
        $this->assertArrayHasKey('patients', $payload);
        $this->assertArrayHasKey('templates', $payload);

        // Data the role has no authorization for:
        $this->assertArrayNotHasKey('invoices', $payload, 'radiologist received invoices');
        $this->assertArrayNotHasKey('staff', $payload, 'radiologist received the staff directory');
        $this->assertArrayNotHasKey('auditLogs', $payload, 'radiologist received audit logs');
        $this->assertArrayNotHasKey('dicomNodes', $payload, 'radiologist received DICOM node config');
        $this->assertArrayNotHasKey('notificationTemplates', $payload, 'radiologist received gateway templates');
        $this->assertArrayNotHasKey('paymentMethods', $payload, 'radiologist received payment methods');
    }

    public function test_admin_bootstrap_contains_the_full_operational_payload(): void
    {
        $payload = $this->actingAs($this->adminA)
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->json('data');

        foreach (['studies', 'invoices', 'patients', 'staff', 'auditLogs', 'dicomNodes', 'notificationTemplates', 'inventoryItems', 'modalities', 'services', 'referrers', 'screeningForms', 'templates'] as $key) {
            $this->assertArrayHasKey($key, $payload, "admin bootstrap missing {$key}");
        }
    }

    public function test_billing_bootstrap_has_invoices_but_not_clinical_staff_data(): void
    {
        $billing = $this->makeStaff($this->businessA, $this->adminA, 'billing');

        $payload = $this->actingAs($billing)
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('invoices', $payload);
        $this->assertArrayHasKey('patients', $payload);
        $this->assertArrayNotHasKey('staff', $payload);
        $this->assertArrayNotHasKey('auditLogs', $payload);
        $this->assertArrayNotHasKey('dicomNodes', $payload);
    }

    public function test_receptionist_bootstrap_has_reception_data_but_no_audit_trail(): void
    {
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $payload = $this->actingAs($receptionist)
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('studies', $payload);
        $this->assertArrayHasKey('screeningForms', $payload);
        $this->assertArrayHasKey('paymentMethods', $payload);
        $this->assertArrayNotHasKey('auditLogs', $payload);
        $this->assertArrayNotHasKey('dicomNodes', $payload);
        $this->assertArrayNotHasKey('staff', $payload);
    }

    public function test_current_user_payload_carries_permissions_and_version(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $user = $this->actingAs($radiologist)->getJson('/api/v1/me')->assertOk()->json('data.user');

        $this->assertIsArray($user['permissions']);
        $this->assertContains('report sign', $user['permissions']);
        $this->assertNotContains('invoice manage', $user['permissions']);
        $this->assertGreaterThanOrEqual(1, $user['permissionsVersion']);
    }
}
