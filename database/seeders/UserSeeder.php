<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Business;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the single admin user + THE clinic record.
     *
     * @return void
     */
    public function run()
    {
        // Single-clinic app: one admin user, one clinic. No super admin / company split.
        $admin = User::where('type', 'admin')->first();
        if (empty($admin)) {
            $admin = new User();
            $admin->name = 'Admin';
            $admin->email = 'admin@example.com';
            $admin->password = Hash::make('1234');
            $admin->email_verified_at = date('Y-m-d H:i:s');
            $admin->type = 'admin';
            $admin->active_status = 1;
            $admin->avatar = 'uploads/users-avatar/avatar.png';
            $admin->dark_mode = 0;
            $admin->lang = 'en';
            $admin->save();
        }

        $business = Business::first();
        if (empty($business)) {
            $business = new Business();
            $business->name = 'ADC Clinic';
            $business->slug = 'adc-clinic';
            $business->form_type = 'form-layout';
            $business->layouts = 'Formlayout1';
            $business->theme_color = 'color1-Formlayout1';
            $business->created_by = $admin->id;
            $business->is_disable = 1;
            $business->save();
        }

        // Create the clinic-level roles (manager / staff / customer).
        $admin->MakeRole();

        // Seed the clinic's default settings.
        User::CompanySetting($admin->id);
    }
}
