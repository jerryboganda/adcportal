<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\BusinessHours;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Slot availability engine.
 *
 * Single indexed query for booked slots + cached result (60s) per
 * service/staff/date key. Replaces the old per-request PHP loop that
 * re-queried settings + hours + appointments on every datepicker change.
 */
class AvailabilityService
{
    public const CACHE_TTL = 60; // seconds

    public static function cacheKey(int $serviceId, ?int $staffId, string $date): string
    {
        return "slots:{$serviceId}:".($staffId ?: 'any').":{$date}";
    }

    public static function forget(int $serviceId, ?int $staffId, string $date): void
    {
        Cache::forget(self::cacheKey($serviceId, $staffId, $date));
        // Staff-agnostic key is also invalidated (booked set changed)
        Cache::forget(self::cacheKey($serviceId, null, $date));
    }

    /**
     * @return array<int, array{start:string,end:string,service_id:int,flexible_id:null}>
     */
    public function slots(int $serviceId, string $date, ?int $staffId = null): array
    {
        return Cache::remember(
            self::cacheKey($serviceId, $staffId, $date),
            self::CACHE_TTL,
            fn () => $this->compute($serviceId, $date, $staffId)
        );
    }

    private function compute(int $serviceId, string $date, ?int $staffId): array
    {
        $service = Service::find($serviceId);
        if (! $service) {
            return [];
        }

        $settings = getCompanyAllSetting($service->created_by, $service->business_id);
        $maximumSlot = (int) ($settings['maximum_slot'] ?? 1);

        $booked = Appointment::query()
            ->forClinic($service->business_id, $service->created_by)
            ->where('service_id', $serviceId)
            ->where('date', $date)
            ->when($staffId, fn ($q) => $q->where('staff_id', $staffId))
            ->pluck('time')
            ->all();

        try {
            $selected = Carbon::createFromFormat('d-m-Y', $date);
        } catch (Throwable) {
            return [];
        }

        $day = $selected->format('l');
        $hours = BusinessHours::where('created_by', $service->created_by)
            ->where('business_id', $service->business_id)
            ->where('day_name', $day)
            ->first();

        $duration = max(1, (int) $service->duration);
        $start = Carbon::createFromFormat('H:i:s', $hours->start_time ?? '09:30:00');
        $end = Carbon::createFromFormat('H:i:s', $hours->end_time ?? '18:00:00');
        $breaks = isset($hours->break_hours) ? (json_decode($hours->break_hours, true) ?: []) : [];

        // Today: cut off slots that already started.
        $now = Carbon::now($settings['defult_timezone'] ?? config('app.timezone'));
        $cutoff = $selected->isToday() ? $now->format('H:i') : null;

        $inBreak = function (Carbon $slotStart, Carbon $slotEnd) use ($breaks) {
            foreach ($breaks as $break) {
                if (empty($break['start']) || empty($break['end'])) {
                    continue;
                }
                $bs = Carbon::createFromFormat('H:i', $break['start']);
                $be = Carbon::createFromFormat('H:i', $break['end']);
                if ($slotStart->lt($be) && $slotEnd->gt($bs)) {
                    return true;
                }
            }

            return false;
        };

        $slots = [];
        $cursor = $start->copy();
        while ($cursor->lt($end)) {
            $slotEnd = $cursor->copy()->addMinutes($duration);
            if ($slotEnd->gt($end)) {
                break;
            }

            $label = $cursor->format('H:i');
            $taken = $slotEnd->format('H:i');

            if ($cutoff !== null && $label <= $cutoff) {
                $cursor->addMinutes($duration);
                continue;
            }

            $capacity = $maximumSlot;
            foreach ($booked as $bookedTime) {
                if ($bookedTime === $label) {
                    $capacity--;
                }
            }

            if ($capacity > 0 && ! $inBreak($cursor, $slotEnd)) {
                $slots[] = [
                    'start' => $label,
                    'end' => $taken,
                    'service_id' => $serviceId,
                    'flexible_id' => null,
                ];
            }

            $cursor->addMinutes($duration);
        }

        return $slots;
    }
}
