<?php

namespace App\Services;

use App\Enums\UserRoleEnum;
use App\Models\Fee;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

class FeeAssignmentService
{
    /**
     * Assign fees to a student based on their year level and semester.
     */
    public static function assignFeesToStudent(User $user, string $semester, string $schoolYear)
    {
        // Use the enum value for comparison — role column is cast to UserRoleEnum.
        if ($user->role !== UserRoleEnum::STUDENT) {
            return;
        }

        // Get active fees for this student's year level and semester
        $fees = Fee::active()
            ->forTerm($user->year_level, $semester, $schoolYear)
            ->get();

        $totalAmount = 0;

        foreach ($fees as $fee) {
            // Check if this fee is already assigned
            $exists = Transaction::where('user_id', $user->id)
                ->where('fee_id', $fee->id)
                ->where('year', explode('-', $schoolYear)[0])
                ->where('semester', $semester)
                ->exists();

            if (! $exists) {
                Transaction::create([
                    'user_id'  => $user->id,
                    'fee_id'   => $fee->id,
                    'reference' => 'FEE-' . strtoupper(Str::random(8)),
                    'kind'     => 'charge',
                    'type'     => $fee->category,
                    'year'     => explode('-', $schoolYear)[0],
                    'semester' => $semester,
                    'amount'   => $fee->amount,
                    'status'   => 'pending',
                    'meta'     => [
                        'fee_code'      => $fee->code,
                        'fee_name'      => $fee->name,
                        'auto_assigned' => true,
                        'assigned_at'   => now()->toDateTimeString(),
                    ],
                ]);

                $totalAmount += $fee->amount;
            }
        }

        // Update account balance
        if ($totalAmount > 0) {
            AccountService::recalculate($user);
        }

        return $totalAmount;
    }

    /**
     * Bulk assign fees to multiple students.
     */
    public static function bulkAssignFees(array $userIds, array $feeIds)
    {
        // Use the named scope — avoids raw string role comparison.
        $users = User::students()->whereIn('id', $userIds)->get();
        $fees  = Fee::whereIn('id', $feeIds)->get();

        $assigned = 0;

        foreach ($users as $user) {
            foreach ($fees as $fee) {
                // Check if already assigned
                $exists = Transaction::where('user_id', $user->id)
                    ->where('fee_id', $fee->id)
                    ->where('year', explode('-', $fee->school_year)[0])
                    ->where('semester', $fee->semester)
                    ->exists();

                if (! $exists) {
                    Transaction::create([
                        'user_id'  => $user->id,
                        'fee_id'   => $fee->id,
                        'reference' => 'FEE-' . strtoupper(Str::random(8)),
                        'kind'     => 'charge',
                        'type'     => $fee->category,
                        'year'     => explode('-', $fee->school_year)[0],
                        'semester' => $fee->semester,
                        'amount'   => $fee->amount,
                        'status'   => 'pending',
                        'meta'     => [
                            'fee_code'      => $fee->code,
                            'fee_name'      => $fee->name,
                            'bulk_assigned' => true,
                            'assigned_at'   => now()->toDateTimeString(),
                        ],
                    ]);

                    $assigned++;
                }
            }

            // Recalculate balance for each user
            AccountService::recalculate($user);
        }

        return $assigned;
    }

    /**
     * Remove a fee assignment from a student.
     */
    public static function removeFeeFromStudent(User $user, Fee $fee)
    {
        $transaction = Transaction::where('user_id', $user->id)
            ->where('fee_id', $fee->id)
            ->where('status', 'pending')
            ->first();

        if ($transaction) {
            $transaction->delete();
            AccountService::recalculate($user);
            return true;
        }

        return false;
    }
}