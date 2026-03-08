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
    /**
     * Start a workflow instance for the given entity.
     * 
     * ✅ FIX: Notifications are sent AFTER the transaction commits, so if the mail
     * server is down, the workflow is already safely stored in the database.
     */
    public function startWorkflow(Workflow $workflow, Model $entity, int $userId): WorkflowInstance
    {
        $instance = DB::transaction(function () use ($workflow, $entity, $userId) {
            $firstStep = $workflow->steps[0] ?? null;

            if (!$firstStep) {
                throw new \Exception('Workflow has no steps defined');
            }

            // ── Build metadata from the entity for use in Approvals listing ──
            $metadata = [];
            if ($entity instanceof Transaction) {
                $metadata = [
                    'transaction_id'  => $entity->id,
                    'amount'          => (float) $entity->amount,
                    'payment_method'  => $entity->payment_channel,
                    'term_name'       => $entity->meta['term_name'] ?? $entity->type,
                    'year'            => $entity->year,
                    'semester'        => $entity->semester,
                    'student_user_id' => $entity->user_id,
                    'submitted_at'    => now()->toIso8601String(),
                ];
            }

            $instance = WorkflowInstance::create([
                'workflow_id'        => $workflow->id,
                'workflowable_type'  => get_class($entity),
                'workflowable_id'    => $entity->id,
                'current_step'       => $firstStep['name'],
                'status'             => 'in_progress',
                'step_history'       => [],
                'metadata'           => $metadata,   // ← FIX: was always empty
                'initiated_by'       => $userId,
            ]);

            $instance->addStepToHistory($firstStep['name'], [
                'action'  => 'started',
                'user_id' => $userId,
            ]);

            if (!($firstStep['requires_approval'] ?? false)) {
                $this->advanceWorkflow($instance, $userId);
            } else {
                $this->createApprovalRequest($instance, $firstStep);
            }

            return $instance->fresh();
        });

        $instance->refresh();
        try {
            $this->notifyApproversForStep($instance, $workflow->steps[0]);
        } catch (\Exception $e) {
            Log::warning('Failed to send approval notifications', [
                'workflow_instance_id' => $instance->id,
                'error'                => $e->getMessage(),
            ]);
        }

        return $instance;
    }

    public function advanceWorkflow(WorkflowInstance $instance, int $userId): void
    {
        $nextStepData = DB::transaction(function () use ($instance, $userId) {
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
                
                return null;
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

            // Return the next step if approvals are needed (for notification after transaction)
            return ($nextStep['requires_approval'] ?? false) ? $nextStep : null;
        });

        // Send notifications AFTER the transaction is committed
        if ($nextStepData !== null) {
            $instance->refresh();
            try {
                $this->notifyApproversForStep($instance, $nextStepData);
            } catch (\Exception $e) {
                Log::warning('Failed to send approval notifications after step advance', [
                    'workflow_instance_id' => $instance->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't re-throw; approvals are already in the database
            }
        } else {
            // If the step we just advanced to doesn't require approval,
            // continue advancing until we hit an approval-required step or complete
            $instance->refresh();
            if (!$instance->isCompleted()) {
                $this->advanceWorkflow($instance, $userId);
            }
        }
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
            // NOTE: Approval notifications are sent AFTER the transaction commits
            // by the notifyApproversForStep() method. This ensures that even if
            // the mail server is unavailable, the approval records exist in the database.
        }
    }

    /**
     * Send notifications to approvers for a specific workflow step.
     * Called AFTER the database transaction completes to prevent rollback on notification errors.
     */
    protected function notifyApproversForStep(WorkflowInstance $instance, array $step): void
    {
        // Find all pending approvals for this step
        $pendingApprovals = WorkflowApproval::where('workflow_instance_id', $instance->id)
            ->where('step_name', $step['name'])
            ->where('status', 'pending')
            ->get();

        foreach ($pendingApprovals as $approval) {
            $approver = User::find($approval->approver_id);
            if ($approver && !app()->environment('testing')) {
                try {
                    $approver->notify(new \App\Notifications\ApprovalRequired($approval));
                } catch (\Exception $e) {
                    Log::warning('Failed to send approval notification', [
                        'approval_id' => $approval->id,
                        'approver_id' => $approver->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue sending to other approvers even if one fails
                }
            }
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