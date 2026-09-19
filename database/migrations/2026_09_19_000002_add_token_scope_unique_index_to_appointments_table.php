<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Database-level guard for the study-token invariant: within one tenant and
 * one scheduled day, a token number may exist at most once.
 *
 * The allocator (StudyTokenAllocator) is the only writer and is race-free,
 * but an invariant that matters this much — two patients answering to the
 * same token — deserves a constraint that holds even if a future code path
 * forgets to use the allocator.
 *
 * Pre-existing duplicates are REPORTED, not silently rewritten: the index is
 * skipped and the finding is logged so an operator can reconcile the history
 * deliberately.
 */
return new class extends Migration
{
    private const INDEX = 'appointments_token_scope_unique';

    public function up(): void
    {
        if (! Schema::hasTable('appointments') || Schema::hasIndex('appointments', self::INDEX)) {
            return;
        }

        $duplicates = DB::table('appointments')
            ->select('business_id', 'date_sort', 'token_number')
            ->selectRaw('count(*) as occurrences')
            ->whereNotNull('token_number')
            ->whereNotNull('date_sort')
            ->groupBy('business_id', 'date_sort', 'token_number')
            ->havingRaw('count(*) > 1')
            ->limit(5)
            ->get();

        if ($duplicates->isNotEmpty()) {
            Log::warning('Study token duplicates found in appointments; unique guard index was not created.', [
                'index' => self::INDEX,
                'duplicates' => $duplicates->map(fn ($row) => [
                    'business_id' => $row->business_id,
                    'date' => $row->date_sort,
                    'token_number' => $row->token_number,
                    'occurrences' => (int) $row->occurrences,
                ])->all(),
            ]);

            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->unique(['business_id', 'date_sort', 'token_number'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('appointments') && Schema::hasIndex('appointments', self::INDEX)) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropUnique(self::INDEX);
            });
        }
    }
};
