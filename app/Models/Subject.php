<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $fillable = [
        'code',
        'name',
        'units',
        'lec_units',
        'lab_units',
        'price_per_unit',
        'year_level',
        'semester',
        'course',
        'description',
        'has_lab',
        'lab_fee',
        'is_active',
    ];

    protected $casts = [
        'units'          => 'integer',
        'lec_units'      => 'integer',
        'lab_units'      => 'integer',
        'price_per_unit' => 'decimal:2',
        'lab_fee'        => 'decimal:2',
        'has_lab'        => 'boolean',
        'is_active'      => 'boolean',
    ];

    /**
     * Get total units (LEC + LAB combined).
     * Used for assessments to calculate total credit hours displayed to the student.
     */
    public function getTotalUnitsAttribute(): int
    {
        return ($this->lec_units ?? 0) + ($this->lab_units ?? 0);
    }

    /**
     * Get the computed total cost for this subject.
     *
     * FIX #3: Was using the deprecated `units` column and `price_per_unit` from the
     * subjects table. Both are unreliable since:
     *   - `units` is no longer the billing source (replaced by lec_units / lab_units)
     *   - `price_per_unit` on the subjects row is not kept in sync with config('fees.tuition_per_unit')
     *
     * Now matches the billing model used throughout the system:
     *   - Tuition  = lec_units × config('fees.tuition_per_unit')      (per lecture unit)
     *   - Lab      = lab_units > 0 ? config('fees.lab_fee_per_subject') : 0  (flat per subject)
     *   - Total    = Tuition + Lab
     *
     * This accessor is not called by buildSubjectMap() (which computes inline),
     * but is used in seeders, tests, and any future feature that calls $subject->total_cost.
     */
    public function getTotalCostAttribute(): float
    {
        $rate   = (float) config('fees.tuition_per_unit',    364.00);
        $labFee = (float) config('fees.lab_fee_per_subject', 1656.00);

        $tuition = ($this->lec_units ?? 0) * $rate;
        $lab     = ($this->lab_units ?? 0) > 0 ? $labFee : 0.0;

        return round($tuition + $lab, 2);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    // Scope for active subjects
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope for specific term and course
    public function scopeForTerm($query, $yearLevel, $semester, $course)
    {
        return $query->where('year_level', $yearLevel)
                     ->where('semester', $semester)
                     ->where('course', $course);
    }
}