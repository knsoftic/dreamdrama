<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdView extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'earning' => 'decimal:4',
            'completed_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function adUnit(): BelongsTo
    {
        return $this->belongsTo(AdUnit::class);
    }
}
