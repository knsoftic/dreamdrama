<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatchSession extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_playing' => 'boolean',
            'is_earning' => 'boolean',
            'counted' => 'boolean',
            'earning' => 'decimal:4',
            'last_heartbeat_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }
}
