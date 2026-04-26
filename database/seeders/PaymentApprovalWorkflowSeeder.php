<?php

namespace Database\Seeders;

use App\Models\Workflow;
use App\Models\User;
use App\Enums\UserRoleEnum;
use Illuminate\Database\Seeder;

class PaymentApprovalWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        // If the workflow already exists, update its steps to ensure they are correct.
        $existing = Workflow::where('type', 'payment_approval')->first();
        if ($existing) {
            $existing->update(['steps' => self::workflowSteps()]);
            $this->command->info('Payment approval workflow steps updated.');
            return;
        }

        // Get all accounting user IDs to be approvers
        $accountingUserIds = User::where('role', UserRoleEnum::ACCOUNTING->value)
            ->pluck('id')
            ->toArray();

        // Fallback to admin if no accounting users exist yet
        if (empty($accountingUserIds)) {
            $accountingUserIds = User::where('role', UserRoleEnum::ADMIN->value)
                ->pluck('id')
                ->toArray();
        }

        Workflow::create([
            'name'        => 'Student Payment Approval',
            'type'        => 'payment_approval',
            'description' => 'Student-submitted payments require accounting verification before being marked as paid.',
            'is_active'   => true,
            'steps'       => self::workflowSteps(),
        ]);

        $this->command->info('✅ Payment approval workflow created with approver role: accounting');
    }

    /**
     * The canonical step definitions for the payment approval workflow.
     * Step 1 requires approval immediately — this ensures that when startWorkflow()
     * is called, createApprovalRequest() fires right away and accounting receives
     * the pending approval without needing a manual advance.
     */
    public static function workflowSteps(): array
    {
        return [
            [
                'name'              => 'Accounting Verification',
                'description'       => 'Accounting staff verifies the payment details and amount.',
                'requires_approval' => true,
                'approver_role'     => 'accounting',
            ],
            [
                'name'              => 'Payment Verified',
                'description'       => 'Payment has been verified and is now marked as paid.',
                'requires_approval' => false,
            ],
        ];
    }
}