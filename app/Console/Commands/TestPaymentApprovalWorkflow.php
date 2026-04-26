<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\StudentPaymentService;
use App\Models\Transaction;
use App\Models\WorkflowApproval;
use App\Services\WorkflowService;
use DB;

class TestPaymentApprovalWorkflow extends Command
{
    protected $signature = 'test:payment-workflow';
    protected $description = 'Test the complete payment approval workflow';

    public function handle()
    {
        $this->info('Testing Payment Approval Workflow...');
        
        // Find a student
        $student = User::where('role', 'student')->firstOrFail();
        $this->info("Testing with student: {$student->email}");
        
        // Get student payment terms
        $studentModel = $student->student;
        $assessments = $studentModel ? $studentModel->assessments()->get() : collect();
        $terms = $assessments->isNotEmpty() ? $assessments->first()->paymentTerms()->get() : collect();
        
        $this->info("Terms available: " . $terms->count());
        
        // Simulate a student payment submission
        $this->info("\n1. Submitting payment from student...");
        try {
            $paymentService = app(StudentPaymentService::class);
            
            $selectedTermIds = $terms->take(1)->pluck('id')->toArray();
            $termAmount = $terms->take(1)->sum('balance');
            
            $transactionData = [
                'amount' => $termAmount,
                'selected_term_ids' => $selectedTermIds,
                'payment_method' => 'check',
                'reference' => 'CHK-TEST-' . now()->timestamp,
                'check_number' => 'TEST123',
                'check_date' => now()->toDateString(),
            ];
            
            $transaction = $paymentService->processPayment(
                $student,
                $transactionData['amount'],
                $transactionData,
                true  // $requiresApproval = true for students
            );
            
            $this->info("✓ Transaction created: ID {$transaction->id}, Status: {$transaction->status}");
            $this->info("  Amount: ₱" . number_format($transaction->amount, 2));
            $this->info("  Reference: {$transaction->reference}");
            
            // Check for pending workflow
            $workflow = $transaction->workflowInstances()->first();
            if ($workflow) {
                $this->info("✓ Workflow instance created: ID {$workflow->id}");
                $this->info("  Status: {$workflow->status}");
                $this->info("  Current Step: {$workflow->current_step}");
                
                // Find pending approvals
                $pendingApprovals = WorkflowApproval::where('workflow_instance_id', $workflow->id)
                    ->where('status', 'pending')
                    ->get();
                
                $this->info("  Pending approvals: " . $pendingApprovals->count());
                
                // Get accounting user and approve
                if ($pendingApprovals->isNotEmpty()) {
                    $this->info("\n2. Approving payment as accounting user...");
                    
                    $accountingUser = User::where('role', 'accounting')->first();
                    if (!$accountingUser) {
                        $this->error('No accounting user found!');
                        return;
                    }
                    
                    foreach ($pendingApprovals as $approval) {
                        $workflowService = app(WorkflowService::class);
                        $workflowService->approveStep($approval, $accountingUser->id, 'Payment verified');
                        $this->info("✓ Approval processed");
                    }
                    
                    // Refresh and check status
                    $workflow->refresh();
                    $transaction->refresh();
                    
                    $this->info("\n3. Workflow Result:");
                    $this->info("  Workflow Status: {$workflow->status}");
                    $this->info("  Transaction Status: {$transaction->status}");
                    $this->info("  Transaction Amount: ₱" . number_format($transaction->amount, 2));
                    
                    // Check payment terms
                    $this->info("\n4. Payment Terms Updated:");
                    foreach ($selectedTermIds as $termId) {
                        $term = DB::table('student_payment_terms')
                            ->where('id', $termId)
                            ->first();
                        if ($term) {
                            $this->info("  Term {$term->term_name}: Balance = ₱" . number_format($term->balance, 2));
                        }
                    }
                    
                    // Success indicator
                    if ($transaction->status === 'paid') {
                        $this->info("\n✅ SUCCESS: Payment workflow completed successfully!");
                    } else {
                        $this->error("\n❌ FAILED: Transaction still in {$transaction->status} status");
                    }
                } else {
                    $this->error('No pending approvals found!');
                }
            } else {
                $this->error('No workflow instance created!');
            }
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }
}
