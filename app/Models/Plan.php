<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'rate_multiplier' => 'decimal:2',
            'daily_earning_cap' => 'decimal:2',
            'min_withdrawal' => 'decimal:2',
            'max_withdrawal' => 'decimal:2',
            'withdrawals_enabled' => 'boolean',
            'videos_per_cycle' => 'integer',
            'ads_per_cycle' => 'integer',
            'withdrawal_gap_days' => 'integer',
            'required_referrals' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Videos in one cycle on this plan (plan value, or the global setting). */
    public function videosPerCycle(): int
    {
        return max(1, (int) ($this->videos_per_cycle ?: setting('videos_per_cycle', 10)));
    }

    /** Ads in one cycle on this plan (plan value — 0 allowed — or the global setting). */
    public function adsPerCycle(): int
    {
        return max(0, (int) ($this->ads_per_cycle ?? setting('ads_per_cycle', 10)));
    }

    public function dailyVideos(): int
    {
        return (int) $this->daily_cycle_limit * $this->videosPerCycle();
    }

    public function multiplierLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->rate_multiplier, 2), '0'), '.').'x';
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }

    public static function defaultPlan(): self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->orderBy('price')->first()
            ?? new static([
                'name' => 'Free',
                'slug' => 'free',
                'price' => 0,
                'duration_days' => 0,
                'daily_cycle_limit' => 3,
                'rate_multiplier' => 1,
                'daily_earning_cap' => 0,
                'min_withdrawal' => 500,
                'max_withdrawal' => 0,
                'withdrawals_enabled' => false,
                'withdrawal_gap_days' => 0,
                'required_referrals' => 0,
                'color' => '#64748b',
            ]);
    }
}
