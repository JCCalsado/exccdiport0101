<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentStatusLog extends Model
{
    protected $fillable = [
        'student_id',
        'changed_by',
        'from_status',
        'to_status',
        'reason',
        'action',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }
}