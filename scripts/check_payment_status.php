#!/usr/bin/env php
<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n=== CHECKING PAYMENT TERMS ===\n\n";

// First, check total assessments in DB
$totalAssessments = \App\Models\StudentAssessment::count();
echo "Total Assessments in DB: " . $totalAssessments . "\n\n";

$user = \App\Models\User::where('email', 'student1@ccdi.edu.ph')->first();
if ($user) {
  echo "✓ Student found: " . $user->first_name . " " . $user->last_name . " (ID: {$user->id})\n\n";
  
  $assessment = \App\Models\StudentAssessment::where('user_id', $user->id)->latest('created_at')->first();
  if ($assessment) {
    echo "✓ Assessment found (ID: {$assessment->id})\n";
    echo "  Total Assessment: ₱" . number_format($assessment->total_assessment, 2) . "\n\n";
    
    $terms = $assessment->paymentTerms()->get();
    echo "Payment Terms Count: " . $terms->count() . "\n\n";
    
    if ($terms->count() > 0) {
      foreach ($terms as $t) {
        echo "  ✓ Term {$t->id}: {$t->term_name}\n";
        echo "    Amount: ₱" . number_format($t->amount, 2) . "\n";
        echo "    Balance: ₱" . number_format($t->balance, 2) . "\n";
        echo "    Status: {$t->status}\n\n";
      }
    } else {
      echo "  ✗ NO PAYMENT TERMS FOUND!\n";
    }
  } else {
    echo "✗ No assessment found\n";
  }
} else {
  echo "✗ Student not found\n";
}

echo "=== CHECKING PENDING PAYMENTS ===\n\n";
$pending = DB::table('transactions')
  ->where('kind', 'payment')
  ->where('status', 'awaiting_approval')
  ->get();

echo "Pending Payments Count: " . $pending->count() . "\n";
foreach ($pending as $p) {
  echo "  Transaction {$p->id}: ₱" . number_format($p->amount, 2) . " (User {$p->user_id})\n";
}

echo "\n";
