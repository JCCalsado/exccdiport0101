<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowApproval;

class WorkflowApprovalPolicy
{
    public function view(User $user, WorkflowApproval $approval): bool
    {
        // Direct assignee can always view
        if ($approval->approver_id === $user->id) {
            return true;
        }

        // Accounting users can view any accounting-step approval
        if ($user->role->value === 'accounting' || $user->role->value === 'admin') {
            $stepDef = $this->getStepDefinition($approval);
            if ($stepDef && ($stepDef['approver_role'] ?? null) === 'accounting') {
                return true;
            }
        }

        return false;
    }

    public function approve(User $user, WorkflowApproval $approval): bool
    {
        if ($approval->status !== 'pending') {
            return false;
        }

        // Direct assignee
        if ($approval->approver_id === $user->id) {
            return true;
        }

        // BUG FIX: Allow any accounting (or admin) user to approve steps
        // where approver_role = 'accounting', in case their user ID was not
        // present when the WorkflowApproval records were originally created.
        if ($user->role->value === 'accounting' || $user->role->value === 'admin') {
            $stepDef = $this->getStepDefinition($approval);
            if ($stepDef && ($stepDef['approver_role'] ?? null) === 'accounting') {
                return true;
            }
        }

        return false;
    }

    /**
     * Look up the workflow step definition for this approval.
     */
    private function getStepDefinition(WorkflowApproval $approval): ?array
    {
        $workflow = $approval->workflowInstance?->workflow;
        if (!$workflow) {
            return null;
        }

        foreach ($workflow->steps as $step) {
            if ($step['name'] === $approval->step_name) {
                return $step;
            }
        }

        return null;
    }
}