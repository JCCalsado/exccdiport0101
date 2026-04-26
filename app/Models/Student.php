<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    // =========================================================================
    // FILLABLE
    // =========================================================================
    // All personal data (name, email, course, phone, etc.) lives in users table.
    // students holds only enrollment / financial metadata.
    //
    // NOTE: total_balance has been removed from this table (migration
    // 2026_03_17_000001_drop_total_balance_from_students_table).
    // Balance is now exclusively in accounts.balance, written by AccountService.
    // =========================================================================
    protected $fillable = [
        'user_id',
        'student_id',
        'student_number',
        'enrollment_status',
        'enrollment_date',
        'metadata',
    ];

    protected $casts = [
        'enrollment_date' => 'date',
        'metadata'        => 'array',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Transactions via the linked user (cross-table convenience relation). */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_id', 'user_id');
    }

    /** Account record — balance is read through this relation. */
    public function account(): HasOne
    {
        return $this->hasOne(Account::class, 'user_id', 'user_id');
    }

    public function workflowInstances(): MorphMany
    {
        return $this->morphMany(WorkflowInstance::class, 'workflowable');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(StudentAssessment::class, 'user_id', 'user_id');
    }

    public function accountingTransactions(): MorphMany
    {
        return $this->morphMany(AccountingTransaction::class, 'transactionable');
    }

    /** Full audit trail of every status change for this student. */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(StudentStatusLog::class, 'student_id')
                    ->with('changedBy')
                    ->orderByDesc('created_at');
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Full name from the related User.
     * Lazy-loads the user relation if not already loaded.
     */
    public function getFullNameAttribute(): string
    {
        if (! isset($this->relations['user'])) {
            $this->load('user');
        }

        $user = $this->user;
        if (! $user) {
            return 'Unknown Student';
        }

        $parts = array_filter([
            $user->last_name,
            $user->middle_initial ? $user->middle_initial . '.' : null,
            $user->first_name,
        ]);

        return implode(' ', $parts);
    }

    /**
     * Authoritative balance — reads from accounts.balance (single source of truth).
     *
     * This replaces the old getRemainingBalanceAttribute() which recomputed balance
     * directly from transactions every time it was called, causing N+1 query issues
     * and potentially returning a value different from accounts.balance.
     *
     * Usage: $student->balance   → current outstanding amount
     */
    public function getBalanceAttribute(): float
    {
        // Eager-load the account relation if not already loaded.
        if (! isset($this->relations['account'])) {
            $this->load('account');
        }

        return (float) ($this->account?->balance ?? 0);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('enrollment_status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('enrollment_status', 'pending');
    }

    public function scopeOfCourse($query, string $course)
    {
        return $query->whereHas('user', fn ($q) => $q->where('course', $course));
    }

    public function scopeOfYearLevel($query, string $yearLevel)
    {
        return $query->whereHas('user', fn ($q) => $q->where('year_level', $yearLevel));
    }
}