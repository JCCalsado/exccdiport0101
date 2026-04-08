<?php

/**
 * School Fee Configuration — CCDI (AY 2025-2026)
 * ============================================================================
 * SOURCE OF TRUTH: Rate of Conduct of Consultation, April 2025
 *
 * BILLING MODEL
 * ─────────────
 * Assessment total = (Σ enrolled units × tuition_per_unit)
 *                  + (Σ lab subjects × lab_fee_per_subject)
 *                  + miscellaneous total (fixed per semester)
 *
 * Tuition and lab fees scale with subject load.
 * Dropping a subject reduces the assessment total proportionally.
 * Miscellaneous fees are FIXED per semester regardless of load.
 *
 * CONTROLLER USAGE
 * ─────────────────
 * $rate      = config('fees.tuition_per_unit');       // 364.00
 * $labFee    = config('fees.lab_fee_per_subject');    // 1656.00
 * $miscItems = config('fees.miscellaneous');          // array of {name, category, amount}
 * $miscTotal = collect($miscItems)->sum('amount');    // 6956.00
 *
 * After editing, run: php artisan config:clear
 * ============================================================================
 */

return [

    // =========================================================================
    // RATE SCHEDULE (AY 2025-2026)
    // Source: CCDI Rate of Conduct of Consultation, March 4, 2025
    // Previous rate (AY 2024-2025): ₱317.00/unit, lab ₱1,440.00 — increased 15%
    // =========================================================================

    // Tuition charged per enrolled unit across all subjects
    'tuition_per_unit' => 364.00,

    // Laboratory fee charged once per subject where has_lab = true
    // Regardless of how many lab units the subject carries
    'lab_fee_per_subject' => 1656.00,

    // =========================================================================
    // MISCELLANEOUS FEES — FIXED PER SEMESTER
    // Charged every semester regardless of how many subjects are enrolled.
    // Total: ₱6,956.00
    // =========================================================================
    'miscellaneous' => [
        ['name' => 'Registration Fee',      'category' => 'Miscellaneous', 'amount' => 0.00],
        ['name' => 'LMS Fee',               'category' => 'Miscellaneous', 'amount' => 450.00],
        ['name' => 'Library Fee',           'category' => 'Miscellaneous', 'amount' => 450.00],
        ['name' => 'Athletic Fee',          'category' => 'Miscellaneous', 'amount' => 550.00],
        ['name' => 'PRISAA Fee',            'category' => 'Miscellaneous', 'amount' => 300.00],
        ['name' => 'Publication Fee',       'category' => 'Miscellaneous', 'amount' => 200.00],
        ['name' => 'Audio-Visual Fee',      'category' => 'Miscellaneous', 'amount' => 250.00],
        ['name' => 'ID Fee',                'category' => 'Miscellaneous', 'amount' => 300.00],
        ['name' => 'BICCS/PCCL/League Fee', 'category' => 'Miscellaneous', 'amount' => 150.00],
        ['name' => 'Faculty Development',   'category' => 'Miscellaneous', 'amount' => 250.00],
        ['name' => 'Guidance Services',     'category' => 'Miscellaneous', 'amount' => 225.00],
        ['name' => 'Medical Fee',           'category' => 'Other',         'amount' => 300.00],
        ['name' => 'Insurance Fee',         'category' => 'Other',         'amount' => 100.00],
        ['name' => 'Cultural Arts Fee',     'category' => 'Other',         'amount' => 175.00],
        ['name' => 'Maintenance Fee',       'category' => 'Other',         'amount' => 400.00],
        // Total: 6,956.00
    ],

    // =========================================================================
    // FEE CATEGORIES
    // =========================================================================
    'categories' => [
        'Tuition',
        'Laboratory',
        'Miscellaneous',
        'Other',
    ],

    
    // =========================================================================
    // PAYMENT TERM DEFINITIONS
    // Percentages must sum to exactly 100.
    // Last term absorbs cent-level rounding in StudentFeeController.
    // =========================================================================
    // =========================================================================
    // PAYMENT TERM DEFINITIONS — Updated per CCDI directive
    // Distribution: Registration 30%, Prelim 21%, Midterm 21%, Semi-Final 18%,
    // Final 10%. Sum = 100%. Last term absorbs cent-level rounding differences.
    // IMPORTANT: Changing these values only affects NEW assessments created
    // after running `php artisan config:clear`. Existing student_payment_terms
    // rows in the database are NOT retroactively updated.
    // =========================================================================
    'terms' => [
        1 => ['name' => 'Upon Registration', 'percentage' => 30.00],
        2 => ['name' => 'Prelim',            'percentage' => 21.00],
        3 => ['name' => 'Midterm',           'percentage' => 21.00],
        4 => ['name' => 'Semi-Final',        'percentage' => 18.00],
        5 => ['name' => 'Final',             'percentage' => 10.00],
    ],

];