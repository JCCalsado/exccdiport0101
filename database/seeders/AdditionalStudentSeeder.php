<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Student;
use App\Models\StudentAssessment;
use App\Models\StudentPaymentTerm;
use App\Models\Account;
use App\Models\Notification;
use App\Enums\UserRoleEnum;

/**
 * AdditionalStudentSeeder
 *
 * Creates 3 additional test students with payment terms:
 * 1. Student with full payment terms and due dates
 * 2. Student with full payment terms and due dates
 * 3. Student with payment terms but NO due dates (for testing)
 *
 * Also creates notifications for admins to manage payment due dates.
 *
 * USAGE:
 * ------
 * Run only this seeder:
 *   php artisan db:seed --class=AdditionalStudentSeeder
 */
class AdditionalStudentSeeder extends Seeder
{
    private $students = [
        ['email' => 'maria.santos@test.com', 'first_name' => 'Maria', 'last_name' => 'Santos', 'student_id' => '2024-0002'],
        ['email' => 'juan.dela.cruz@test.com', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'student_id' => '2024-0003'],
        ['email' => 'ana.garcia@test.com', 'first_name' => 'Ana', 'last_name' => 'Garcia', 'student_id' => '2024-0004', 'no_due_dates' => true],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $this->command->info("\n" . str_repeat("=", 60));
            $this->command->info("CREATING 3 ADDITIONAL TEST STUDENTS");
            $this->command->info(str_repeat("=", 60));

            foreach ($this->students as $index => $studentData) {
                $this->createStudent($studentData, $index + 1);
            }

            $this->createPaymentDueNotifications();

            $this->command->info("\n" . str_repeat("=", 60));
            $this->command->info("✅ ALL STUDENTS CREATED SUCCESSFULLY");
            $this->command->info(str_repeat("=", 60) . "\n");
        });
    }

    /**
     * Create a test student with assessment and payment terms
     */
    private function createStudent(array $data, int $number): void
    {
        $email = $data['email'];
        $firstName = $data['first_name'];
        $lastName = $data['last_name'];
        $studentId = $data['student_id'];
        $noDueDates = $data['no_due_dates'] ?? false;

        // Check if user already exists
        $user = User::where('email', $email)->first();

        if (!$user) {
            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role' => UserRoleEnum::STUDENT->value,
                'student_id' => $studentId,
            ]);

            // Create account
            Account::create([
                'user_id' => $user->id,
                'balance' => 0,
            ]);

            $this->command->info("\n✓ User created: {$email} (ID: {$user->id})");
        } else {
            $this->command->info("\n✓ User already exists: {$email}");
        }

        // Check if student record exists
        $student = Student::where('user_id', $user->id)->first();

        if (!$student) {
            $student = Student::create([
                'user_id' => $user->id,
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'student_id' => $studentId,
                'course' => 'Computer Science',
                'year_level' => '2nd Year',
                'total_balance' => 15000,
                'enrollment_status' => 'active',
            ]);

            $this->command->info("  Student record created (ID: {$student->id})");
        }

        // Create assessment
        $currentYear = now()->year;
        $currentMonth = now()->month;
        $semester = $currentMonth >= 6 ? '1st Sem' : '2nd Sem';
        $schoolYear = $currentMonth >= 6
            ? "{$currentYear}-" . ($currentYear + 1)
            : ($currentYear - 1) . "-{$currentYear}";

        $assessment = StudentAssessment::where('user_id', $user->id)
            ->where('semester', $semester)
            ->where('school_year', $schoolYear)
            ->first();

        if (!$assessment) {
            $tuitionFee = 12000.00;
            $otherFees = 3540.00;
            $totalAssessment = $tuitionFee + $otherFees;

            $assessment = StudentAssessment::create([
                'user_id' => $user->id,
                'assessment_number' => StudentAssessment::generateAssessmentNumber(),
                'year_level' => $student->year_level,
                'semester' => $semester,
                'school_year' => $schoolYear,
                'tuition_fee' => $tuitionFee,
                'other_fees' => $otherFees,
                'total_assessment' => $totalAssessment,
                'status' => 'active',
                'created_by' => 1,
            ]);

            $this->command->info("  Assessment created: {$assessment->assessment_number} (Total: ₱" . number_format($totalAssessment, 2) . ")");

            // Create payment terms
            $this->createPaymentTermsForAssessment($assessment, $noDueDates);

            if ($noDueDates) {
                $this->command->info("  ⚠️  Payment terms created WITHOUT due dates (for testing)");
            }
        }

        $this->command->info("  ✓ Student {$number} ready for testing");
    }

    /**
     * Create standard payment terms for an assessment
     */
    private function createPaymentTermsForAssessment(StudentAssessment $assessment, bool $noDueDates = false): void
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
                $amount = round($assessment->total_assessment * ($termData['percentage'] / 100), 2);
                $totalAmount += $amount;
            }

            // Only set due dates if not the "no due dates" student
            $dueDate = null;
            if (!$noDueDates) {
                $dueDate = match ($termOrder) {
                    1 => $baseDate->copy(),
                    2 => $baseDate->copy()->addWeeks(4),
                    3 => $baseDate->copy()->addWeeks(8),
                    4 => $baseDate->copy()->addWeeks(12),
                    5 => $baseDate->copy()->addWeeks(16),
                    default => $baseDate->copy()->addWeeks($termOrder),
                };
            }

            StudentPaymentTerm::create([
                'student_assessment_id' => $assessment->id,
                'user_id' => $assessment->user_id,
                'term_name' => $termData['name'],
                'term_order' => $termOrder,
                'percentage' => $termData['percentage'],
                'amount' => $amount,
                'balance' => $amount,
                'due_date' => $dueDate, // NULL for student without due dates
                'status' => StudentPaymentTerm::STATUS_PENDING,
                'remarks' => null,
                'paid_date' => null,
            ]);
        }
    }

    /**
     * Create notifications for managing payment due dates
     */
    private function createPaymentDueNotifications(): void
    {
        // Clear existing payment due notifications
        Notification::where('type', 'payment_due')->delete();

        $notifications = [
            [
                'title' => 'Payment Term Due Date Update Required',
                'message' => 'Some students have payment terms without due dates. Please review and set appropriate due dates to ensure students meet payment deadlines.',
                'type' => 'payment_due',
                'target_role' => 'admin',
                'start_date' => now()->toDateString(),
                'end_date' => null,
                'is_active' => true,
            ],
            [
                'title' => 'Upcoming Payment Deadlines - February',
                'message' => 'Remember to monitor payment term due dates for all students. Update dates as needed to align with academic schedules.',
                'type' => 'payment_due',
                'target_role' => 'admin',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(7)->toDateString(),
                'is_active' => true,
            ],
        ];

        foreach ($notifications as $notifData) {
            Notification::create($notifData);
        }

        $this->command->info("\n✓ Payment management notifications created for admins");
    }
}
