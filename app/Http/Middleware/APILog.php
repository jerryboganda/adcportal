<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * API request log — privacy-safe and cheap.
 * Logs method/URL/status/duration + sanitized input (no passwords/tokens),
 * never dumps response bodies. Disable entirely with API_LOG_ENABLED=false.
 */
class APILog
{
    private const SENSITIVE = ['password', 'password_confirmation', 'token', 'current_password', 'new_password', 'g-recaptcha-response'];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate($request, $response): void
    {
        if (env('API_LOG_ENABLED', true) === false || ! config('logging.channels.API_log')) {
            return;
        }

        $input = collect($request->all())
            ->map(fn ($v, $k) => in_array(strtolower((string) $k), self::SENSITIVE, true) ? '[REDACTED]' : $v)
            ->take(25)
            ->all();

        Log::channel('API_log')->info('api', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status' => method_exists($response, 'status') ? $response->status() : null,
            'duration_ms' => defined('LARAVEL_START') ? round((microtime(true) - LARAVEL_START) * 1000, 1) : null,
            'ip' => $request->ip(),
            'user_id' => optional($request->user())->id,
            'input' => $input,
            'response_bytes' => strlen((string) $response->getContent()),
        ]);
    }
}
