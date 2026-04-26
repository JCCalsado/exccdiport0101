<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fee extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'amount',
        'year_level',
        'semester',
        'school_year',
        'description',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // Scope for active fees
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope for specific term
    public function scopeForTerm($query, $yearLevel, $semester, $schoolYear)
    {
        return $query->where('year_level', $yearLevel)
                     ->where('semester', $semester)
                     ->where('school_year', $schoolYear);
    }

    // Generate unique code
    public static function generateCode($category, $schoolYear, $semester)
    {
        $base = strtoupper(substr($category, 0, 3)) . '-' . $schoolYear . '-' . strtoupper(substr($semester, 0, 3));
        $count = self::where('code', 'like', $base . '%')->count();
        return $count > 0 ? $base . '-' . ($count + 1) : $base;
    }
}