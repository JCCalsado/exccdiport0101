<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowApproval;
use App\Models\User;
use App\Enums\UserRoleEnum;
use App\Services\WorkflowService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoWorkflowSeeder extends Seeder
{
    /**
     * This seeder demonstrates the complete workflow system:
     * 1. Creates Workflow templates (reusable workflows)
     * 2. Creates WorkflowInstances for students
     * 3. Creates pending approvals to showcase approval workflow
     * 4. Shows step transitions and history tracking
     */
    public function run(): void
    {
        $this->command->info("\n╔════════════════════════════════════════════════════════╗");
        $this->command->info("║          WORKFLOW SYSTEM DEMONSTRATION SEEDER           ║");
        $this->command->info("╚════════════════════════════════════════════════════════╝\n");

        // Clear existing workflow data in FK dependency order
        $this->command->info("🧹 Clearing existing workflow data...");
        
        // Disable foreign key checks for truncation
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        WorkflowApproval::truncate();
        WorkflowInstance::truncate();
        Workflow::truncate();
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Get key users
        $admin = User::where('role', UserRoleEnum::ADMIN->value)->first();
        $accountingUser = User::where('role', UserRoleEnum::ACCOUNTING->value)->first();

        if (!$admin) {
            $this->command->error("✗ No admin user found. Run ComprehensiveUserSeeder first.");
            return;
        }

        $this->command->info("✓ Found admin user: {$admin->name}\n");

        // ====================================================================
        // SECTION 1: CREATE WORKFLOW TEMPLATES
        // ====================================================================
        $this->command->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->command->info("📋 SECTION 1: Creating Workflow Templates");
        $this->command->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

        // Workflow 1: Student Enrollment
        $enrollmentWorkflow = Workflow::create([
            'name' => 'Student Enrollment Process',
            'type' => 'student',
            'description' => 'Complete student enrollment workflow from application to active status',
            'is_active' => true,
            'steps' => [
                [
                    'name' => 'Application Received',
                    'description' => 'Initial application submitted by student',
                    'requires_approval' => false,
                    'approvers' => [],
                ],
                [
                    'name' => 'Document Verification',
                    'description' => 'Verify submitted documents (birth cert, school records, etc)',
                    'requires_approval' => true,
                    'approvers' => [$admin->id],
                ],
                [
                    'name' => 'Academic Review',
                    'description' => 'Review academic qualifications and prerequisites',
                    'requires_approval' => true,
                    'approvers' => [$admin->id],
                ],
                [
                    'name' => 'Payment Setup',
                    'description' => 'Configure payment terms and fee schedule',
                    'requires_approval' => false,
                    'approvers' => [],
                ],
                [
                    'name' => 'Enrollment Complete',
                    'description' => 'Student is now active and can access courses',
                    'requires_approval' => false,
                    'approvers' => [],
                ],
            ],
        ]);

        $this->command->info("✓ Created: '{$enrollmentWorkflow->name}'");
        $this->command->info("  → Type: {$enrollmentWorkflow->type}");
        $this->command->info("  → Steps: 5 (2 requiring approvals)");
        $this->command->info("  → Status: " . ($enrollmentWorkflow->is_active ? 'ACTIVE' : 'INACTIVE') . "\n");

        // Workflow 2: Transaction Approval
        $transactionWorkflow = Workflow::create([
            'name' => 'Transaction Approval Process',
            'type' => 'accounting',
            'description' => 'Multi-level approval workflow for financial transactions ≥ 10,000 PHP',
            'is_active' => true,
            'steps' => [
                [
                    'name' => 'Submitted',
                    'description' => 'Transaction submitted for approval queue',
                    'requires_approval' => false,
                    'approvers' => [],
                ],
                [
                    'name' => 'Manager Review',
                    'description' => 'Department manager preliminary review',
                    'requires_approval' => true,
                    'approvers' => [$admin->id],
                ],
                [
                    'name' => 'Finance Verification',
                    'description' => 'Accounting department verification',
                    'requires_approval' => true,
                    'approvers' => [$accountingUser?->id ?? $admin->id],
                ],
                [
                    'name' => 'Final Approval',
                    'description' => 'Executive approval (required for amounts > 50,000)',
                    'requires_approval' => true,
                    'approvers' => [$admin->id],
                ],
                [
                    'name' => 'Processed',
                    'description' => 'Transaction approved and processed',
                    'requires_approval' => false,
                    'approvers' => [],
                ],
            ],
        ]);

        $this->command->info("✓ Created: '{$transactionWorkflow->name}'");
        $this->command->info("  → Type: {$transactionWorkflow->type}");
        $this->command->info("  → Steps: 5 (3 requiring approvals)");
        $this->command->info("  → Status: " . ($transactionWorkflow->is_active ? 'ACTIVE' : 'INACTIVE') . "\n");

        // Workflow 3: General Document
        $documentWorkflow = Workflow::create([
            'name' => 'General Document Approval',
            'type' => 'general',
            'description' => 'Standard approval workflow for office documents and policies',
            'is_active' => true,
            'steps' => [
                [
                    'name' => 'Draft',
                    'description' => 'Document created and in draft state',
                    'requires_approval' => false,
                    'approvers' => [],
                ],
                [
                    'name' => 'Review',
                    'description' => 'Under peer review for feedback',
                    'requires_approval' => true,
                    'approvers' => [$admin->id],
                ],
                [
                    'name' => 'Approved',
                    'description' => 'Approved and ready for publication',
                    'requires_approval' => false,
                    'approvers' => [],
                ],
            ],
        ]);

        $this->command->info("✓ Created: '{$documentWorkflow->name}'");
        $this->command->info("  → Type: {$documentWorkflow->type}");
        $this->command->info("  → Steps: 3 (1 requiring approval)");
        $this->command->info("  → Status: " . ($documentWorkflow->is_active ? 'ACTIVE' : 'INACTIVE') . "\n");

        // ====================================================================
        // SECTION 2: CREATE WORKFLOW INSTANCES
        // ====================================================================
        $this->command->newLine();
        $this->command->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->command->info("⚙️  SECTION 2: Creating Workflow Instances");
        $this->command->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

        $workflowService = app(WorkflowService::class);

        // Get some sample students to attach workflows to
        $sampleStudents = Student::where('enrollment_status', 'pending')
            ->take(3)
            ->get();

        if ($sampleStudents->isEmpty()) {
            $this->command->warn("⚠️  No pending students found for workflow instances. Run seeding order:");
            $this->command->warn("   1. ComprehensiveUserSeeder (creates users + students)");
            $this->command->warn("   2. DemoWorkflowSeeder (this seeder)");
            return;
        }

        $instanceCount = 0;

        foreach ($sampleStudents as $index => $student) {
            $studentNum = $index + 1;
            $this->command->info("📌 Student #{$studentNum}: {$student->user->name}");
            $this->command->info("   ID: {$student->student_id} | Status: {$student->enrollment_status}\n");

            try {
                // Start enrollment workflow
                $instance = $workflowService->startWorkflow(
                    $enrollmentWorkflow,
                    $student,
                    $admin->id
                );

                $this->command->info("   ✓ Workflow started: {$instance->workflow->name}");
                $this->command->info("   ✓ Current step: {$instance->current_step}");
                $this->command->info("   ✓ Status: {$instance->status}");
                $instanceCount++;

                // For variety, advance some workflows through steps
                if ($index === 0) {
                    // Workflow 1: Advance to Document Verification
                    $workflowService->advanceWorkflow($instance, $admin->id);
                    $this->command->info("   ↗ Advanced → {$instance->current_step}");

                    // Workflow 1: Advance again to Academic Review
                    $workflowService->advanceWorkflow($instance, $admin->id);
                    $this->command->info("   ↗ Advanced → {$instance->current_step}");
                } elseif ($index === 1) {
                    // Workflow 2: Just advance once
                    $workflowService->advanceWorkflow($instance, $admin->id);
                    $this->command->info("   ↗ Advanced → {$instance->current_step}");
                }
                // Workflow 3: Leave at Application Received (pending approvals)

                $this->command->newLine();
            } catch (\Exception $e) {
                $this->command->error("   ✗ Error: {$e->getMessage()}\n");
            }
        }

        // ====================================================================
        // SECTION 3: DISPLAY WORKFLOW APPROVALS
        // ====================================================================
        $this->command->newLine();
        $this->command->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->command->info("✅ SECTION 3: Pending Approvals Summary");
        $this->command->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

        $pendingApprovals = WorkflowApproval::where('status', 'pending')->get();

        if ($pendingApprovals->isEmpty()) {
            $this->command->info("ℹ️  No pending approvals (all workflows are in non-approval steps)\n");
        } else {
            $this->command->info("Found {$pendingApprovals->count()} pending approvals:\n");

            foreach ($pendingApprovals as $approval) {
                $this->command->info("   • Approval ID: {$approval->id}");
                $this->command->info("     Workflow: {$approval->workflowInstance->workflow->name}");
                $this->command->info("     Step: {$approval->step_name}");
                $this->command->info("     Approver: {$approval->approver->name}");
                $this->command->info("     Status: PENDING");
                $this->command->newLine();
            }
        }

        // ====================================================================
        // SECTION 4: FINAL SUMMARY
        // ====================================================================
        $this->command->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->command->info("📊 Workflow System Summary");
        $this->command->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

        $totalWorkflows = Workflow::count();
        $totalInstances = WorkflowInstance::count();
        $totalApprovals = WorkflowApproval::count();
        $completedApprovals = WorkflowApproval::where('status', 'approved')->count();

        $this->command->info("✓ Workflow Templates....... {$totalWorkflows}");
        $this->command->info("✓ Active Instances......... {$totalInstances}");
        $this->command->info("✓ Total Approvals.......... {$totalApprovals}");
        $this->command->info("✓ Approved................. {$completedApprovals}");
        $this->command->info("✓ Pending Approval......... {$pendingApprovals->count()}\n");

        $this->command->info("╔════════════════════════════════════════════════════════╗");
        $this->command->info("║            ✨ WORKFLOW SEEDING COMPLETE ✨              ║");
        $this->command->info("╚════════════════════════════════════════════════════════╝\n");
    }
}
