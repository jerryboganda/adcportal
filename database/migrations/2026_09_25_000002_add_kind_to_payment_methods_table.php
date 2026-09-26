<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payment methods gain a KIND.
 *
 * `code` is the immutable wire format, which is correct — a tenant may name its
 * methods anything. But two things genuinely need to know what a method *is*, not
 * what it is called:
 *
 *   - `ShiftLedgerService::cashExpected()` asks "which method is the drawer?" to
 *     put the right figure on a shift-closing financial document;
 *   - the counter asks the same question to offer the cash-change calculator.
 *
 * Both hardcoded the string `'cash'`, so a tenant whose methods were coded
 * `currency`, `jazzcash`, `bank_transfer` got a cash total of zero on a signed
 * statement. A name-based guess would only move the breakage, so the tenant says
 * what each method is.
 *
 * Backfilled from the seeded convention so every existing tenant keeps the
 * behaviour it has today without anyone having to open a screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('kind', 12)->default('other')->after('code');
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->index(['business_id', 'kind'], 'payment_methods_business_kind_index');
        });

        // The seeded convention, matched case-insensitively against the code.
        foreach (['cash' => 'cash', 'card' => 'card', 'mobile' => 'digital', 'bank' => 'digital', 'insurance' => 'insurance'] as $code => $kind) {
            DB::table('payment_methods')
                ->whereRaw('lower(code) = ?', [$code])
                ->update(['kind' => $kind]);
        }
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropIndex('payment_methods_business_kind_index');
            $table->dropColumn('kind');
        });
    }
};
