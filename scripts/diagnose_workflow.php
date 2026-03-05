<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$workflow = \App\Models\Workflow::where('type', 'payment_approval')->first();
echo "Workflow: " . $workflow->name . "\n\n";

echo "Step Configuration:\n";
foreach ($workflow->steps as $i => $step) {
    $appRole = $step['approver_role'] ?? 'NOT SET';
    $reqApproval = $step['requires_approval'] ? 'yes' : 'no';
    echo "$i. " . $step['name'] . " - requires_approval: $reqApproval, approver_role: $appRole\n";
}

echo "\n\nApprover Resolution for 'Accounting Verification' step:\n";
$accountingStep = $workflow->steps[1];
$approverIds = $accountingStep['approvers'] ?? [];
echo "Initial approvers (from step config): " . json_encode($approverIds) . "\n";

if (isset($accountingStep['approver_role'])) {
    $roleApprovers = \App\Models\User::where('role', $accountingStep['approver_role'])->pluck('id')->toArray();
    echo "Users with role '" . $accountingStep['approver_role'] . "': " . json_encode($roleApprovers) . "\n";
    $approverIds = array_merge($approverIds, $roleApprovers);
    $approverIds = array_unique($approverIds);
} else {
    echo "No approver_role specified, checking fallbacks...\n";
}

if (empty($approverIds)) {
    $approverIds = \App\Models\User::where('role', 'accounting')->pluck('id')->toArray();
    echo "Fallback 1 - accounting users: " . json_encode($approverIds) . "\n";
}

if (empty($approverIds)) {
    $approverIds = \App\Models\User::where('role', 'admin')->pluck('id')->toArray();
    echo "Fallback 2 - admin users: " . json_encode($approverIds) . "\n";
}

echo "\nFinal approver IDs: " . json_encode($approverIds) . "\n";
foreach ($approverIds as $id) {
    $user = \App\Models\User::find($id);
    echo "  - User $id: " . $user->email . " (role: " . $user->role . ")\n";
}
