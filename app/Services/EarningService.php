<?php

namespace App\Services;

use App\Models\AdUnit;
use App\Models\AdView;
use App\Models\Cycle;
use App\Models\User;
use App\Models\Video;
use App\Models\WatchSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The earning engine.
 *
 * A "cycle" = N videos + M ads (admin settings). Video seconds and ad views
 * earn into the cycle; nothing reaches the wallet until the whole cycle
 * (including the ads) is complete. Watch time is measured on the server from
 * heartbeats, never trusted from the browser.
 */
class EarningService
{
    public function __construct(private WalletService $wallet) {}

    /* ------------------------------------------------------------------ */
    /*  Cycle helpers                                                       */
    /* ------------------------------------------------------------------ */

    public function completedCyclesToday(User $user): int
    {
        return Cycle::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('completed_at', '>=', today())
            ->count();
    }

    public function creditedToday(User $user): float
    {
        return (float) Cycle::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('completed_at', '>=', today())
            ->sum('credited_amount');
    }

    public function activeCycle(User $user, bool $create = true): ?Cycle
    {
        $cycle = Cycle::query()
            ->where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->latest('id')
            ->first();

        if ($cycle || ! $create) {
            return $cycle;
        }

        $plan = $user->currentPlan();
        if ($this->completedCyclesToday($user) >= (int) $plan->daily_cycle_limit) {
            return null;
        }

        return Cycle::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->exists ? $plan->id : null,
            'status' => 'in_progress',
            'videos_required' => $plan->videosPerCycle(),
            'ads_required' => $plan->adsPerCycle(),
            'rate_per_second' => (float) setting('rate_per_second', 0),
            'rate_per_ad' => (float) setting('rate_per_ad', 0),
            'rate_multiplier' => (float) $plan->rate_multiplier ?: 1,
        ]);
    }

    /** How many ads the user must watch right now before the next video can earn. */
    public function adsDue(Cycle $cycle): int
    {
        if ($cycle->ads_required <= 0) {
            return 0;
        }

        if (setting('ad_placement', 'spread') === 'after_cycle') {
            $expected = $cycle->videos_watched >= $cycle->videos_required ? $cycle->ads_required : 0;
        } else {
            $expected = intdiv($cycle->videos_watched * $cycle->ads_required, max(1, $cycle->videos_required));
        }

        return max(0, min($cycle->ads_required, $expected) - $cycle->ads_watched);
    }

    private function completeIfReady(Cycle $cycle): void
    {
        if ($cycle->status !== 'in_progress'
            || $cycle->videos_watched < $cycle->videos_required
            || $cycle->ads_watched < $cycle->ads_required) {
            return;
        }

        $user = $cycle->user;
        $amount = (float) $cycle->total_earning;
        $cap = (float) ($cycle->plan?->daily_earning_cap ?? 0);

        if ($cap > 0) {
            $amount = max(0, min($amount, $cap - $this->creditedToday($user)));
        }

        $cycle->status = 'completed';
        $cycle->completed_at = now();
        $cycle->credited_amount = round($amount, 4);
        $cycle->save();

        if ($amount > 0) {
            $this->wallet->credit($user, $amount, 'cycle', $cycle, "Cycle #{$cycle->id} complete");
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Video watching                                                      */
    /* ------------------------------------------------------------------ */

    public function startWatch(User $user, Video $video, ?string $ip): array
    {
        return DB::transaction(function () use ($user, $video, $ip) {
            // Only one video can earn at a time: close anything still open.
            WatchSession::query()
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->get()
                ->each(fn (WatchSession $s) => $this->finalize($s));

            $cycle = $this->activeCycle($user);
            $earning = true;
            $reason = null;

            if (! $cycle) {
                $earning = false;
                $reason = 'limit_reached';
            } elseif ($this->adsDue($cycle) > 0 || $cycle->videos_watched >= $cycle->videos_required) {
                return ['status' => 'ad_due', 'state' => $this->state($user)];
            }

            if ($earning && setting('earn_once_per_video', '1') === '1') {
                $already = WatchSession::query()
                    ->where('user_id', $user->id)
                    ->where('video_id', $video->id)
                    ->where('counted', true)
                    ->exists();
                if ($already) {
                    $earning = false;
                    $reason = 'already_earned';
                }
            }

            $session = WatchSession::query()->create([
                'user_id' => $user->id,
                'video_id' => $video->id,
                'cycle_id' => $earning ? $cycle->id : null,
                'token' => (string) Str::uuid(),
                'is_playing' => true,
                'is_earning' => $earning,
                'last_heartbeat_at' => now(),
                'ip' => $ip,
            ]);

            $video->increment('views_count');

            return [
                'status' => 'ok',
                'token' => $session->token,
                'earning' => $earning,
                'reason' => $reason,
                'state' => $this->state($user),
            ];
        });
    }

    public function heartbeat(User $user, string $token, bool $playing): array
    {
        return DB::transaction(function () use ($user, $token, $playing) {
            $session = WatchSession::query()
                ->with('video')
                ->where('token', $token)
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if (! $session) {
                return ['status' => 'invalid'];
            }

            $this->creditElapsed($session);
            $session->is_playing = $playing;
            $session->save();

            return [
                'status' => 'ok',
                'counted_seconds' => $session->counted_seconds,
            ];
        });
    }

    public function endWatch(User $user, string $token): array
    {
        return DB::transaction(function () use ($user, $token) {
            $session = WatchSession::query()
                ->where('token', $token)
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($session) {
                $this->finalize($session);
            }

            return [
                'status' => 'ok',
                'counted' => (bool) $session?->counted,
                'earned' => (float) ($session?->earning ?? 0),
                'state' => $this->state($user),
            ];
        });
    }

    /** Add the real time passed since the last heartbeat (capped) to the session. */
    private function creditElapsed(WatchSession $session): void
    {
        // The database stores whole seconds, so compare whole seconds too;
        // otherwise every heartbeat would round up and over-count.
        $now = now()->startOfSecond();

        if ($session->is_playing) {
            $elapsed = (int) round(max(0, $session->last_heartbeat_at->diffInSeconds($now)));
            $maxStep = (int) setting('heartbeat_seconds', 5) + 3;   // a missed "pause" can't add more than this
            $duration = (int) ($session->video?->duration_seconds ?: 600);

            $session->counted_seconds = min($duration, $session->counted_seconds + min($elapsed, $maxStep));
        }

        $session->last_heartbeat_at = $now;
    }

    private function finalize(WatchSession $session): void
    {
        $session->loadMissing('video');
        $this->creditElapsed($session);
        $session->is_playing = false;
        $session->ended_at = now();

        $duration = (int) $session->video?->duration_seconds;
        $minSeconds = (int) setting('min_watch_seconds', 5);
        if ($duration > 0) {
            $minSeconds = min($minSeconds, max(1, $duration - 1));
        }

        if ($session->is_earning && $session->cycle_id && $session->counted_seconds >= $minSeconds) {
            $cycle = Cycle::query()->whereKey($session->cycle_id)->lockForUpdate()->first();

            if ($cycle && $cycle->status === 'in_progress'
                && $cycle->videos_watched < $cycle->videos_required
                && $this->adsDue($cycle) === 0) {
                $earn = round($session->counted_seconds * (float) $cycle->rate_per_second * (float) $cycle->rate_multiplier, 4);

                $session->counted = true;
                $session->earning = $earn;

                $cycle->videos_watched += 1;
                $cycle->video_seconds += $session->counted_seconds;
                $cycle->video_earning = round((float) $cycle->video_earning + $earn, 4);
                $cycle->total_earning = round((float) $cycle->total_earning + $earn, 4);
                $cycle->save();

                $this->completeIfReady($cycle);
            }
        }

        $session->save();
    }

    /* ------------------------------------------------------------------ */
    /*  Ads                                                                 */
    /* ------------------------------------------------------------------ */

    public function startAd(User $user, ?string $ip): array
    {
        return DB::transaction(function () use ($user, $ip) {
            $cycle = $this->activeCycle($user, false);
            if (! $cycle || $this->adsDue($cycle) === 0) {
                return ['status' => 'not_due', 'state' => $this->state($user)];
            }

            $view = AdView::query()
                ->where('user_id', $user->id)
                ->where('cycle_id', $cycle->id)
                ->where('status', 'started')
                ->latest('id')
                ->first();

            if (! $view) {
                $unit = AdUnit::pick('ad_break');
                $view = AdView::query()->create([
                    'user_id' => $user->id,
                    'cycle_id' => $cycle->id,
                    'ad_unit_id' => $unit?->id,
                    'required_seconds' => max(1, (int) setting('ad_duration_seconds', 15)),
                    'ip' => $ip,
                ]);
                $unit?->increment('impressions');
            }

            $elapsed = (int) floor($view->created_at->diffInSeconds(now()));

            return [
                'status' => 'ok',
                'ad_view_id' => $view->id,
                'frame_url' => $view->ad_unit_id ? route('ad.frame', $view->ad_unit_id) : null,
                'seconds' => max(0, $view->required_seconds - $elapsed),
                'slot' => $cycle->ads_watched + 1,
                'total' => $cycle->ads_required,
                'due' => $this->adsDue($cycle),
                'reward' => round((float) $cycle->rate_per_ad * (float) $cycle->rate_multiplier, 4),
            ];
        });
    }

    public function completeAd(User $user, int $adViewId): array
    {
        return DB::transaction(function () use ($user, $adViewId) {
            $view = AdView::query()
                ->whereKey($adViewId)
                ->where('user_id', $user->id)
                ->where('status', 'started')
                ->lockForUpdate()
                ->first();

            if (! $view) {
                return ['status' => 'invalid', 'state' => $this->state($user)];
            }

            $elapsed = $view->created_at->diffInSeconds(now());
            if ($elapsed < $view->required_seconds - 1) {
                return ['status' => 'too_early', 'wait' => (int) ceil($view->required_seconds - $elapsed)];
            }

            $cycle = Cycle::query()->whereKey($view->cycle_id)->lockForUpdate()->first();
            $earn = 0.0;

            if ($cycle && $cycle->status === 'in_progress' && $this->adsDue($cycle) > 0) {
                $earn = round((float) $cycle->rate_per_ad * (float) $cycle->rate_multiplier, 4);
                $cycle->ads_watched += 1;
                $cycle->ad_earning = round((float) $cycle->ad_earning + $earn, 4);
                $cycle->total_earning = round((float) $cycle->total_earning + $earn, 4);
                $cycle->save();
            }

            $view->status = 'completed';
            $view->completed_at = now();
            $view->earning = $earn;
            $view->save();

            $justCompleted = false;
            if ($cycle) {
                $this->completeIfReady($cycle);
                $justCompleted = $cycle->status === 'completed';
            }

            return [
                'status' => 'ok',
                'earned' => $earn,
                'cycle_completed' => $justCompleted,
                'credited' => $justCompleted ? (float) $cycle->credited_amount : 0,
                'state' => $this->state($user),
            ];
        });
    }

    /* ------------------------------------------------------------------ */
    /*  State for the app UI                                                */
    /* ------------------------------------------------------------------ */

    public function state(User $user): array
    {
        $user->refresh();
        $plan = $user->currentPlan();
        $cycle = $this->activeCycle($user, false);
        $completedToday = $this->completedCyclesToday($user);
        $limit = (int) $plan->daily_cycle_limit;

        return [
            'balance' => round((float) $user->balance, 4),
            'today_earning' => round($this->creditedToday($user), 4),
            'cycles_today' => $completedToday,
            'cycle_limit' => $limit,
            'limit_reached' => ! $cycle && $completedToday >= $limit,
            'plan' => [
                'name' => $plan->name,
                'multiplier' => (float) $plan->rate_multiplier,
            ],
            'ads_due' => $cycle ? $this->adsDue($cycle) : 0,
            'cycle' => [
                'id' => $cycle?->id,
                'videos_watched' => $cycle?->videos_watched ?? 0,
                'videos_required' => $cycle?->videos_required ?? $plan->videosPerCycle(),
                'ads_watched' => $cycle?->ads_watched ?? 0,
                'ads_required' => $cycle?->ads_required ?? $plan->adsPerCycle(),
                'video_earning' => round((float) ($cycle?->video_earning ?? 0), 4),
                'ad_earning' => round((float) ($cycle?->ad_earning ?? 0), 4),
                'total' => round((float) ($cycle?->total_earning ?? 0), 4),
            ],
        ];
    }
}
