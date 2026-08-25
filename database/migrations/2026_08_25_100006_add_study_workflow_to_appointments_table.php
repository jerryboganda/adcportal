<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Radiology study lifecycle: booked|checked_in|preparing|in_progress|
            // acquired|reading|reported|delivered|cancelled|no_show
            $table->string('workflow_state', 20)->default('booked')->after('appointment_status')->index();
            $table->string('priority', 10)->default('routine')->after('workflow_state'); // routine|urgent|stat

            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('preparing_at')->nullable();
            $table->timestamp('in_progress_at')->nullable();
            $table->timestamp('acquired_at')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->unsignedBigInteger('assigned_radiologist_id')->nullable()->index();
            $table->unsignedBigInteger('performed_by_staff_id')->nullable();

            $table->boolean('screening_required')->default(false);
            $table->boolean('screening_cleared')->default(false);
            $table->text('cancel_reason')->nullable();
        });

        // Fast worklist queries.
        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['workflow_state', 'date_sort'], 'appointments_state_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_state_date_idx');
            $table->dropColumn([
                'workflow_state', 'priority',
                'checked_in_at', 'preparing_at', 'in_progress_at', 'acquired_at', 'reported_at', 'delivered_at',
                'assigned_radiologist_id', 'performed_by_staff_id',
                'screening_required', 'screening_cleared', 'cancel_reason',
            ]);
        });
    }
};
