<?php

namespace App\Models;

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
        'due_date',
        'status',
        'remarks',
        'paid_date',
        'carryover_from_term_id',
        'carryover_amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'carryover_amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_date' => 'datetime',
    ];

    // Payment statuses
    const STATUS_PENDING = 'pending';
    const STATUS_PARTIAL = 'partial';
    const STATUS_PAID = 'paid';
    const STATUS_OVERDUE = 'overdue';

    // Term names and orders
    const TERMS = [
        1 => ['name' => 'Upon Registration', 'percentage' => 42.15],
        2 => ['name' => 'Prelim', 'percentage' => 17.86],
        3 => ['name' => 'Midterm', 'percentage' => 17.86],
        4 => ['name' => 'Semi-Final', 'percentage' => 14.88],
        5 => ['name' => 'Final', 'percentage' => 7.25], // Changed from 7.26 to 7.25 (was 100.01%, now 100.00%)
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(StudentAssessment::class, 'student_assessment_id');
    }

    /**
     * Source term if this carries over balance
     */
    public function carryoverFromTerm(): BelongsTo
    {
        return $this->belongsTo(self::class, 'carryover_from_term_id');
    }

    /**
     * Check if this term is overdue
     */
    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_PENDING && now()->isAfter($this->due_date);
    }

    /**
     * Get total accumulated balance (including carryover)
     */
    public function getAccumulatedBalanceAttribute(): float
    {
        return (float) $this->balance;
    }

    /**
     * Check if balance carries to next term
     */
    public function hasCarryover(): bool
    {
        return $this->remarks && str_contains($this->remarks, 'carries');
    }
}
