<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Decides whether a user may withdraw right now, based on their plan:
 * withdrawals on/off, min / max amount, days between withdrawals and
 * the number of active invites (referrals) required.
 */
class WithdrawalRules
{
    /**
     * Invited users that count: not blocked, not flagged, and who completed
     * at least `referral_min_cycles` cycles (so fake sign-ups don't count).
     */
    public function activeReferrals(User $user): int
    {
        $minCycles = (int) setting('referral_min_cycles', 1);

        return $user->referrals()
            ->where('status', 'active')
            ->where('is_flagged', false)
            ->when($minCycles > 0, fn ($q) => $q->whereHas('cycles', fn ($c) => $c->where('status', 'completed'), '>=', $minCycles))
            ->count();
    }

    /**
     * @return array{plan: \App\Models\Plan, checks: list<array{key: string, label: string, ok: bool, detail: ?string, action: ?string}>, allowed: bool, min: float, max: float, plan_max: float}
     */
    public function check(User $user): array
    {
        $plan = $user->currentPlan();
        $balance = (float) $user->balance;
        $min = (float) $plan->min_withdrawal;
        $planMax = (float) $plan->max_withdrawal;
        $checks = [];

        if (setting('withdrawals_enabled', '1') !== '1') {
            $checks[] = $this->item('global', 'Withdrawals abhi band hain', false, 'Admin jald khol dega');
        }

        $checks[] = $plan->withdrawals_enabled
            ? $this->item('plan', "{$plan->name} plan mein withdrawal allowed hai", true)
            : $this->item('plan', "{$plan->name} plan mein withdrawal nahi", false, 'Withdrawal ke liye plan upgrade karein', 'plans');

        $checks[] = $this->item('balance', 'Kam az kam '.money(max($min, 1), 0).' balance', $balance >= max($min, 1), 'Aap ka balance '.money($balance));

        if ($plan->required_referrals > 0) {
            $active = $this->activeReferrals($user);
            $checks[] = $this->item('referrals', "{$plan->required_referrals} active invites", $active >= $plan->required_referrals,
                "{$active} / {$plan->required_referrals} poore", $active >= $plan->required_referrals ? null : 'invite');
        }

        if ($user->withdrawals()->where('status', 'pending')->exists()) {
            $checks[] = $this->item('pending', 'Pichli request abhi process honi hai', false, 'Ek waqt mein ek hi request');
        }

        if ($plan->withdrawal_gap_days > 0) {
            $last = $user->withdrawals()->where('status', '!=', 'rejected')->latest('created_at')->value('created_at');
            $next = $last ? Carbon::parse($last)->addDays($plan->withdrawal_gap_days) : null;
            $waiting = $next && $next->isFuture();
            $checks[] = $this->item('gap', "Har {$plan->withdrawal_gap_days} din mein ek withdrawal", ! $waiting,
                $waiting ? 'Agli withdrawal: '.$next->format('d M, h:i A') : null);
        }

        $max = floor($balance);
        if ($planMax > 0) {
            $max = min($max, $planMax);
        }

        return [
            'plan' => $plan,
            'checks' => $checks,
            'allowed' => collect($checks)->every(fn ($c) => $c['ok']),
            'min' => max($min, 1),
            'max' => $max,
            'plan_max' => $planMax,
        ];
    }

    private function item(string $key, string $label, bool $ok, ?string $detail = null, ?string $action = null): array
    {
        return compact('key', 'label', 'ok', 'detail', 'action');
    }
}
