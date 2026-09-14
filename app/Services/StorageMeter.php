<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Computes the storage a tenant actually occupies on disk (report PDFs,
 * uploaded files), scoped strictly by tenant ownership. Values are real
 * filesystem sizes — never estimates — cached briefly under a tenant-scoped
 * cache key.
 */
class StorageMeter
{
    public static function bytes(int $businessId): int
    {
        if ($businessId <= 0) {
            return 0;
        }

        return (int) Cache::remember(
            "tenant:{$businessId}:storage_bytes",
            now()->addMinutes(10),
            fn () => self::compute($businessId)
        );
    }

    public static function flush(int $businessId): void
    {
        Cache::forget("tenant:{$businessId}:storage_bytes");
    }

    private static function compute(int $businessId): int
    {
        $paths = [];

        if (Schema::hasTable('radiology_reports') && Schema::hasColumn('radiology_reports', 'pdf_path')) {
            $paths[] = DB::table('radiology_reports')
                ->join('appointments', 'appointments.id', '=', 'radiology_reports.appointment_id')
                ->where('appointments.business_id', $businessId)
                ->whereNotNull('radiology_reports.pdf_path')
                ->pluck('radiology_reports.pdf_path');
        }

        if (Schema::hasTable('appointment_reports')) {
            $paths[] = DB::table('appointment_reports')
                ->join('appointments', 'appointments.id', '=', 'appointment_reports.appointment_id')
                ->where('appointments.business_id', $businessId)
                ->pluck('appointment_reports.file_path');
        }

        if (Schema::hasTable('files')) {
            $paths[] = DB::table('files')->where('business_id', $businessId)->pluck('value');
        }

        $total = 0;
        foreach ($paths as $collection) {
            foreach ($collection as $path) {
                if (! is_string($path) || $path === '') {
                    continue;
                }
                $candidates = [
                    storage_path('app/public/'.ltrim($path, '/')),
                    storage_path('app/'.ltrim($path, '/')),
                    public_path(ltrim($path, '/')),
                ];
                foreach ($candidates as $candidate) {
                    if (is_file($candidate)) {
                        $total += (int) filesize($candidate);
                        break;
                    }
                }
            }
        }

        return $total;
    }
}
