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

        // Any accounting user can view accounting-step approvals.
        // Admin is excluded by design — only accounting processes approvals.
        if ($user->role->value === 'accounting') {
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

        // Any accounting user may approve steps where approver_role = 'accounting'.
        // This covers cases where the specific user ID was not yet assigned when
        // the WorkflowApproval record was originally created.
        // Admin is excluded by design.
        if ($user->role->value === 'accounting') {
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