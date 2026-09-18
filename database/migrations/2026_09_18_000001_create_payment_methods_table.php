<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-configured payment methods. Replaces the hard-coded
 * cash|card|bank|mobile|insurance enum that previously lived in the
 * BillingController validation rules and the SPA dropdowns: each clinic now
 * owns its own method list, and payment validation resolves against THIS
 * table (active rows only) instead of a fixed vocabulary.
 *
 * The invoice_payments.method column keeps storing the method CODE, so
 * legacy payment rows remain valid once TenantBootstrap has seeded the
 * five original codes for every tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->index();   // stable wire format (invoice_payments.method)
            $table->string('name', 80);            // display label, tenant-editable
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['business_id', 'code']);
        });

        // Bootstrap strategy for EXISTING tenants: production deploys run
        // `migrate --force` but not the seeder, so payment validation against
        // the tenant catalog must succeed immediately after this migration.
        // Idempotent insert-if-missing (unique business_id + code) — never
        // duplicates, never touches methods a tenant later configured.
        $methods = [
            ['code' => 'cash', 'name' => 'Cash (Counter Drawer)', 'sort_order' => 1],
            ['code' => 'card', 'name' => 'Credit / Debit Card (POS)', 'sort_order' => 2],
            ['code' => 'bank', 'name' => 'Bank Transfer / Raast QR', 'sort_order' => 3],
            ['code' => 'mobile', 'name' => 'Mobile Wallet (Easypaisa / JazzCash)', 'sort_order' => 4],
            ['code' => 'insurance', 'name' => 'Insurance / Corporate Panel', 'sort_order' => 5],
        ];

        foreach (\Illuminate\Support\Facades\DB::table('businesses')->pluck('id') as $businessId) {
            foreach ($methods as $m) {
                \App\Models\PaymentMethod::withTrashed()->updateOrCreate(
                    ['code' => $m['code'], 'business_id' => $businessId],
                    $m
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
