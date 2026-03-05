<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "=== Detailed Workflow Approval Debug ===\n\n";

// Get the approval
$approval = App\Models\WorkflowApproval::find(16);
if (!$approval) {
    echo "Approval 16 not found\n";
    exit(1);
}

echo "Approval ID: {$approval->id}\n";
echo "Status: {$approval->status}\n";
echo "Step: {$approval->step_name}\n\n";

// Get the workflow instance
$instance = $approval->workflowInstance;
echo "Workflow Instance ID: {$instance->id}\n";
echo "Status: {$instance->status}\n";
echo "Current Step: {$instance->current_step}\n";

// Get the workflow
$workflow = $instance->workflow;
echo "\nWorkflow: {$workflow->name}\n";
echo "Workflow Type: {$workflow->type}\n";
echo "Steps data:\n";
foreach ($workflow->steps as $i => $step) {
    echo "  [{$i}] {$step['name']}\n";
    echo "      requires_approval: " . ($step['requires_approval'] ? 'yes' : 'no') . "\n";
    if (isset($step['approver_role'])) {
        echo "      approver_role: {$step['approver_role']}\n";
    }
}

echo "\n=== Step History ===\n";
foreach ($instance->step_history as $entry) {
    echo $entry['step'] . " - " . $entry['action'] . " (" . $entry['timestamp'] . ")\n";
}

echo "\n=== Related Transaction ===\n";
$transaction = $instance->workflowable;
if ($transaction instanceof App\Models\Transaction) {
    echo "Transaction ID: {$transaction->id}\n";
    echo "Reference: {$transaction->reference}\n";
    echo "Status: {$transaction->status}\n";
    echo "Amount: ₱{$transaction->amount}\n";
}

echo "\n=== Checking approvals for this step ===\n";
$approvals = App\Models\WorkflowApproval::where('workflow_instance_id', $instance->id)
    ->where('step_name', $approval->step_name)
    ->get();
echo "Total approvals for step '{$approval->step_name}': {$approvals->count()}\n";
foreach ($approvals as $app) {
    echo "  - ID: {$app->id}, Status: {$app->status}\n";
}

// Count pending approvals for this step
$pending = App\Models\WorkflowApproval::where('workflow_instance_id', $instance->id)
    ->where('step_name', $approval->step_name)
    ->where('status', 'pending')
    ->count();
echo "\nPending approvals for this step: {$pending}\n";

if ($pending === 0) {
    echo "  -> All approvals for this step are done, workflow should advance\n";
}
