<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\InventoryItem;

class InventoryAndAuthTest extends ApiTestCase
{
    public function test_stock_movements_are_server_authoritative(): void
    {
        $tech = $this->makeStaff($this->businessA, $this->adminA, 'technologist');
        $item = InventoryItem::where('code', 'CT-ULTRA-370-100')->where('business_id', $this->businessA->id)->firstOrFail();
        $before = $item->current_stock;

        // stock in
        $response = $this->actingAs($tech)->postJson('/api/v1/inventory/transactions', [
            'itemId' => $item->id,
            'type' => 'stock_in',
            'quantity' => 5,
            'batchNumber' => 'NEW-BATCH-1',
            'notes' => 'CI regression',
        ])->assertCreated();

        $this->assertSame($before + 5, $response->json('data.item.currentStock'));

        // wastage out
        $response = $this->actingAs($tech)->postJson('/api/v1/inventory/transactions', [
            'itemId' => $item->id,
            'type' => 'wastage',
            'quantity' => 2,
        ])->assertCreated();

        $this->assertSame($before + 3, $response->json('data.item.currentStock'));
    }

    public function test_low_stock_crossing_creates_notification(): void
    {
        $item = InventoryItem::where('code', 'CT-VISI-320-100')->where('business_id', $this->businessA->id)->firstOrFail();
        $item->forceFill(['min_threshold' => 100, 'current_stock' => 102])->save();

        $tech = $this->makeStaff($this->businessA, $this->adminA, 'technologist');
        $this->actingAs($tech)->postJson('/api/v1/inventory/transactions', [
            'itemId' => $item->id,
            'type' => 'usage_study',
            'quantity' => 5,
        ])->assertCreated();

        $this->assertTrue(
            AppNotification::where('business_id', $this->businessA->id)
                ->where('title', 'like', '%Low Stock%')
                ->where('title', 'like', '%'.$item->name.'%')
                ->exists()
        );
    }

    public function test_new_clinic_registration_provisions_a_working_tenant(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'clinic_name' => 'Gamma Scan Centre',
            'name' => 'Dr. Gamma',
            'email' => 'gamma@test.local',
            'password' => 'Gamma#2026ok',
        ])->assertCreated();

        $this->assertEquals('admin', $response->json('data.user.role'));

        // The new tenant admin immediately sees seeded masters.
        $bootstrap = $this->getJson('/api/v1/bootstrap')->assertOk();
        $this->assertCount(5, $bootstrap->json('data.modalities'));
        $this->assertGreaterThanOrEqual(8, count($bootstrap->json('data.services')));
        $this->assertSame('trialing', $response->json('data.user.subscriptionStatus') ?? null);
    }

    public function test_login_logout_flow(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => $this->adminA->email,
            'password' => 'wrong-password',
        ])->assertStatus(401);

        $this->postJson('/api/v1/login', [
            'email' => $this->adminA->email,
            'password' => 'R1s!T3st#2026x',
        ])->assertOk()->assertJsonPath('data.user.email', $this->adminA->email);

        $this->postJson('/api/v1/logout')->assertOk();
    }
}
