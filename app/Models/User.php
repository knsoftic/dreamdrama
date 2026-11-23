<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // All inputs to User are built from fixed arrays in the controllers (never
    // request->all()), so guarding only the id keeps admin updates (status,
    // role, referral_code, sub_admin_id, ...) working without listing each one.
    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    private ?Plan $resolvedPlan = null;

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->referral_code ??= static::newReferralCode();
        });
    }

    public static function newReferralCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 7; $i++) {
                $code .= 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[random_int(0, 31)];
            }
        } while (static::query()->where('referral_code', $code)->exists());

        return $code;
    }

    /** The user who invited this user. */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /** Users this user invited. */
    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function inviteLink(): string
    {
        return route('invite.link', $this->referral_code);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
            'balance' => 'decimal:4',
            'total_earned' => 'decimal:4',
            'total_withdrawn' => 'decimal:4',
            'commission_percent' => 'decimal:2',
            'is_flagged' => 'boolean',
            'withdraw_exempt' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSubAdmin(): bool
    {
        return $this->role === 'sub_admin';
    }

    /** Admin or sub-admin (a staff account, not a normal earning user). */
    public function isStaff(): bool
    {
        return $this->role === 'admin' || $this->role === 'sub_admin';
    }

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    /** The sub-admin who owns this user's network (null = under super admin). */
    public function subAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sub_admin_id');
    }

    /** Everyone in this sub-admin's network (direct + indirect, any depth). */
    public function networkUsers(): HasMany
    {
        return $this->hasMany(User::class, 'sub_admin_id');
    }

    /** Plan deposits made by this sub-admin's network. */
    public function deposits(): HasMany
    {
        return $this->hasMany(\App\Models\Payment::class, 'sub_admin_id');
    }

    /**
     * Downline user ids grouped by level (1..$levels) based on who invited whom.
     *
     * @return array<int, list<int>>
     */
    public function downlineByLevel(int $levels = 3): array
    {
        $out = [];
        $parents = [$this->id];
        for ($level = 1; $level <= $levels; $level++) {
            $ids = static::query()->whereIn('referred_by', $parents)->pluck('id')->all();
            $out[$level] = $ids;
            if (! $ids) {
                break;
            }
            $parents = $ids;
        }

        return $out;
    }

    /** @return array{1:int,2:int,3:int} */
    public function downlineCounts(int $levels = 3): array
    {
        $by = $this->downlineByLevel($levels);

        return [
            1 => count($by[1] ?? []),
            2 => count($by[2] ?? []),
            3 => count($by[3] ?? []),
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(Cycle::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function watchSessions(): HasMany
    {
        return $this->hasMany(WatchSession::class);
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->with('plan')
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latest('starts_at')
            ->first();
    }

    /** The plan the user is on right now (falls back to the default/free plan). */
    public function currentPlan(): Plan
    {
        if ($this->resolvedPlan) {
            return $this->resolvedPlan;
        }

        $plan = $this->activeSubscription()?->plan ?? Plan::defaultPlan();

        return $this->resolvedPlan = $plan;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));

        return strtoupper(mb_substr($parts[0] ?? 'U', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }
}
