<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Student;
use App\Models\StudentAssessment;
use App\Models\StudentPaymentTerm;
use App\Models\Transaction;
use App\Models\Account;
use App\Events\PaymentRecorded;
use App\Enums\UserRoleEnum;
use App\Services\AccountService;
use Illuminate\Support\Str;

/**
 * StudentFirstPaymentSeeder
 *
 * Creates a test student with unpaid payment terms ready for manual payment testing.
 * All payment terms remain UNPAID so students can test the payment flow from the UI.
 *
 * USAGE:
 * ------
 * Run all seeders including this one:
 *   php artisan db:seed
 *
 * Run only this seeder:
 *   php artisan db:seed --class=StudentFirstPaymentSeeder
 *
 * STUDENT DETAILS:
 * ----------------
 * Email: jcdc742713@gmail.com
 * Password: password
 * If not found, a test student will be created.
 *
 * EXPECTED OUTCOME:
 * -----------------
 * ✅ One test student account created
 * ✅ One student assessment generated ($15,540 total)
 * ✅ Five unpaid payment terms with standard percentages:
 *    - Upon Registration (42.15%)
 *    - Prelim (17.86%)
 *    - Midterm (17.86%)
 *    - Semi-Final (14.88%)
 *    - Final (7.26%)
 * ✅ All terms remain PENDING status (ready for student to pay)
 * ✅ Carryover system enabled (excess payment carries to next term)
 * ✅ Student can now make selective term payments via UI
 * ✅ No auto-generated transactions
 */
class StudentFirstPaymentSeeder extends Seeder
{
    private static $accountIdCounter = 101;

    public function run(): void
    {
        DB::transaction(function () {
            // ============================================
            // 1. FIND OR CREATE STUDENT
            // ============================================
            $student = $this->getOrCreateStudent();

            if (!$student) {
                $this->command->error('Failed to find or create student');
                return;
            }

            $this->command->info("✓ Student found/created: {$student->email} (ID: {$student->id})");

            // ============================================
            // 2. GET CURRENT SEMESTER ASSESSMENT
            // ============================================
            $assessment = $this->getCurrentSemesterAssessment($student);

            if (!$assessment) {
                $this->command->error('No assessment found and could not create one');
                return;
            }

            // Check if assessment was just created (by looking at created_at timestamp)
            $isNewlyCreated = $assessment->created_at->diffInSeconds(now()) < 5;

            if ($isNewlyCreated) {
                $this->command->info("✓ Assessment CREATED: {$assessment->assessment_number} (Total: " .
                    number_format($assessment->total_assessment, 2) . ")");
                $this->command->info("  └─ Generated 5 payment terms with standard percentages");
            } else {
                $this->command->info("✓ Assessment found: {$assessment->assessment_number} (Total: " .
                    number_format($assessment->total_assessment, 2) . ")");
            }

            // ============================================
            // 3. GET FIRST PAYMENT TERM
            // ============================================
            $firstTerm = StudentPaymentTerm::where('student_assessment_id', $assessment->id)
                ->where('term_order', 1)
                ->first();

            if (!$firstTerm) {
                $this->command->error('First payment term not found for assessment');
                return;
            }

            $this->command->info("✓ First term found: {$firstTerm->term_name} - Amount: " .
                number_format($firstTerm->amount, 2));

            // ============================================
            // 4. VERIFY ALL PAYMENT TERMS ARE UNPAID
            // ============================================
            $allTerms = StudentPaymentTerm::where('student_assessment_id', $assessment->id)
                ->orderBy('term_order')
                ->get();

            $unpaidCount = 0;
            foreach ($allTerms as $term) {
                if ($term->status === StudentPaymentTerm::STATUS_PENDING) {
                    $unpaidCount++;
                    $this->command->info("  • {$term->term_order}. {$term->term_name}: " .
                        number_format($term->amount, 2) . " ({$term->percentage}%) - Status: PENDING");
                }
            }

            $this->command->info("✓ All {$unpaidCount} payment terms are UNPAID and ready for student payments");

            // ============================================
            // 5. STUDENT PAYMENT READY
            // ============================================
            $this->command->info("\n" . str_repeat("=", 60));
            $this->command->info("✅ SEEDER COMPLETED - STUDENT READY FOR TESTING");
            $this->command->info(str_repeat("=", 60));
            $this->command->info("Summary:");
            $this->command->info("  • Student: {$student->email}");
            $this->command->info("  • Assessment: {$assessment->assessment_number}");
            $this->command->info("  • Total Payable: " . number_format($assessment->total_assessment, 2));
            $this->command->info("  • Payment Terms: 5 (all UNPAID)");
            $this->command->info("  • Carryover System: ENABLED");
            $this->command->info("  • Payment Method: Student can now make selective term payments");
            $this->command->info("  • Note: When a student pays more than a term amount,");
            $this->command->info("          excess will carry over to the next term");
            $this->command->info(str_repeat("=", 60) . "\n");
        });
    }

    /**
     * Find student by email or create a test student
     */
    private function getOrCreateStudent(): ?Student
    {
        $email = 'jcdc742713@gmail.com';

        // Try to find existing student
        $student = Student::whereHas('user', function ($query) use ($email) {
            $query->where('email', $email);
        })->first();

        if ($student) {
            return $student;
        }

        // Try to find user first
        $user = User::where('email', $email)->first();

        if ($user) {
            // User exists but no student record - create one
            $accountId = '2025-' . str_pad(self::$accountIdCounter++, 4, '0', STR_PAD_LEFT);
            $student = Student::create([
                'user_id' => $user->id,
                'email' => $email,
                'first_name' => 'Test',
                'last_name' => 'Student',
                'student_id' => $accountId,
                'course' => 'Computer Science',
                'year_level' => '2nd Year',
                'total_balance' => 15000,
                'enrollment_status' => 'active',
            ]);

            return $student;
        }

        // Create new user and student
        $accountId = '2025-' . str_pad(self::$accountIdCounter++, 4, '0', STR_PAD_LEFT);
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'Student',
            'email' => $email,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role' => UserRoleEnum::STUDENT->value,
            'student_id' => $accountId,
        ]);

        // Ensure user has an account
        if (!$user->account) {
            Account::create([
                'user_id' => $user->id,
                'balance' => 0,
            ]);
        }

        $student = Student::create([
            'user_id' => $user->id,
            'email' => $email,
            'first_name' => 'Test',
            'last_name' => 'Student',
            'student_id' => $accountId,
            'course' => 'Computer Science',
            'year_level' => '2nd Year',
            'total_balance' => 15000,
            'enrollment_status' => 'active',
        ]);

        return $student;
    }

    /**
     * Get current semester assessment for student
     */
    private function getCurrentSemesterAssessment(Student $student): ?StudentAssessment
    {
        $currentYear = now()->year;
        $currentMonth = now()->month;

        // Determine semester (1st sem: June-November, 2nd sem: December-May)
        $semester = $currentMonth >= 6 ? '1st Sem' : '2nd Sem';
        $schoolYear = $currentMonth >= 6
            ? "{$currentYear}-" . ($currentYear + 1)
            : ($currentYear - 1) . "-{$currentYear}";

        // Find or create assessment
        $assessment = StudentAssessment::where('user_id', $student->user_id)
            ->where('semester', $semester)
            ->where('school_year', $schoolYear)
            ->orderByDesc('created_at')
            ->first();

        if (!$assessment) {
            // Look for any recent assessment
            $assessment = StudentAssessment::where('user_id', $student->user_id)
                ->orderByDesc('created_at')
                ->first();
        }

        // If still no assessment, create one with payment terms
        if (!$assessment) {
            $assessment = $this->createAssessmentWithPaymentTerms($student, $semester, $schoolYear);
        }

        return $assessment;
    }

    /**
     * Create a new assessment with payment terms for the student
     */
    private function createAssessmentWithPaymentTerms(
        Student $student,
        string $semester,
        string $schoolYear
    ): StudentAssessment {
        // Default assessment amounts
        $tuitionFee = 12000.00;
        $otherFees = 3540.00;
        $totalAssessment = $tuitionFee + $otherFees;

        // Create the assessment
        $assessment = StudentAssessment::create([
            'user_id' => $student->user_id,
            'assessment_number' => StudentAssessment::generateAssessmentNumber(),
            'year_level' => $student->year_level ?? '2nd Year',
            'semester' => $semester,
            'school_year' => $schoolYear,
            'tuition_fee' => $tuitionFee,
            'other_fees' => $otherFees,
            'total_assessment' => $totalAssessment,
            'status' => 'active',
            'created_by' => 1, // Admin user
        ]);

        // Create payment terms for this assessment
        $this->createPaymentTermsForAssessment($assessment);

        // ── FIX: Create a charge Transaction for the full assessment ──────────────
        // AccountService::recalculate() derives account.balance ONLY from the
        // transactions table (kind='charge' minus kind='payment' status='paid').
        // Without a charge transaction, account.balance stays 0 and the frontend
        // displays ₱0.00 instead of ₱15,540 in both Index and Show.
        \App\Models\Transaction::create([
            'user_id'   => $student->user_id,
            'reference' => 'ASSESS-' . strtoupper(Str::random(8)),
            'kind'      => 'charge',
            'type'      => 'Tuition',
            'year'      => explode('-', $schoolYear)[0],
            'semester'  => $semester,
            'amount'    => $totalAssessment,
            'status'    => 'pending',
            'meta'      => [
                'assessment_id' => $assessment->id,
                'description'   => "Assessment Charge - {$semester} {$schoolYear}",
            ],
        ]);

        // Sync account.balance → will equal $totalAssessment (no payments yet)
        AccountService::recalculate($student->user);

        return $assessment;
    }

    /**
     * Create standard payment terms for an assessment
     */
    private function createPaymentTermsForAssessment(StudentAssessment $assessment): void
    {
        $baseDate = now()->startOfMonth();
        $terms = StudentPaymentTerm::TERMS;
        $totalAmount = 0;
        $totalTerms = count($terms);

        foreach ($terms as $termOrder => $termData) {
            // For the last term, use remainder to ensure total equals assessment (no rounding)
            if ($termOrder === $totalTerms) {
                $amount = $assessment->total_assessment - $totalAmount;
            } else {
                // Calculate amount based on percentage
                $amount = round($assessment->total_assessment * ($termData['percentage'] / 100), 2);
                $totalAmount += $amount;
            }

            // Calculate due date based on term order
            $dueDate = match ($termOrder) {
                1 => $baseDate->copy(), // Upon Registration - due immediately
                2 => $baseDate->copy()->addWeeks(4), // Prelim - 4 weeks
                3 => $baseDate->copy()->addWeeks(8), // Midterm - 8 weeks
                4 => $baseDate->copy()->addWeeks(12), // Semi-Final - 12 weeks
                5 => $baseDate->copy()->addWeeks(16), // Final - 16 weeks
                default => $baseDate->copy()->addWeeks($termOrder),
            };

            StudentPaymentTerm::create([
                'student_assessment_id' => $assessment->id,
                'user_id' => $assessment->user_id,
                'term_name' => $termData['name'],
                'term_order' => $termOrder,
                'percentage' => $termData['percentage'],
                'amount' => $amount,
                'balance' => $amount, // Initially full balance
                'due_date' => $dueDate,
                'status' => StudentPaymentTerm::STATUS_PENDING,
                'remarks' => null,
                'paid_date' => null,
            ]);
        }
    }
}