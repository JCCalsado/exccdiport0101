<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StudentPaymentTerm;
use App\Enums\UserRoleEnum;

echo "=== PAYMENT SYSTEM VERIFICATION ===\n\n";

// Get a test student
$testStudent = \App\Models\User::where('role', UserRoleEnum::STUDENT)->first();

if (!$testStudent) {
    echo "❌ No student users found. Create one first.\n";
    exit;
}

echo "Test Student: {$testStudent->email} (ID: {$testStudent->id})\n\n";

// Get payment terms for student
$assessment = \App\Models\StudentAssessment::where('user_id', $testStudent->id)->latest('created_at')->first();

if (!$assessment) {
    echo "❌ No assessment found for this student.\n";
    exit;
}

$terms = $assessment->paymentTerms()->where('balance', '>', 0)->get();

if ($terms->isEmpty()) {
    echo "❌ No payment terms with balance found.\n";
    exit;
}

echo "Available Payment Terms:\n";
foreach ($terms as $term) {
    echo "  - " . $term->term_name . ": ₱" . number_format($term->balance, 2) . "\n";
}

echo "\n✅ System is ready for payments\n";
echo "\nTo test the payment flow:\n";
echo "1. Navigate to http://localhost:8000/student/account\n";
echo "2. Try submitting 3 payments\n";
echo "3. The error message should now display if blocking occurs\n";
echo "\nExpected errors:\n";
echo "- 2nd payment for SAME term: \"A payment for this term is already awaiting accounting approval\"\n";
echo "- 3rd+ payments after 2 successful: May be blocked by business logic\n";
