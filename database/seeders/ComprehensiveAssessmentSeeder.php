<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Fee;
use App\Models\StudentAssessment;
use App\Models\StudentPaymentTerm;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * ComprehensiveAssessmentSeeder
 *
 * Creates complete assessments for every Year Level × Semester combination.
 * Each assessment includes:
 *   - A detailed fee_breakdown (mirrors the Admin/Accounting Fees Breakdown view)
 *   - 5 Payment Terms: Upon Registration, Prelim, Midterm, Semi-Final, Final
 *
 * Students start with NO assessment — this seeder rebuilds them all fresh.
 */
class ComprehensiveAssessmentSeeder extends Seeder
{
    private string $schoolYear = '2025-2026';

    /**
     * Detailed fee breakdown per year level × semester.
     * Categories: Academic, Laboratory, Miscellaneous, Other
     * (mirrors the Fees Breakdown panel used by Admin & Accounting)
     */
    private array $feeStructure = [
        '1st Year' => [
            '1st Sem' => [
                ['category' => 'Academic',      'name' => 'Tuition Fee',          'amount' => 15000.00],
                ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' => 1500.00],
                ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' => 500.00],
                ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' => 600.00],
                ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' => 200.00],
                ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' => 200.00],
                ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' => 300.00],
                ['category' => 'Other',         'name' => 'ICT Fee',              'amount' => 400.00],
                ['category' => 'Other',         'name' => 'Medical/Dental Fee',   'amount' => 300.00],
            ],
            '2nd Sem' => [
                ['category' => 'Academic',      'name' => 'Tuition Fee',          'amount' => 15000.00],
                ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' => 1500.00],
                ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' => 400.00],
                ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' => 600.00],
                ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' => 200.00],
                ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' => 200.00],
                ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' => 300.00],
                ['category' => 'Other',         'name' => 'ICT Fee',              'amount' => 400.00],
                ['category' => 'Other',         'name' => 'Medical/Dental Fee',   'amount' => 300.00],
            ],
        ],
        '2nd Year' => [
            '1st Sem' => [
                ['category' => 'Academic',      'name' => 'Tuition Fee',          'amount' => 16500.00],
                ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' => 1800.00],
                ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' => 500.00],
                ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' => 600.00],
                ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' => 200.00],
                ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' => 200.00],
                ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' => 300.00],
                ['category' => 'Other',         'name' => 'ICT Fee',              'amount' => 400.00],
                ['category' => 'Other',         'name' => 'Medical/Dental Fee',   'amount' => 300.00],
            ],
            '2nd Sem' => [
                ['category' => 'Academic',      'name' => 'Tuition Fee',          'amount' => 16500.00],
                ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' => 1800.00],
                ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' => 400.00],
                ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' => 600.00],
                ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' => 200.00],
                ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' => 200.00],
                ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' => 300.00],
                ['category' => 'Other',         'name' => 'ICT Fee',              'amount' => 400.00],
                ['category' => 'Other',         'name' => 'Medical/Dental Fee',   'amount' => 300.00],
            ],
        ],
        '3rd Year' => [
            '1st Sem' => [
                ['category' => 'Academic',      'name' => 'Tuition Fee',          'amount' => 18000.00],
                ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' => 2000.00],
                ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' => 500.00],
                ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' => 700.00],
                ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' => 200.00],
                ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' => 200.00],
                ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' => 300.00],
                ['category' => 'Other',         'name' => 'ICT Fee',              'amount' => 500.00],
                ['category' => 'Other',         'name' => 'Medical/Dental Fee',   'amount' => 300.00],
            ],
            '2nd Sem' => [
                ['category' => 'Academic',      'name' => 'Tuition Fee',          'amount' => 18000.00],
                ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' => 2000.00],
                ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' => 400.00],
                ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' => 700.00],
                ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' => 200.00],
                ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' => 200.00],
                ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' => 300.00],
                ['category' => 'Other',         'name' => 'ICT Fee',              'amount' => 500.00],
                ['category' => 'Other',         'name' => 'Medical/Dental Fee',   'amount' => 300.00],
            ],
        ],
        '4th Year' => [
            '1st Sem' => [
                ['category' => 'Academic',      'name' => 'Tuition Fee',          'amount' => 20000.00],
                ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' => 2200.00],
                ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' => 500.00],
                ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' => 800.00],
                ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' => 200.00],
                ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' => 200.00],
                ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' => 300.00],
                ['category' => 'Other',         'name' => 'ICT Fee',              'amount' => 500.00],
                ['category' => 'Other',         'name' => 'Medical/Dental Fee',   'amount' => 300.00],
            ],
            '2nd Sem' => [
                ['category' => 'Academic',      'name' => 'Tuition Fee',          'amount' => 20000.00],
                ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' => 2200.00],
                ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' => 400.00],
                ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' => 800.00],
                ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' => 200.00],
                ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' => 200.00],
                ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' => 300.00],
                ['category' => 'Other',         'name' => 'ICT Fee',              'amount' => 500.00],
                ['category' => 'Other',         'name' => 'Medical/Dental Fee',   'amount' => 300.00],
            ],
        ],
    ];

    /**
     * 5 Payment Terms (as used by StudentPaymentTerm::TERMS).
     * Percentages sum to exactly 100.00%.
     */
    private array $termDefinitions = [
        1 => ['name' => 'Upon Registration', 'percentage' => 42.15],
        2 => ['name' => 'Prelim',            'percentage' => 17.86],
        3 => ['name' => 'Midterm',           'percentage' => 17.86],
        4 => ['name' => 'Semi-Final',        'percentage' => 14.88],
        5 => ['name' => 'Final',             'percentage' => 7.25],
    ];

    // ─────────────────────────────────────────────────────────────────
    public function run(): void
    {
        $admin   = User::where('role', 'admin')->first();
        $adminId = $admin?->id ?? 1;

        // Step 1 — Clear student assessment data (students start clean)
        $this->command->info('🗑️  Clearing existing assessments & payment terms for all students...');

        $studentIds = User::where('role', 'student')->pluck('id');

        StudentPaymentTerm::whereIn('user_id', $studentIds)->delete();
        StudentAssessment::whereIn('user_id', $studentIds)->delete();
        Transaction::whereIn('user_id', $studentIds)->where('kind', 'charge')->delete();

        $this->command->info('✓ Cleared. Students now have ZERO assessments.');
        $this->command->newLine();

        // Step 2 — Re-seed Fees table with full breakdown
        $this->command->info('💰 Seeding Fees table (full breakdown for all Year × Sem)...');
        Fee::query()->delete();
        $this->seedFeesTable();
        $this->command->info('✓ Fees table seeded: ' . Fee::count() . ' fee records created.');
        $this->command->newLine();

        // Step 3 — Generate assessments for every student
        $students = User::where('role', 'student')
            ->whereNotNull('year_level')
            ->get();

        $this->command->info("📋 Creating assessments for {$students->count()} students (1st Sem + 2nd Sem per student)...");

        $created  = 0;
        $skipped  = 0;
        $semesters = ['1st Sem', '2nd Sem'];

        foreach ($students as $student) {
            if (empty($student->year_level) || !isset($this->feeStructure[$student->year_level])) {
                $skipped++;
                continue;
            }

            foreach ($semesters as $semester) {
                $this->createStudentAssessment($student, $semester, $adminId);
                $created++;
            }
        }

        $this->command->info("✓ Created {$created} assessments ({$students->count()} students × 2 semesters).");
        if ($skipped > 0) {
            $this->command->warn("  ⚠  Skipped {$skipped} students with missing/unknown year_level.");
        }

        $this->command->newLine();
        $termCount = StudentPaymentTerm::count();
        $this->command->info("✅ Done! Summary:");
        $this->command->table(
            ['Item', 'Count'],
            [
                ['Fee Records',       Fee::count()],
                ['Assessments',       StudentAssessment::count()],
                ['Payment Terms (5 per assessment)', $termCount],
                ['Charge Transactions', Transaction::whereIn('user_id', $studentIds)->where('kind', 'charge')->count()],
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // Seed the fees table — one row per fee item per Year × Semester
    // ─────────────────────────────────────────────────────────────────
    private function seedFeesTable(): void
    {
        foreach ($this->feeStructure as $yearLevel => $semesters) {
            foreach ($semesters as $semester => $fees) {
                foreach ($fees as $fee) {
                    // Build a compact unique code
                    $yrSlug  = str_replace([' ', 'nd', 'rd', 'th', 'st'], ['', '', '', '', ''], $yearLevel); // 1Year
                    $semSlug = str_replace([' ', 'nd', 'st'], ['', '', ''], $semester); // 1Sem
                    $catSlug = strtoupper(substr($fee['category'], 0, 3)); // ACA / LAB / MIS / OTH
                    $nameSlug = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $fee['name']), 0, 6));
                    $code    = "{$catSlug}-{$yrSlug}-{$semSlug}-{$nameSlug}";

                    Fee::create([
                        'code'        => $code,
                        'name'        => $fee['name'],
                        'category'    => $fee['category'],
                        'amount'      => $fee['amount'],
                        'year_level'  => $yearLevel,
                        'semester'    => $semester,
                        'school_year' => $this->schoolYear,
                        'description' => "{$fee['name']} for {$yearLevel} {$semester} {$this->schoolYear}",
                        'is_active'   => true,
                    ]);
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Create one full assessment for a student + semester
    // ─────────────────────────────────────────────────────────────────
    private function createStudentAssessment(User $student, string $semester, int $adminId): void
    {
        $yearLevel    = $student->year_level;
        $fees         = $this->feeStructure[$yearLevel][$semester];
        $tuitionFee   = 0.00;
        $otherFees    = 0.00;
        $feeBreakdown = [];

        foreach ($fees as $fee) {
            $feeBreakdown[] = [
                'category'    => $fee['category'],
                'name'        => $fee['name'],
                'amount'      => $fee['amount'],
                'description' => "{$fee['name']} — {$yearLevel} {$semester} {$this->schoolYear}",
            ];

            if ($fee['category'] === 'Academic') {
                $tuitionFee += $fee['amount'];
            } else {
                $otherFees += $fee['amount'];
            }
        }

        $totalAssessment = round($tuitionFee + $otherFees, 2);

        // ── Assessment record ──────────────────────────────────────────
        $assessment = StudentAssessment::create([
            'user_id'           => $student->id,
            'assessment_number' => StudentAssessment::generateAssessmentNumber(),
            'year_level'        => $yearLevel,
            'semester'          => $semester,
            'school_year'       => $this->schoolYear,
            'tuition_fee'       => $tuitionFee,
            'other_fees'        => $otherFees,
            'total_assessment'  => $totalAssessment,
            'subjects'          => [],
            'fee_breakdown'     => $feeBreakdown,
            'status'            => 'active',
            'created_by'        => $adminId,
        ]);

        // ── Charge transactions (one per fee category) ─────────────────
        $yearNum = substr($this->schoolYear, 0, 4); // "2025"
        $grouped = collect($fees)->groupBy('category');

        foreach ($grouped as $category => $items) {
            $categoryTotal = $items->sum('amount');
            $itemNames     = $items->pluck('name')->implode(', ');

            Transaction::create([
                'user_id'   => $student->id,
                'reference' => strtoupper(substr($category, 0, 4)) . '-' . strtoupper(Str::random(8)),
                'kind'      => 'charge',
                'type'      => $category,
                'year'      => $yearNum,
                'semester'  => $semester,
                'amount'    => $categoryTotal,
                'status'    => 'pending',
                'meta'      => [
                    'assessment_id' => $assessment->id,
                    'description'   => "{$itemNames} — {$yearLevel} {$semester} {$this->schoolYear}",
                    'items'         => $items->values()->toArray(),
                ],
            ]);
        }

        // ── 5 Payment Terms ────────────────────────────────────────────
        $this->createPaymentTerms($assessment, $student, $semester, $totalAssessment);
    }

    // ─────────────────────────────────────────────────────────────────
    // Create 5 payment terms for an assessment
    // ─────────────────────────────────────────────────────────────────
    private function createPaymentTerms(
        StudentAssessment $assessment,
        User              $student,
        string            $semester,
        float             $totalAssessment
    ): void {
        // Anchor due dates to semester start
        $semStart = ($semester === '1st Sem')
            ? Carbon::create(2025, 8, 1)   // 1st Semester: starts August
            : Carbon::create(2026, 1, 5);  // 2nd Semester: starts January

        $dueDates = [
            1 => $semStart->copy(),                     // Term 1 — Registration Day
            2 => $semStart->copy()->addWeeks(6),        // Term 2 — Prelim (~6 wks)
            3 => $semStart->copy()->addWeeks(12),       // Term 3 — Midterm (~12 wks)
            4 => $semStart->copy()->addWeeks(16),       // Term 4 — Semi-Final (~16 wks)
            5 => $semStart->copy()->addWeeks(19),       // Term 5 — Final (~19 wks)
        ];

        $allocated = 0.00;
        $lastOrder = array_key_last($this->termDefinitions); // 5

        foreach ($this->termDefinitions as $order => $term) {
            // Final term absorbs any rounding remainder to ensure exact total
            if ($order === $lastOrder) {
                $termAmount = round($totalAssessment - $allocated, 2);
            } else {
                $termAmount = round(($term['percentage'] / 100) * $totalAssessment, 2);
                $allocated += $termAmount;
            }

            StudentPaymentTerm::create([
                'student_assessment_id'  => $assessment->id,
                'user_id'                => $student->id,
                'term_name'              => $term['name'],
                'term_order'             => $order,
                'percentage'             => $term['percentage'],
                'amount'                 => $termAmount,
                'balance'                => $termAmount,   // Fully unpaid — no payments yet
                'due_date'               => $dueDates[$order]->toDateString(),
                'status'                 => StudentPaymentTerm::STATUS_PENDING,
                'remarks'                => null,
                'paid_date'              => null,
                'carryover_from_term_id' => null,
                'carryover_amount'       => 0.00,
            ]);
        }
    }
}