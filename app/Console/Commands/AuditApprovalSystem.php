<?php

namespace App\Console\Commands;

use App\Models\Workflow;
use App\Models\User;
use App\Models\WorkflowApproval;
use App\Models\Transaction;
use App\Models\WorkflowInstance;
use App\Enums\UserRoleEnum;
use Illuminate\Console\Command;

class AuditApprovalSystem extends Command
{
    protected $signature = 'audit:approvals';
    protected $description = 'Audit the approval system to diagnose issues';

    public function handle()
    {
        $this->info('');
        $this->info('=== APPROVAL SYSTEM AUDIT ===');
        $this->info('');

        // Check 1: Payment approval workflow
        $this->line('STEP 1: Workflow Configuration');
        $this->line('──────────────────────────────');
        $workflow = Workflow::where('type', 'payment_approval')->first();
        if ($workflow) {
            $this->info("✅ Workflow EXISTS (ID: {$workflow->id}, Active: {$workflow->is_active})");
            $this->info("   Steps: " . count($workflow->steps));
            foreach ($workflow->steps as $step) {
                $req = isset($step['requires_approval']) && $step['requires_approval'] ? 'true' : 'false';
                $this->line("   - {$step['name']}: requires_approval=$req");
            }
        } else {
            $this->error("❌ NO WORKFLOW FOUND");
            $this->line("   Run: php artisan db:seed --class=PaymentApprovalWorkflowSeeder");
        }

        // Check 2: Approvers
        $this->line('');
        $this->line('STEP 2: Approver Users');
        $this->line('──────────────────────');
        $accountingUsers = User::where('role', UserRoleEnum::ACCOUNTING->value)->where('is_active', true)->get();
        $adminUsers = User::where('role', UserRoleEnum::ADMIN->value)->where('is_active', true)->get();
        $accountingCount = $accountingUsers->count();
        $adminCount = $adminUsers->count();

        $this->info("Active Accounting Users: $accountingCount");
        if ($accountingCount > 0) {
            foreach ($accountingUsers as $u) {
                $this->line("  - {$u->email}");
            }
        }

        $this->info("Active Admin Users: $adminCount");
        if ($adminCount > 0) {
            foreach ($adminUsers as $u) {
                $this->line("  - {$u->email}");
            }
        }

        if ($accountingCount === 0 && $adminCount === 0) {
            $this->error("❌ CRITICAL: NO APPROVERS FOUND");
        }

        // Check 3: Transactions vs Approvals
        $this->line('');
        $this->line('STEP 3: Transaction & Approval State');
        $this->line('─────────────────────────────────────');
        $pending = Transaction::where('status', 'awaiting_approval')->count();
        $pendings = WorkflowApproval::where('status', 'pending')->count();
        $totalApprovals = WorkflowApproval::count();

        $this->info("Transactions with status='awaiting_approval': $pending");
        $this->info("WorkflowApprovals with status='pending': $pendings");
        $this->info("WorkflowApprovals (all): $totalApprovals");

        if ($pending > 0 && $pendings === 0) {
            $this->error("⚠️  CRITICAL: Pending transactions exist but NO approvals!");
            $missing = Transaction::where('status', 'awaiting_approval')
                ->whereDoesntHave('workflowInstance')->count();
            $this->error("   Transactions without workflows: $missing");
            if ($missing > 0) {
                $this->line("   → startPaymentApprovalWorkflow() was not called or failed");
            }
        }

        // Check 4: Sample data
        if ($pending > 0) {
            $this->line('');
            $this->line('STEP 4: Sample Pending Transactions');
            $this->line('────────────────────────────────────');
            $samples = Transaction::where('status', 'awaiting_approval')->latest()->take(3)->get();
            foreach ($samples as $t) {
                $this->line("\n📌 Transaction ID: {$t->id}");
                $this->line("   Amount: {$t->amount}, User: {$t->user_id}");
                $this->line("   Created: {$t->created_at}");

                $wi = $t->workflowInstance;
                if ($wi) {
                    $this->info("   ✅ WorkflowInstance exists (ID: {$wi->id})");
                    $this->line("      Status: {$wi->status}, Current Step: {$wi->current_step}");
                    $approvals = $wi->approvals;
                    $this->line("      Approvals: " . $approvals->count());
                    foreach ($approvals as $a) {
                        $approver = User::find($a->approver_id);
                        $approverName = $approver ? $approver->email : "DELETED";
                        $this->line("         └─ {$approverName} ({$a->status})");
                    }
                } else {
                    $this->error("   ❌ NO WORKFLOW INSTANCE");
                    $this->line("      → Will NOT appear in /approvals");
                }
            }
        } else {
            $this->line('');
            $this->line('STEP 4: No Pending Transactions');
            $this->line('────────────────────────────────');
            $this->line('No transactions found. Test by submitting a student payment.');
        }

        // Check 5: Accounting view
        $this->line('');
        $this->line('STEP 5: Accounting View Simulation');
        $this->line('──────────────────────────────────');
        if ($accountingUsers->count() > 0) {
            $user = $accountingUsers->first();
            $this->info("Simulating view for: {$user->email}");

            $visible = WorkflowApproval::query()
                ->with(['workflowInstance.workflow'])
                ->whereHas('workflowInstance.workflow', function ($wq) {
                    $wq->where('type', 'payment_approval');
                })
                ->latest()
                ->get();

            $this->line("");
            $this->info("Approvals visible on /approvals page: " . $visible->count());

            if ($visible->count() > 0) {
                $this->line("\nFirst 3 approvals:");
                foreach ($visible->take(3) as $a) {
                    $this->line("  - ID: {$a->id}, Status: {$a->status}, Step: {$a->step_name}");
                }
            }
        } else {
            $this->warn("⚠️  Cannot simulate: No active accounting users");
        }

        // Summary
        $this->line('');
        $this->line('=== SUMMARY ===');
        $issues = [];

        if (!$workflow) {
            $issues[] = "❌ Payment approval workflow not found";
        }
        if ($accountingCount === 0 && $adminCount === 0) {
            $issues[] = "❌ No active approver users";
        }
        if ($pending > 0) {
            $missing = Transaction::where('status', 'awaiting_approval')
                ->whereDoesntHave('workflowInstance')->count();
            if ($missing > 0) {
                $issues[] = "❌ $missing pending transaction(s) missing workflow instances";
            }
        }
        if ($pending > 0 && $pendings === 0) {
            $issues[] = "❌ Pending transactions exist but NO approvals created";
        }

        if (empty($issues)) {
            $this->info("✅ System appears healthy");
        } else {
            $this->error("\nIssues found:");
            foreach ($issues as $issue) {
                $this->line("  $issue");
            }
        }

        $this->info('');
    }
}
