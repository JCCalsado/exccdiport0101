<?php

/**
 * CCDI Fee Configuration — AY 2025-2026
 *
 * Source: Rate of Conduct of Consultation, March 4, 2025
 * Approved increase of 15% from AY 2024-2025 rates.
 *
 * To update rates for a new school year:
 *   1. Change the values below
 *   2. Run: php artisan config:clear
 *   No other code changes required.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Tuition Rate
    |--------------------------------------------------------------------------
    | Charged per lecture unit enrolled.
    | AY 2024-2025: ₱317.00  →  AY 2025-2026: ₱364.00 (+15%)
    */
    'tuition_per_lec_unit' => env('CCDI_TUITION_PER_UNIT', 364.00),

    /*
    |--------------------------------------------------------------------------
    | Laboratory Fee
    |--------------------------------------------------------------------------
    | Charged ONCE per subject that has a laboratory component.
    | This is NOT per lab unit — it's per lab subject.
    | AY 2024-2025: ₱1,440.00  →  AY 2025-2026: ₱1,656.00 (+15%)
    */
    'lab_fee_per_subject' => env('CCDI_LAB_FEE_PER_SUBJECT', 1656.00),

    /*
    |--------------------------------------------------------------------------
    | Miscellaneous Fees (Fixed Per Semester)
    |--------------------------------------------------------------------------
    | Charged once per semester regardless of subject load.
    | This is the sum of all line items in the misc fee schedule.
    |
    | Breakdown:
    |   Entrep Fee          ₱600
    |   Registration Fee    ₱600
    |   LMS                 ₱450
    |   Library Fee         ₱450
    |   Athletic Fee        ₱550
    |   PRISAA              ₱300
    |   Publication Fee     ₱200
    |   Audio-Visual Fee    ₱250
    |   ID                  ₱300
    |   BICCS/PCCL/League   ₱150
    |   Faculty Development ₱250
    |   Guidance Services   ₱225
    |   Medical             ₱300
    |   Insurance Fee       ₱100
    |   Cultural Arts Fee   ₱175
    |   Maintenance Fee     ₱400
    |   ─────────────────────────
    |   TOTAL               ₱5,300
    |
    | NOTE: Laboratory fee (₱1,656 per lab subject) is billed separately
    | via lab_fee_per_subject above and is NOT included in this total.
    */
    'misc_fee_fixed' => env('CCDI_MISC_FEE', 5300.00),

    /*
    |--------------------------------------------------------------------------
    | Payment Terms
    |--------------------------------------------------------------------------
    | How the total assessment is split into payment terms.
    | Percentages must sum to 100.
    |
    | term_name   → label shown to student/accounting
    | percentage  → portion of total due at that term (0-100)
    */
    'payment_terms' => [
        ['term_name' => 'Upon Registration', 'term_order' => 1, 'percentage' => 25],
        ['term_name' => 'Prelim',            'term_order' => 2, 'percentage' => 25],
        ['term_name' => 'Midterm',           'term_order' => 3, 'percentage' => 25],
        ['term_name' => 'Semi-Final',        'term_order' => 4, 'percentage' => 12.5],
        ['term_name' => 'Final',             'term_order' => 5, 'percentage' => 12.5],
    ],

];