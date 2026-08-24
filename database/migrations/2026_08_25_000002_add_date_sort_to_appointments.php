<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a sortable, indexable mirror of the string `date` column.
 * `date` stays 'd-m-Y' (display format, 40+ call sites), while `date_sort`
 * ('Y-m-d') enables indexed range queries — the calendar previously used
 * whereRaw(STR_TO_DATE(...)) which forces a full table scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'date_sort')) {
                $table->date('date_sort')->nullable()->after('date');
                $table->index('date_sort', 'appointments_date_sort_index');
            }
        });

        // Backfill existing rows ('d-m-Y' -> 'Y-m-d')
        $rows = DB::table('appointments')->whereNull('date_sort')->select('id', 'date')->get();
        foreach ($rows as $row) {
            $sort = null;
            foreach (['d-m-Y', 'Y-m-d', 'd/m/Y'] as $fmt) {
                try {
                    $sort = \Carbon\Carbon::createFromFormat($fmt, $row->date)->format('Y-m-d');
                    break;
                } catch (Throwable $e) {
                    continue;
                }
            }
            if ($sort) {
                DB::table('appointments')->where('id', $row->id)->update(['date_sort' => $sort]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_date_sort_index');
            $table->dropColumn('date_sort');
        });
    }
};
