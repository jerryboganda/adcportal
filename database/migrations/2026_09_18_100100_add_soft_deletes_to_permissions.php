<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft deletes on the permission catalog. `PermissionCatalog::sync()`
 * restores previously deleted permission rows instead of colliding with the
 * unique `name` index — that requires a real deleted_at column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('permissions', 'deleted_at')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('permissions', 'deleted_at')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
