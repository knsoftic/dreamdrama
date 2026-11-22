<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudLog extends Model
{
    protected $guarded = ['id'];

    public const TYPES = [
        'ip_limit' => 'IP limit reached on register',
        'shared_device' => 'Same device, multiple accounts',
        'ip_shared' => 'Same IP, multiple accounts',
        'self_referral' => 'Invite: inviter and new user on the same IP',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(?int $userId, string $type, string $details, ?string $ip = null): void
    {
        static::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'details' => $details,
            'ip' => $ip,
        ]);
    }
}
