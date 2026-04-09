<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MiscellaneousFee extends Model
{
    protected $table = 'miscellaneous_fees';

    protected $fillable = ['name', 'category', 'amount', 'is_active', 'order'];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get all active miscellaneous fees
     */
    public static function getActive()
    {
        return cache()->remember('misc_fees_active', 3600, function () {
            return self::where('is_active', true)->orderBy('order')->get();
        });
    }

    /**
     * Calculate total of all active misc fees
     */
    public static function getTotal(): float
    {
        return (float) self::getActive()->sum('amount');
    }

    /**
     * Clear all misc fees cache
     */
    public static function clearCache(): void
    {
        cache()->forget('misc_fees_active');
    }
}
