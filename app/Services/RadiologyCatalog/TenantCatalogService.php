<?php

namespace App\Services\RadiologyCatalog;

use App\Models\Business;
use App\Models\CanonicalModality;
use App\Models\Category;
use App\Models\Modality;
use App\Models\RadiologyCatalogRelease;
use App\Models\Service;
use App\Models\Setting;
use App\Support\BookingMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TenantCatalogService
{
    public const OWNER_PRICE_RULE_VERSION = '2026-09-20.1';

    public function initialPriceMinor(?string $category, string $contrast, string $currencyCode): ?int
    {
        if ($currencyCode !== 'PKR') {
            return null;
        }

        return match ($category) {
            'ct_standard' => match ($contrast) {
                'without' => 200000,
                'with', 'with_and_without' => 350000,
                default => null,
            },
            'mr_standard' => match ($contrast) {
                'without' => 300000,
                'with', 'with_and_without' => 400000,
                default => null,
            },
            'us_standard' => in_array($contrast, ['without', 'not_applicable'], true) ? 20000 : null,
            'radiography_standard' => in_array($contrast, ['without', 'not_applicable'], true) ? 30000 : null,
            default => null,
        };
    }

    public function initialize(Business $business, RadiologyCatalogRelease $release, string $currencyCode, int $minorUnitPrecision): array
    {
        if (! preg_match('/\A[A-Z]{3}\z/D', $currencyCode) || ! in_array($minorUnitPrecision, [0, 1, 2], true)) {
            throw ValidationException::withMessages(['currency' => 'An explicit currency code and a precision supported by the service price column are required.']);
        }
        if ($currencyCode === 'PKR' && $minorUnitPrecision !== 2) {
            throw ValidationException::withMessages(['currency' => 'PKR uses two minor-unit decimal places.']);
        }

        return DB::transaction(function () use ($business, $release, $currencyCode, $minorUnitPrecision) {
            $release = RadiologyCatalogRelease::whereKey($release->id)->lockForUpdate()->firstOrFail();
            if ($release->status !== 'published' || $release->published_at === null || $release->validated_at === null) {
                throw ValidationException::withMessages(['release' => 'Only a validated, published release can initialize a tenant.']);
            }
            if (($release->manifest['fixture'] ?? true) && ! app()->environment('testing')) {
                throw ValidationException::withMessages(['fixture' => 'Synthetic catalog fixtures cannot initialize real tenants.']);
            }
            $business = Business::whereKey($business->id)->lockForUpdate()->firstOrFail();
            $profileValue = Setting::where('business', $business->id)->where('key', 'ris_clinic_profile')->value('value');
            $profile = $profileValue ? json_decode($profileValue, true, 512, JSON_THROW_ON_ERROR) : [];
            if (! is_array($profile)) {
                throw ValidationException::withMessages(['currency' => 'The clinic profile must be valid before catalog initialization.']);
            }
            if (isset($profile['currencyCode']) && $profile['currencyCode'] !== $currencyCode) {
                throw ValidationException::withMessages(['currency' => 'Catalog initialization cannot replace the configured clinic currency.']);
            }
            if (isset($profile['currencyMinorUnitPrecision']) && (int) $profile['currencyMinorUnitPrecision'] !== $minorUnitPrecision) {
                throw ValidationException::withMessages(['currency' => 'Catalog initialization cannot change the configured clinic currency precision.']);
            }
            $state = DB::table('tenant_catalog_states')->where('business_id', $business->id)->lockForUpdate()->first();
            if ($state && ($state->currency_code !== $currencyCode || (int) $state->minor_unit_precision !== $minorUnitPrecision)) {
                throw ValidationException::withMessages(['currency' => 'Catalog initialization cannot change an existing tenant currency.']);
            }
            if ($state?->release_id !== null && (int) $state->release_id !== $release->id) {
                throw ValidationException::withMessages(['release' => 'Release upgrades require a reconciliation path; initialization cannot repoint existing services.']);
            }

            $created = ['modalities' => 0, 'regions' => 0, 'services' => 0, 'pricedServices' => 0, 'unpricedServices' => 0];
            $modalities = [];
            foreach ($release->manifest['modalities'] as $entry) {
                $canonical = CanonicalModality::where('canonical_key', $entry['canonical_key'])->firstOrFail();
                $modality = Modality::withTrashed()->forClinic($business->id)->where('canonical_modality_id', $canonical->id)->first();
                if (! $modality) {
                    $code = 'C'.$canonical->id;
                    if (Modality::withTrashed()->forClinic($business->id)->where('code', $code)->exists()) {
                        throw ValidationException::withMessages(['code' => 'A tenant modality code conflicts with catalog initialization.']);
                    }
                    $modality = new Modality;
                    $modality->forceFill([
                        'business_id' => $business->id,
                        'created_by' => $business->created_by,
                        'canonical_modality_id' => $canonical->id,
                        'name' => $entry['name'],
                        'code' => $code,
                        'dicom_code' => $canonical->dicom_code,
                        'buffer_minutes' => 0,
                        'is_active' => true,
                    ])->save();
                    $created['modalities']++;
                }
                $modalities[$canonical->id] = $modality;
            }
            foreach ($release->manifest['regions'] as $entry) {
                $regionId = DB::table('anatomical_regions')->where('canonical_key', $entry['canonical_key'])->value('id');
                $created['regions'] += DB::table('tenant_anatomical_regions')->insertOrIgnore([
                    'business_id' => $business->id,
                    'anatomical_region_id' => $regionId,
                    'is_active' => true,
                    'lock_version' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $category = Category::firstOrCreate(
                ['business_id' => $business->id, 'name' => 'Radiology'],
                ['created_by' => $business->created_by],
            );
            foreach ($release->revisions()->where('status', 'active')->where('is_orderable', true)->where('workflow_supported', true)->with('modality')->lazyById(250) as $revision) {
                $existing = Service::withTrashed()->forClinic($business->id)
                    ->where('canonical_procedure_id', $revision->canonical_procedure_id)->where('local_variant_key', '')->exists();
                if ($existing) {
                    continue;
                }
                $code = 'CAT-'.$revision->canonical_procedure_id;
                if (Service::withTrashed()->forClinic($business->id)->where('code', $code)->exists()) {
                    throw ValidationException::withMessages(['code' => 'A tenant service code conflicts with catalog initialization.']);
                }
                $priceMinor = $this->initialPriceMinor($revision->pricing_category, $revision->contrast_category, $currencyCode);
                $routes = $revision->contrast_routes;
                $route = match (true) {
                    $routes === [] => 'none',
                    $routes === ['intravenous'] => 'intravenous',
                    $routes === ['oral'] => 'oral',
                    count($routes) === 2 && in_array('oral', $routes, true) && in_array('intravenous', $routes, true) => 'both',
                    default => 'other',
                };
                Service::create([
                    'business_id' => $business->id,
                    'created_by' => $business->created_by,
                    'category_id' => $category->id,
                    'canonical_procedure_id' => $revision->canonical_procedure_id,
                    'canonical_procedure_revision_id' => $revision->id,
                    'local_variant_key' => '',
                    'modality_id' => $modalities[$revision->canonical_modality_id]->id,
                    'name' => $revision->name,
                    'code' => $code,
                    'duration' => '',
                    'duration_minutes' => null,
                    'preparation_instructions' => null,
                    'is_active' => true,
                    'is_bookable_online' => false,
                    'contrast_type' => $route,
                    'requires_screening' => in_array('MR', $revision->modality->acquisition_codes, true) || $route !== 'none',
                    'price' => BookingMoney::minorToDecimal($priceMinor, $minorUnitPrecision),
                    'currency_code' => $currencyCode,
                    'minor_unit_precision' => $minorUnitPrecision,
                    'pricing_state' => $priceMinor === null ? 'unconfigured' : 'configured',
                    'price_source' => $priceMinor === null ? null : 'owner_bootstrap',
                    'price_rule_version' => $priceMinor === null ? null : self::OWNER_PRICE_RULE_VERSION,
                    'price_configured_at' => null,
                ]);
                $created['services']++;
                $created[$priceMinor === null ? 'unpricedServices' : 'pricedServices']++;
            }
            if (! $state || $state->initialized_at === null || $created['services'] + $created['regions'] + $created['modalities'] > 0) {
                DB::table('tenant_catalog_states')->updateOrInsert(['business_id' => $business->id], [
                    'release_id' => $release->id,
                    'currency_code' => $currencyCode,
                    'minor_unit_precision' => $minorUnitPrecision,
                    'lock_version' => $state ? (int) $state->lock_version + 1 : 1,
                    'initialized_at' => $state?->initialized_at ?? now(),
                    'created_at' => $state?->created_at ?? now(),
                    'updated_at' => now(),
                ]);
                \App\Models\AuditLog::create([
                    'user_id' => auth()->id() ?? 0,
                    'business_id' => $business->id,
                    'action' => 'tenant_catalog_initialized',
                    'subject_type' => 'Business',
                    'subject_id' => $business->id,
                    'changes' => ['release_key' => $release->release_key, 'currency_code' => $currencyCode, 'created' => $created],
                    'ip' => null,
                ]);
            }

            return $created;
        }, 3);
    }
}