<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdUnit extends Model
{
    protected $guarded = ['id'];

    public const NETWORKS = ['adsterra' => 'Adsterra', 'monetag' => 'Monetag', 'custom' => 'Custom / Other'];

    public const PLACEMENTS = [
        'ad_break' => 'Ad Break (videos ke beech full screen)',
        'page_banner' => 'Page Banner (wallet / earnings pages par)',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public static function pick(string $placement): ?self
    {
        return static::active()->where('placement', $placement)->inRandomOrder()->first();
    }
}
