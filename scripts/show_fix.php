#!/usr/bin/env php
<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n========== FINAL PAYMENT TERMS CHECK ==========\n\n";

// Get the 3 assessments that were fixed by the migration
$assessments = DB::table('student_assessments as sa')
    ->join('users as u', 'sa.user_id', '=', 'u.id')
    ->select('sa.id', 'sa.user_id', 'sa.total_assessment', 
             DB::raw("CONCAT(u.last_name, ', ', u.first_name) as full_name"))
    ->whereIn('sa.id', [50, 51, 52])
    ->get();

foreach ($assessments as $a) {
    echo "=" . str_repeat("=", 50) . "\n";
    echo "Student: {$a->full_name}\n";
    echo "Assessment #{$a->id} | Total: ₱" . number_format($a->total_assessment, 2) . "\n";
    echo "=" . str_repeat("=", 50) . "\n\n";
    
    $terms = DB::table('student_payment_terms')
        ->where('student_assessment_id', $a->id)
        ->orderBy('term_order')
        ->get();
    
    $totalAmount = 0;
    foreach ($terms as $t) {
        echo sprintf("  %-25s: ₱%10.2f\n", $t->term_name, $t->amount);
        $totalAmount += $t->amount;
    }
    
    echo "  " . str_repeat("-", 40) . "\n";
    echo sprintf("  %-25s: ₱%10.2f\n\n", "TOTAL", $totalAmount);
    
    // Show the fix
    $difference = abs($totalAmount - $a->total_assessment);
    echo "✅ Sum matches total assessment: ";
    echo ($difference < 0.01) ? "YES\n" : "NO (diff: ₱" . number_format($difference, 4) . ")\n";
    echo "\n";
}

// Show which term was corrected
echo "=" . str_repeat("=", 50) . "\n";
echo "📝 NOTE: LAST TERM WAS CORRECTED\n";
echo "=" . str_repeat("=", 50) . "\n\n";
echo "To prevent rounding errors, the final payment term\n";
echo "amount is calculated as:\n\n";
echo "  Final = Total Assessment - Sum(Other Terms)\n\n";
echo "This ensures the payment terms always sum to exactly\n";
echo "the total assessment amount, with no ₱0.01 discrepancies.\n\n";
