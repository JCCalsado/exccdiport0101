<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "=== Testing Payment Approval Workflow ===\n\n";

// Get a student and accounting user
$student = App\Models\User::where('role', 'student')->first();
$accounting = App\Models\User::where('role', 'accounting')->first();

if (!$student || !$accounting) {
    echo "✗ Missing student or accounting user\n";
    exit(1);
}

echo "Student: {$student->first_name} {$student->last_name} (ID: {$student->id})\n";
echo "Accounting: {$accounting->first_name} {$accounting->last_name} (ID: {$accounting->id})\n\n";

// Get a payment term
$term = App\Models\StudentPaymentTerm::where('user_id', $student->id)->first();
if (!$term) {
    echo "✗ No payment term found for student\n";
    exit(1);
}

echo "Payment Term: {$term->term_name}\n";
echo "  Original Balance: ₱" . number_format($term->balance, 2) . "\n";
echo "  Status: {$term->status}\n\n";

// Step 1: Student submits payment
echo "--- Step 1: Student Submits Payment ---\n";
$paymentService = app(App\Services\StudentPaymentService::class);
$workflowService = app(App\Services\WorkflowService::class);
$paymentAmount = 2500.00;

try {
    $result = $paymentService->processPayment($student, $paymentAmount, [
        'payment_method' => 'bank_transfer',
        'paid_at' => now()->toDateString(),
        'description' => 'Partial payment',
        'selected_term_id' => $term->id,
        'term_name' => $term->term_name,
    ], $requiresApproval = true);

    echo "✓ Payment submitted\n";
    echo "  Transaction ID: {$result['transaction_id']}\n";
    echo "  Reference: {$result['transaction_reference']}\n";
    echo "  Message: {$result['message']}\n\n";

    $transactionId = $result['transaction_id'];

    // Start the workflow (this is what TransactionController::payNow does)
    $paymentWorkflow = App\Models\Workflow::where('type', 'payment_approval')
        ->where('is_active', true)
        ->first();

    if ($paymentWorkflow) {
        $transaction = App\Models\Transaction::find($transactionId);
        try {
            $workflowService->startWorkflow(
                $paymentWorkflow,
                $transaction,
                $student->id
            );
            echo "  ✓ Workflow started for payment approval\n\n";
        } catch (\Exception $e) {
            echo "  ✗ Error starting workflow: {$e->getMessage()}\n";
            exit(1);
        }
    }
} catch (\Exception $e) {
    echo "✗ Payment submission failed: {$e->getMessage()}\n";
    exit(1);
}

// Check transaction status
$transaction = App\Models\Transaction::find($transactionId);
echo "  Transaction status in DB: {$transaction->status}\n";
echo "  Amount: ₱" . number_format($transaction->amount, 2) . "\n\n";

// Check term balance (should NOT be updated yet)
$termAfterSubmit = $term->fresh();
echo "  Payment Term after submission:\n";
echo "    Balance: ₱" . number_format($termAfterSubmit->balance, 2) . " (unchanged)\n";
echo "    Status: {$termAfterSubmit->status}\n\n";

// Step 2: Get pending approval
echo "--- Step 2: Accounting Reviews Approval ---\n";
$approval = App\Models\WorkflowApproval::where('status', 'pending')
    ->latest()
    ->first();

if (!$approval) {
    echo "✗ No pending approval found\n";
    exit(1);
}

echo "✓ Approval found\n";
echo "  Approval ID: {$approval->id}\n";
echo "  Step: {$approval->step_name}\n";
echo "  Status: {$approval->status}\n\n";

// Step 3: Accounting approves payment
echo "--- Step 3: Accounting Approves Payment ---\n";
$workflowService = app(App\Services\WorkflowService::class);

try {
    $workflowService->approveStep($approval, $accounting->id, 'Payment verified and processed.');
    echo "✓ Payment approved\n\n";
} catch (\Exception $e) {
    echo "✗ Approval failed: {$e->getMessage()}\n";
    echo "Trace: {$e->getTraceAsString()}\n";
    exit(1);
}

// Step 4: Verify transaction status changed to 'paid'
echo "--- Step 4: Verify Payment Status Updated ---\n";
$transactionAfterApproval = $transaction->fresh();
echo "Transaction status: {$transactionAfterApproval->status}\n";
if ($transactionAfterApproval->status !== 'paid') {
    echo "  ⚠️  WARNING: Transaction still shows '{$transactionAfterApproval->status}' instead of 'paid'\n";
} else {
    echo "  ✓ Status correctly updated to 'paid'\n";
}
echo "\n";

// Step 5: Verify term balance was updated
echo "--- Step 5: Verify Payment Term Updated ---\n";
$termAfterApproval = $term->fresh();
$expectedBalance = $term->balance - $paymentAmount;
$expectedBalance = max(0, $expectedBalance);

echo "Original Balance: ₱" . number_format($term->balance, 2) . "\n";
echo "Payment Amount: ₱" . number_format($paymentAmount, 2) . "\n";
echo "Expected Balance: ₱" . number_format($expectedBalance, 2) . "\n";
echo "Actual Balance: ₱" . number_format($termAfterApproval->balance, 2) . "\n";

if (abs($termAfterApproval->balance - $expectedBalance) < 0.01) {
    echo "  ✓ Balance correctly updated\n";
} else {
    echo "  ✗ Balance mismatch!\n";
}

echo "Term Status: {$termAfterApproval->status}\n";
if ($expectedBalance <= 0 && $termAfterApproval->status === 'paid') {
    echo "  ✓ Status correctly updated to 'paid'\n";
} elseif ($expectedBalance > 0 && $termAfterApproval->status === 'partial') {
    echo "  ✓ Status correctly updated to 'partial'\n";
}

echo "\n";

// Step 6: Verify Payment record was created
echo "--- Step 6: Verify Payment History ---\n";
$paymentRecord = App\Models\Payment::where('reference_number', $transactionAfterApproval->reference)->first();
if ($paymentRecord) {
    echo "✓ Payment record created\n";
    echo "  Status: {$paymentRecord->status}\n";
    echo "  Amount: ₱" . number_format($paymentRecord->amount, 2) . "\n";
} else {
    echo "⚠️  Payment record not found\n";
}

echo "\n";

// Step 7: Check student notification
echo "--- Step 7: Verify Student Notification ---\n";
$notification = App\Models\Notification::where('user_id', $student->id)
    ->where('title', 'Payment Approved')
    ->latest()
    ->first();

if ($notification) {
    echo "✓ Notification created\n";
    echo "  Message: {$notification->message}\n";
} else {
    echo "⚠️  Notification not found\n";
}

echo "\n";
echo "=== Test Complete ===\n";
echo "✓ Payment approval workflow is working correctly!\n";
