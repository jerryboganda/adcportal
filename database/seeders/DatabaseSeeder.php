<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Platform bootstrap (SaaS):
 *  1. plans catalog
 *  2. platform super admin
 *  3. demo tenant with fake clinical data — ONLY when RIS_DEMO_MODE=true
 *    (local development / CI e2e; never in production)
 *
 * The legacy single-clinic seeders were removed; TenantBootstrap provisions
 * each clinic tenant (real signups and demo alike).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // `db:seed` runs inside Model::unguarded() (Laravel SeedCommand) — restore
        // mass-assignment protection for everything this seeder (and its children) do.
        Model::reguard();

        $this->call(NotificationsTableSeeder::class);
        $this->call(EmailTemplates::class);
        $this->call(LanguageTableSeeder::class);

        $this->seedPlans();

        $superAdminEmail = config('ris.super_admin_email');
        if (! $superAdminEmail) {
            throw new \RuntimeException(
                'RIS_SUPER_ADMIN_EMAIL is not set. Refusing to seed without a platform super admin identity.'
            );
        }

        $superAdmin = User::updateOrCreate(
            ['email' => $superAdminEmail],
            [
                'name' => 'Platform Administrator',
                'password' => Hash::make(config('ris.super_admin_password') ?? Str::password(24)),
                'email_verified_at' => now(),
                'type' => 'super_admin',
                'active_status' => 1,
                'business_id' => 0,
                'created_by' => 0,
                'department' => 'Platform Operations',
            ]
        );

        // Demo tenant with a fully-populated clinic. Only for local development
        // and the CI e2e job — requires RIS_DEMO_MODE=true and never runs in
        // production (config/ris.php defaults it to false).
        if (config('ris.demo_mode')) {
            $demoCode = config('ris.demo_tenant_code');
            $demoEmail = config('ris.demo_admin_email');
            $demoPassword = config('ris.demo_password');

            if (! $demoEmail || ! $demoPassword) {
                throw new \RuntimeException(
                    'RIS_DEMO_MODE=true requires RIS_DEMO_ADMIN_EMAIL and RIS_DEMO_PASSWORD.'
                );
            }

            $existing = Business::where('tenant_code', $demoCode)->first();

            if (! $existing) {
                // Owner first - businesses.created_by references users.
                $admin = User::updateOrCreate(
                    ['email' => $demoEmail],
                    [
                        'name' => 'Muhammad Farhan (CFO / Admin)',
                        'password' => Hash::make($demoPassword),
                        'email_verified_at' => now(),
                        'type' => 'admin',
                        'active_status' => 1,
                        'lang' => 'en',
                        'department' => 'Executive Administration',
                        'initials' => 'MF',
                        'capabilities' => [
                            'canSignReports' => true,
                            'canVoidInvoices' => true,
                            'canOverrideScreening' => true,
                            'canEditMasters' => true,
                            'canAccessPacs' => true,
                        ],
                    ]
                );

                $business = Business::firstOrCreate(
                    ['tenant_code' => $demoCode],
                    [
                        'name' => 'PolytronX - RIS',
                        'form_type' => 'form-layout',
                        'layouts' => 'Formlayout11',
                        'theme_color' => 'color1-Formlayout11',
                        'plan_id' => Plan::where('slug', 'professional')->value('id'),
                        'subscription_status' => 'active',
                        'subscription_ends_at' => now()->addYear(),
                        'created_by' => $admin->id,
                    ]
                );

                $admin->forceFill([
                    'active_business' => $business->id,
                    'business_id' => $business->id,
                    'created_by' => $business->id,
                ])->save();

                $admin->MakeRole();
                app(TenantBootstrap::class)->run($business, $admin);
                app(RisDemoData::class)->run($business, $admin);
            }
        }

        // Legacy platform settings need an admin user to exist.
        $this->call(DefultSetting::class);
    }

    private function seedPlans(): void
    {
        $plans = [
            ['Starter', 'starter', 'Single-location clinic getting started with digital RIS.', 7500, 14, 6, 300],
            ['Professional', 'professional', 'Full imaging workflow: reporting, billing, inventory and analytics.', 15000, 14, null, null],
            ['Enterprise', 'enterprise', 'Multi-site diagnostics with PACS networking and priority support.', 35000, 30, null, null],
        ];

        foreach ($plans as [$name, $slug, $description, $price, $trial, $maxUsers, $maxStudies]) {
            Plan::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => $description,
                    'price_monthly' => $price,
                    'currency' => 'PKR',
                    'trial_days' => $trial,
                    'max_users' => $maxUsers,
                    'max_studies_per_month' => $maxStudies,
                    'is_active' => true,
                ]
            );
        }
    }
}
