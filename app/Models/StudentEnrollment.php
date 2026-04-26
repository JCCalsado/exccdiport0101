<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollment extends Model
{
    protected $fillable = [
        'user_id',
        'subject_id',
        'school_year',
        'semester',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Return subject IDs that a student is actively enrolled in
     * for a specific school year + semester combination.
     *
     * Only 'enrolled' status is blocking.
     * 'dropped' and 'completed' records do NOT prevent re-enrollment.
     *
     * Used for Regular assessment creation — scoped to one specific term.
     *
     * @return int[]
     */
    public static function enrolledSubjectIds(
        int    $userId,
        string $schoolYear,
        string $semester
    ): array {
        return self::where('user_id', $userId)
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->where('status', 'enrolled')
            ->pluck('subject_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    /**
     * Return ALL subject IDs that a student is actively enrolled in
     * across every semester within a given school year.
     *
     * Used for Irregular assessment creation — an Irregular student
     * selects subjects from multiple semesters, so checking only the
     * assessment-level semester would miss subjects the student already
     * enrolled in from a different semester of the same year.
     *
     * @return int[]
     */
    public static function enrolledSubjectIdsForYear(
        int    $userId,
        string $schoolYear
    ): array {
        return self::where('user_id', $userId)
            ->where('school_year', $schoolYear)
            ->where('status', 'enrolled')
            ->pluck('subject_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    /**
     * Calculate total cost for this enrollment record.
     */
    public function getCostAttribute(): float
    {
        return $this->subject->total_cost ?? 0.0;
    }
}