<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatLabel extends Model
{
    protected $guarded = ['id'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isGlobal(): bool
    {
        return $this->owner_id === null;
    }

    /** Labels a staff member may use: their own + the global (super-admin) ones. */
    public function scopeAvailableFor(Builder $q, User $staff): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('owner_id')->orWhere('owner_id', $staff->id))
            ->orderByRaw('owner_id is null desc')   // global first
            ->orderBy('name');
    }
}
