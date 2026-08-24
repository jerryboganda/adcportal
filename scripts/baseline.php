<?php

/**
 * Performance baseline / benchmark probe.
 * Usage: php scripts/baseline.php
 *
 * Reports query counts + wall time for the hottest paths:
 *  - Dashboard aggregates
 *  - Slot availability engine (timeSlot)
 *  - Appointment list query (DataTable source)
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Authenticate as admin for context-dependent code
$admin = App\Models\User::where('type', 'admin')->first();
if ($admin) {
    Auth::login($admin);
}

function probe(string $label, callable $fn): void
{
    DB::enableQueryLog();
    $start = microtime(true);
    try {
        $fn();
    } catch (Throwable $e) {
        echo sprintf("[%s] ERROR: %s\n", $label, $e->getMessage());
        DB::disableQueryLog();

        return;
    }
    $ms = (microtime(true) - $start) * 1000;
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();
    echo sprintf("[%s] queries=%d time=%.1fms\n", $label, $count, $ms);
}

probe('dashboard aggregates', function () {
    $bid = getActiveBusiness();
    $cid = creatorId();
    \App\Models\Service::where('business_id', $bid)->where('created_by', $cid)->count();
    \App\Models\Appointment::where('business_id', $bid)->where('created_by', $cid)->count();
    \App\Models\User::where('type', 'staff')->where('business_id', $bid)->where('created_by', $cid)->count();
    \App\Models\Location::where('business_id', $bid)->where('created_by', $cid)->count();
    $latest = \App\Models\Appointment::where('business_id', $bid)->where('created_by', $cid)->latest()->take(4)->get();
    // Simulate blade N+1 access pattern
    foreach ($latest as $a) {
        $a->StaffData?->name;
        $a->StaffData?->user?->email;
        $a->ServiceData?->name;
        $a->LocationData?->name;
        $a->StatusData?->title;
        \App\Models\Appointment::appointmentNumberFormat($a->id, $a->created_by, $a->business_id);
    }
});

probe('slot availability (timeSlot)', function () {
    $service = \App\Models\Service::first();
    if ($service) {
        timeSlot($service->id, now()->format('d-m-Y'));
    }
});

probe('appointment list page-size 25 (N+1 pattern)', function () {
    $bid = getActiveBusiness();
    $rows = \App\Models\Appointment::where('business_id', $bid)->latest()->take(25)->get();
    foreach ($rows as $a) {
        $a->CustomerData?->name;
        $a->StaffData?->name;
        $a->ServiceData?->name;
        $a->LocationData?->name;
        $a->StatusData?->title;
    }
});

probe('settings cache read x100', function () {
    for ($i = 0; $i < 100; $i++) {
        getAdminAllSetting();
    }
});

echo "Baseline complete.\n";
