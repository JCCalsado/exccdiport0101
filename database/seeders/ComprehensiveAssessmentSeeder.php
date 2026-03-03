<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\StudentAssessment;
use App\Models\Transaction;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * ComprehensiveAssessmentSeeder
 *
 * NOTE: Fee Management and Subject Management have been removed.
 * Assessments are now generated using fixed flat-rate amounts per year level
 * instead of dynamically loading from the fees/subjects tables.
 *
 * ⚠️  Adjust the $assessmentByYearLevel array below to match your
 *     school's actual fee structure before seeding.
 */
class ComprehensiveAssessmentSeeder extends Seeder
{
    /**
     * Fixed assessment amounts per year level.
     * Replaces the old Subject::active() + Fee::active() lookups.
     */
    private array $assessmentByYearLevel = [
        '1st Year' => ['tuition' => 18000.00, 'other_fees' => 2000.00],
        '2nd Year' => ['tuition' => 20000.00, 'other_fees' => 2000.00],
        '3rd Year' => ['tuition' => 22000.00, 'other_fees' => 2000.00],
        '4th Year' => ['tuition' => 24000.00, 'other_fees' => 2000.00],
    ];

    public function run(): void
    {
        $schoolYear = '2025-2026';
        $semester   = '1st Sem';

        $students = User::where('role', 'student')
            ->where('email', 'like', 'student%@ccdi.edu.ph')
            ->get();

        $this->command->info('Generating assessments for ' . $students->count() . ' students...');

        foreach ($students as $student) {
            if (!$student->year_level || !$student->course) {
                continue;
            }

            $rates = $this->assessmentByYearLevel[$student->year_level]
                ?? ['tuition' => 18000.00, 'other_fees' => 2000.00];

            $tuitionFee      = $rates['tuition'];
            $otherFees       = $rates['other_fees'];
            $totalAssessment = $tuitionFee + $otherFees;

            // Create student assessment (no subject/fee FK references)
            $assessment = StudentAssessment::create([
                'user_id'           => $student->id,
                'assessment_number' => StudentAssessment::generateAssessmentNumber(),
                'year_level'        => $student->year_level,
                'semester'          => $semester,
                'school_year'       => $schoolYear,
                'tuition_fee'       => $tuitionFee,
                'other_fees'        => $otherFees,
                'total_assessment'  => $totalAssessment,
                'subjects'          => [], // Empty — subject management disabled
                'fee_breakdown'     => [], // Empty — fee management disabled
                'status'            => 'active',
                'created_by'        => 1,
            ]);

            // Tuition charge transaction
            Transaction::create([
                'user_id'   => $student->id,
                'reference' => 'TUITION-' . strtoupper(Str::random(8)),
                'kind'      => 'charge',
                'type'      => 'Tuition',
                'year'      => '2025',
                'semester'  => $semester,
                'amount'    => $tuitionFee,
                'status'    => 'pending',
                'meta'      => [
                    'assessment_id' => $assessment->id,
                    'description'   => "Tuition Fee - {$student->year_level} {$semester} {$schoolYear}",
                ],
            ]);

            // Miscellaneous fees charge transaction
            Transaction::create([
                'user_id'   => $student->id,
                'reference' => 'MISC-' . strtoupper(Str::random(8)),
                'kind'      => 'charge',
                'type'      => 'Miscellaneous',
                'year'      => '2025',
                'semester'  => $semester,
                'amount'    => $otherFees,
                'status'    => 'pending',
                'meta'      => [
                    'assessment_id' => $assessment->id,
                    'description'   => "Miscellaneous Fees - {$student->year_level} {$semester} {$schoolYear}",
                ],
            ]);

            // Generate payment history for students with lower balances or fully paid
            $currentBalance = abs($student->account->balance ?? 0);

            if ($currentBalance < $totalAssessment) {
                $amountPaid           = $totalAssessment - $currentBalance;
                $numberOfPayments     = rand(1, 3);
                $paymentPerInstallment = $amountPaid / $numberOfPayments;

                for ($i = 0; $i < $numberOfPayments; $i++) {
                    $paymentAmount = $i === ($numberOfPayments - 1)
                        ? $amountPaid - ($paymentPerInstallment * $i)
                        : $paymentPerInstallment;

                    $paymentDate = now()->subDays(rand(1, 60));

                    if ($student->student) {
                        Payment::create([
                            'student_id'       => $student->student->id,
                            'amount'           => $paymentAmount,
                            'payment_method'   => ['cash', 'gcash', 'bank_transfer'][rand(0, 2)],
                            'reference_number' => 'PAY-' . strtoupper(Str::random(10)),
                            'description'      => 'Payment #' . ($i + 1),
                            'status'           => Payment::STATUS_COMPLETED,
                            'paid_at'          => $paymentDate,
                        ]);
                    }

                    Transaction::create([
                        'user_id'         => $student->id,
                        'reference'       => 'PAY-' . strtoupper(Str::random(8)),
                        'payment_channel' => ['cash', 'gcash', 'bank_transfer'][rand(0, 2)],
                        'kind'            => 'payment',
                        'type'            => 'Payment',
                        'year'            => '2025',
                        'semester'        => $semester,
                        'amount'          => $paymentAmount,
                        'status'          => 'paid',
                        'paid_at'         => $paymentDate,
                        'meta'            => [
                            'description' => 'Payment #' . ($i + 1),
                        ],
                    ]);
                }
            }
        }

        $this->command->info('✓ Assessments and transactions generated successfully!');
        $this->command->info('✓ Payment history created for students with partial/full payments');
    }
}