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
        if(!Schema::hasTable('users'))
        {
            Schema::create('users', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('email');
                $table->string('mobile_no')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password')->nullable();
                $table->rememberToken();
                $table->string('type')->default('admin');
                $table->boolean('active_status')->default(false);
                $table->integer('active_business')->default(0);
                $table->string('avatar')->default('uploads/users-avatar/avatar.png');
                $table->boolean('dark_mode')->default(false);
                $table->string('lang', 191)->default('en');
                $table->string('messenger_color')->default('#2180f3');
                $table->integer('seeder_run')->default(0);
                $table->integer('is_enable_login')->default(1);
                $table->integer('is_disable')->default(1);
                $table->integer('business_id')->default(0);
                $table->integer('created_by')->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
