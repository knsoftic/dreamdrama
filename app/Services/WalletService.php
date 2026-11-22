<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Every balance change goes through here so that the ledger
 * (wallet_transactions) and users.balance always agree.
 */
class WalletService
{
    public function credit(User $user, float $amount, string $source, ?Model $reference = null, ?string $description = null): WalletTransaction
    {
        return $this->apply($user, 'credit', $amount, $source, $reference, $description);
    }

    public function debit(User $user, float $amount, string $source, ?Model $reference = null, ?string $description = null): WalletTransaction
    {
        return $this->apply($user, 'debit', $amount, $source, $reference, $description);
    }

    private function apply(User $user, string $type, float $amount, string $source, ?Model $reference, ?string $description): WalletTransaction
    {
        $amount = round(abs($amount), 4);

        return DB::transaction(function () use ($user, $type, $amount, $source, $reference, $description) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $balance = (float) $locked->balance;

            if ($type === 'debit' && $amount > $balance + 0.00001) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $newBalance = round($type === 'credit' ? $balance + $amount : $balance - $amount, 4);
            $locked->balance = $newBalance;

            if ($type === 'credit' && $source === 'cycle') {
                $locked->total_earned = round((float) $locked->total_earned + $amount, 4);
            }

            $locked->save();
            $user->setRawAttributes($locked->getAttributes(), true);

            return WalletTransaction::query()->create([
                'user_id' => $locked->id,
                'type' => $type,
                'source' => $source,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'description' => $description,
            ]);
        });
    }
}
