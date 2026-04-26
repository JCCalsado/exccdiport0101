<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\FeeAssignmentService;

class AssignFeesToStudents extends Command
{
    protected $signature = 'fees:assign {semester} {school_year}';

    protected $description = 'Assign fees to all active students for a specific semester';

    public function handle(): int
    {
        $semester = $this->argument('semester');
        $schoolYear = $this->argument('school_year');

        $this->info("Assigning fees for {$semester} - {$schoolYear}...");

        $students = User::where('role', 'student')
            ->where('status', User::STATUS_ACTIVE)
            ->get();

        $totalAssigned = 0;

        foreach ($students as $student) {
            $amount = FeeAssignmentService::assignFeesToStudent($student, $semester, $schoolYear);
            $totalAssigned += $amount;
            $this->info("✓ Assigned ₱{$amount} to {$student->name}");
        }

        $this->info("✅ Total fees assigned: ₱{$totalAssigned}");

        return self::SUCCESS;
    }
}