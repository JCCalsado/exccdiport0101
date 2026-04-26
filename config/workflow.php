<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Workflow Notifications
    |--------------------------------------------------------------------------
    |
    | Enable or disable automatic notifications when approval is required.
    |
    */
    'notifications_enabled' => env('WORKFLOW_NOTIFICATION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Auto-Advance
    |--------------------------------------------------------------------------
    |
    | Automatically advance workflow when all approvals are granted.
    |
    */
    'auto_advance' => env('WORKFLOW_AUTO_ADVANCE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Workflow Types
    |--------------------------------------------------------------------------
    |
    | Available workflow types in the system.
    |
    */
    'types' => [
        'student' => 'Student Workflows',
        'accounting' => 'Accounting Workflows',
        'general' => 'General Workflows',
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Types
    |--------------------------------------------------------------------------
    |
    | Available accounting transaction types.
    |
    */
    'transaction_types' => [
        'invoice' => 'Invoice',
        'payment' => 'Payment',
        'refund' => 'Refund',
        'adjustment' => 'Adjustment',
    ],
];