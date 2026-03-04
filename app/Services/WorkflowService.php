<?php

namespace App\Services;

use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowApproval;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\WorkflowStepAdvanced;

class WorkflowService
{
    public function startWorkflow(Workflow $workflow, Model $entity, int $userId): WorkflowInstance
    {
        return DB::transaction(function () use ($workflow, $entity, $userId) {
            $firstStep = $workflow->steps[0] ?? null;
            
            if (!$firstStep) {
                throw new \Exception('Workflow has no steps defined');
            }

            $instance = WorkflowInstance::create([
                'workflow_id' => $workflow->id,
                'workflowable_type' => get_class($entity),
                'workflowable_id' => $entity->id,
                'current_step' => $firstStep['name'],
                'status' => 'in_progress',
                'step_history' => [],
                'initiated_by' => $userId,
            ]);

            $instance->addStepToHistory($firstStep['name'], [
                'action' => 'started',
                'user_id' => $userId,
            ]);

            // Create approval request if step requires approval
            if ($firstStep['requires_approval'] ?? false) {
                $this->createApprovalRequest($instance, $firstStep);
            }

            return $instance;
        });
    }

    public function advanceWorkflow(WorkflowInstance $instance, int $userId): void
    {
        DB::transaction(function () use ($instance, $userId) {
            $workflow = $instance->workflow;
            $currentStepIndex = $this->getStepIndex($workflow, $instance->current_step);
            $previousStep = $instance->current_step; // Store previous step
            
            if ($currentStepIndex === null) {
                throw new \Exception('Current step not found in workflow');
            }

            $nextStepIndex = $currentStepIndex + 1;
            
            if ($nextStepIndex >= count($workflow->steps)) {
                $instance->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
                
                $instance->addStepToHistory('completed', [
                    'action' => 'completed',
                    'user_id' => $userId,
                ]);
                
                Log::info('Workflow advanced to completed', [
                    'workflow_instance_id' => $instance->id,
                    'final_step' => $previousStep,
                ]);
                
                return;
            }

            $nextStep = $workflow->steps[$nextStepIndex];
            
            $instance->update([
                'current_step' => $nextStep['name'],
            ]);

            $instance->addStepToHistory($nextStep['name'], [
                'action' => 'advanced',
                'user_id' => $userId,
            ]);

            if ($nextStep['requires_approval'] ?? false) {
                $this->createApprovalRequest($instance, $nextStep);
            }

            // Dispatch event after successful advancement
            WorkflowStepAdvanced::dispatch($instance, $previousStep, $nextStep['name']);
        });
    }

    public function approveStep(WorkflowApproval $approval, int $userId, ?string $comments = null): void
    {
        DB::transaction(function () use ($approval, $userId, $comments) {
            $approval->approve($comments);
            
            // Check if all approvals for this step are approved
            $pendingApprovals = WorkflowApproval::where('workflow_instance_id', $approval->workflow_instance_id)
                ->where('step_name', $approval->step_name)
                ->where('status', 'pending')
                ->count();

            if ($pendingApprovals === 0) {
                // All approvals done, advance workflow
                $this->advanceWorkflow($approval->workflowInstance, $userId);

                // After advancing, check if workflow is now completed
                $instance = $approval->workflowInstance->fresh();
                if ($instance->isCompleted()) {
                    $this->onWorkflowCompleted($instance);
                }
            }
        });
    }

    public function rejectStep(WorkflowApproval $approval, int $userId, string $comments): void
    {
        DB::transaction(function () use ($approval, $userId, $comments) {
            $approval->reject($comments);
            
            $instance = $approval->workflowInstance;
            $instance->update([
                'status' => 'rejected',
            ]);

            $instance->addStepToHistory($approval->step_name, [
                'action' => 'rejected',
                'user_id' => $userId,
                'comments' => $comments,
            ]);

            // Handle workflow-specific rejection logic
            $this->onWorkflowRejected($instance, $comments);
        });
    }

    protected function createApprovalRequest(WorkflowInstance $instance, array $step): void
    {
        $approverIds = $step['approvers'] ?? [];

        // Support dynamic role-based approvers
        if (isset($step['approver_role'])) {
            $roleApprovers = User::where('role', $step['approver_role'])
                ->pluck('id')
                ->toArray();
            $approverIds = array_merge($approverIds, $roleApprovers);
            $approverIds = array_unique($approverIds);
        }

        // Fallback 1: assign to all accounting users
        if (empty($approverIds)) {
            $approverIds = User::where('role', 'accounting')->pluck('id')->toArray();
        }

        // Fallback 2: assign to admin users if no accounting users exist
        if (empty($approverIds)) {
            $approverIds = User::where('role', 'admin')->pluck('id')->toArray();
        }

        // Last resort: fail hard if no approvers can be found at all
        if (empty($approverIds)) {
            throw new \Exception(
                'No approvers found for workflow step "' . $step['name'] . '". ' .
                'Ensure at least one accounting or admin user exists in the system.'
            );
        }

        foreach ($approverIds as $approverId) {
            WorkflowApproval::create([
                'workflow_instance_id' => $instance->id,
                'step_name' => $step['name'],
                'approver_id' => $approverId,
                'status' => 'pending',
            ]);
        }
    }

    protected function getStepIndex(Workflow $workflow, string $stepName): ?int
    {
        foreach ($workflow->steps as $index => $step) {
            if ($step['name'] === $stepName) {
                return $index;
            }
        }
        return null;
    }

    /**
     * Called when workflow reaches 'completed' status.
     * Finalizes payment if this was a payment_approval workflow.
     */
    protected function onWorkflowCompleted(WorkflowInstance $instance): void
    {
        Log::info('WorkflowService::onWorkflowCompleted called', [
            'workflow_instance_id' => $instance->id,
            'workflow_type' => $instance->workflow->type,
        ]);

        if ($instance->workflow->type !== 'payment_approval') {
            Log::info('Not a payment_approval workflow, skipping');
            return;
        }

        try {
            $transaction = $instance->workflowable;
            Log::info('Workflowable retrieved', [
                'transaction_id' => $transaction?->id,
                'transaction_class' => get_class($transaction),
            ]);

            if ($transaction instanceof Transaction) {
                Log::info('Finalizing approved payment', [
                    'transaction_id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'current_status' => $transaction->status,
                ]);

                app(StudentPaymentService::class)->finalizeApprovedPayment($transaction);

                // Refresh to see updated data
                $transaction->refresh();
                Log::info('Payment finalized', [
                    'transaction_id' => $transaction->id,
                    'new_status' => $transaction->status,
                ]);

                // Notify the student that payment was approved
                $student = $transaction->user;
                \App\Models\Notification::create([
                    'title'       => 'Payment Approved',
                    'message'     => "Your payment of ₱" . number_format($transaction->amount, 2) . 
                                     " (Ref: {$transaction->reference}) has been verified by accounting.",
                    'target_role' => 'student',
                    'user_id'     => $student->id,
                    'is_active'   => true,
                    'start_date'  => now()->toDateString(),
                    'end_date'    => now()->addDays(7)->toDateString(),
                ]);

                Log::info('Notification created for student', ['user_id' => $student->id]);
            } else {
                Log::warning('Workflowable is not a Transaction', [
                    'workflowable_class' => get_class($transaction),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error in onWorkflowCompleted', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Called when workflow is rejected.
     * Cancels payment and notifies student.
     */
    protected function onWorkflowRejected(WorkflowInstance $instance, string $reason): void
    {
        Log::info('WorkflowService::onWorkflowRejected called', [
            'workflow_instance_id' => $instance->id,
            'workflow_type' => $instance->workflow->type,
            'reason' => $reason,
        ]);

        if ($instance->workflow->type !== 'payment_approval') {
            Log::info('Not a payment_approval workflow, skipping');
            return;
        }

        try {
            $transaction = $instance->workflowable;
            Log::info('Workflowable retrieved for rejection', [
                'transaction_id' => $transaction?->id,
                'transaction_class' => get_class($transaction),
            ]);

            if ($transaction instanceof Transaction) {
                Log::info('Cancelling rejected payment', [
                    'transaction_id' => $transaction->id,
                    'amount' => $transaction->amount,
                ]);

                app(StudentPaymentService::class)->cancelRejectedPayment($transaction);

                // Refresh to see updated data
                $transaction->refresh();
                Log::info('Payment cancelled', [
                    'transaction_id' => $transaction->id,
                    'new_status' => $transaction->status,
                ]);

                // Notify the student that payment was rejected
                $student = $transaction->user;
                \App\Models\Notification::create([
                    'title'       => 'Payment Rejected',
                    'message'     => "Your payment of ₱" . number_format($transaction->amount, 2) . 
                                     " (Ref: {$transaction->reference}) was not verified. Reason: {$reason}",
                    'target_role' => 'student',
                    'user_id'     => $student->id,
                    'is_active'   => true,
                    'start_date'  => now()->toDateString(),
                    'end_date'    => now()->addDays(14)->toDateString(),
                ]);

                Log::info('Rejection notification created for student', ['user_id' => $student->id]);
            } else {
                Log::warning('Workflowable is not a Transaction for rejection', [
                    'workflowable_class' => get_class($transaction),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error in onWorkflowRejected', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}