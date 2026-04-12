<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentAssessment extends Model
{
    protected $fillable = [
        'assessment_number',
        'user_id',
        'year_level',
        'semester',
        'school_year',
        'lec_units',        // ← NEW: lecture units (from matriculation form)
        'lab_units',        // ← NEW: lab units (informational)
        'lab_subjects',     // ← NEW: number of subjects with lab (for lab fee billing)
        'status',           // active | archived
        'courser',
    ];

    protected $casts = [
        'lec_units'    => 'integer',
        'lab_units'    => 'integer',
        'lab_subjects' => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentTerms(): HasMany
    {
        return $this->hasMany(StudentPaymentTerm::class, 'student_assessment_id')
            ->orderBy('term_order');
    }

    // ─── Computed Attributes ──────────────────────────────────────────────────

    /**
     * Total units displayed on the UI (LEC + LAB).
     * This matches the "Total Units" column on the matriculation form.
     */
    public function getTotalUnitsAttribute(): int
    {
        return $this->lec_units + $this->lab_units;
    }

    /**
     * Compute the tuition fee for this assessment.
     * Uses the live config value so rate changes take effect immediately.
     */
    public function getTuitionFeeAttribute(): float
    {
        return $this->lec_units * (float) config('fees.tuition_per_lec_unit', 364.00);
    }

    /**
     * Compute the lab fee for this assessment.
     */
    public function getLabFeeAttribute(): float
    {
        return $this->lab_subjects * (float) config('fees.lab_fee_per_subject', 1656.00);
    }

    /**
     * Fixed miscellaneous fees.
     */
    public function getMiscFeeAttribute(): float
    {
        return (float) config('fees.misc_fee_fixed', 5300.00);
    }

    /**
     * Total assessment amount (tuition + lab + misc).
     */
    public function getTotalAssessmentAttribute(): float
    {
        return $this->tuition_fee + $this->lab_fee + $this->misc_fee;
    }

    /**
     * Outstanding balance — sum of unpaid payment term balances.
     * This is the source of truth. Never compute from raw transactions.
     */
    public function getOutstandingBalanceAttribute(): float
    {
        return (float) $this->paymentTerms->sum('balance');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }
}