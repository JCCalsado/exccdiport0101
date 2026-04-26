<?php

namespace Database\Seeders;

use App\Enums\UserRoleEnum;
use App\Models\Account;
use App\Models\Student;
use App\Models\StudentAssessment;
use App\Models\User;
use Database\Seeders\Traits\GetAdminUserTrait;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * StudentFirstPaymentSeeder
 *
 * Creates a newly registered student with NO course and NO assessment.
 * Simulates a student who has just enrolled and is waiting for the
 * Accounting/Admin staff to create their first assessment.
 *
 * USAGE:
 * ------
 *   php artisan db:seed --class=StudentFirstPaymentSeeder
 *
 * STUDENT DETAILS:
 * ----------------
 * Email     : newstudent2025@gmail.com
 * Password  : password
 * Year Level: 1st Year
 * Course    : (none — not yet assigned)
 * Assessment: (none — not yet created)
 *
 * EXPECTED OUTCOME:
 * -----------------
 * ✅ One newly registered student (users + students + accounts)
 * ✅ Year level = "1st Year"
 * ✅ Course = null on users table (not yet assigned)
 * ✅ NO StudentAssessment (staff creates it later via UI)
 * ✅ NO payment terms, NO transactions, NO charges
 * ✅ Account balance = ₱0.00
 * ✅ Student status = enrolled
 */
class StudentFirstPaymentSeeder extends Seeder
{
    use GetAdminUserTrait;

    // ── Student credentials ────────────────────────────────────────────────────
    private const STUDENT_EMAIL          = 'newstudent2025@gmail.com';
    private const STUDENT_PASSWORD       = 'password';
    private const STUDENT_LAST_NAME      = 'Dela Cruz';
    private const STUDENT_FIRST_NAME     = 'Juan';
    private const STUDENT_MIDDLE_INITIAL = 'A';
    private const YEAR_LEVEL             = '1st Year';

    // ── Account ID counter fallback ───────────────────────────────────────────
    private static int $accountIdCounter = 200;

    // =========================================================================
    // MAIN ENTRY POINT
    // =========================================================================

    public function run(): void
    {
        DB::transaction(function () {

            // ── 1. User ───────────────────────────────────────────────────────
            $user = $this->findOrCreateUser();

            $this->command->info("✓ User ready      : {$user->email} (ID: {$user->id})");
            $this->command->info("  └─ Year Level   : " . self::YEAR_LEVEL);
            $this->command->info("  └─ Course       : (not assigned)");

            // ── 2. Student record ─────────────────────────────────────────────
            $student = $this->ensureStudentRecord($user);

            $this->command->info("✓ Student record  : student.id = {$student->id}");

            // ── 3. Account row ────────────────────────────────────────────────
            $account = $this->ensureAccount($user);

            $this->command->info("✓ Account ready   : {$account->account_number} (₱0.00)");

            // ── 4. Confirm no assessment ──────────────────────────────────────
            $assessmentCount = StudentAssessment::where('user_id', $user->id)->count();

            if ($assessmentCount > 0) {
                $this->command->warn(
                    "⚠  Student already has {$assessmentCount} assessment(s). " .
                    "This seeder targets students with NO assessment."
                );
            } else {
                $this->command->info("✓ Assessment      : NONE — student awaiting first assessment");
            }

            // ── 5. Summary ────────────────────────────────────────────────────
            $this->printSummary($user, $account);
        });
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Find an existing user by email or create a fresh one.
     * course is intentionally null on the users table.
     */
    private function findOrCreateUser(): User
    {
        $existing = User::where('email', self::STUDENT_EMAIL)->first();

        if ($existing) {
            if ($existing->year_level !== self::YEAR_LEVEL) {
                $existing->update(['year_level' => self::YEAR_LEVEL]);
                $this->command->warn("  ↳ year_level corrected to '" . self::YEAR_LEVEL . "'");
            }
            return $existing;
        }

        $accountId = $this->generateAccountId();

        return User::create([
            'last_name'         => self::STUDENT_LAST_NAME,
            'first_name'        => self::STUDENT_FIRST_NAME,
            'middle_initial'    => self::STUDENT_MIDDLE_INITIAL,
            'email'             => self::STUDENT_EMAIL,
            'password'          => bcrypt(self::STUDENT_PASSWORD),
            'email_verified_at' => now(),
            'role'              => UserRoleEnum::STUDENT->value,
            'account_id'        => $accountId,
            'year_level'        => self::YEAR_LEVEL,
            'course'            => null,    // not yet assigned — users.course IS nullable
            'status'            => 'active',
        ]);
    }

    /**
     * Ensure a Student pivot record exists.
     *
     * students table schema requires:
     *   - student_id  VARCHAR NOT NULL UNIQUE  (CCDI student ID string)
     *   - course      VARCHAR NOT NULL          (placeholder until assigned)
     *   - status      ENUM('enrolled','graduated','inactive')
     *
     * users.course stays null; students.course gets 'N/A' as a placeholder
     * so the NOT NULL constraint is satisfied without assigning a real program.
     */
    private function ensureStudentRecord(User $user): Student
    {
        $existing = Student::where('user_id', $user->id)->first();

        if ($existing) {
            return $existing;
        }

        return Student::create([
            'user_id'          => $user->id,
            'student_id'       => $user->account_id,  // CCDI student ID (e.g. "2026-0200")
            'enrollment_status' => 'active',           // enum: active|pending|suspended|graduated
        ]);
    }

    /**
     * Ensure the user has an Account row for balance tracking.
     * Balance is ₱0.00 — no fees assessed yet.
     */
    private function ensureAccount(User $user): Account
    {
        $account = Account::where('user_id', $user->id)->first();

        if ($account) {
            return $account;
        }

        return Account::create([
            'user_id'        => $user->id,
            'account_number' => $this->generateAccountNumber(),
            'balance'        => 0.00,
        ]);
    }

    /**
     * Generate a unique student account ID (e.g. "2026-0200").
     */
    private function generateAccountId(): string
    {
        $year = now()->year;

        $last = User::where('account_id', 'like', "{$year}-%")
            ->orderByRaw("CAST(SUBSTRING(account_id, 6) AS UNSIGNED) DESC")
            ->value('account_id');

        if ($last) {
            $lastNumber             = (int) (explode('-', $last)[1] ?? self::$accountIdCounter);
            self::$accountIdCounter = $lastNumber + 1;
        }

        return $year . '-' . str_pad(self::$accountIdCounter, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a unique Account number (e.g. "ACC-2026-0042").
     */
    private function generateAccountNumber(): string
    {
        $year = now()->year;

        $last = Account::where('account_number', 'like', "ACC-{$year}-%")
            ->orderByRaw("CAST(SUBSTRING(account_number, 10) AS UNSIGNED) DESC")
            ->value('account_number');

        $next = 1;
        if ($last) {
            $next = ((int) substr($last, strrpos($last, '-') + 1)) + 1;
        }

        return 'ACC-' . $year . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Print the final summary to the console.
     */
    private function printSummary(User $user, Account $account): void
    {
        $line = str_repeat('=', 60);

        $this->command->info('');
        $this->command->info($line);
        $this->command->info('✅  SEEDER COMPLETED — NEWLY REGISTERED STUDENT READY');
        $this->command->info($line);
        $this->command->info('  Name       : ' . $user->name);
        $this->command->info('  Email      : ' . $user->email);
        $this->command->info('  Password   : ' . self::STUDENT_PASSWORD);
        $this->command->info('  Student ID : ' . $user->account_id);
        $this->command->info('  Account#   : ' . $account->account_number);
        $this->command->info('  Year Level : ' . self::YEAR_LEVEL);
        $this->command->info('  Course     : (not yet assigned)');
        $this->command->info('  Assessment : NONE');
        $this->command->info('  Balance    : ₱0.00');
        $this->command->info($line);
        $this->command->info('');
        $this->command->info('NEXT STEPS FOR STAFF:');
        $this->command->info('  1. Log in as Accounting or Admin');
        $this->command->info('  2. Go to Student Fee Management → Create Assessment');
        $this->command->info('  3. Search for this student — Year Level pre-fills "1st Year"');
        $this->command->info('  4. Assign a Course and complete the assessment form');
        $this->command->info($line);
    }
}