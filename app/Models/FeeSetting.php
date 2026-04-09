<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FeeSetting extends Model
{
    protected $table = 'fee_settings';

    protected $fillable = ['key', 'label', 'amount', 'category', 'is_active'];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    private const CACHE_KEY = 'fee_settings_all';
    private const CACHE_TTL = 3600;

    /**
     * Boot: bust cache on any change
     */
    protected static function boot(): void
    {
        parent::boot();
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Get all active settings keyed by 'key' column, cached for 1 hour.
     */
    public static function allActive()
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return self::where('is_active', true)->get()->keyBy('key');
        });
    }

    /**
     * Build the fee array expected by StudentFeeController.
     * Replaces all config('fees.*') calls.
     */
    public static function forController(): array
    {
        $settings = self::allActive();

        $miscellaneous = [];
        $other = [];

        foreach ($settings as $s) {
            if ($s->category === 'miscellaneous') {
                $miscellaneous[] = [
                    'name' => $s->label,
                    'category' => 'Miscellaneous',
                    'amount' => (float) $s->amount,
                ];
            } elseif ($s->category === 'other') {
                $other[] = [
                    'name' => $s->label,
                    'category' => 'Other',
                    'amount' => (float) $s->amount,
                ];
            }
        }

        $terms = [];
        for ($i = 1; $i <= 5; $i++) {
            $key = "term_{$i}_pct";
            if (isset($settings[$key])) {
                $terms[$i] = [
                    'name' => $settings[$key]->label,
                    'percentage' => (float) $settings[$key]->amount,
                ];
            }
        }

        return [
            'tuition_per_unit' => (float) ($settings['tuition_per_unit']->amount ?? 364.00),
            'lab_fee_per_subject' => (float) ($settings['lab_fee_per_subject']->amount ?? 1656.00),
            'miscellaneous' => $miscellaneous,
            'other' => $other,
            'terms' => $terms ?: config('fees.terms'),
        ];
    }
}
