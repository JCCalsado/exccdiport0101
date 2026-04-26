<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPaymentTerm extends Model
{
    protected $fillable = [
        'student_assessment_id',
        'term_name',
        'term_order',
        'percentage',
        'amount',
        'balance',
        'payment_intent_id',
        'due_date',
        'status',
        'remarks',
        'paid_date',
        'carryover_from_term_id',
        'carryover_amount',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'balance'          => 'decimal:2',
        'carryover_amount' => 'decimal:2',
        'due_date'         => 'date',
        'paid_date'        => 'datetime',
    ];

    // ── Payment term statuses — use PaymentStatus enum values ─────────────────
    // These constants are kept as string aliases so any existing code that
    // references StudentPaymentTerm::STATUS_* continues to work without
    // a mass find-and-replace. New code should use PaymentStatus::* directly.
    const STATUS_PENDING = PaymentStatus::PENDING->value;   // 'pending'
    const STATUS_PARTIAL = PaymentStatus::PARTIAL->value;   // 'partial'
    const STATUS_PAID    = PaymentStatus::PAID->value;      // 'paid'
    const STATUS_OVERDUE = 'overdue';                       // not in PaymentStatus (display-only flag)

    /**
     * ⚠️ DEPRECATED: StudentPaymentTerm::TERMS is OUT OF SYNC with config/fees.php
     *
     * The percentages below are stale and are NOT used by the application.
     * Use config('fees.terms') instead, which is the authoritative source.
     *
     * This constant exists only for backward compatibility and should not
     * be referenced in new code. It will be removed in a future migration.
     *
     * @deprecated Use config('fees.terms') instead
     */
    const TERMS = [
        1 => ['name' => 'Upon Registration', 'percentage' => 42.15],
        2 => ['name' => 'Prelim',            'percentage' => 17.86],
        3 => ['name' => 'Midterm',           'percentage' => 17.86],
        4 => ['name' => 'Semi-Final',        'percentage' => 14.88],
        5 => ['name' => 'Final',             'percentage' =>  7.25],
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // RELATIONS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * The assessment this term belongs to.
     * Use term → assessment → user to reach the owning student.
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(StudentAssessment::class, 'student_assessment_id');
    }

    /**
     * Source term if this carries over balance.
     */
    public function carryoverFromTerm(): BelongsTo
    {
        return $this->belongsTo(self::class, 'carryover_from_term_id');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check if this term is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_PENDING && now()->isAfter($this->due_date);
    }

    /**
     * Get total accumulated balance (including carryover).
     */
    public function getAccumulatedBalanceAttribute(): float
    {
        return (float) $this->balance;
    }

    /**
     * Check if balance carries to next term.
     */
    public function hasCarryover(): bool
    {
        return $this->remarks && str_contains($this->remarks, 'carries');
    }
}