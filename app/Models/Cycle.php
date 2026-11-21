<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cycle extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rate_per_second' => 'decimal:6',
            'rate_per_ad' => 'decimal:4',
            'rate_multiplier' => 'decimal:2',
            'video_earning' => 'decimal:4',
            'ad_earning' => 'decimal:4',
            'total_earning' => 'decimal:4',
            'credited_amount' => 'decimal:4',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function watchSessions(): HasMany
    {
        return $this->hasMany(WatchSession::class);
    }

    public function adViews(): HasMany
    {
        return $this->hasMany(AdView::class);
    }

    public function progressPercent(): int
    {
        $total = $this->videos_required + $this->ads_required;
        if ($total === 0) {
            return 100;
        }

        return (int) round(100 * (min($this->videos_watched, $this->videos_required) + min($this->ads_watched, $this->ads_required)) / $total);
    }
}
