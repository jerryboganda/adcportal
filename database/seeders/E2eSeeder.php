<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\BusinessHours;
use App\Models\Category;
use App\Models\Location;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds the minimal bookable clinic used by the Playwright e2e suite:
 * enabled business on Formlayout11, one category/service/staff/location,
 * and a fully-open week of business hours so time slots generate.
 */
class E2eSeeder extends Seeder
{
    public function run(): void
    {
        if (! User::where('type', 'admin')->exists()) {
            // Base chain — MakeRole() grants permissions, so they must exist first.
            $this->call(PermissionTableSeeder::class);
            $this->call(UserSeeder::class);
            $this->call(DefultSetting::class);
        }

        $admin = User::where('type', 'admin')->first();

        $business = Business::first();
        if (! $business) {
            $this->call(UserSeeder::class);
            $business = Business::first();
        }
        if (! $business || ! $admin) {
            return;
        }

        $business->is_disable = 0;
        $business->layouts = 'Formlayout11';
        $business->theme_color = 'color1-Formlayout11';
        $business->save();

        $category = Category::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'Radiology'],
            ['created_by' => $admin->id]
        );

        $location = Location::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'Main Centre'],
            [
                'address' => 'Test Address, Gujranwala',
                'phone' => '+923001234567',
                'description' => 'E2E testing location.',
                'created_by' => $admin->id,
            ]
        );

        $service = Service::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'X-Ray Chest'],
            [
                'category_id' => $category->id,
                'price' => 1500,
                'duration' => 15,
                'duration_minutes' => 15,
                'is_free' => 0,
                'is_bookable_online' => 1,
                'requires_screening' => 0,
                'created_by' => $admin->id,
            ]
        );

        Staff::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'Dr E2E Radiologist'],
            [
                'location_id' => (string) $location->id,
                'service_id' => (string) $service->id,
                'description' => 'Automated test radiologist.',
                'created_by' => $admin->id,
            ]
        );

        foreach (BusinessHours::$weekdays as $day) {
            BusinessHours::updateOrCreate(
                [
                    'business_id' => $business->id,
                    'created_by' => $admin->id,
                    'day_name' => $day,
                ],
                [
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                    'day_off' => 'off',
                    'break_hours' => null,
                ]
            );
        }
    }
}
