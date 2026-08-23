<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ConvertToSingleClinic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clinic:convert-to-single
                            {--name= : Optional new name for THE clinic}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert an existing multi-business database to a single-clinic setup (run AFTER the drop_saas_architecture migration)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Converting database to single-clinic setup...');

        // 1. Ensure exactly one business exists.
        $businesses = DB::table('businesses')->orderBy('id')->get();

        if ($businesses->isEmpty()) {
            $this->error('No business record found. Run migrations + seeders first.');
            return 1;
        }

        $keep = $businesses->first();
        $name = $this->option('name');
        if ($name) {
            DB::table('businesses')->where('id', $keep->id)->update(['name' => $name]);
        }
        // Re-point any stray records to THE clinic (should already be correct).
        foreach ($businesses->skip(1) as $extra) {
            foreach (['appointments', 'services', 'staff', 'customers', 'categories', 'locations',
                      'business_hours', 'business_holidays', 'custom_statuses', 'files', 'custom_fields',
                      'theme_settings', 'contact_us', 'blogs', 'testimonials', 'subscribes',
                      'appointment_payments', 'notifications', 'email_templates', 'referrers'] as $table) {
                if (DB::getSchemaBuilder()->hasTable($table) && DB::getSchemaBuilder()->hasColumn($table, 'business_id')) {
                    DB::table($table)->where('business_id', $extra->id)->update(['business_id' => $keep->id]);
                }
            }
        }
        if ($businesses->count() > 1) {
            SchemalessDeleteExtraBusinesses($businesses);
        }

        $this->info("Clinic: {$keep->name} (id={$keep->id})");

        // 2. Normalize users.
        $admin = DB::table('users')->where('type', 'admin')->orderBy('id')->first();
        if (!$admin) {
            // Fall back to first company user (migration should have promoted one).
            $admin = DB::table('users')->where('type', 'company')->orderBy('id')->first();
            if ($admin) {
                DB::table('users')->where('id', $admin->id)->update(['type' => 'admin']);
            } else {
                $admin = DB::table('users')->orderBy('id')->first();
                if ($admin) {
                    DB::table('users')->where('id', $admin->id)->update(['type' => 'admin']);
                }
            }
        }

        if ($admin) {
            // All clinic users belong to the admin and THE business.
            DB::table('users')
                ->where('id', '!=', $admin->id)
                ->whereIn('type', ['company', 'super admin'])
                ->delete();

            DB::table('users')->where('id', '!=', $admin->id)->update([
                'created_by' => $admin->id,
                'business_id' => $keep->id,
                'active_business' => $keep->id,
            ]);
            DB::table('users')->where('id', $admin->id)->update([
                'business_id' => $keep->id,
                'active_business' => $keep->id,
            ]);
            $this->info("Admin user: {$admin->email}");
        }

        // 3. Normalize settings: dedupe by key (keep latest), all owned by admin,
        //    system settings at business=0, clinic settings at THE business id.
        if ($admin) {
            $settings = DB::table('settings')->orderBy('updated_at', 'desc')->get();
            $seen = [];
            $deleteIds = [];
            foreach ($settings as $setting) {
                $key = $setting->key . '|' . $setting->business;
                if (isset($seen[$key])) {
                    $deleteIds[] = $setting->id;
                } else {
                    $seen[$key] = true;
                    if ($setting->created_by != $admin->id) {
                        DB::table('settings')->where('id', $setting->id)->update(['created_by' => $admin->id]);
                    }
                }
            }
            if (!empty($deleteIds)) {
                DB::table('settings')->whereIn('id', $deleteIds)->delete();
                $this->info('Removed ' . count($deleteIds) . ' duplicate settings.');
            }
        }

        // 4. Flush every cache used by the app.
        Cache::flush();
        $this->info('Cache flushed.');

        $this->info('Done! Single-clinic conversion complete.');
        return 0;
    }
}

/**
 * Delete extra businesses after re-pointing their records.
 */
function SchemalessDeleteExtraBusinesses($businesses): void
{
    $ids = $businesses->skip(1)->pluck('id')->toArray();
    DB::table('businesses')->whereIn('id', $ids)->delete();
}
