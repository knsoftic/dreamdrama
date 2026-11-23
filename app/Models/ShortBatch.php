<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShortBatch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['options' => 'array'];
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class, 'short_batch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function progress(): int
    {
        if ($this->total > 0) {
            return (int) round(min($this->done, $this->total) / $this->total * 100);
        }

        return $this->status === 'done' ? 100 : 0;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['done', 'failed'], true);
    }

    public function statusLabel(): string
    {
        return [
            'queued' => 'Queued',
            'processing' => 'Processing',
            'done' => 'Done',
            'failed' => 'Failed',
        ][$this->status] ?? ucfirst($this->status);
    }
}
