<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "=== Checking Latest Payment Submission ===\n\n";

$transaction = App\Models\Transaction::where('reference', 'PAY-NKCDTJX5')->first();
if (!$transaction) {
    echo "Transaction not found\n";
    exit(1);
}

echo "Transaction ID: {$transaction->id}\n";
echo "Reference: {$transaction->reference}\n";
echo "Status: {$transaction->status}\n";
echo "Amount: ₱{$transaction->amount}\n\n";

// Check for workflow instance
$instances = App\Models\WorkflowInstance::where('workflowable_type', 'App\\Models\\Transaction')
    ->where('workflowable_id', $transaction->id)
    ->get();

echo "Workflow Instances for this transaction: {$instances->count()}\n";
foreach ($instances as $instance) {
    echo "  - ID: {$instance->id}, Status: {$instance->status}, Current Step: {$instance->current_step}\n";
    
    $approvals = $instance->approvals;
    echo "    Approvals: {$approvals->count()}\n";
    foreach ($approvals as $approval) {
        echo "      - ID: {$approval->id}, Step: {$approval->step_name}, Status: {$approval->status}\n";
    }
}

// Check all pending approvals
echo "\nAll pending approvals in system:\n";
$allPending = App\Models\WorkflowApproval::where('status', 'pending')->get();
echo "Count: {$allPending->count()}\n";
foreach ($allPending->take(5) as $approval) {
    $instance = $approval->workflowInstance;
    echo "  - Approval ID: {$approval->id}, Instance ID: {$instance->id}, Step: {$approval->step_name}\n";
}

// Check logs
echo "\nRecent logs:\n";
$logFile = 'storage/logs/laravel.log';
if (file_exists($logFile)) {
    $lines = array_reverse(explode("\n", file_get_contents($logFile)));
    $count = 0;
    foreach ($lines as $line) {
        if (!empty(trim($line)) && $count < 20) {
            if (strpos($line, 'workflow') !== false || strpos($line, 'approval') !== false || strpos($line, 'payment') !== false) {
                echo trim($line) . "\n";
                $count++;
            }
        }
    }
}
