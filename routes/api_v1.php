<?php

use App\Http\Controllers\Api\V1\V1Controller;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1  (/api/v1)
|--------------------------------------------------------------------------
| Versioned, resource-based contract. Legacy /api/* endpoints keep working
| as shims and send a Sunset header (see DeprecateLegacyApi).
*/

Route::get('/health', function () {
    try {
        Illuminate\Support\Facades\DB::select('select 1');
        $db = 'ok';
    } catch (Throwable) {
        $db = 'down';
    }

    $cache = 'ok';
    try {
        Illuminate\Support\Facades\Cache::put('health:ping', 1, 10);
    } catch (Throwable) {
        $cache = 'down';
    }

    return response()->json([
        'ok' => $db === 'ok' && $cache === 'ok',
        'db' => $db,
        'cache' => $cache,
        'time' => now()->toIso8601String(),
        'version' => 'v1',
    ]);
});

Route::post('/login', [V1Controller::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [V1Controller::class, 'logout']);

    Route::get('/dashboard', [V1Controller::class, 'dashboard']);
    Route::get('/services', [V1Controller::class, 'services']);
    Route::get('/appointments', [V1Controller::class, 'appointments']);
    Route::get('/availability', [V1Controller::class, 'availability'])->middleware('throttle:availability');

    // Patient portal
    Route::get('/me/appointments', [V1Controller::class, 'myAppointments']);
    Route::post('/me/appointments/{id}/cancel', [V1Controller::class, 'cancelMyAppointment']);
});
