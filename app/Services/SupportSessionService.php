<?php

namespace App\Services;

use App\Models\Business;
use App\Models\SupportSession;
use App\Models\User;
use Illuminate\Support\Facades\Session;

/**
 * Break-glass support sessions. A platform user may hold at most ONE active
 * session; it is bound to one tenant, requires a reason, auto-expires, and is
 * fully audited. While active, the tenant context flows through the verified
 * server-side session row — never through client-supplied tenant ids.
 */
class SupportSessionService
{
    public static function activeFor(User $platformUser, ?int $businessId = null): ?SupportSession
    {
        return SupportSession::query()
            ->where('platform_user_id', $platformUser->id)
            ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
            ->whereNull('ended_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();
    }

    public static function start(User $platformUser, Business $tenant, string $reason, int $minutes): SupportSession
    {
        // One live break-glass session per platform user.
        self::activeFor($platformUser)?->update(['ended_at' => now()]);

        $session = SupportSession::create([
            'platform_user_id' => $platformUser->id,
            'business_id' => $tenant->id,
            'reason' => $reason,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(min($minutes, (int) config('ris.support_session_max_minutes', 240))),
            'created_ip' => request()?->ip(),
        ]);

        \App\Models\AuditLog::record('support_session_started', $tenant, [
            'summary' => "Support session opened for {$tenant->name} by {$platformUser->name}: {$reason}",
        ], $tenant->id);
        \App\Models\TenantLifecycleEvent::record($tenant, 'support_session_started', $tenant->subscription_status, $tenant->subscription_status, [
            'summary' => "Platform support access granted to {$platformUser->name} until ".$session->expires_at->toIso8601String().'.',
        ]);

        return $session;
    }

    public static function end(SupportSession $session, string $note = ''): SupportSession
    {
        if ($session->ended_at === null) {
            $session->update(['ended_at' => now()]);

            Session::forget('support_context');

            \App\Models\AuditLog::record('support_session_ended', $session->business, [
                'summary' => 'Support session closed'.($note !== '' ? ": {$note}" : '.'),
            ], $session->business_id);
            \App\Models\TenantLifecycleEvent::record($session->business, 'support_session_ended', null, null, [
                'summary' => 'Platform support access revoked'.($note !== '' ? ": {$note}" : '.'),
            ]);
        }

        return $session->fresh();
    }

    /** Auto-close every session past its expiry (scheduler + defensive check). */
    public static function expireStale(): int
    {
        return SupportSession::query()
            ->whereNull('ended_at')
            ->where('expires_at', '<=', now())
            ->update(['ended_at' => now()]);
    }

    /** Enter the tenant context for an active session (verified server-side). */
    public static function enter(User $platformUser, int $businessId): SupportSession
    {
        $session = self::activeFor($platformUser, $businessId);

        abort_unless($session, 403, 'No active support session authorizes this tenant.');

        Session::put('support_context', $session->business_id);

        return $session;
    }

    public static function leaveContext(): void
    {
        Session::forget('support_context');
    }
}
