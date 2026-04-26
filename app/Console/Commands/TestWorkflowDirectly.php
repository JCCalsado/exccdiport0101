<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Transaction;
use App\Models\WorkflowApproval;
use App\Services\WorkflowService;
use App\Models\Workflow;
use DB;

class TestWorkflowDirectly extends Command
{
    protected $signature = 'test:workflow-direct';
    protected $description = 'Test workflow approval directly without complex payment logic';

    public function handle()
    {
        $this->info('Testing Workflow Approval Process Directly...');
        
        // Step 1: Create a mock transaction
        $this->info("\n1. Creating test transaction...");
        $student = User::where('role', 'student')->firstOrFail();
        
        $transaction = Transaction::create([
            'user_id' => $student->id,
            'amount' => 1000.00,
            'status' => 'awaiting_approval',
            'type' => 'payment',
            'description' => 'Test payment for workflow',
            'reference' => 'TEST-WORKFLOW-' . now()->timestamp,
            'payment_method' => 'check',
            'meta' => [
                'selected_term_id' => 1,
                'payment_method' => 'check',
            ],
        ]);
        
        $this->info("✓ Transaction created: ID {$transaction->id}");
        
        // Step 2: Create workflow instance
        $this->info("\n2. Starting payment approval workflow...");
        $workflow = Workflow::where('type', 'payment_approval')->firstOrFail();
        $workflowService = app(WorkflowService::class);
        
        $instance = $workflowService->startWorkflow($workflow, $transaction, $student->id);
        $this->info("✓ Workflow instance created: ID {$instance->id}");
        $this->info("  Current Status: {$instance->status}");
        $this->info("  Current Step: {$instance->current_step}");
        
        // Step 3: Find and approve pending approvals
        $this->info("\n3. Searching for pending approvals...");
        $pendingApprovals = WorkflowApproval::where('workflow_instance_id', $instance->id)
            ->where('status', 'pending')
            ->get();
        
        $this->info("  Found {$pendingApprovals->count()} pending approvals");
        
        if ($pendingApprovals->isEmpty()) {
            $this->error("No pending approvals found!");
            return;
        }
        
        // Step 4: Approve the payment
        $this->info("\n4. Approving payment...");
        $accountingUser = User::where('role', 'accounting')->firstOrFail();
        
        foreach ($pendingApprovals as $approval) {
            $this->info("  Approving approval ID {$approval->id}...");
            try {
                $workflowService->approveStep($approval, $accountingUser->id, 'Payment verified by system test');
                $this->info("  ✓ Approval successful");
            } catch (\Exception $e) {
                $this->error("  ✗ Error approving: " . $e->getMessage());
                throw $e;
            }
        }
        
        // Step 5: Check result
        $this->info("\n5. Checking final status...");
        $instance->refresh();
        $transaction->refresh();
        
        $this->info("  Workflow Status: {$instance->status}");
        $this->info("  Transaction Status: {$transaction->status}");
        $this->info("  Transaction Amount: ₱" . number_format($transaction->amount, 2));
        
        if ($instance->status === 'completed' && $transaction->status === 'paid') {
            $this->info("\n✅ SUCCESS: Workflow completed and payment finalized!");
        } else {
            $this->warn("\n⚠️  WARNING: Workflow or transaction in unexpected state");
            $this->info("   Expected: workflow=completed, transaction=paid");
            $this->info("   Actual: workflow={$instance->status}, transaction={$transaction->status}");
        }
        
        // Check logs
        $this->info("\n6. Checking logs...");
        $logs = \DB::table('transactions')->where('id', $transaction->id)->first();
        if ($logs) {
            $this->info("  Transaction in DB:");
            $this->info("    Status: {$logs->status}");
            $this->info("    Amount: ₱" . number_format($logs->amount, 2));
        }
    }
}
