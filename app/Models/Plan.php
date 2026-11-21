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
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
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
                'color' => '#64748b',
            ]);
    }
}
