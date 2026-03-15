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

    // ============================================
    // FILLABLE FIELDS (Student-specific only)
    // ============================================
    // All personal data (name, email, course, phone, etc.) is stored in users table.
    // This students table only contains student-specific enrollment and financial data.
    protected $fillable = [
        'user_id',
        'student_id',
        'student_number',
        'total_balance',
        'enrollment_status',
        'enrollment_date',
        'metadata',
    ];

    // ============================================
    // CASTS
    // ============================================
    protected $casts = [
        'enrollment_date' => 'date',
        'total_balance' => 'decimal:2',
        'metadata' => 'array',
    ];

    // ============================================
    // RELATIONSHIPS - EXISTING (Your original code)
    // ============================================
    
    /**
     * Student belongs to a User account
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Student has many payments
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Student has many transactions via the linked user
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_id', 'user_id');
    }

    /**
     * Student has one account
     */
    public function account(): HasOne
    {
        return $this->hasOne(Account::class, 'user_id', 'user_id');
    }

    // ============================================
    // RELATIONSHIPS - NEW (Workflow integration)
    // ============================================
    
    /**
     * Student can have multiple workflow instances
     * (enrollment workflows, academic workflows, etc.)
     */
    public function workflowInstances(): MorphMany
    {
        return $this->morphMany(WorkflowInstance::class, 'workflowable');
    }

    /**
     * Student has many assessments
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(StudentAssessment::class, 'user_id', 'user_id');
    }

    /**
     * Student can have accounting transactions
     * (invoices, payments, refunds linked to this student)
     */
    public function accountingTransactions(): MorphMany
    {
        return $this->morphMany(AccountingTransaction::class, 'transactionable');
    }

    // ============================================
    // ACCESSORS & COMPUTED ATTRIBUTES
    // ============================================
    
    /**
     * Get full name of student from user relationship
     */
    public function getFullNameAttribute(): string
    {
        // Lazy load user if not already loaded
        if (!isset($this->relations['user'])) {
            $this->load('user');
        }
        
        $user = $this->user;
        if (!$user) {
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
     * Calculate remaining balance (from transactions)
     */
    public function getRemainingBalanceAttribute()
    {
        $charges = $this->transactions()->where('kind', 'charge')->sum('amount');
        $payments = $this->transactions()->where('kind', 'payment')->where('status', 'paid')->sum('amount');
        return round(max(0, (float)$charges - (float)$payments), 2);
    }

    // ============================================
    // QUERY SCOPES
    // ============================================
    
    /**
     * Scope to get only active students
     */
    public function scopeActive($query)
    {
        return $query->where('enrollment_status', 'active');
    }

    /**
     * Scope to get pending enrollment students
     */
    public function scopePending($query)
    {
        return $query->where('enrollment_status', 'pending');
    }

    /**
     * Scope to filter by course (via user relationship)
     */
    public function scopeOfCourse($query, string $course)
    {
        return $query->whereHas('user', function ($q) use ($course) {
            $q->where('course', $course);
        });
    }

    /**
     * Scope to filter by year level (via user relationship)
     */
    public function scopeOfYearLevel($query, string $yearLevel)
    {
        return $query->whereHas('user', function ($q) use ($yearLevel) {
            $q->where('year_level', $yearLevel);
        });
    }
}