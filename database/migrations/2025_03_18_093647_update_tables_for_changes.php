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
        // PERF_GUARD: dump-only tables may not exist on fresh installs.
        foreach (['plans', 'add_ons', 'bank_transfer_payments', 'businesses', 'users', 'services', 'locations', 'staff', 'customers', 'appointments', 'appointment_payments', 'settings', 'categories', 'business_hours'] as $__t) {
            if (!Schema::hasTable($__t)) { return; }
        }
        if(Schema::hasColumn('login_details', 'business'))
        {
            Schema::table('login_details', function (Blueprint $table) {
                $table->unsignedBigInteger('business')->nullable()->change();
            });
        }

        if(Schema::hasTable('bank_transfer_payments'))
        {
            Schema::table('bank_transfer_payments', function (Blueprint $table) {
                if(Schema::hasColumn('bank_transfer_payments','workspace')){
                    $table->renameColumn('workspace', 'business')->default(0);
                }
            });
        }

        if(Schema::hasTable('services'))
        {
            Schema::table('services', function (Blueprint $table) {
                $table->string('price')->nullable()->change();
            });
        }

        if(Schema::hasColumn('locations', 'description'))
        {
            Schema::table('locations', function (Blueprint $table) {
                $table->longText('description')->nullable()->change();
            });
        }

        if(Schema::hasColumn('services', 'description'))
        {
            Schema::table('services', function (Blueprint $table) {
                $table->longText('description')->nullable()->change();
            });
        }

        if(Schema::hasColumn('staff', 'description'))
        {
            Schema::table('staff', function (Blueprint $table) {
                $table->longText('description')->nullable()->change();
            });
        }

        if(Schema::hasColumn('appointment_payments', 'appointment_id'))
        {
            Schema::table('appointment_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('appointment_id')->nullable()->change();
            });
        }

        if(Schema::hasColumn('appointments', 'notes'))
        {
            Schema::table('appointments', function (Blueprint $table) {
                $table->longText('notes')->nullable()->change();
            });
        }

        if(Schema::hasColumn('customers', 'description'))
        {
            Schema::table('customers', function (Blueprint $table) {
                $table->longText('description')->nullable()->change();
            });
        }

        if(Schema::hasColumn('contact_us', 'description'))
        {
            Schema::table('contact_us', function (Blueprint $table) {
                $table->longText('description')->nullable()->change();
            });
        }

        if(Schema::hasColumn('files', 'label'))
        {
            Schema::table('files', function (Blueprint $table) {
                $table->string('label')->nullable()->change();
            });
        }

        if(Schema::hasColumn('testimonials', 'description'))
        {
            Schema::table('testimonials', function (Blueprint $table) {
                $table->longText('description')->change();
            });
        }

        if(Schema::hasColumn('blogs', 'description'))
        {
            Schema::table('blogs', function (Blueprint $table) {
                $table->longText('description')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if(Schema::hasColumn('login_details', 'business'))
        {
            Schema::table('login_details', function (Blueprint $table) {
                $table->unsignedBigInteger('business')->nullable(false)->change();
            });
        }

        Schema::table('bank_transfer_payments', function (Blueprint $table) {
            $table->renameColumn('workspace', 'business')->default(0);
        });

        if(Schema::hasTable('services'))
        {
            Schema::table('services', function (Blueprint $table) {
                $table->string('price')->nullable(false)->change();
            });
        }

        if(Schema::hasColumn('locations', 'description'))
        {
            Schema::table('locations', function (Blueprint $table) {
                $table->string('description')->nullable();
            });
        }

        if(Schema::hasColumn('services', 'description'))
        {
            Schema::table('services', function (Blueprint $table) {
                $table->string('description')->nullable();
            });
        }

        if(Schema::hasColumn('staff', 'description'))
        {
            Schema::table('staff', function (Blueprint $table) {
                $table->string('description')->nullable();
            });
        }

        if(Schema::hasColumn('appointment_payments', 'appointment_id'))
        {
            Schema::table('appointment_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('appointment_id')->nullable()->change();
            });
        }
    }
};
