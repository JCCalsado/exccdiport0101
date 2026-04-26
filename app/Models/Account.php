<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Account extends Model
{
    protected $fillable = ['user_id', 'account_number', 'balance'];

    protected $casts = ['balance' => 'decimal:2'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Generate a unique formatted account number.
     * Format: ACC-YEAR-0001
     *
     * FIX (Bug #6): The previous implementation called lockForUpdate() outside
     * of a DB transaction, which made the pessimistic lock a no-op. Two
     * concurrent registrations could read the same last account number,
     * derive the same next number, and attempt to insert a duplicate.
     *
     * Fix: the entire read-compute-return block is now wrapped in
     * DB::transaction() so the lockForUpdate() actually holds until the
     * caller inserts the new row within the same transaction scope.
     *
     * IMPORTANT: The CALLER must insert/create the Account record within the
     * same DB transaction that calls this method, otherwise the lock is
     * released before the insert and the race condition reappears.
     *
     * Example usage (inside a DB::transaction block):
     *
     *   DB::transaction(function () use ($user) {
     *       $number  = Account::generateAccountNumber();
     *       $account = Account::create([
     *           'user_id'        => $user->id,
     *           'account_number' => $number,
     *           'balance'        => 0,
     *       ]);
     *   });
     */
    public static function generateAccountNumber(): string
    {
        return DB::transaction(function () {
            $year = now()->year;

            // Pessimistic lock — prevents concurrent reads from getting the same value.
            // This only works correctly when called inside a DB::transaction().
            $lastAccount = self::where('account_number', 'like', "ACC-{$year}-%")
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(account_number, 10) AS UNSIGNED) DESC')
                ->first();

            if ($lastAccount) {
                $lastNumber = intval(substr($lastAccount->account_number, -4));
                $newNumber  = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '0001';
            }

            $newAccountNumber = "ACC-{$year}-{$newNumber}";

            // Safety net: if by some edge case the number already exists, increment
            // until we find a free slot (capped at 10 attempts).
            $attempts = 0;
            while (self::where('account_number', $newAccountNumber)->lockForUpdate()->exists() && $attempts < 10) {
                $lastNumber       = intval($newNumber);
                $newNumber        = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                $newAccountNumber = "ACC-{$year}-{$newNumber}";
                $attempts++;
            }

            if ($attempts >= 10) {
                throw new \Exception('Unable to generate a unique account number after 10 attempts. Please try again.');
            }

            return $newAccountNumber;
        });
    }
}