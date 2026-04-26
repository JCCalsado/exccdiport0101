<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\Workflow;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Database\Seeder;

class WorkflowInstanceSeeder extends Seeder
{
    public function run(): void
    {
        $workflowService = app(WorkflowService::class);
        
        // Get admin user for initiating workflows
        $admin = User::where('role', 'admin')->first();
        
        if (!$admin) {
            $this->command->warn('⚠️  No admin user found. Skipping workflow instance creation.');
            return;
        }

        // Get student enrollment workflow
        $studentWorkflow = Workflow::where('type', 'student')
            ->where('name', 'like', '%enrollment%')
            ->first();

        if (!$studentWorkflow) {
            $this->command->warn('⚠️  No student enrollment workflow found. Skipping student workflow instances.');
        } else {
            // Create workflow instances for some pending students
            $pendingStudents = Student::where('enrollment_status', 'pending')
                ->limit(10)
                ->get();

            $this->command->info("   Creating workflow instances for {$pendingStudents->count()} pending students...");

            foreach ($pendingStudents as $student) {
                try {
                    $instance = $workflowService->startWorkflow(
                        $studentWorkflow,
                        $student,
                        $admin->id
                    );
                    
                    $this->command->info("   ✓ Workflow started for {$student->full_name}");
                    
                    // Randomly advance some workflows for variety
                    if (rand(1, 100) > 50) {
                        $workflowService->advanceWorkflow($instance, $admin->id);
                        $this->command->info("   ↗ Advanced to next step");
                    }
                } catch (\Exception $e) {
                    $this->command->error("   ✗ Failed for {$student->full_name}: {$e->getMessage()}");
                }
            }
        }

        $this->command->newLine();
        
        // Summary
        $totalInstances = \App\Models\WorkflowInstance::count();
        $pendingApprovals = \App\Models\WorkflowApproval::where('status', 'pending')->count();
        
        $this->command->info("✓ Created {$totalInstances} workflow instances");
        $this->command->info("✓ Generated {$pendingApprovals} pending approvals");
    }
}