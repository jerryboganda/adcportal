<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refund ledger for the money path: a refund is stored as a NEGATIVE payment
 * row pointing at the collection it reverses, so paid_total/status stay one
 * consistent derivation (Σ amount) with no new concept. `refunded_at` on the
 * collection row marks when money went back (null = nothing returned yet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('refunds_payment_id')->nullable()->after('created_by');
            $table->timestamp('refunded_at')->nullable()->after('refunds_payment_id');

            $table->index('refunds_payment_id');
            $table->index(['business_id', 'paid_at'], 'invoice_payments_business_paid_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropIndex('invoice_payments_business_paid_idx');
            $table->dropIndex(['refunds_payment_id']);
            $table->dropColumn(['refunds_payment_id', 'refunded_at']);
        });
    }
};
