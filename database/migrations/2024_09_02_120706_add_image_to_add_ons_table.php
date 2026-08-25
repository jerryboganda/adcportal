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
        if (!Schema::hasTable('add_ons')) { return; } // dump-only table
        if(!Schema::hasColumn('add_ons', 'image','is_enable','package_name'))
        {
            Schema::table('add_ons', function (Blueprint $table) {
                $table->string('image')->nullable()->after('yearly_price');
                $table->boolean('is_enable')->default(0)->after('image');
                $table->string('package_name')->nullable()->after('is_enable');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('add_ons', function (Blueprint $table) {
            //
        });
    }
};
