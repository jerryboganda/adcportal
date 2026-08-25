<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('plans')) { return; } // dump-only table
        if(!Schema::hasColumn('plans', 'is_enabled'))
        {
            Schema::table('plans', function (Blueprint $table) {
                if (!Schema::hasColumn('plans', 'is_enabled')) {
                    $table->integer('is_enabled')->default(1)->after('trial_days');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('is_enabled');
        });
    }
};
