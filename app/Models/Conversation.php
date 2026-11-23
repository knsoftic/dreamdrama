<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'muted' => 'boolean',
            'blocked' => 'boolean',
        ];
    }

    /** The member (normal user). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The sub-admin (or super-admin) who handles this member. */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function hasParticipant(User $u): bool
    {
        return $this->user_id === $u->id || $this->staff_id === $u->id;
    }

    /** Unread count for whichever side this user is on. */
    public function unreadFor(User $u): int
    {
        return $u->id === $this->staff_id ? $this->staff_unread : $this->user_unread;
    }
}
