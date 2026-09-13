<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — SPA host
|--------------------------------------------------------------------------
|
| The React SPA is the product UI and is served from `public/app` by Apache
| (Hostinger) with this catch-all only receiving real application routes
| (deep links that miss a static file). The JSON API lives in routes/api.php.
*/

$serveSpa = function () {
    // CI/deploys copy the Vite build (dist/) into public/ — index.html included.
    $spa = public_path('index.html');

    if (! is_file($spa)) {
        return response('<h1>ADC Portal</h1><p>Frontend build missing. Deploy the compiled SPA into <code>public/</code>.</p>', 503)
            ->header('Content-Type', 'text/html');
    }

    return response()->file($spa, ['Cache-Control' => 'no-cache']);
};

Route::get('/', $serveSpa)->name('spa.root');

// SPA deep-link fallback for any non-API path that reaches PHP.
Route::get('/{any}', $serveSpa)->where('any', '^(?!api/|sanctum/|up$).*$');
