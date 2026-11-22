<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'callback_at' => 'datetime',
            'gateway_meta' => 'array',
        ];
    }

    public function isGateway(): bool
    {
        return $this->gateway === 'pkpay';
    }

    public function subAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sub_admin_id');
    }

    /** The sub-admin's share of this deposit, after the platform tax. */
    public function netAmount(): float
    {
        return round((float) $this->amount * (1 - (float) $this->tax_percent / 100), 2);
    }

    public function taxAmount(): float
    {
        return round((float) $this->amount * (float) $this->tax_percent / 100, 2);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
