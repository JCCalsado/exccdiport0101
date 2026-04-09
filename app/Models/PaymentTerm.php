<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTerm extends Model
{
    protected $table = 'payment_terms';

    protected $fillable = ['term_order', 'term_name', 'percentage'];

    protected $casts = [
        'percentage' => 'decimal:2',
    ];

    /**
     * Get all payment terms in order
     */
    public static function getTerms()
    {
        return cache()->remember('payment_terms', 3600, function () {
            return self::orderBy('term_order')->get();
        });
    }

    /**
     * Validate percentages sum to 100
     */
    public static function validatePercentages(): bool
    {
        $total = self::sum('percentage');
        return abs($total - 100.0) < 0.01; // Allow small rounding errors
    }

    /**
     * Clear payment terms cache
     */
    public static function clearCache(): void
    {
        cache()->forget('payment_terms');
    }
}
