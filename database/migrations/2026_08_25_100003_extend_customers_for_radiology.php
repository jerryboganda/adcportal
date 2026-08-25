<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('mrn')->nullable()->after('id')->unique();      // Medical Record Number
            $table->string('cnic', 20)->nullable()->after('dob');          // national ID
            $table->string('blood_group', 8)->nullable();
            $table->text('allergies')->nullable();
            $table->text('chronic_conditions')->nullable();
            $table->string('emergency_contact', 30)->nullable();
        });

        // Issue sequential MRNs to any pre-existing patient rows.
        \Illuminate\Support\Facades\DB::statement(
            "UPDATE customers SET mrn = 'MRN-' || printf('%06d', id) WHERE mrn IS NULL"
        );
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'mrn', 'cnic', 'blood_group', 'allergies', 'chronic_conditions', 'emergency_contact',
            ]);
        });
    }
};
