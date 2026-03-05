<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Transaction;
use App\Models\WorkflowInstance;
use App\Models\WorkflowApproval;

// Get all stuck pending transactions
$stuckTransactions = Transaction::where('status', 'pending')
    ->where('kind', 'payment')
    ->get();

echo "Found " . count($stuckTransactions) . " stuck pending transactions\n";

$transactionIds = $stuckTransactions->pluck('id')->toArray();

if (empty($transactionIds)) {
    echo "No stuck transactions to clean up.\n";
    exit;
}

// Delete associated workflow approvals
$workflowInstances = WorkflowInstance::where('workflowable_type', 'App\\Models\\Transaction')
    ->whereIn('workflowable_id', $transactionIds)
    ->get();

echo "Found " . count($workflowInstances) . " workflow instances\n";

$deletedApprovals = WorkflowApproval::whereIn(
    'workflow_instance_id',
    $workflowInstances->pluck('id')->toArray()
)->delete();

echo "Deleted $deletedApprovals workflow approvals\n";

// Delete workflow instances
$deletedInstances = WorkflowInstance::whereIn('id', $workflowInstances->pluck('id')->toArray())->delete();
echo "Deleted $deletedInstances workflow instances\n";

// Delete transactions
$deletedTransactions = Transaction::whereIn('id', $transactionIds)->delete();
echo "Deleted $deletedTransactions transactions\n";

echo "✅ Cleanup complete!\n";
