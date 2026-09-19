<?php

use App\Support\BodyRegion;
use App\Support\ReportMacroLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data backfill for the reporting upgrade.
 *
 * Purely additive: no existing clinical record is rewritten. Without it,
 * clinics provisioned before this release would resolve every study through
 * the modality tier only (their services/templates have no body region) and
 * would have an empty macro library.
 *
 * Deliberately written against the query builder rather than Eloquent models:
 * a migration must keep working when the models evolve.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- 1) Procedure body regions (drives the region resolution tier) ----
        DB::table('services')
            ->select('id', 'code', 'name')
            ->where(fn ($q) => $q->whereNull('body_region')->orWhere('body_region', ''))
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $region = BodyRegion::classify($row->code, $row->name);

                    if ($region !== null) {
                        DB::table('services')->where('id', $row->id)->update(['body_region' => $region]);
                    }
                }
            });

        // ---- 2) Template matching dimensions ---------------------------------
        DB::table('report_templates')
            ->select('id', 'name', 'service_id', 'code', 'body_region')
            ->where(fn ($q) => $q->whereNull('code')->orWhereNull('body_region'))
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $service = $row->service_id
                        ? DB::table('services')->where('id', $row->service_id)->first(['code', 'name', 'body_region'])
                        : null;

                    $region = $row->body_region
                        ?: ($service->body_region ?? BodyRegion::classify($service->code ?? null, $row->name));

                    $update = [];

                    // The procedure's own code is the strongest identifier for a
                    // procedure-specific template.
                    if (empty($row->code) && $service?->code) {
                        $update['code'] = $service->code;
                    }

                    if (empty($row->body_region) && $region !== null) {
                        $update['body_region'] = $region;
                    }

                    if ($update !== []) {
                        DB::table('report_templates')->where('id', $row->id)->update($update);
                    }
                }
            });

        // ---- 3) Starter macro library ---------------------------------------
        DB::table('businesses')
            ->select('id', 'created_by')
            ->orderBy('id')
            ->chunkById(100, function ($businesses) {
                foreach ($businesses as $business) {
                    // Never overwrite a clinic's own curation.
                    if (DB::table('report_macros')->where('business_id', $business->id)->exists()) {
                        continue;
                    }

                    $modalities = DB::table('modalities')
                        ->where('business_id', $business->id)
                        ->pluck('id', 'code');

                    $now = now();
                    $rows = [];

                    foreach (ReportMacroLibrary::all() as $macro) {
                        $rows[] = [
                            'name' => $macro['name'],
                            'shortcut' => $macro['shortcut'],
                            'modality_id' => $macro['modality'] ? ($modalities[$macro['modality']] ?? null) : null,
                            'service_id' => null,
                            'findings' => $macro['findings'],
                            'impression' => $macro['impression'],
                            'recommendations' => $macro['recommendations'],
                            'scope' => 'tenant',
                            'is_archived' => false,
                            'usage_count' => 0,
                            'business_id' => $business->id,
                            'created_by' => $business->created_by,
                            'updated_by' => $business->created_by,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if ($rows !== []) {
                        DB::table('report_macros')->insert($rows);
                    }
                }
            });
    }

    public function down(): void
    {
        // Additive data backfill: dropping the derived regions/macros would
        // destroy clinic curation, so the down path is intentionally a no-op.
    }
};
