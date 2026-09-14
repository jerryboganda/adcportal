<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Charter §25/§51: clinical document identifiers are unique WITHIN a tenant,
 * not globally. The old global uniques made tenant B's first invoice/MRN
 * collide with tenant A's — a genuine cross-tenant defect. Generators were
 * already tenant-scoped; only the constraints were wrong. Existing data
 * cannot violate the new composite uniques (per-tenant values were already
 * collision-free).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_invoice_number_unique');
            $table->unique(['business_id', 'invoice_number']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_mrn_unique');
            $table->unique(['business_id', 'mrn']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'invoice_number']);
            $table->unique('invoice_number');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'mrn']);
            $table->unique('mrn');
        });
    }
};
