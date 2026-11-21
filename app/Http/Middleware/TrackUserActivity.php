<?php

namespace App\Http\Middleware;

use App\Models\FraudLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs out blocked users, keeps last IP / activity up to date and flags
 * accounts that share one device (device id is a random id the browser keeps).
 */
class TrackUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isBlocked()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Account blocked'], 403);
            }

            return redirect()->route('login')->withErrors(['login' => 'Aap ka account block kar diya gaya hai. Support se rabta karein.']);
        }

        if ($user) {
            $this->track($request, $user);
        }

        return $next($request);
    }

    private function track(Request $request, User $user): void
    {
        $deviceId = substr(preg_replace('/[^a-zA-Z0-9-]/', '', (string) $request->cookie('pe_did')), 0, 64);
        $dirty = false;

        if (! $user->last_active_at || $user->last_active_at->lt(now()->subMinute())) {
            $user->last_active_at = now();
            $user->last_ip = $request->ip();
            $dirty = true;
        }

        if ($deviceId && $user->device_id !== $deviceId) {
            $user->device_id = $deviceId;
            $dirty = true;

            $others = User::query()->where('device_id', $deviceId)->where('id', '!=', $user->id)->pluck('id');
            if ($others->isNotEmpty() && ! $user->isAdmin()) {
                $user->is_flagged = true;
                User::query()->whereIn('id', $others)->update(['is_flagged' => true]);
                FraudLog::record($user->id, 'shared_device', 'Same device as user IDs: '.$others->implode(', '), $request->ip());
            }
        }

        if ($dirty) {
            $user->saveQuietly();
        }
    }
}
