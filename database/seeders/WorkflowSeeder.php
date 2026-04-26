<?php

namespace Database\Seeders;

use App\Models\Workflow;
use Illuminate\Database\Seeder;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        // Student Enrollment Workflow
        Workflow::create([
            'name' => 'Student Enrollment Process',
            'type' => 'student',
            'description' => 'Complete student enrollment workflow from application to active status',
            'is_active' => true,
            'steps' => [
                [
                    'name' => 'Application Received',
                    'description' => 'Initial application submitted',
                    'requires_approval' => false,
                ],
                [
                    'name' => 'Document Verification',
                    'description' => 'Verify submitted documents',
                    'requires_approval' => true,
                    'approvers' => [1], // Admin user ID
                ],
                [
                    'name' => 'Academic Review',
                    'description' => 'Review academic qualifications',
                    'requires_approval' => true,
                    'approvers' => [1],
                ],
                [
                    'name' => 'Payment Processing',
                    'description' => 'Process enrollment fees',
                    'requires_approval' => false,
                ],
                [
                    'name' => 'Enrollment Complete',
                    'description' => 'Student is now active',
                    'requires_approval' => false,
                ],
            ],
        ]);

        // Accounting Transaction Approval Workflow
        Workflow::create([
            'name' => 'Transaction Approval Process',
            'type' => 'accounting',
            'description' => 'Multi-level approval for financial transactions',
            'is_active' => true,
            'steps' => [
                [
                    'name' => 'Submitted',
                    'description' => 'Transaction submitted for approval',
                    'requires_approval' => false,
                ],
                [
                    'name' => 'Manager Review',
                    'description' => 'Department manager approval',
                    'requires_approval' => true,
                    'approvers' => [1],
                ],
                [
                    'name' => 'Finance Review',
                    'description' => 'Finance department approval',
                    'requires_approval' => true,
                    'approvers' => [1],
                ],
                [
                    'name' => 'Final Approval',
                    'description' => 'Executive approval for large amounts',
                    'requires_approval' => true,
                    'approvers' => [1],
                ],
                [
                    'name' => 'Approved',
                    'description' => 'Transaction approved and ready for processing',
                    'requires_approval' => false,
                ],
            ],
        ]);

        // General Document Approval Workflow
        Workflow::create([
            'name' => 'General Document Approval',
            'type' => 'general',
            'description' => 'Standard document approval workflow',
            'is_active' => true,
            'steps' => [
                [
                    'name' => 'Draft',
                    'description' => 'Document in draft state',
                    'requires_approval' => false,
                ],
                [
                    'name' => 'Review',
                    'description' => 'Document under review',
                    'requires_approval' => true,
                    'approvers' => [1],
                ],
                [
                    'name' => 'Published',
                    'description' => 'Document approved and published',
                    'requires_approval' => false,
                ],
            ],
        ]);
    }
}